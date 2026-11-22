<?php
/**
 * The challenge payload shared by the home feed and the quiz room.
 *
 * One mapping, one study-pack query, one live-state pass - so the card a student
 * sees on Home and the card they see under "Take a Quiz" are the same event with
 * the same numbers, and neither can drift from the website's own challenge page.
 *
 * Callers must already have loaded models/Quiz.php (the prize split lives there).
 */

/**
 * The study pack attached to each challenge, through the same quiz_materials
 * links the website's own challenge page reads. Only the gated download URLs go
 * out, never the file path: a paid document has no URL that hands it away.
 */
function api_home_challenge_materials(PDO $pdo, array $quizIds): array
{
    $quizIds = array_values(array_filter(array_map('intval', $quizIds)));
    if (!$quizIds) {
        return [];
    }
    $in = implode(',', array_fill(0, count($quizIds), '?'));
    try {
        $stmt = $pdo->prepare("SELECT qm.quiz_id, m.id, m.title, m.description, m.level, m.is_paid, m.price,
                                      m.file_size, m.mime_type, m.original_name, m.download_count, c.name AS category_name
                                 FROM quiz_materials qm
                                 JOIN materials m ON m.id = qm.material_id
                                 LEFT JOIN material_categories c ON c.id = m.category_id
                                WHERE qm.quiz_id IN ($in) AND m.status = 'approved'
                                ORDER BY m.title ASC");
        $stmt->execute($quizIds);
    } catch (Throwable $e) {
        error_log('api challenge materials: ' . $e->getMessage());
        return [];
    }

    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $price = (float)($r['price'] ?? 0);
        $bytes = (int)($r['file_size'] ?? 0);
        $name = (string)($r['original_name'] ?? '');
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        if ($ext === '' && !empty($r['mime_type'])) {
            $ext = (string)substr(strrchr((string)$r['mime_type'], '/'), 1);
        }
        $out[(int)$r['quiz_id']][] = [
            'id' => (int)$r['id'],
            'title' => (string)$r['title'],
            'description' => (string)($r['description'] ?? ''),
            'category' => $r['category_name'] ?? null,
            'level' => $r['level'] ?? null,
            'is_paid' => (int)($r['is_paid'] ?? 0) === 1,
            'price_label' => $price > 0 ? '₦' . number_format($price, 0) : 'Free',
            'format' => $ext !== '' ? strtoupper($ext) : 'FILE',
            'size_label' => $bytes > 1048576 ? round($bytes / 1048576, 1) . ' MB'
                            : ($bytes > 1024 ? max(1, (int)round($bytes / 1024)) . ' KB' : null),
            'downloads' => (int)($r['download_count'] ?? 0),
            'page_url' => api_url('material.php?id=' . (int)$r['id']),
            'view_url' => api_url('material_download.php?id=' . (int)$r['id'] . '&inline=1'),
            'download_url' => api_url('material_download.php?id=' . (int)$r['id']),
        ];
    }
    return $out;
}

/** How many questions each challenge actually holds, in one round trip. */
function api_home_challenge_questions(PDO $pdo, array $quizIds): array
{
    $quizIds = array_values(array_filter(array_map('intval', $quizIds)));
    if (!$quizIds) {
        return [];
    }
    $in = implode(',', array_fill(0, count($quizIds), '?'));
    try {
        $stmt = $pdo->prepare("SELECT quiz_id, COUNT(*) n FROM quiz_questions WHERE quiz_id IN ($in) GROUP BY quiz_id");
        $stmt->execute($quizIds);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int)$r['quiz_id']] = (int)$r['n'];
        }
        return $out;
    } catch (Throwable $e) {
        error_log('api challenge questions: ' . $e->getMessage());
        return [];
    }
}

/** The cacheable shape of one challenge row: everything except who has entered. */
function api_home_challenge_map(array $rows, array $materials, array $questions): array
{
    return array_map(function ($c) use ($materials, $questions) {
        $id = (int)$c['id'];
        $pool = isset($c['prize_amount']) ? (float)$c['prize_amount'] : 0.0;
        $seconds = isset($c['duration_seconds']) ? (int)$c['duration_seconds'] : 0;
        $type = (string)($c['prize_type'] ?? '');
        return [
            'id' => $id,
            'title' => (string)$c['title'],
            'subject' => $c['subject'] ?? null,
            'level' => $c['level'] ?? null,
            'description' => (string)($c['description'] ?? ''),
            'entry_fee' => isset($c['entry_fee']) ? (float)$c['entry_fee'] : 0.0,
            'is_paid' => (int)($c['is_paid'] ?? 0) === 1,
            'prize_amount' => $pool,
            'prize_type' => $type !== '' ? $type : null,
            'prize_text' => $c['prize_text'] ?? null,
            // The site's own 50/30/20 split, so the app cannot promise a podium
            // payout that the reward processor will not honour.
            'prize_shares' => $pool > 0 ? [
                ['rank' => 1, 'amount' => Quiz::prizeShare($pool, 1)],
                ['rank' => 2, 'amount' => Quiz::prizeShare($pool, 2)],
                ['rank' => 3, 'amount' => Quiz::prizeShare($pool, 3)],
            ] : [],
            'minutes' => $seconds > 0 ? (int)round($seconds / 60) : 0,
            'questions' => $questions[$id] ?? 0,
            'total_phases' => max(1, (int)($c['total_phases'] ?? 1)),
            'current_phase' => max(1, (int)($c['current_phase'] ?? 1)),
            'max_participants' => isset($c['max_participants']) && (int)$c['max_participants'] > 0 ? (int)$c['max_participants'] : null,
            'required_level' => (int)($c['required_level'] ?? 0),
            'event_start' => $c['event_start'] ?? null,
            'event_end' => $c['event_end'] ?? null,
            'materials' => $materials[$id] ?? [],
        ];
    }, $rows);
}

/**
 * The fields that cannot be cached: who has entered, and how long is left.
 *
 * The countdown is anchored to the server clock and shipped with a `now` stamp,
 * so a phone with the wrong date still counts down the right number of seconds.
 */
function api_home_challenge_live(PDO $pdo, int $userId, array $challenges): array
{
    if (!$challenges) {
        return [];
    }
    $ids = array_values(array_filter(array_map('intval', array_column($challenges, 'id'))));
    $entries = [];
    if ($ids) {
        // The viewer's id sits inside MAX(), so the list placeholders have to be
        // named too - PDO cannot mix named and positional parameters in one query.
        $ph = [];
        $params = [':u' => $userId];
        foreach ($ids as $i => $id) {
            $ph[] = ':q' . $i;
            $params[':q' . $i] = $id;
        }
        try {
            $stmt = $pdo->prepare('SELECT quiz_id, COUNT(*) n, MAX(user_id = :u) mine
                                     FROM challenge_participants WHERE quiz_id IN (' . implode(',', $ph) . ') GROUP BY quiz_id');
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $entries[(int)$r['quiz_id']] = ['n' => (int)$r['n'], 'mine' => (int)$r['mine'] === 1];
            }
        } catch (Throwable $e) {
            error_log('api challenge entries: ' . $e->getMessage());
        }
    }

    $now = time();

    // What this account has already done about each paid event, because a card
    // that still says "ENTRY FEE: ₦100" while an admin is checking the receipt
    // invites the student to pay twice. Three tiny aggregate queries, and only
    // when a paid event is actually on the list.
    $paidIds = array_values(array_filter(array_map(
        static fn($c) => !empty($c['is_paid']) ? (int)($c['id'] ?? 0) : 0,
        $challenges
    )));
    $entry = [];
    if ($paidIds) {
        $ph = [];
        $params = [':u' => $userId];
        foreach ($paidIds as $i => $pid) {
            $ph[] = ':p' . $i;
            $params[':p' . $i] = $pid;
        }
        $list = implode(',', $ph);

        try {
            $stmt = $pdo->prepare("SELECT quiz_id,
                                      MAX(status = 'successful') AS paid,
                                      MAX(status = 'pending') AS awaiting
                                    FROM quiz_payments WHERE user_id = :u AND quiz_id IN ($list)
                                    GROUP BY quiz_id");
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $entry[(int)$r['quiz_id']] = [
                    'paid' => (int)$r['paid'] === 1,
                    'awaiting' => (int)$r['awaiting'] === 1,
                ];
            }
        } catch (Throwable $e) {
            error_log('api challenge payment state: ' . $e->getMessage());
        }

        // A redeemed voucher is as good as money: the door is open either way.
        try {
            $stmt = $pdo->prepare("SELECT DISTINCT quiz_id FROM quiz_access_codes
                                   WHERE used_by_user_id = :u AND quiz_id IN ($list)");
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $id = (int)$r['quiz_id'];
                $entry[$id] = ($entry[$id] ?? []) + ['awaiting' => false];
                $entry[$id]['paid'] = true;
            }
        } catch (Throwable $e) {
            error_log('api challenge code state: ' . $e->getMessage());
        }
    }

    foreach ($challenges as $i => $c) {
        $id = (int)($c['id'] ?? 0);
        $start = !empty($c['event_start']) ? strtotime((string)$c['event_start']) : false;
        $end = !empty($c['event_end']) ? strtotime((string)$c['event_end']) : false;
        $upcoming = $start !== false && $start > $now;

        $challenges[$i]['state'] = $upcoming ? 'upcoming' : 'live';
        $challenges[$i]['start_ts'] = $start === false ? null : $start;
        $challenges[$i]['end_ts'] = $end === false ? null : $end;
        $challenges[$i]['target_ts'] = $upcoming ? $start : ($end === false ? null : $end);
        $challenges[$i]['seconds_left'] = $challenges[$i]['target_ts'] ? max(0, (int)$challenges[$i]['target_ts'] - $now) : 0;
        $challenges[$i]['entries'] = $entries[$id]['n'] ?? 0;
        $challenges[$i]['joined'] = $entries[$id]['mine'] ?? false;
        // 'granted' > 'awaiting' > 'none': the paid door beats the pending one,
        // because a late approval and an old receipt can both be true at once.
        $challenges[$i]['entry_state'] = !empty($entry[$id]['paid']) ? 'granted'
            : (!empty($entry[$id]['awaiting']) ? 'awaiting' : ($entries[$id]['mine'] ?? false ? 'joined' : 'none'));
    }

    // Whatever changes hands soonest leads: an arena that opens in twenty minutes
    // outranks one opening next week, and a live challenge about to close beats
    // one with a month left.
    usort($challenges, static function ($a, $b) {
        return (int)$a['seconds_left'] <=> (int)$b['seconds_left'];
    });
    return $challenges;
}