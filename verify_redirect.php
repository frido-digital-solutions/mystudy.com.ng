<?php
require_once __DIR__ . '/config/config.php';
require_login();

require_once __DIR__ . '/models/Quiz.php';
require_once __DIR__ . '/models/Payment.php';
require_once __DIR__ . '/models/AccessCode.php';
require_once __DIR__ . '/includes/paystack_reconcile.php';

$reference = trim((string)($_GET['reference'] ?? $_GET['trxref'] ?? ''));
$quizId = (int)($_GET['quiz_id'] ?? 0);
$userId = (int)$_SESSION['user_id'];

$quizData = $quizId > 0 ? (new Quiz($pdo))->getById($quizId) : null;
$expectedAmount = (float)($quizData['entry_fee'] ?? 0);
$paymentModel = new Payment($pdo);
$accessCodeModel = new AccessCode($pdo);

// A browser that reloaded can arrive here without the reference Paystack called
// back with. Re-check the references still bound to this session before giving up.
$recover = function () use ($pdo, $paymentModel, $accessCodeModel, $userId, $quizId, $quizData, $expectedAmount) {
    $code = paystack_reconcile_user($pdo, $userId, $quizId, (array)$quizData, $expectedAmount);
    if ($code === null && $paymentModel->hasPaid($userId, $quizId)) {
        $code = $accessCodeModel->getCodeForUser($userId, $quizId)
            ?: $accessCodeModel->generateForUser($quizId, $userId);
    }
    return $code;
};

if (!$quizData) {
    set_flash_message('error', 'Quiz not found.');
    redirect(SITE_URL . '/quizzes.php');
}

$backUrl = SITE_URL . '/quiz_special.php?id=' . $quizId;

$fail = function (string $userMessage, string $detail = '') use ($backUrl, $reference, $quizId, $userId) {
    log_error('Paystack verification failed: ' . ($detail !== '' ? $detail : $userMessage), [
        'reference' => $reference,
        'quiz_id'   => $quizId,
        'user_id'   => $userId,
    ]);
    set_flash_message('error', $userMessage);
    redirect($backUrl);
};

$successUrl = function (string $code) use ($quizId) {
    return SITE_URL . '/payment_success.php?code=' . urlencode($code) . '&quiz_id=' . $quizId;
};

if ($reference === '') {
    $recovered = $recover();
    if ($recovered !== null) {
        set_flash_message('success', 'Payment confirmed! Your access code has been applied.');
        redirect($successUrl($recovered));
    }
    $fail('We could not confirm your payment. If you were charged, contact support.', 'Missing payment reference.');
}

// A reference may only ever unlock the quiz and user it was created for.
$existing = $paymentModel->getPaymentByRef($reference);
if ($existing) {
    if ((int)$existing['quiz_id'] !== $quizId || (int)$existing['user_id'] !== $userId) {
        $fail('This payment reference belongs to a different purchase.', 'Reference reuse across quiz/user.');
    }
    if ($existing['status'] === 'successful') {
        $code = $accessCodeModel->getCodeForUser($userId, $quizId) ?: $accessCodeModel->generateForUser($quizId, $userId);
        unset($_SESSION['paystack_pending'][$reference]);
        redirect($successUrl($code));
    }
}

$bound = $_SESSION['paystack_pending'][$reference] ?? null;
if (!$bound || (int)$bound['quiz_id'] !== $quizId || (int)$bound['user_id'] !== $userId) {
    $recovered = $recover();
    if ($recovered !== null) {
        set_flash_message('success', 'Payment confirmed! Your access code has been applied.');
        redirect($successUrl($recovered));
    }
    $fail('This payment session could not be matched. Please start the payment again.', 'No server-side reference binding.');
}
$checkAmount = (float)($bound['amount'] ?? $expectedAmount);
if (abs($checkAmount - $expectedAmount) > 0.01) {
    $fail('The payment amount no longer matches this quiz. Please start again.', 'Bound amount differs from entry fee.');
}

$result = paystack_fetch_transaction($reference);
if (!$result['ok']) {
    $fail('We could not reach Paystack to confirm your payment. If you were charged, contact support with reference ' . $reference . '.', $result['detail']);
}

$check = paystack_transaction_matches((array)$result['tx'], $reference, $expectedAmount, $quizId, $userId);
if (!$check['ok']) {
    // Another checkout for this quiz may be the one that was actually funded.
    $_SESSION['paystack_pending'][$reference]['checked'] = time();
    $recovered = $recover();
    if ($recovered !== null) {
        set_flash_message('success', 'Payment confirmed! Your access code has been applied.');
        redirect($successUrl($recovered));
    }
    if (strpos($check['reason'], 'Underpayment') === 0) {
        $fail('The amount paid (₦' . number_format($check['paid'], 2) . ') is less than the entry fee (₦' . number_format($expectedAmount, 2) . ').', $check['reason']);
    }
    $fail('Your payment is not confirmed yet. If you were charged, stay on this page for a minute and open the quiz again.', $check['reason']);
}

$code = paystack_grant_access($pdo, $userId, $quizId, (array)$quizData, $reference, $check['paid']);
redirect($successUrl($code));
