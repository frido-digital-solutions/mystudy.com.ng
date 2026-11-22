<?php
/**
 * The challenge arena, natively: everything quiz_special.php does for a browser,
 * shaped for the app.
 *
 *   GET  ?id=12          the full state: timing, seats, access, code, payments
 *   POST action=join     a free challenge's waitlist
 *   POST action=redeem   an access code (universal or one-time voucher)
 *   POST action=pay_init start a Paystack checkout, returns the hosted page URL
 *   POST action=verify   re-check one checkout with Paystack after the browser
 *   POST action=receipt  multipart: a bank-transfer receipt for admin review
 *
 * The rules are the website's rules, not a second set: the same participant
 * union, the same code redemption, the same receipt validation, and the same
 * paystack_grant_access() that sends the notification and the email. A Paystack
 * checkout never creates a pending payment row - pending means a human must
 * approve a receipt.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../models/Quiz.php';
require_once __DIR__ . '/../../models/QuizAttempt.php';
require_once __DIR__ . '/../../models/Payment.php';
require_once __DIR__ . '/../../models/AccessCode.php';
require_once __DIR__ . '/../../models/Gamification.php';
require_once __DIR__ . '/../../models/Notification.php';
require_once __DIR__ . '/_challenge.php';

$user = api_auth();
$userId = (int)$user['id'];
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

$quizModel = new Quiz($pdo);
$paymentModel = new Payment($pdo);
$accessCodeModel = new AccessCode($pdo);
$attemptModel = new QuizAttempt($pdo);

/**
 * A checkout reference the app started, bound server-side to the buyer, the set
 * and the fee. user_meta stands in for the browser session the website keeps -
 * the phone has no cookie jar, and the binding must survive a killed app.
 */
function challenge_pay_meta_key(string $ref): string
{
    return 'msb_pay_' . $ref;
}

function challenge_pay_bindings(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT meta_key, meta_value FROM user_meta WHERE user_id = ? AND meta_key LIKE 'msb_pay_%'");
    $stmt->execute([$userId]);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $ref = substr((string)$r['meta_key'], strlen('msb_pay_'));
        $bound = json_decode((string)$r['meta_value'], true);
        if ($ref !== '' && is_array($bound)) {
            $out[$ref] = $bound;
        }
    }
    return $out;
}

function challenge_pay_forget(PDO $pdo, int $userId, string $ref): void
{
    $pdo->prepare('DELETE FROM user_meta WHERE user_id = ? AND meta_key = ?')
        ->execute([$userId, challenge_pay_meta_key($ref)]);
}

/** The participant count the website counts: codes, sittings and waitlist, unioned. */
function challenge_participant_count(PDO $pdo, int $quizId): int
{
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT u) FROM (
            SELECT used_by_user_id as u FROM quiz_access_codes WHERE quiz_id = ? AND used_by_user_id IS NOT NULL
            UNION
            SELECT user_id as u FROM quiz_attempts WHERE quiz_id = ?
            UNION
            SELECT user_id as u FROM challenge_participants WHERE quiz_id = ?
        ) as participants
    ");
    $stmt->execute([$quizId, $quizId, $quizId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Re-check the checkouts this student still has outstanding for this set, the
 * way the website reconciles a reloaded browser. Returns a code the moment one
 * of them turns out to have been paid.
 */
function challenge_reconcile(PDO $pdo, int $userId, array $quiz, float $fee, bool $force = false, string $onlyRef = ''): ?string
{
    require_once __DIR__ . '/../../includes/paystack_reconcile.php';

    $bindings = challenge_pay_bindings($pdo, $userId);
    $checked = 0;
    // Newest first: the reference just paid is the one worth asking about.
    foreach (array_reverse($bindings, true) as $ref => $bound) {
        if ($onlyRef !== '' && $ref !== $onlyRef) {
            continue;
        }
        if ((int)($bound['quiz_id'] ?? 0) !== (int)$quiz['id']) {
            continue;
        }
        if ((int)($bound['created'] ?? 0) < time() - 86400) {
            challenge_pay_forget($pdo, $userId, $ref);
            continue;
        }
        if (!$force && (int)($bound['checked'] ?? 0) > time() - 60) {
            continue;
        }
        if ($checked >= 3) {
            break;
        }
        $checked++;

        $result = paystack_fetch_transaction($ref);
        $bound['checked'] = time();
        $pdo->prepare('UPDATE user_meta SET meta_value = ? WHERE user_id = ? AND meta_key = ?')
            ->execute([json_encode($bound), $userId, challenge_pay_meta_key($ref)]);

        if (!$result['ok'] || !is_array($result['tx'])) {
            log_error('App challenge reconciliation lookup failed: ' . $result['detail'], [
                'reference' => $ref, 'quiz_id' => (int)$quiz['id'], 'user_id' => $userId,
            ]);
            continue;
        }

        $amount = (float)($bound['amount'] ?? $fee);
        $check = paystack_transaction_matches($result['tx'], $ref, $amount, (int)$quiz['id'], $userId);
        if (!$check['ok']) {
            continue;
        }

        $code = paystack_grant_access($pdo, $userId, (int)$quiz['id'], $quiz, $ref, $check['paid']);
        challenge_pay_forget($pdo, $userId, $ref);
        return $code;
    }
    return null;
}

/**
 * Who the arena lets in: the website turns level-locked and wrong-audience
 * students away at the door, before any join, code or payment form exists.
 * The API must refuse the same actions, not just describe the refusal.
 */
function challenge_entry_blocked(PDO $pdo, array $quiz, array $user): ?array
{
    $quizType = (string)($quiz['type'] ?? 'both');
    $userType = (string)($user['user_type'] ?? '');
    if ($quizType !== 'both' && $userType !== '' && $quizType !== $userType) {
        return ['code' => 'wrong_students', 'message' => $quizType === 'university'
            ? 'This challenge is for university students.'
            : 'This challenge is for secondary students.'];
    }
    $reqLevel = (int)($quiz['required_level'] ?? 0);
    if ($reqLevel > 0) {
        $level = (int)((new Gamification($pdo))->getUserStats((int)$user['id'])['level'] ?? 1);
        if ($level < $reqLevel) {
            return ['code' => 'level_locked', 'message' => "You need to be Level $reqLevel to enter. You are Level $level."];
        }
    }
    return null;
}

/**
 * Everything the entry page shows, built in one place so every action hands
 * back the same fresh shape and the screen never holds a stale state.
 */
function challenge_state(PDO $pdo, array $quiz, array $user, array $opts = []): array
{
    global $quizModel, $paymentModel, $accessCodeModel, $attemptModel;

    $userId = (int)$user['id'];
    $id = (int)$quiz['id'];
    $isChallenge = ($quiz['mode'] ?? 'normal') === 'challenge';
    $fee = (float)($quiz['entry_fee'] ?? 0);
    $isPaid = !empty($quiz['is_paid']) && $fee > 0;

    // The card fields, in the same shape the home feed and the carousel use.
    $materials = api_home_challenge_materials($pdo, [$id]);
    $questions = api_home_challenge_questions($pdo, [$id]);
    $card = api_home_challenge_map([$quiz], $materials, $questions)[0];
    $card = api_home_challenge_live($pdo, $userId, [$card])[0];

    $now = time();
    $startTs = $card['start_ts'];
    $endTs = $card['end_ts'];
    if ($isChallenge && $startTs && $endTs) {
        $card['state'] = $now < $startTs ? 'upcoming' : ($now < $endTs ? 'live' : 'ended');
    } else {
        $card['state'] = 'live';
    }

    // Seats: the website's union, not just the waitlist.
    $participants = challenge_participant_count($pdo, $id);
    $card['entries'] = $participants;
    $max = $card['max_participants'];
    $isFull = $max && $participants >= $max;

    $stmt = $pdo->prepare('SELECT id FROM challenge_participants WHERE quiz_id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    $joined = (bool)$stmt->fetch();

    $hasAttempted = $attemptModel->hasAttempted($id, $userId);

    // Access, exactly as quiz_special.php grants it.
    $hasAccess = false;
    $code = '';
    if ($isPaid) {
        $hasAccess = $paymentModel->hasPaid($userId, $id) || $accessCodeModel->hasUserUsedACodeForQuiz($userId, $id);
        if ($hasAccess) {
            $code = (string)($accessCodeModel->getCodeForUser($userId, $id) ?? '');
            if ($isChallenge && !$joined) {
                try {
                    $pdo->prepare('INSERT IGNORE INTO challenge_participants (quiz_id, user_id) VALUES (?, ?)')->execute([$id, $userId]);
                    $joined = true;
                } catch (Throwable $e) { /* the seat is a courtesy, the access is not */ }
            }
        }
    } elseif ($isChallenge) {
        $hasAccess = $joined || $hasAttempted || !$max;
    } else {
        $hasAccess = !$isFull || $hasAttempted;
    }

    // A browser that closed mid-checkout: the money may already be captured.
    if (!$hasAccess && $isPaid && empty($opts['skip_reconcile'])) {
        $recovered = challenge_reconcile($pdo, $userId, $quiz, $fee, !empty($opts['force_reconcile']), (string)($opts['only_ref'] ?? ''));
        if ($recovered !== null) {
            $hasAccess = true;
            $code = $recovered;
        }
    }

    $pending = (!$hasAccess && !$hasAttempted) ? $paymentModel->getPendingPayment($userId, $id) : null;

    // The sitting itself, if there is one: mark, and the place on the board.
    $attempt = null;
    if ($hasAttempted) {
        $stmt = $pdo->prepare('SELECT id, score, total, completed_at FROM quiz_attempts WHERE quiz_id = ? AND user_id = ? ORDER BY completed_at DESC, id DESC LIMIT 1');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $score = (int)$row['score'];
            $total = (int)$row['total'];
            $attempt = [
                'attempt_id' => (int)$row['id'],
                'score' => $score,
                'total' => $total,
                'percent' => $total > 0 ? (int)round($score / $total * 100) : 0,
                'completed_at' => (string)$row['completed_at'],
            ];
            if ($isChallenge) {
                $board = $attemptModel->topScores($id, null, 1000);
                foreach ($board as $i => $entry) {
                    if ((int)$entry['user_id'] === $userId) {
                        $attempt['rank'] = ['position' => $i + 1, 'of' => count($board)];
                        break;
                    }
                }
            }
        }
    }

    // The gates the entry page explains rather than trips over.
    $blocked = challenge_entry_blocked($pdo, $quiz, $user);
    $quizType = (string)($quiz['type'] ?? 'both');

    $bank = payment_bank_details();
    $paystackReady = msb_paystack_key(true) !== '';

    return [
        'ok' => true,
        'now' => $now,
        'challenge' => $card + [
            'is_challenge' => $isChallenge,
            'is_paid' => $isPaid,
            'entry_fee' => $isPaid ? $fee : 0.0,
            'type' => $quizType,
            'access_code_set' => trim((string)($quiz['access_code'] ?? '')) !== '',
        ],
        'access' => [
            'granted' => $hasAccess,
            // The code is only ever shipped once a verified payment or redeemed
            // voucher stands behind it - never before.
            'code' => $hasAccess ? $code : '',
            'joined' => $joined,
            'attempted' => $hasAttempted,
            'full' => (bool)$isFull,
            'blocked' => $blocked,
        ],
        'attempt' => $attempt,
        'payment' => [
            'paystack_available' => $paystackReady,
            'bank' => $bank,
            'pending_receipt' => $pending ? [
                'reference' => (string)$pending['payment_ref'],
                'amount' => (float)$pending['amount'],
                'created_at' => (string)$pending['created_at'],
            ] : null,
        ],
    ];
}

// ───────────────────────────────────────────────────────────── the state ──────
$in = $isPost ? api_input() : [];
$quizId = $isPost ? (int)api_field($in, 'quiz_id') : (int)($_GET['id'] ?? 0);
$quiz = $quizId > 0 ? $quizModel->getById($quizId) : null;
if (!$quiz) {
    api_err('not_found', 'That challenge does not exist.', 404);
}
$isChallenge = ($quiz['mode'] ?? 'normal') === 'challenge';
if (!$isChallenge && empty($quiz['is_paid'])) {
    api_err('not_available', 'This quiz is not a challenge or a paid set.', 404);
}

if (!$isPost) {
    api_out(challenge_state($pdo, $quiz, $user));
}

$action = api_field($in, 'action');
$fee = (float)($quiz['entry_fee'] ?? 0);
$isPaid = !empty($quiz['is_paid']) && $fee > 0;

// The website shows these students the door, not the forms. `verify` stays
// open: it only recovers money already captured, never sells a new seat.
if (in_array($action, ['join', 'redeem', 'pay_init', 'receipt'], true)) {
    $entryBlocked = challenge_entry_blocked($pdo, $quiz, $user);
    if ($entryBlocked !== null) {
        api_err($entryBlocked['code'], $entryBlocked['message'], 403);
    }
}

// ─────────────────────────────────────────────────────────── join (free) ──────
if ($action === 'join') {
    if (!$isChallenge || $isPaid) {
        api_err('not_joinable', 'This challenge is entered by payment or access code, not a waitlist.');
    }
    $max = !empty($quiz['max_participants']) ? (int)$quiz['max_participants'] : null;
    if ($max && challenge_participant_count($pdo, $quizId) >= $max) {
        api_err('full', 'This challenge is full - every seat is taken.');
    }
    try {
        $pdo->prepare('INSERT IGNORE INTO challenge_participants (quiz_id, user_id) VALUES (?, ?)')->execute([$quizId, $userId]);
        (new Notification($pdo))->create(
            $userId,
            'challenge_joined',
            '🎯 Joined: ' . $quiz['title'],
            "You've joined the challenge \"" . $quiz['title'] . "\". We'll notify you when it's about to go live!",
            SITE_URL . '/quiz_special.php?id=' . $quizId
        );
    } catch (Throwable $e) { /* duplicates are a join too */ }
    api_out(challenge_state($pdo, $quiz, $user, ['skip_reconcile' => true]));
}

// ────────────────────────────────────────────────────────── redeem a code ─────
if ($action === 'redeem') {
    $submitted = trim(api_field($in, 'code'));
    if ($submitted === '') {
        api_err('code_empty', 'Type the access code first.');
    }

    $granted = false;
    $error = '';
    // Universal code first, exactly as the website checks it.
    if (!empty($quiz['access_code']) && strtoupper($submitted) === strtoupper(trim((string)$quiz['access_code']))) {
        $paymentModel->recordPayment($userId, $quizId, 0, 'CODE-' . strtoupper($submitted) . '-' . time(), 'successful');
        $granted = true;
    } else {
        $voucher = $accessCodeModel->verifyCode($submitted, $quizId);
        if ($voucher) {
            if ((int)$voucher['is_used'] === 1) {
                if ((int)$voucher['used_by_user_id'] === $userId) {
                    $granted = true;
                } else {
                    $error = 'This access code has already been used by another user.';
                }
            } elseif ($accessCodeModel->useCode((int)$voucher['id'], $userId)) {
                $paymentModel->recordPayment($userId, $quizId, 0, 'VOUCHER-' . strtoupper($submitted) . '-' . time(), 'successful');
                $granted = true;
            } else {
                $error = 'Failed to redeem the code. Please try again.';
            }
        } else {
            $error = 'Invalid access code. Please try again.';
        }
    }

    if (!$granted) {
        api_err('code_invalid', $error !== '' ? $error : 'Invalid access code. Please try again.');
    }
    api_out(challenge_state($pdo, $quiz, $user, ['skip_reconcile' => true]));
}

// ─────────────────────────────────────────────────────── start a checkout ─────
if ($action === 'pay_init') {
    if (!$isPaid) {
        api_err('not_paid', 'This challenge does not have an entry fee.');
    }
    if (msb_paystack_key(true) === '') {
        api_err('payments_offline', 'Online payment is not available right now. Please use bank transfer or an access code.', 503);
    }
    if ($paymentModel->hasPaid($userId, $quizId) || $accessCodeModel->hasUserUsedACodeForQuiz($userId, $quizId)) {
        api_out(challenge_state($pdo, $quiz, $user, ['skip_reconcile' => true]));
    }
    if ($attemptModel->hasAttempted($quizId, $userId)) {
        api_err('already_taken', 'You have already sat this challenge. Its result is in My Quizzes.', 409);
    }
    $max = !empty($quiz['max_participants']) ? (int)$quiz['max_participants'] : null;
    if ($max && challenge_participant_count($pdo, $quizId) >= $max) {
        api_err('full', 'This challenge is full - every seat is taken.');
    }

    // Drop yesterday's abandoned checkouts so the list cannot grow forever.
    foreach (challenge_pay_bindings($pdo, $userId) as $oldRef => $bound) {
        if ((int)($bound['created'] ?? 0) < time() - 86400) {
            challenge_pay_forget($pdo, $userId, $oldRef);
        }
    }

    $ref = 'MSB-APP-' . $userId . '-' . $quizId . '-' . bin2hex(random_bytes(5));
    $payload = [
        'email' => (string)$user['email'],
        'amount' => (int)round($fee * 100),
        'reference' => $ref,
        'metadata' => ['quiz_id' => $quizId, 'user_id' => $userId],
        'callback_url' => SITE_URL . '/api/v1/challenge_callback.php',
    ];

    $ch = curl_init('https://api.paystack.co/transaction/initialize');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . msb_paystack_key(true),
            'Content-Type: application/json',
            'Accept: application/json',
            'Cache-Control: no-cache',
        ],
    ]);
    $response = curl_exec($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);

    $data = $response !== false ? json_decode((string)$response, true) : null;
    if ($curlErrno !== 0 || !is_array($data) || empty($data['status']) || empty($data['data']['authorization_url'])) {
        log_error('App Paystack initialize failed', ['quiz_id' => $quizId, 'user_id' => $userId, 'errno' => $curlErrno, 'resp' => substr((string)$response, 0, 300)]);
        api_err('pay_init_failed', 'The checkout could not be opened. Please try again, or pay by bank transfer.', 502);
    }

    $pdo->prepare('INSERT INTO user_meta (user_id, meta_key, meta_value) VALUES (?, ?, ?)')
        ->execute([$userId, challenge_pay_meta_key($ref), json_encode([
            'quiz_id' => $quizId,
            'user_id' => $userId,
            'amount' => $fee,
            'created' => time(),
            'checked' => 0,
        ])]);

    api_out([
        'ok' => true,
        'reference' => $ref,
        'authorization_url' => (string)$data['data']['authorization_url'],
    ]);
}

// ──────────────────────────────────────────────── re-check one checkout ───────
if ($action === 'verify') {
    $ref = trim(api_field($in, 'reference'));
    if ($ref === '' || !preg_match('/^[A-Za-z0-9-]+$/', $ref)) {
        api_err('bad_reference', 'That payment reference is not valid.');
    }
    $bindings = challenge_pay_bindings($pdo, $userId);
    if (!isset($bindings[$ref]) || (int)($bindings[$ref]['quiz_id'] ?? 0) !== $quizId) {
        // It may already have been credited and forgotten - the state says so.
        api_out(challenge_state($pdo, $quiz, $user, ['skip_reconcile' => true]));
    }
    api_out(challenge_state($pdo, $quiz, $user, ['force_reconcile' => true, 'only_ref' => $ref]));
}

// ─────────────────────────────────────────────────── upload a receipt ─────────
if ($action === 'receipt') {
    if (payment_bank_details() === null) {
        api_err('bank_unavailable', 'Bank transfer payments are not available right now. Please pay online or use an access code.');
    }
    if (!$isPaid) {
        api_err('not_paid', 'This challenge does not have an entry fee to pay.');
    }
    if ($paymentModel->hasPaid($userId, $quizId) || $accessCodeModel->hasUserUsedACodeForQuiz($userId, $quizId)) {
        api_err('already_paid', 'Your entry is already confirmed - no receipt is needed.');
    }
    if ($paymentModel->getPendingPayment($userId, $quizId)) {
        api_err('receipt_pending', 'You already have a receipt under review for this challenge.');
    }

    // The fee is whatever the quiz actually costs, never whatever the phone posts.
    $amount = (float)api_field($in, 'amount', '0');
    if (abs($amount - $fee) > 0.01) {
        api_err('amount_mismatch', 'The amount does not match this challenge entry fee of ₦' . number_format($fee, 2) . '.');
    }

    if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
        api_err('no_file', 'Please attach a valid receipt file.');
    }
    $file = $_FILES['receipt'];
    if ($file['size'] > 1 * 1024 * 1024) {
        api_err('file_too_large', 'File size exceeds the 1MB limit.');
    }
    if (!in_array($file['type'], ['image/jpeg', 'image/png', 'application/pdf'], true)) {
        api_err('bad_type', 'Invalid file type. Only JPG, PNG and PDF are allowed.');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
        api_err('bad_extension', 'Invalid file extension.');
    }

    $uploadDir = UPLOAD_PATH . '/receipts/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $newFileName = 'rcpt_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $newFileName)) {
        api_err('save_failed', 'Failed to save the uploaded file.', 500);
    }

    $paymentModel->recordPayment($userId, $quizId, $fee, 'MANUAL-' . $userId . '-' . time(), 'pending', '/assets/uploads/receipts/' . $newFileName);
    api_out(challenge_state($pdo, $quiz, $user, ['skip_reconcile' => true]));
}

api_err('unknown_action', 'That action is not supported.', 400);