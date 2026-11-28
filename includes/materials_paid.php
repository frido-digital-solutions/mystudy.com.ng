<?php
/**
 * Paid study materials.
 *
 * Two problems had to be solved together:
 *
 * 1. A price on a download button protects nothing. Materials used to be handed out
 *    by redirecting the browser to a public file URL, so anyone who typed the path
 *    got the file for free. Paid files now live under assets/secure/, which carries
 *    a committed deny-all .htaccess, and reach students only through material_stream()
 *    after this file has checked that they own it.
 *
 * 2. "He paid but could not download" happens when delivery depends on the buyer's
 *    browser. It does not here: every checkout writes an open row first, then three
 *    independent paths can close it - the Paystack webhook (server to server, so it
 *    works even if the tab is closed instantly), the return redirect, and the next
 *    time the student opens the page. Only the path that actually flips the row
 *    sends the email, so the student is never told twice and never told zero times.
 */

require_once __DIR__ . '/../models/MaterialPayment.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/paystack_reconcile.php';

const MATERIAL_SECURE_DIR = 'assets/secure/materials';

/** Largest file we are willing to base64 into an email. */
const MATERIAL_EMAIL_ATTACHMENT_MAX = 4 * 1024 * 1024;

function material_is_paid(array $material): bool
{
    return !empty($material['is_paid']) && (float)($material['price'] ?? 0) > 0;
}

/**
 * Put a paid file where no URL can reach it.
 */
function material_store_dir(bool $paid): string
{
    return $paid ? MATERIAL_SECURE_DIR : 'assets/uploads/materials';
}

/**
 * Absolute path of the paid-material store, created (and re-guarded) on demand.
 */
function material_secure_base(): string
{
    $guardRoot = ROOT_PATH . '/assets/secure';
    if (!is_dir($guardRoot)) {
        @mkdir($guardRoot, 0775, true);
    }
    // One deny-all in the parent covers every folder below it, including any that
    // already exists or is created later.
    $guard = $guardRoot . '/.htaccess';
    if (!is_file($guard)) {
        @file_put_contents($guard, implode("\n", [
            '# Paid study material. A PHP gateway streams these to buyers only;',
            '# nothing here may ever be fetched by URL.',
            '<IfModule mod_authz_core.c>',
            '    Require all denied',
            '</IfModule>',
            '<IfModule !mod_authz_core.c>',
            '    Order allow,deny',
            '    Deny from all',
            '</IfModule>',
            '',
        ]));
    }

    $dir = ROOT_PATH . '/' . MATERIAL_SECURE_DIR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function material_new_ref(int $userId, int $materialId): string
{
    return 'MAT-' . $userId . '-' . $materialId . '-' . bin2hex(random_bytes(5));
}

/** Admins and the uploader never pay their own material. */
function material_can_access(PDO $pdo, ?int $userId, array $material): bool
{
    if (!material_is_paid($material)) {
        return true;
    }
    if ($userId === null || $userId <= 0) {
        return false;
    }
    if (has_role('admin')) {
        return true;
    }
    if ((int)($material['uploader_id'] ?? 0) === $userId) {
        return true;
    }
    return (new MaterialPayment($pdo))->owns($userId, (int)$material['id']);
}

/**
 * Resolve file_path to a real file inside the app. Rejects traversal and anything
 * outside ROOT_PATH/assets.
 */
function material_resolve_file(array $material): ?string
{
    $relative = trim((string)($material['file_path'] ?? ''));
    if ($relative === '') {
        return null;
    }
    $absolute = realpath(ROOT_PATH . '/' . ltrim($relative, '/'));
    $allowedRoot = realpath(ROOT_PATH . '/assets');
    if ($absolute === false || $allowedRoot === false || !is_file($absolute)) {
        return null;
    }
    if (stripos($absolute, $allowedRoot . DIRECTORY_SEPARATOR) !== 0) {
        return null;
    }
    return $absolute;
}

function material_download_name(array $material): string
{
    $name = trim((string)($material['original_name'] ?? ''));
    if ($name === '') {
        $name = 'material-' . (int)($material['id'] ?? 0);
    }
    // Strip anything path-like or control-character-ish; keep the extension.
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $name);
    $name = trim(preg_replace('/\s+/', ' ', $name));
    $name = ltrim($name, '.');
    if ($name === '' || $name === '.' || $name === '..') {
        $name = 'material-' . (int)($material['id'] ?? 0);
    }
    $ext = strtolower(pathinfo((string)($material['file_path'] ?? ''), PATHINFO_EXTENSION));
    if ($ext !== '' && !preg_match('/\.' . preg_quote($ext, '/') . '$/i', $name)) {
        $name .= '.' . $ext;
    }
    return $name;
}

/**
 * Stream a file to a browser that has already been checked. Supports one byte range
 * so the built-in PDF viewer can page through a large document.
 */
function material_stream(string $absolute, string $downloadName, ?string $mime, bool $inline = false): void
{
    $size = (int)@filesize($absolute);
    if ($size <= 0) {
        http_response_code(404);
        exit('This file is no longer available.');
    }

    $safeMime = $mime ?: 'application/octet-stream';
    $disposition = $inline ? 'inline' : 'attachment';
    $quoted = '"' . str_replace('"', '', $downloadName) . '"';

    header('Content-Type: ' . $safeMime);
    header('Content-Disposition: ' . $disposition . '; filename=' . $quoted . '; filename*=UTF-8\'\'' . rawurlencode($downloadName));
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . $size);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');

    $start = 0;
    $end = $size - 1;
    $range = $_SERVER['HTTP_RANGE'] ?? '';
    if ($inline && $range !== '' && preg_match('/bytes=(\d*)-(\d*)/', $range, $m)) {
        $reqStart = $m[1] === '' ? null : (int)$m[1];
        $reqEnd = $m[2] === '' ? null : (int)$m[2];
        if ($reqStart === null && $reqEnd !== null) {
            $start = max(0, $size - $reqEnd); // suffix range
            $reqEnd = null;
        } else {
            $start = (int)$reqStart;
        }
        $end = $reqEnd === null ? $size - 1 : min($size - 1, (int)$reqEnd);
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            exit;
        }
        if ($start > 0 || $end < $size - 1) {
            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
            header('Content-Length: ' . ($end - $start + 1));
        }
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $fh = @fopen($absolute, 'rb');
    if ($fh === false) {
        http_response_code(500);
        exit('This file could not be opened.');
    }
    if ($start > 0) {
        fseek($fh, $start);
    }
    $remaining = $end - $start + 1;
    while ($remaining > 0 && !feof($fh)) {
        $chunk = fread($fh, (int)min(262144, $remaining));
        if ($chunk === false || $chunk === '') {
            break;
        }
        echo $chunk;
        $remaining -= strlen($chunk);
        flush();
    }
    fclose($fh);
    exit;
}

/**
 * Confirm one reference with Paystack and, if the money is there, close the row.
 * Safe to call from the webhook, the browser and a page load at the same time.
 *
 * @param array|null $tx a transaction payload already fetched (webhook body)
 * @return array{credited:bool,reason:string,payment:array|null,material:array|null}
 */
function material_confirm_ref(PDO $pdo, string $ref, ?array $tx = null): array
{
    $payments = new MaterialPayment($pdo);
    $payment = $payments->getByRef($ref);
    if (!$payment) {
        return ['credited' => false, 'reason' => 'Unknown reference', 'payment' => null, 'material' => null];
    }
    if ($payment['status'] === 'successful') {
        return ['credited' => false, 'reason' => 'Already credited', 'payment' => $payment, 'material' => null];
    }

    $stmt = $pdo->prepare('SELECT * FROM materials WHERE id = :id');
    $stmt->execute([':id' => (int)$payment['material_id']]);
    $material = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$material) {
        return ['credited' => false, 'reason' => 'Material no longer exists', 'payment' => $payment, 'material' => null];
    }

    // The stored row is the invoice: whatever the browser or webhook claims, the
    // price charged at checkout is what must have been paid.
    $needed = (float)$payment['amount'];
    $expectMeta = ['material_id' => (int)$payment['material_id'], 'user_id' => (int)$payment['user_id']];

    if ($tx === null) {
        $fetch = paystack_fetch_transaction($ref);
        if (!$fetch['ok'] || !is_array($fetch['tx'])) {
            log_error('Material payment lookup failed: ' . $fetch['detail'], ['reference' => $ref]);
            return ['credited' => false, 'reason' => 'Paystack unreachable', 'payment' => $payment, 'material' => $material];
        }
        $tx = $fetch['tx'];
    }

    $check = paystack_transaction_binds($tx, $ref, $needed, $expectMeta);
    if (!$check['ok']) {
        return ['credited' => false, 'reason' => $check['reason'], 'payment' => $payment, 'material' => $material];
    }

    $won = $payments->markSuccessful($ref, $check['paid'], (string)($tx['status'] ?? 'success'));
    if (!$won) {
        return ['credited' => false, 'reason' => 'Another path credited it first', 'payment' => $payment, 'material' => $material];
    }

    material_deliver($pdo, $ref);
    return ['credited' => true, 'reason' => '', 'payment' => $payment, 'material' => $material];
}

/**
 * The student now owns this material: tell them in the app and post the file to
 * their email. Failure to email must never undo the purchase, so every step here is
 * wrapped and the site copy always works regardless.
 */
function material_deliver(PDO $pdo, string $ref): void
{
    $payments = new MaterialPayment($pdo);
    $payment = $payments->getByRef($ref);
    if (!$payment) {
        return;
    }
    $stmt = $pdo->prepare('SELECT * FROM materials WHERE id = :id');
    $stmt->execute([':id' => (int)$payment['material_id']]);
    $material = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$material) {
        return;
    }

    $userId = (int)$payment['user_id'];
    $pageUrl = SITE_URL . '/material.php?id=' . (int)$material['id'];
    $title = (string)$material['title'];

    $buyerName = (string)$pdo->query('SELECT name FROM users WHERE id = ' . $userId)->fetchColumn();
    $buyerEmail = trim((string)$payment['email']);
    if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
        $buyerEmail = trim((string)$pdo->query('SELECT email FROM users WHERE id = ' . $userId)->fetchColumn());
    }

    try {
        $msg  = "Your download is unlocked and will stay unlocked on your account.\n\n";
        $msg .= "Material: {$title}\n";
        $msg .= "Amount paid: ₦" . number_format((float)$payment['amount'], 0) . "\n\n";
        $msg .= "Open it any time from your account: {$pageUrl}";
        (new Notification($pdo))->create($userId, 'material_unlocked', '📘 Unlocked: ' . $title, $msg, $pageUrl);
    } catch (Throwable $e) {
        log_error('Material unlock notification failed: ' . $e->getMessage(), ['reference' => $ref]);
    }

    if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    try {
        require_once __DIR__ . '/EmailService.php';
        require_once __DIR__ . '/EmailTemplates.php';

        $attachment = null;
        $path = material_resolve_file($material);
        if ($path !== null && (int)filesize($path) <= MATERIAL_EMAIL_ATTACHMENT_MAX) {
            $attachment = [
                'content' => base64_encode((string)file_get_contents($path)),
                'name' => material_download_name($material),
                'filename' => material_download_name($material),
            ];
        }

        $svc = EmailService::fromConfig();
        if ($svc) {
            $tpl = EmailTemplates::materialPurchased(
                $buyerName ?: 'there',
                $title,
                (float)$payment['amount'],
                $pageUrl,
                $attachment !== null
            );
            $svc->send($buyerEmail, $buyerName ?: $buyerEmail, $tpl['subject'], $tpl['html'], $tpl['text'], $attachment ? [$attachment] : []);
        }
    } catch (Throwable $e) {
        log_error('Material purchase email failed: ' . $e->getMessage(), ['reference' => $ref]);
    }
}

/**
 * Chase down any checkout this student opened for this material that is still
 * showing as unpaid. Runs on page load, so a payment that landed while their browser
 * was asleep is applied the moment they come back - no session data needed.
 */
function material_verify_open_payments(PDO $pdo, int $userId, array $material, int $limit = 2): bool
{
    if ($userId <= 0 || !material_is_paid($material)) {
        return false;
    }
    $payments = new MaterialPayment($pdo);
    foreach ($payments->openFor($userId, (int)$material['id'], 48, $limit) as $payment) {
        $result = material_confirm_ref($pdo, (string)$payment['payment_ref']);
        if ($result['credited']) {
            return true;
        }
    }
    return false;
}

/**
 * Guard a download/preview request. Returns the material when the visitor may have
 * it, otherwise sends them somewhere useful and does not return.
 */
function material_require_access(PDO $pdo, array $material, ?int $userId): void
{
    if (material_can_access($pdo, $userId, $material)) {
        return;
    }
    if ($userId === null || $userId <= 0) {
        set_flash_message('info', 'Please log in to buy and download this material.');
        header('Location: ' . SITE_URL . '/login.php?return=' . rawurlencode(msb_return_path()));
        exit;
    }
    // They may have paid and simply been redirected too early.
    if (material_verify_open_payments($pdo, $userId, $material) && material_can_access($pdo, $userId, $material)) {
        return;
    }
    set_flash_message('error', 'This material is locked until payment is confirmed.');
    header('Location: ' . SITE_URL . '/material.php?id=' . (int)$material['id']);
    exit;
}
