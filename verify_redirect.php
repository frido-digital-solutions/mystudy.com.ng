<?php
require_once __DIR__ . '/config/config.php';
require_login();

require_once __DIR__ . '/models/Quiz.php';
require_once __DIR__ . '/models/Payment.php';
require_once __DIR__ . '/models/AccessCode.php';
require_once __DIR__ . '/models/Notification.php';
require_once __DIR__ . '/models/User.php';

$reference = trim((string)($_GET['reference'] ?? $_GET['trxref'] ?? ''));
$quizId = (int)($_GET['quiz_id'] ?? 0);
$userId = (int)$_SESSION['user_id'];

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

if ($reference === '' || $quizId <= 0) {
    $fail('We could not confirm your payment. Please try again or contact support.', 'Missing reference or quiz id.');
}

$quizData = (new Quiz($pdo))->getById($quizId);
if (!$quizData) {
    $fail('Quiz not found.', 'Quiz id ' . $quizId . ' does not exist.');
}

$expectedAmount = (float)($quizData['entry_fee'] ?? 0);
$paymentModel = new Payment($pdo);
$accessCodeModel = new AccessCode($pdo);

$grantAccess = function (float $paidNaira) use ($paymentModel, $accessCodeModel, $pdo, $reference, $quizId, $userId, $quizData) {
    $paymentModel->recordPayment($userId, $quizId, $paidNaira, $reference, 'successful');
    $newCode = $accessCodeModel->generateForUser($quizId, $userId);

    $uModel = new User($pdo);
    $uModel->getById($userId);

    $quizTitle = $quizData['title'] ?? 'Special Quiz';
    $quizLink = SITE_URL . '/quiz_special.php?id=' . $quizId;

    $nMsg  = "Your payment was successful! Here are your details:\n\n";
    $nMsg .= "Quiz: {$quizTitle}\n";
    $nMsg .= "Access Code: {$newCode}\n";
    $nMsg .= "Amount Paid: ₦" . number_format($paidNaira, 0) . "\n\n";
    $nMsg .= "Your access code has been automatically applied. Click below to go to the quiz.";
    (new Notification($pdo))->create($userId, 'payment_success', '🎟️ Access Code Ready: ' . $quizTitle, $nMsg, $quizLink);

    if (!empty($uModel->email)) {
        require_once __DIR__ . '/includes/EmailService.php';
        require_once __DIR__ . '/includes/EmailTemplates.php';
        $svc = EmailService::fromConfig();
        if ($svc) {
            $tpl = EmailTemplates::paymentAccessCode($uModel->name ?: 'Student', $quizTitle, $newCode, $quizLink);
            $svc->send($uModel->email, $uModel->name ?: 'Student', $tpl['subject'], $tpl['html'], $tpl['text']);
        }
    }

    unset($_SESSION['paystack_pending'][$reference]);
    redirect(SITE_URL . '/payment_success.php?code=' . urlencode($newCode) . '&quiz_id=' . $quizId);
};

// A reference may only ever unlock the quiz and user it was created for.
$existing = $paymentModel->getPaymentByRef($reference);
if ($existing) {
    if ((int)$existing['quiz_id'] !== $quizId || (int)$existing['user_id'] !== $userId) {
        $fail('This payment reference belongs to a different purchase.', 'Reference reuse across quiz/user.');
    }
    if ($existing['status'] === 'successful') {
        $code = $accessCodeModel->getCodeForUser($userId, $quizId) ?: $accessCodeModel->generateForUser($quizId, $userId);
        unset($_SESSION['paystack_pending'][$reference]);
        redirect(SITE_URL . '/payment_success.php?code=' . urlencode($code) . '&quiz_id=' . $quizId);
    }
}

$bound = $_SESSION['paystack_pending'][$reference] ?? null;
if (!$bound || (int)$bound['quiz_id'] !== $quizId || (int)$bound['user_id'] !== $userId) {
    $fail('This payment session could not be matched. Please start the payment again.', 'No server-side reference binding.');
}
if (abs((float)$bound['amount'] - $expectedAmount) > 0.01) {
    $fail('The payment amount no longer matches this quiz. Please start again.', 'Bound amount differs from entry fee.');
}

$ch = curl_init('https://api.paystack.co/transaction/verify/' . rawurlencode($reference));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
        'Accept: application/json',
        'Cache-Control: no-cache',
    ],
]);
$response = curl_exec($ch);
$curlErrno = curl_errno($ch);
$curlError = curl_error($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $curlErrno !== 0) {
    $fail('We could not reach Paystack to confirm your payment. If you were charged, contact support with reference ' . $reference . '.', 'cURL error ' . $curlErrno . ': ' . $curlError);
}

$resData = json_decode((string)$response, true);
if (!is_array($resData) || empty($resData['status']) || !isset($resData['data'])) {
    $fail('Paystack could not confirm this payment. If you were charged, contact support with reference ' . $reference . '.', 'HTTP ' . $httpCode . ' response: ' . substr((string)$response, 0, 500));
}

$tx = $resData['data'];

if (($tx['status'] ?? '') !== 'success') {
    $fail('Your payment was not completed. Status: ' . htmlspecialchars((string)($tx['status'] ?? 'unknown')), 'Transaction status not success.');
}
if (($tx['reference'] ?? '') !== $reference) {
    $fail('Payment reference mismatch. Please contact support.', 'Returned reference differs from requested.');
}
if (strtoupper((string)($tx['currency'] ?? '')) !== 'NGN') {
    $fail('Unsupported payment currency. Please contact support.', 'Currency: ' . ($tx['currency'] ?? 'none'));
}

// Paystack reports amounts in kobo; the quiz entry fee is in naira.
$paidNaira = ((int)($tx['amount'] ?? 0)) / 100;
if ($paidNaira + 0.01 < $expectedAmount) {
    $fail('The amount paid (₦' . number_format($paidNaira, 2) . ') is less than the entry fee (₦' . number_format($expectedAmount, 2) . ').', 'Underpayment.');
}

$meta = $tx['metadata'] ?? [];
if (is_array($meta)) {
    if (isset($meta['quiz_id']) && (int)$meta['quiz_id'] !== $quizId) {
        $fail('This payment was made for a different quiz.', 'Metadata quiz_id mismatch.');
    }
    if (isset($meta['user_id']) && (int)$meta['user_id'] !== $userId) {
        $fail('This payment was made by a different account.', 'Metadata user_id mismatch.');
    }
}

$grantAccess($paidNaira);