<?php
/**
 * Where Paystack sends the phone's browser after a challenge checkout.
 *
 * The app cannot hold a PHP session, so the reference was bound server-side to
 * the buyer, the set and the fee when the checkout started (user_meta). This
 * page re-reads that binding, verifies the transaction with Paystack itself,
 * and credits through the same paystack_grant_access() the website uses - which
 * also sends the notification and the email. Nothing here trusts the query
 * string beyond the reference it looks up.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/Payment.php';
require_once __DIR__ . '/../../includes/paystack_reconcile.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$ref = trim((string)($_GET['reference'] ?? $_GET['trxref'] ?? ''));

$title = 'Return to the app';
$tone = 'wait';
$message = 'Close this page and return to the MyStudy Base app - your entry will be confirmed there.';

if ($ref !== '' && preg_match('/^[A-Za-z0-9-]+$/', $ref)) {
    $metaKey = 'msb_pay_' . $ref;
    $stmt = $pdo->prepare('SELECT user_id, meta_value FROM user_meta WHERE meta_key = ? LIMIT 1');
    $stmt->execute([$metaKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $bound = $row ? json_decode((string)$row['meta_value'], true) : null;

    if ($row && is_array($bound)) {
        $userId = (int)$row['user_id'];
        $quizId = (int)($bound['quiz_id'] ?? 0);
        $paymentModel = new Payment($pdo);

        $existing = $paymentModel->getPaymentByRef($ref);
        if ($existing && $existing['status'] === 'successful') {
            $pdo->prepare('DELETE FROM user_meta WHERE meta_key = ?')->execute([$metaKey]);
            $title = 'Payment confirmed';
            $tone = 'good';
            $message = 'Your entry is already confirmed. Return to the MyStudy Base app - your access code is waiting on the challenge page.';
        } elseif ($quizId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM quizzes WHERE id = ?');
            $stmt->execute([$quizId]);
            $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
            $fee = $quiz ? (float)($quiz['entry_fee'] ?? 0) : 0;

            if ($quiz && $fee > 0) {
                $result = paystack_fetch_transaction($ref);
                if ($result['ok'] && is_array($result['tx'])) {
                    $check = paystack_transaction_matches($result['tx'], $ref, $fee, $quizId, $userId);
                    if ($check['ok']) {
                        paystack_grant_access($pdo, $userId, $quizId, $quiz, $ref, $check['paid']);
                        $pdo->prepare('DELETE FROM user_meta WHERE meta_key = ?')->execute([$metaKey]);
                        $title = 'Payment confirmed';
                        $tone = 'good';
                        $message = 'Your entry is confirmed and your access code has been applied. Return to the MyStudy Base app to start the challenge.';
                    } else {
                        $title = 'Payment not confirmed yet';
                        $tone = 'wait';
                        $message = 'Paystack has not confirmed this payment yet. If you completed it, return to the app and tap "I have paid - check again". If you were charged, contact support with reference ' . htmlspecialchars($ref, ENT_QUOTES) . '.';
                    }
                } else {
                    log_error('Challenge callback verify failed: ' . $result['detail'], ['reference' => $ref, 'quiz_id' => $quizId, 'user_id' => $userId]);
                    $title = 'Could not confirm yet';
                    $tone = 'wait';
                    $message = 'We could not reach Paystack just now. Return to the app and tap "I have paid - check again" - your payment will be confirmed there.';
                }
            }
        }
    }
}

$badge = $tone === 'good' ? '#047857' : '#B45309';
$icon = $tone === 'good' ? '&#10003;' : '&#8987;';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($title, ENT_QUOTES); ?> · MyStudy Base</title>
<style>
  body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
         background:linear-gradient(160deg,#ECFDF5,#F8FAFC); font-family:system-ui,-apple-system,'Segoe UI',sans-serif; padding:24px; }
  .card { max-width:420px; width:100%; background:#fff; border:1px solid #E2E8F0; border-radius:24px;
          padding:36px 28px; text-align:center; box-shadow:0 18px 50px rgba(4,120,87,.12); }
  .badge { width:64px; height:64px; border-radius:32px; margin:0 auto 18px; display:flex; align-items:center;
           justify-content:center; font-size:30px; color:#fff; background:<?php echo $badge; ?>; }
  h1 { font-size:21px; margin:0 0 10px; color:#0F172A; }
  p { font-size:14.5px; line-height:1.6; color:#475569; margin:0; }
  .brand { margin-top:22px; font-size:12px; font-weight:700; letter-spacing:.6px; color:#047857; }
</style>
</head>
<body>
  <div class="card">
    <div class="badge"><?php echo $icon; ?></div>
    <h1><?php echo htmlspecialchars($title, ENT_QUOTES); ?></h1>
    <p><?php echo htmlspecialchars($message, ENT_QUOTES); ?></p>
    <div class="brand">MYSTUDY BASE</div>
  </div>
</body>
</html>