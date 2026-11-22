<?php
/**
 * Challenge rewards, natively: claim_reward.php's contract, shaped for the app.
 *
 *   GET                 the wins worth celebrating, the claims in flight, the bank list
 *   GET  ?quiz_id=N     one challenge's podium truth plus that user's claim
 *   POST action=claim   bank details for a win -> reward_claims, as the web posts them
 *   POST action=ack     this device has celebrated that win; do not show it again
 *
 * There is no second set of rules here. The rank comes from the same
 * QuizAttempt::topScores(), the share from the same Quiz::prizeShare(), the row
 * lands in the same reward_claims table admin/rewards.php already reads, and the
 * ended events are settled by the site's own Quiz::processEndedChallenges() -
 * the call that mints the winner notification the app then celebrates.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../models/Quiz.php';
require_once __DIR__ . '/../../models/QuizAttempt.php';
require_once __DIR__ . '/../../models/Notification.php';

$user = api_auth();
$userId = (int)$user['id'];
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$in = $isPost ? api_input() : [];

$quizModel = new Quiz($pdo);
$attemptModel = new QuizAttempt($pdo);

/** The website's claim page creates this table lazily; the app must not fail on it. */
function rewards_table(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS reward_claims (
            id INT AUTO_INCREMENT PRIMARY KEY,
            quiz_id INT NOT NULL,
            user_id INT NOT NULL,
            rank_position INT NOT NULL,
            amount DECIMAL(10,2) NULL,
            full_name VARCHAR(255) NOT NULL DEFAULT '',
            bank_name VARCHAR(255) NOT NULL DEFAULT '',
            account_number VARCHAR(50) NOT NULL DEFAULT '',
            account_name VARCHAR(255) NOT NULL DEFAULT '',
            phone VARCHAR(30) NULL,
            status ENUM('pending','processing','sent','paid','rejected') DEFAULT 'pending',
            admin_notes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_claim (quiz_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
        error_log('api reward_claims: ' . $e->getMessage());
    }
}

/** The site's own prize wording (config.php), with a name for non-cash prizes. */
function reward_prize_label(?float $amount, ?string $type, ?string $text): string
{
    $label = format_prize((float)($amount ?? 0), ($type ?? '') !== '' ? (string)$type : 'money');
    if ($label !== '') {
        return $label;
    }
    $text = trim((string)$text);
    return $text !== '' ? $text : 'Leaderboard prize';
}

const RANK_ORDER = ['1st', '2nd', '3rd'];

/** What a claim reads on a card, in the words the money is actually in. */
function reward_status_label(string $status): array
{
    return match ($status) {
        'processing' => ['Claim is processing', 'Your bank details are with the rewards team.'],
        'sent'       => ['Reward sent', 'Your prize has been sent to the account you gave.'],
        'paid'       => ['Reward paid', 'Your prize has been paid. Keep chasing the top.'],
        // An admin could not pay this one as submitted - the reason they gave is
        // the sentence the student has to act on, so it is the note itself.
        'rejected'   => ['Sent back by an admin', 'Fix the details and send it again.'],
        default      => ['Claim is processing', 'An admin is checking your details before the payout.'],
    };
}

/** This user's place on one ended challenge's podium, or 0 when they are off it. */
function rewards_rank(QuizAttempt $attemptModel, int $quizId, int $userId, int $depth = 3): array
{
    $board = $attemptModel->topScores($quizId, null, $depth);
    foreach ($board as $i => $entry) {
        if ((int)$entry['user_id'] === $userId) {
            return [
                'rank' => $i + 1,
                'of' => count($board),
                'score' => (int)($entry['score'] ?? 0),
                'total' => (int)($entry['total'] ?? 0),
            ];
        }
    }
    return ['rank' => 0, 'of' => count($board), 'score' => 0, 'total' => 0];
}

function rewards_claim(PDO $pdo, int $quizId, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM reward_claims WHERE quiz_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$quizId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function rewards_claim_shape(?array $row): ?array
{
    if (!$row) {
        return null;
    }
    [$title, $note] = reward_status_label((string)$row['status']);
    return [
        'id' => (int)$row['id'],
        'quiz_id' => (int)$row['quiz_id'],
        'rank' => (int)$row['rank_position'],
        'amount' => (float)($row['amount'] ?? 0),
        'amount_label' => (float)($row['amount'] ?? 0) > 0 ? reward_prize_label((float)$row['amount'], 'money', null) : null,
        'full_name' => (string)$row['full_name'],
        'bank_name' => (string)$row['bank_name'],
        'account_number' => (string)$row['account_number'],
        'account_name' => (string)$row['account_name'],
        'phone' => (string)($row['phone'] ?? ''),
        'status' => (string)$row['status'],
        'status_title' => $title,
        'status_note' => $note,
        'admin_notes' => (string)($row['admin_notes'] ?? ''),
        // Only a sent-back claim may be edited and sent again. Everything else
        // is already moving, and a second submission would overwrite an admin's
        // note about money that has left the building.
        'resubmittable' => (string)$row['status'] === 'rejected',
        'settled' => in_array((string)$row['status'], ['sent', 'paid'], true),
        'created_at' => (string)$row['created_at'],
    ];
}

/**
 * The whole reward truth for this account.
 *
 * A win only celebrates once: it must be ended, on the podium, with a prize, not
 * already claimed and not already acknowledged by this device. That is the list
 * the app pops confetti for; the claims list is everything already in flight.
 */
function rewards_payload(PDO $pdo, Quiz $quizModel, QuizAttempt $attemptModel, int $userId, int $onlyQuizId = 0): array
{
    rewards_table($pdo);

    // Settling an ended event is what creates the winner notification, and it is
    // idempotent: it only ever touches rows still flagged rewards_notified = 0.
    try {
        $quizModel->processEndedChallenges($pdo);
    } catch (Throwable $e) {
        log_error('api rewards: ended challenges not settled', ['err' => $e->getMessage()]);
    }

    $ack = [];
    try {
        $stmt = $pdo->prepare("SELECT meta_key, meta_value FROM user_meta WHERE user_id = ? AND meta_key LIKE 'msb_win_ack_%'");
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $ack[(int)substr((string)$r['meta_key'], strlen('msb_win_ack_'))] = true;
        }
    } catch (Throwable $e) { /* a missing acknowledgement is only a repeat win */ }

    $where = "q.mode = 'challenge' AND q.event_end IS NOT NULL AND q.event_end <= NOW()
              AND (q.prize_amount > 0 OR (q.prize_text IS NOT NULL AND q.prize_text <> ''))";
    $params = [];
    if ($onlyQuizId > 0) {
        $where .= ' AND q.id = ?';
        $params[] = $onlyQuizId;
    }

    $sql = "SELECT q.id, q.title, q.subject, q.level, q.prize_type, q.prize_amount, q.prize_text,
                   q.entry_fee, q.is_paid, q.event_end, q.total_phases, q.current_phase
              FROM quizzes q WHERE $where
             ORDER BY q.event_end DESC LIMIT " . ($onlyQuizId > 0 ? 1 : 12);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $wins = [];
    $claims = [];
    $ready = [];
    $focus = null;

    foreach ($rows as $q) {
        $id = (int)$q['id'];
        $pool = (float)($q['prize_amount'] ?? 0);
        $type = (string)($q['prize_type'] ?? 'money');
        $board = rewards_rank($attemptModel, $id, $userId, $onlyQuizId > 0 ? 1000 : 3);
        $claim = rewards_claim_shape(rewards_claim($pdo, $id, $userId));
        $share = $board['rank'] >= 1 && $board['rank'] <= 3 ? Quiz::prizeShare($pool, $board['rank']) : 0.0;

        $prizeLabel = reward_prize_label($pool, $type, $q['prize_text'] ?? null);
        $entry = [
            'quiz_id' => $id,
            'title' => (string)$q['title'],
            'subject' => $q['subject'] ?? null,
            'level' => $q['level'] ?? null,
            'event_end' => (string)$q['event_end'],
            'prize_type' => $type,
            'pool' => $pool,
            'pool_label' => $prizeLabel,
            'rank' => $board['rank'],
            'of' => $board['of'],
            'rank_label' => RANK_ORDER[$board['rank'] - 1] ?? ($board['rank'] . 'th'),
            'score' => $board['score'],
            'total' => $board['total'],
            'percent' => $board['total'] > 0 ? (int)round($board['score'] / $board['total'] * 100) : 0,
            'share' => $share,
            'share_label' => reward_prize_label($share, $type, $q['prize_text'] ?? null),
            'on_podium' => $board['rank'] >= 1 && $board['rank'] <= 3,
            'claim' => $claim,
        ];

        if ($claim) {
            $claims[] = $claim + ['quiz_title' => $entry['title'], 'prize_type' => $type, 'pool_label' => $prizeLabel];
        } elseif ($entry['on_podium'] && $share > 0) {
            // On the podium, prize owed, no claim row yet: this is the state a
            // student leaves by tapping "I'll claim it later", and the front page
            // must keep saying so until the claim exists.
            $ready[] = $entry + [
                'kind' => 'ready',
                'quiz_title' => $entry['title'],
                'amount_label' => $entry['share_label'],
                'status' => 'ready',
                'status_title' => 'Prize ready to claim',
                'status_note' => 'Tap to send your bank details.',
                'resubmittable' => false,
            ];
        }

        if ($onlyQuizId > 0) {
            $focus = $entry;
            continue;
        }

        if ($entry['on_podium'] && !$claim && empty($ack[$id])) {
            $wins[] = $entry;
        }
    }

    if ($onlyQuizId > 0 && !$focus) {
        api_err('not_found', 'That challenge is not a rewarded event, or it has not ended yet.', 404);
    }

    usort($claims, static fn($a, $b) => strcmp((string)$b['created_at'], (string)$a['created_at']));

    // Everything the front page has to keep saying: a prize nobody has claimed
    // yet, and a claim that has not yet reached a bank. A claim leaves the list
    // the moment its status is sent - not before.
    $open = array_values(array_filter($claims, static fn($c) => !$c['settled']));
    $rewards = array_values(array_merge(
        $ready,
        array_map(static fn($c) => $c + ['kind' => 'claim', 'share_label' => $c['amount_label']], $open)
    ));

    return [
        'ok' => true,
        'wins' => $wins,
        'claim' => $focus ? $focus['claim'] : null,
        'challenge' => $focus,
        'claims' => array_slice($claims, 0, 8),
        'pending' => $open,
        'ready' => $ready,
        'rewards' => array_slice($rewards, 0, 8),
        'banks' => reward_bank_options(),
        'networks' => reward_network_options(),
    ];
}

// ───────────────────────────────────────────────────────────── the state ──────
if (!$isPost) {
    api_out(rewards_payload($pdo, $quizModel, $attemptModel, $userId, (int)($_GET['quiz_id'] ?? 0)));
}

$action = api_field($in, 'action');
$quizId = (int)api_field($in, 'quiz_id');

// ─────────────────────────────────────────────────── celebrate it once ────────
if ($action === 'ack') {
    if ($quizId <= 0) {
        api_err('not_found', 'That challenge is not here.', 404);
    }
    $pdo->prepare("INSERT INTO user_meta (user_id, meta_key, meta_value) VALUES (?, ?, NOW())
                   ON DUPLICATE KEY UPDATE meta_value = NOW()")
        ->execute([$userId, 'msb_win_ack_' . $quizId]);
    api_out(['ok' => true]);
}

// ─────────────────────────────────────────────────────── submit a claim ──────
if ($action === 'claim') {
    if ($quizId <= 0) {
        api_err('not_found', 'That challenge is not here.', 404);
    }
    $quiz = $quizModel->getById($quizId);
    if (!$quiz || ($quiz['mode'] ?? 'normal') !== 'challenge') {
        api_err('not_found', 'That challenge does not exist.', 404);
    }
    if (empty($quiz['event_end']) || strtotime((string)$quiz['event_end']) > time()) {
        api_err('ongoing', 'This challenge is still running. Rewards unlock once the window closes.', 409);
    }
    if (!$attemptModel->hasAttempted($quizId, $userId)) {
        api_err('not_entered', 'You did not sit this challenge, so there is no reward to claim.', 403);
    }

    $pool = (float)($quiz['prize_amount'] ?? 0);
    $prizeText = trim((string)($quiz['prize_text'] ?? ''));
    if ($pool <= 0 && $prizeText === '') {
        api_err('no_prize', 'This challenge has no reward attached to it.', 409);
    }

    $board = rewards_rank($attemptModel, $quizId, $userId, 1000);
    if ($board['rank'] < 1 || $board['rank'] > 3) {
        api_err('not_on_podium', 'Only the top three finishers can claim a reward.', 403);
    }

    $existing = rewards_claim($pdo, $quizId, $userId);
    // A claim an admin sent back is not a finished claim: the same row is
    // rewritten and goes back into the queue, so a student never has two claims
    // for one prize and the admin's history of the one row survives.
    $resubmit = $existing && (string)$existing['status'] === 'rejected';
    if ($existing && !$resubmit) {
        api_out(rewards_payload($pdo, $quizModel, $attemptModel, $userId, $quizId) + [
            'already_claimed' => true,
            'message' => 'Your claim for this challenge is already with the rewards team.',
        ]);
    }

    $type = (string)($quiz['prize_type'] ?? 'money');
    $amount = Quiz::prizeShare($pool, $board['rank']);

    $fullName = mb_substr(api_field($in, 'full_name'), 0, 255);
    $bankIn = api_field($in, 'bank_name');
    $accountNumber = preg_replace('/\D+/', '', api_field($in, 'account_number')) ?? '';
    $accountName = mb_substr(api_field($in, 'account_name'), 0, 255);
    $phone = preg_replace('/\D+/', '', api_field($in, 'phone')) ?? '';

    $error = '';
    if ($type === 'money') {
        $banks = reward_bank_options();
        // Three questions, not four: the name on the account is the student's
        // full name, and asking for it twice is how a claim ends up with two
        // spellings of one person.
        if ($accountName === '') {
            $error = 'Enter the name on your bank account.';
        } elseif (!isset($banks[$bankIn])) {
            $error = 'Choose the bank from the list.';
        } elseif (strlen($accountNumber) < 10 || strlen($accountNumber) > 20) {
            $error = 'Enter your 10-digit account number.';
        }
        if ($fullName === '') {
            $fullName = $accountName;
        }
        $bankName = $bankIn;
    } elseif (in_array($type, ['airtime', 'data'], true)) {
        $nets = reward_network_options();
        if (!isset($nets[$bankIn])) {
            $error = 'Choose your network provider.';
        } elseif (strlen($phone) < 10 || strlen($phone) > 15) {
            $error = 'Enter the phone number the ' . $type . ' should go to.';
        }
        // The web keeps Network in bank_name and the number in phone; so does this.
        $bankName = $bankIn;
        $fullName = 'N/A';
        $accountName = '';
        $accountNumber = '';
    } else {
        if ($fullName === '') {
            $error = 'Enter your full name.';
        } elseif ($phone === '' || strlen($phone) < 10) {
            $error = 'Enter a phone number so we can reach you about the prize.';
        }
        $bankName = '';
        $accountName = '';
        $accountNumber = '';
    }

    if ($error !== '') {
        api_err('invalid_details', $error);
    }

    try {
        if ($resubmit) {
            // The row an admin sent back is corrected in place and goes into the
            // queue again with a blank note, so the admin reads the new details
            // rather than the old complaint.
            $pdo->prepare('UPDATE reward_claims
                              SET rank_position = ?, amount = ?, full_name = ?, bank_name = ?,
                                  account_number = ?, account_name = ?, phone = ?,
                                  status = \'pending\', admin_notes = NULL
                            WHERE id = ?')
                ->execute([$board['rank'], $amount > 0 ? $amount : null,
                          $fullName, $bankName, $accountNumber, $accountName, $phone, (int)$existing['id']]);
        } else {
            $pdo->prepare('INSERT INTO reward_claims
                            (quiz_id, user_id, rank_position, amount, full_name, bank_name, account_number, account_name, phone)
                           VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$quizId, $userId, $board['rank'], $amount > 0 ? $amount : null,
                           $fullName, $bankName, $accountNumber, $accountName, $phone]);
        }
    } catch (Throwable $e) {
        log_error('api reward claim insert failed', ['quiz_id' => $quizId, 'user_id' => $userId, 'err' => $e->getMessage()]);
        api_err('claim_failed', 'Your claim could not be saved. Check your connection and try again.', 500);
    }

    (new Notification($pdo))->create(
        $userId,
        'reward_claim',
        $resubmit ? '🔁 Claim sent again' : '✅ Reward Claim Submitted',
        $resubmit
            ? 'Your corrected bank details for "' . $quiz['title'] . '" have been sent back to the rewards team. You will be notified when the prize is sent.'
            : 'Your reward claim details for "' . $quiz['title'] . '" have been submitted. We\'ll process it shortly and notify you once complete.',
        SITE_URL . '/claim_reward.php?quiz_id=' . $quizId
    );

    api_out(rewards_payload($pdo, $quizModel, $attemptModel, $userId, $quizId));
}

api_err('unknown_action', 'That action is not supported.', 400);
