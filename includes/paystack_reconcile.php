<?php
/**
 * Paystack checkout references live only in the buyer's session, so a browser that
 * reloads (very common in the bank-transfer flow) can lose the callback after the
 * money has already been captured. These helpers re-check outstanding references
 * with Paystack and credit them late.
 */

require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../models/AccessCode.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/User.php';

/**
 * @return array{ok:bool,detail:string,tx:array|null}
 */
function paystack_fetch_transaction(string $ref): array
{
    $ch = curl_init('https://api.paystack.co/transaction/verify/' . rawurlencode($ref));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20,
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
        return ['ok' => false, 'detail' => 'cURL error ' . $curlErrno . ': ' . $curlError, 'tx' => null];
    }

    $resData = json_decode((string)$response, true);
    if (!is_array($resData) || empty($resData['status']) || !isset($resData['data'])) {
        return ['ok' => false, 'detail' => 'HTTP ' . $httpCode . ' response: ' . substr((string)$response, 0, 500), 'tx' => null];
    }

    return ['ok' => true, 'detail' => '', 'tx' => $resData['data']];
}

/**
 * The rules every product shares: Paystack must say success, the reference must be
 * the one we asked about, the currency must be naira, the amount must cover what we
 * charged, and the metadata must still point at the same buyer and item.
 *
 * @param array $metaExpect e.g. ['quiz_id' => 12, 'user_id' => 3]
 * @return array{ok:bool,reason:string,paid:float}
 */
function paystack_transaction_binds(array $tx, string $ref, float $expectedAmount, array $metaExpect = []): array
{
    if (($tx['status'] ?? '') !== 'success') {
        return ['ok' => false, 'reason' => 'Transaction status: ' . (($tx['status'] ?? '') ?: 'unknown'), 'paid' => 0.0];
    }
    if (($tx['reference'] ?? '') !== $ref) {
        return ['ok' => false, 'reason' => 'Returned reference differs from requested', 'paid' => 0.0];
    }
    if (strtoupper((string)($tx['currency'] ?? '')) !== 'NGN') {
        return ['ok' => false, 'reason' => 'Unsupported currency: ' . ($tx['currency'] ?? 'none'), 'paid' => 0.0];
    }

    // Paystack reports amounts in kobo; our prices are naira.
    $paid = ((int)($tx['amount'] ?? 0)) / 100;
    if ($paid + 0.01 < $expectedAmount) {
        return ['ok' => false, 'reason' => 'Underpayment: paid ' . $paid . ' of ' . $expectedAmount, 'paid' => $paid];
    }

    $meta = $tx['metadata'] ?? [];
    if (is_array($meta)) {
        foreach ($metaExpect as $key => $value) {
            if (isset($meta[$key]) && (int)$meta[$key] !== (int)$value) {
                return ['ok' => false, 'reason' => 'Metadata ' . $key . ' mismatch', 'paid' => $paid];
            }
        }
    }

    return ['ok' => true, 'reason' => '', 'paid' => $paid];
}

/**
 * A reference may only unlock the quiz, user and amount it was created for.
 *
 * @return array{ok:bool,reason:string,paid:float}
 */
function paystack_transaction_matches(array $tx, string $ref, float $expectedAmount, int $quizId, int $userId): array
{
    return paystack_transaction_binds($tx, $ref, $expectedAmount, ['quiz_id' => $quizId, 'user_id' => $userId]);
}

/** Credit a verified transaction and return the student's access code. */
function paystack_grant_access(PDO $pdo, int $userId, int $quizId, array $quiz, string $ref, float $paidNaira): string
{
    $paymentModel = new Payment($pdo);
    $accessCodeModel = new AccessCode($pdo);

    // A second paid reference for an already-unlocked quiz is money, not news.
    $alreadyUnlocked = $paymentModel->hasPaid($userId, $quizId);
    $existing = $paymentModel->getPaymentByRef($ref);
    $alreadyCredited = $existing && $existing['status'] === 'successful';

    if (!$existing) {
        $paymentModel->recordPayment($userId, $quizId, $paidNaira, $ref, 'successful');
    } elseif (!$alreadyCredited) {
        $paymentModel->updateStatusByRef($ref, 'successful');
    }

    $code = $accessCodeModel->getCodeForUser($userId, $quizId)
        ?: $accessCodeModel->generateForUser($quizId, $userId);

    if (!$alreadyCredited && !$alreadyUnlocked) {
        $quizTitle = $quiz['title'] ?? 'Special Quiz';
        $quizLink = SITE_URL . '/quiz_special.php?id=' . $quizId;

        $msg  = "Your payment was successful! Here are your details:\n\n";
        $msg .= "Quiz: {$quizTitle}\n";
        $msg .= "Access Code: {$code}\n";
        $msg .= "Amount Paid: ₦" . number_format($paidNaira, 0) . "\n\n";
        $msg .= "Your access code has been automatically applied. Click below to go to the quiz.";
        (new Notification($pdo))->create($userId, 'payment_success', '🎟️ Access Code Ready: ' . $quizTitle, $msg, $quizLink);

        $uModel = new User($pdo);
        $uModel->getById($userId);
        if (!empty($uModel->email)) {
            require_once __DIR__ . '/EmailService.php';
            require_once __DIR__ . '/EmailTemplates.php';
            $svc = EmailService::fromConfig();
            if ($svc) {
                $tpl = EmailTemplates::paymentAccessCode($uModel->name ?: 'Student', $quizTitle, $code, $quizLink);
                $svc->send($uModel->email, $uModel->name ?: 'Student', $tpl['subject'], $tpl['html'], $tpl['text']);
            }
        }
    }

    unset($_SESSION['paystack_pending'][$ref]);
    return (string)$code;
}

/**
 * Credit a quiz from a transaction payload we did not obtain from the buyer's
 * browser (the webhook). The reference is the only claim, so the metadata has to
 * name a real paid quiz and the amount has to cover it.
 *
 * @return array{ok:bool,reason:string}
 */
function paystack_credit_quiz_transaction(PDO $pdo, string $ref, array $tx): array
{
    $paymentModel = new Payment($pdo);
    $existing = $paymentModel->getPaymentByRef($ref);
    if ($existing && $existing['status'] === 'successful') {
        return ['ok' => false, 'reason' => 'Already credited'];
    }

    $quizId = (int)($tx['metadata']['quiz_id'] ?? 0);
    $userId = (int)($tx['metadata']['user_id'] ?? 0);
    if ($quizId <= 0 || $userId <= 0) {
        return ['ok' => false, 'reason' => 'Transaction carries no quiz/user metadata'];
    }

    $stmt = $pdo->prepare('SELECT * FROM quizzes WHERE id = :id');
    $stmt->execute([':id' => $quizId]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$quiz) {
        return ['ok' => false, 'reason' => 'Quiz no longer exists'];
    }

    // What we charged for this quiz, not what the payload claims.
    $fee = (float)($quiz['entry_fee'] ?? 0);
    if ($fee <= 0) {
        return ['ok' => false, 'reason' => 'Quiz is not a paid quiz'];
    }

    $check = paystack_transaction_binds($tx, $ref, $fee, ['quiz_id' => $quizId, 'user_id' => $userId]);
    if (!$check['ok']) {
        return ['ok' => false, 'reason' => $check['reason']];
    }

    // Paystack can deliver the same event twice, or while a browser is mid-checkout.
    $lock = 'msb_quiz_' . md5($ref);
    $locked = false;
    try {
        $locked = (int)$pdo->query("SELECT GET_LOCK('" . $lock . "', 5)")->fetchColumn() === 1;
    } catch (Throwable $e) {
        $locked = false;
    }

    // Re-read under the lock - a browser may have credited it while we were talking to Paystack.
    $fresh = $paymentModel->getPaymentByRef($ref);
    if ($fresh && $fresh['status'] === 'successful') {
        if ($locked) {
            try { $pdo->query("SELECT RELEASE_LOCK('" . $lock . "')"); } catch (Throwable $e) {}
        }
        return ['ok' => false, 'reason' => 'Already credited'];
    }

    paystack_grant_access($pdo, $userId, $quizId, (array)$quiz, $ref, $check['paid']);

    if ($locked) {
        try {
            $pdo->query("SELECT RELEASE_LOCK('" . $lock . "')");
        } catch (Throwable $e) { /* lock dies with the connection */ }
    }

    return ['ok' => true, 'reason' => ''];
}

/**
 * Ask Paystack about the references this student still has outstanding for this quiz.
 * Returns an access code as soon as one of them turns out to have been paid.
 */
function paystack_reconcile_user(PDO $pdo, int $userId, int $quizId, array $quiz, float $currentFee, int $maxRefs = 3): ?string
{
    $pending = $_SESSION['paystack_pending'] ?? [];
    if (!is_array($pending) || $pending === []) {
        return null;
    }

    $candidates = [];
    foreach (array_reverse($pending, true) as $ref => $bound) {
        if (!is_array($bound)) {
            continue;
        }
        if ((int)($bound['user_id'] ?? 0) !== $userId || (int)($bound['quiz_id'] ?? 0) !== $quizId) {
            continue;
        }
        if ((int)($bound['created'] ?? 0) < time() - 86400) {
            continue;
        }
        if ((int)($bound['checked'] ?? 0) > time() - 60) {
            continue;
        }
        $candidates[] = (string)$ref;
        if (count($candidates) >= $maxRefs) {
            break;
        }
    }

    foreach ($candidates as $ref) {
        $amount = (float)($pending[$ref]['amount'] ?? $currentFee);
        $result = paystack_fetch_transaction($ref);
        $_SESSION['paystack_pending'][$ref]['checked'] = time();

        if (!$result['ok'] || !is_array($result['tx'])) {
            log_error('Paystack reconciliation lookup failed: ' . $result['detail'], [
                'reference' => $ref,
                'quiz_id'   => $quizId,
                'user_id'   => $userId,
            ]);
            continue;
        }

        $check = paystack_transaction_matches($result['tx'], $ref, $amount, $quizId, $userId);
        if (!$check['ok']) {
            continue;
        }

        return paystack_grant_access($pdo, $userId, $quizId, $quiz, $ref, $check['paid']);
    }

    return null;
}
