<?php
/**
 * Paystack returns the browser here after checkout. We never trust the redirect
 * itself - the money is confirmed against Paystack's API and the open row we wrote
 * before checkout. If any of that fails, the student is sent back to the material
 * page, where the same check runs again on load and the webhook will still catch it.
 */
require_once __DIR__ . '/config/config.php';
require_login();
require_once __DIR__ . '/models/Material.php';
require_once __DIR__ . '/includes/materials_paid.php';

$userId = (int)$_SESSION['user_id'];
$materialId = (int)($_GET['material_id'] ?? 0);
$reference = trim((string)($_GET['reference'] ?? $_GET['trxref'] ?? ''));

$back = SITE_URL . '/material.php?id=' . $materialId;
if ($materialId <= 0) {
    redirect(SITE_URL . '/materials.php');
}

$materialModel = new Material($pdo);
$material = $materialModel->getById($materialId);
if (!$material || $material['status'] !== 'approved') {
    set_flash_message('error', 'Material not found.');
    redirect(SITE_URL . '/materials.php');
}

if ($reference === '') {
    // No reference came back: try every open checkout this account has for it.
    if (material_verify_open_payments($pdo, $userId, $material)) {
        set_flash_message('success', 'Payment confirmed! Your download is unlocked.');
        redirect($back . '&paid=1');
    }
    set_flash_message('error', 'We could not match that payment to your account. If you completed it, your download will unlock as soon as Paystack confirms it.');
    redirect($back);
}

$payments = new MaterialPayment($pdo);
$payment = $payments->getByRef($reference);

if (!$payment || (int)$payment['user_id'] !== $userId) {
    // Either it is not theirs, or it was opened from another browser/session.
    if (material_verify_open_payments($pdo, $userId, $material)) {
        set_flash_message('success', 'Payment confirmed! Your download is unlocked.');
        redirect($back . '&paid=1');
    }
    set_flash_message('error', 'That payment does not belong to your account. Please contact support with the reference: ' . $reference);
    redirect($back);
}

$result = material_confirm_ref($pdo, $reference);

if ($result['credited'] || $payments->owns($userId, $materialId)) {
    set_flash_message('success', 'Payment confirmed! Your download is unlocked, and we have emailed you a copy.');
    redirect($back . '&paid=1');
}

if ($result['reason'] === 'Paystack unreachable') {
    set_flash_message('info', 'We are still confirming your payment with Paystack. This page will pick it up automatically in a moment.');
    redirect($back . '&pending=1');
}

if ($result['reason'] === 'Already credited') {
    set_flash_message('success', 'Payment confirmed! Your download is unlocked.');
    redirect($back . '&paid=1');
}

log_error('Material verification rejected: ' . $result['reason'], [
    'reference' => $reference,
    'material_id' => $materialId,
    'user_id' => $userId,
]);
set_flash_message('error', 'Paystack has not marked that payment as successful yet. If money left your account, it will unlock automatically as soon as they confirm it.');
redirect($back);
