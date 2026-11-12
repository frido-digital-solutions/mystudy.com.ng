<?php
// quiz_battle_result.php - The Standalone Premium Battle Result Page
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Battle.php';
require_once __DIR__ . '/models/Gamification.php';

require_login();

$battleId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$userId = (int) $_SESSION['user_id'];

if ($battleId <= 0) {
    header("Location: profile.php");
    exit();
}

$battleModel = new Battle($pdo);
$battle = $battleModel->getBattle($battleId);

if (!$battle) {
    header("Location: profile.php");
    exit();
}

$isChallenger = ((int) $battle['challenger_id'] === $userId);
$isOpponent = ((int) $battle['opponent_id'] === $userId);

if (!$isChallenger && !$isOpponent) {
    header("Location: index.php");
    exit();
}

// Ensure at least *this* user has completed their turn
$myScore = $isChallenger ? $battle['challenger_score'] : $battle['opponent_score'];
$myTime = $isChallenger ? $battle['challenger_time_seconds'] : $battle['opponent_time_seconds'];

if ($myScore === null) {
    redirect_with_notice('profile.php', 'You have not completed this battle.', 'error');
}

$oppScore = $isChallenger ? $battle['opponent_score'] : $battle['challenger_score'];
$oppTime = $isChallenger ? $battle['opponent_time_seconds'] : $battle['challenger_time_seconds'];

// Identify Avatars securely
$myAvatar = $isChallenger ? $battle['challenger_avatar'] : $battle['opponent_avatar'];
$myName = $isChallenger ? $battle['challenger_name'] : $battle['opponent_name'];
$oppAvatar = $isChallenger ? $battle['opponent_avatar'] : $battle['challenger_avatar'];
$oppName = $isChallenger ? $battle['opponent_name'] : $battle['challenger_name'];

$myAvatar = !empty($myAvatar) ? SITE_URL . '/' . htmlspecialchars($myAvatar) : SITE_URL . '/assets/img/default-avatar.png';
$oppAvatar = !empty($oppAvatar) ? SITE_URL . '/' . htmlspecialchars($oppAvatar) : SITE_URL . '/assets/img/default-avatar.png';

$status = $battle['status'];
$isWinner = $status === 'completed' && (int) $battle['winner_id'] === $userId;
$isDraw = $status === 'completed' && empty($battle['winner_id']);
$isLoser = $status === 'completed' && !$isWinner && !$isDraw;
$xpWager = (int) $battle['xp_wager'];

// Card-specific data
$quizSubject = htmlspecialchars($battle['quiz_subject'] ?? 'General');
$quizTitle = htmlspecialchars($battle['quiz_title'] ?? 'Battle Quiz');
$matchType = $battle['match_type'] ?? 'friendly';
$completedDate = !empty($battle['completed_at']) ? date('M j, Y', strtotime($battle['completed_at'])) : date('M j, Y');

// For the downloadable card: WINNER/DEFEATED labels (always from current user's view)
$myLabel = $isWinner ? 'WINNER' : ($isDraw ? 'DRAW' : ($isLoser ? 'DEFEATED' : 'PENDING'));
$oppLabel = $isWinner ? 'DEFEATED' : ($isDraw ? 'DRAW' : ($isLoser ? 'WINNER' : 'PENDING'));

// Level & rank info for the card
$myPoints = (int)($isChallenger ? ($battle['challenger_points'] ?? 0) : ($battle['opponent_points'] ?? 0));
$oppPoints = (int)($isChallenger ? ($battle['opponent_points'] ?? 0) : ($battle['challenger_points'] ?? 0));
$myLevel = Gamification::getLevel($myPoints);
$oppLevel = Gamification::getLevel($oppPoints);

$myUserType = $isChallenger ? ($battle['challenger_user_type'] ?? '') : ($battle['opponent_user_type'] ?? '');
$oppUserType = $isChallenger ? ($battle['opponent_user_type'] ?? '') : ($battle['challenger_user_type'] ?? '');

$myRankName = ($myUserType === 'university')
    ? (Gamification::UNI_LEVEL_NAMES[$myLevel] ?? "Level $myLevel")
    : (Gamification::SEC_LEVEL_NAMES[$myLevel] ?? Gamification::UNI_LEVEL_NAMES[$myLevel] ?? "Level $myLevel");
$oppRankName = ($oppUserType === 'university')
    ? (Gamification::UNI_LEVEL_NAMES[$oppLevel] ?? "Level $oppLevel")
    : (Gamification::SEC_LEVEL_NAMES[$oppLevel] ?? Gamification::UNI_LEVEL_NAMES[$oppLevel] ?? "Level $oppLevel");

// ELO ratings
$myElo = (int)($isChallenger ? ($battle['challenger_rating'] ?? 1000) : ($battle['opponent_rating'] ?? 1000));
$oppElo = (int)($isChallenger ? ($battle['opponent_rating'] ?? 1000) : ($battle['challenger_rating'] ?? 1000));

// ELO change display (approximate K=32)
$eloGain = 25;
$eloLoss = 20;

$page_title = "Battle Results";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title; ?> - MyStudy Arena</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700;800;900&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --victory-bg: radial-gradient(circle at 50% 0%, #064e3b 0%, #020617 80%);
            --defeat-bg: radial-gradient(circle at 50% 0%, #4c0519 0%, #020617 80%);
            --draw-bg: radial-gradient(circle at 50% 0%, #1e1b4b 0%, #020617 80%);
            --pending-bg: radial-gradient(circle at 50% 0%, #0f172a 0%, #020617 80%);
        }

        body {
            <?php
            if ($status !== 'completed') { echo 'background: var(--pending-bg);'; }
            elseif ($isWinner) { echo 'background: var(--victory-bg);'; }
            elseif ($isLoser) { echo 'background: var(--defeat-bg);'; }
            else { echo 'background: var(--draw-bg);'; }
            ?>
            color: #f8fafc;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        h1,h2,h3,h4,h5,h6,.outfit-font { font-family: 'Outfit', sans-serif; }

        .result-card {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 32px;
            padding: 3rem;
            max-width: 600px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }

        .result-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 6px;
            <?php
            if ($status !== 'completed') { echo 'background: linear-gradient(90deg, #3b82f6, #6366f1);'; }
            elseif ($isWinner) { echo 'background: linear-gradient(90deg, #10b981, #059669);'; }
            elseif ($isLoser) { echo 'background: linear-gradient(90deg, #f43f5e, #be123c);'; }
            else { echo 'background: linear-gradient(90deg, #f59e0b, #ea580c);'; }
            ?>
        }

        .headline-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
            display: inline-block;
            filter: drop-shadow(0 0 15px rgba(255, 255, 255, 0.2));
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .result-title { font-size: 3rem; font-weight: 900; margin-bottom: 0.5rem; letter-spacing: -1px; text-transform: uppercase; }
        .result-subtitle { font-size: 1.1rem; color: #94a3b8; margin-bottom: 2.5rem; font-weight: 500; }

        .score-board {
            display: flex; align-items: center; justify-content: center; gap: 2rem;
            margin-bottom: 3rem; background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 20px; padding: 2rem;
        }

        .player-col { display: flex; flex-direction: column; align-items: center; flex: 1; }

        .avatar {
            width: 80px; height: 80px; border-radius: 50%; object-fit: cover;
            border: 4px solid #334155; margin-bottom: 1rem; box-shadow: 0 0 20px rgba(0,0,0,0.5);
        }
        .player-col.me .avatar { border-color: #3b82f6; }
        .player-col.opponent .avatar { border-color: #ef4444; }

        .score-value { font-size: 3.5rem; font-weight: 900; font-family: 'Outfit', sans-serif; line-height: 1; margin-bottom: 0.25rem; }
        .player-col.me .score-value { color: #f8fafc; }
        .player-col.opponent .score-value { color: #cbd5e1; opacity: <?php echo $status !== 'completed' ? '0.3' : '1'; ?>; }

        .time-value { font-size: 0.9rem; color: #94a3b8; font-weight: 600; background: rgba(255,255,255,0.1); padding: 0.25rem 0.75rem; border-radius: 50px; }
        .vs-divider { font-size: 1.5rem; font-weight: 900; color: #64748b; font-family: 'Outfit', sans-serif; font-style: italic; }

        .rewards-panel {
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.1), rgba(245, 158, 11, 0.05));
            border: 1px solid rgba(251, 191, 36, 0.2); border-radius: 16px;
            padding: 1.5rem; margin-bottom: 2rem; display: flex; justify-content: center; gap: 3rem;
        }
        .reward-item { display: flex; flex-direction: column; align-items: center; }
        .reward-label { font-size: 0.8rem; font-weight: 700; color: #fbbf24; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.5rem; }
        .reward-value { font-size: 1.5rem; font-weight: 800; color: #f8fafc; display: flex; align-items: center; gap: 0.5rem; }
        .reward-value i.fa-arrow-up { color: #10b981; font-size: 1rem; }
        .reward-value i.fa-arrow-down { color: #ef4444; font-size: 1rem; }

        .btn-action {
            display: inline-block; background: #f8fafc; color: #0f172a; font-family: 'Outfit', sans-serif;
            font-weight: 800; font-size: 1.1rem; padding: 1rem 3rem; border-radius: 50px;
            text-decoration: none; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(255,255,255,0.1);
        }
        .btn-action:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(255,255,255,0.2); color: #0f172a; }

        @media (max-width: 576px) {
            .result-card { padding: 2rem 1.5rem; border-radius: 24px; }
            .result-title { font-size: 2.25rem; }
            .score-board { gap: 1rem; padding: 1.5rem 1rem; }
            .avatar { width: 60px; height: 60px; }
            .score-value { font-size: 2.5rem; }
            .rewards-panel { gap: 1.5rem; padding: 1rem; }
            .btn-action { padding: 0.8rem 2rem; font-size: 1rem; width: 100%; }
        }

        /* ========================================= */
        /* CARD PREVIEW & DOWNLOAD SECTION           */
        /* ========================================= */
        .card-preview-section { margin-top: 2rem; }
        .card-preview-container {
            background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px; padding: 12px; margin-bottom: 1rem;
        }
        .card-preview-container img { width: 100%; border-radius: 8px; display: block; }
        .card-preview-placeholder {
            width: 100%; aspect-ratio: 1; border-radius: 8px; background: rgba(255,255,255,0.05);
            display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 0.75rem; color: #64748b;
        }
        .card-preview-placeholder i { font-size: 2rem; }
        .card-preview-placeholder span { font-size: 0.85rem; font-weight: 600; }

        .btn-download-card {
            display: inline-block; background: linear-gradient(135deg, #10b981, #059669); color: #fff;
            font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 0.95rem;
            padding: 0.75rem 2rem; border-radius: 50px; text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: none; cursor: pointer;
        }
        .btn-download-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4); color: #fff; }
        .btn-download-card.downloading { pointer-events: none; opacity: 0.6; }

        .btn-generate-preview {
            display: inline-block; background: transparent; color: #94a3b8;
            font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 0.95rem;
            padding: 0.75rem 2rem; border-radius: 50px; text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid rgba(255,255,255,0.15); cursor: pointer; margin-top: 0.75rem;
        }
        .btn-generate-preview:hover { background: rgba(255,255,255,0.1); color: #f8fafc; border-color: rgba(255,255,255,0.3); transform: translateY(-2px); }
        .btn-generate-preview.generating { pointer-events: none; opacity: 0.6; }

        /* Hidden card — rendered offscreen for html2canvas capture */
        .battle-card-wrapper { position: absolute; left: -9999px; top: -9999px; }

        /* ========================================= */
        /* SQUARE DOWNLOADABLE BATTLE RESULT CARD    */
        /* Matches reference design exactly          */
        /* ========================================= */
        .battle-card {
            width: 1080px; height: 1080px;
            background: #0d121c; /* Very deep navy/black from the image */
            font-family: 'Outfit', 'Inter', sans-serif;
            color: #f8fafc; position: relative; overflow: hidden;
            display: flex; flex-direction: column;
        }

        /* Top left gold accent */
        .bc-gold-accent {
            position: absolute; top: 0; left: 0;
            width: 700px; height: 700px;
            background: linear-gradient(135deg, #facc15 0%, #a16207 20%, #eab308 40%, transparent 42%);
            z-index: 1; pointer-events: none;
            clip-path: polygon(0 0, 100% 0, 0 100%);
            box-shadow: inset -10px -10px 30px rgba(0,0,0,0.5); /* Inner shadow for 3D */
        }

        /* Circuit board background pattern */
        .bc-circuit { position: absolute; top: 0; left: 0; right: 0; bottom: 0; pointer-events: none; z-index: 0; opacity: 0.6; }

        /* Glow orbs - matching the large teal/green radial gradients in corner/bottom */
        .bc-glow { position: absolute; border-radius: 50%; pointer-events: none; filter: blur(120px); }
        .bc-glow.g1 { width: 500px; height: 500px; top: -150px; left: -150px; background: rgba(16,185,129,0.15); } 
        .bc-glow.g2 { width: 500px; height: 500px; top: -50px; right: -150px; background: rgba(56,189,248,0.1); }  
        .bc-glow.g3 { width: 800px; height: 350px; bottom: -100px; left: 50%; transform: translateX(-50%); background: rgba(16,185,129,0.15); }

        /* HEADER: site name + BATTLE RESULT */
        .bc-header { text-align: center; padding: 40px 48px 20px; position: relative; z-index: 10; flex-shrink: 0; }
        .bc-sitename { font-size: 24px; font-weight: 800; color: #f1f5f9; text-transform: uppercase; letter-spacing: 5px; margin-bottom: 5px; text-shadow: 0 2px 10px rgba(0,0,0,0.5); }
        .bc-title { display: flex; align-items: center; justify-content: center; gap: 15px; }
        .bc-title .t-battle { color: #fbbf24; font-size: 72px; font-weight: 900; text-transform: uppercase; line-height: 1; letter-spacing: 2px; text-shadow: 0 4px 20px rgba(251,191,36,0.4), 0 2px 0 #a16207; }
        .bc-title .t-result { color: #f8fafc; font-size: 72px; font-weight: 900; text-transform: uppercase; line-height: 1; letter-spacing: 2px; text-shadow: 0 4px 20px rgba(255,255,255,0.3), 0 2px 0 #94a3b8; }

        /* MAIN ROW: Left Panel | Center | Right Panel */
        .bc-main-row { display: flex; align-items: flex-start; justify-content: space-between; padding: 120px 50px 0; position: relative; z-index: 2; flex: 1; gap: 20px;}

        /* Player Panel (The outer box holding stats) */
        .bc-player-panel { 
            width: 320px; 
            background: linear-gradient(180deg, rgba(30,41,59,0.95) 0%, rgba(15,23,42,0.95) 100%); 
            border-radius: 20px; 
            position: relative; 
            padding: 95px 25px 25px 25px; 
            display: flex; flex-direction: column; align-items: center;
        }
        .bc-player-panel.win { border: 2px solid rgba(16,185,129,0.6); box-shadow: inset 0 0 50px rgba(16,185,129,0.15), 0 20px 40px rgba(0,0,0,0.6); }
        .bc-player-panel.lose { border: 2px solid rgba(225,29,72,0.6); box-shadow: inset 0 0 50px rgba(225,29,72,0.15), 0 20px 40px rgba(0,0,0,0.6); }
        .bc-player-panel.draw { border: 2px solid rgba(245,158,11,0.6); box-shadow: inset 0 0 50px rgba(245,158,11,0.15), 0 20px 40px rgba(0,0,0,0.6); }

        /* Inside Panel: Avatar container jutting out top */
        .bc-avatar-wrapper { position: absolute; top: -72px; left: 50%; transform: translateX(-50%); display: flex; flex-direction: column; align-items: center; }
        
        .bc-aring { width: 144px; height: 144px; border-radius: 50%; padding: 6px; margin-bottom: -18px; position: relative; z-index: 2; box-shadow: 0 10px 20px rgba(0,0,0,0.7); }
        .bc-aring.win { background: linear-gradient(135deg, #fef08a 0%, #ca8a04 100%); }
        .bc-aring.lose { background: linear-gradient(135deg, #cbd5e1 0%, #475569 100%); }
        .bc-aring.draw { background: linear-gradient(135deg, #fcd34d 0%, #b45309 100%); }
        
        .bc-aimg { width: 132px; height: 132px; border-radius: 50%; object-fit: cover; display: block; border: 5px solid #0f172a; }

        /* Inside Panel: Bagde over avatar */
        .bc-badge { position: relative; z-index: 3; font-size: 16px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; padding: 6px 26px; border-radius: 50px; box-shadow: 0 10px 20px rgba(0,0,0,0.5); }
        .bc-badge.bwin { background: linear-gradient(to bottom, #fde047, #eab308); color: #111827; }
        .bc-badge.blose { background: linear-gradient(to bottom, #be123c, #9f1239); color: #fecdd3; border: 1px solid rgba(255,255,255,0.2); }
        .bc-badge.bdraw { background: linear-gradient(to bottom, #f59e0b, #d97706); color: #fef3c7; border: 1px solid rgba(255,255,255,0.2); }

        /* Inside Panel: Name, Level & Divider */
        .bc-pname { font-size: 24px; font-weight: 900; color: #f8fafc; text-align: center; margin-top: 15px; width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-shadow: 0 2px 4px rgba(0,0,0,0.5); }
        .bc-plevel { font-size: 14px; font-weight: 600; color: #5eead4; margin-top: 4px; text-align: center; margin-bottom: 25px; }
        .bc-player-panel.lose .bc-plevel { color: #cbd5e1; } /* Grey for loser */
        .bc-divider { width: 100%; height: 1px; background: rgba(255,255,255,0.1); border: none; margin: 0 0 20px 0; }
        .bc-player-panel.win .bc-divider { background: rgba(16,185,129,0.3); }

        /* Inside Panel: Stats Block */
        .bc-stats-block { width: 100%; background: transparent; padding: 0 5px; }
        .bc-sline { display: flex; align-items: center; font-size: 15px; font-weight: 600; color: #cbd5e1; margin-bottom: 12px; }
        .bc-sline i { width: 22px; text-align: center; margin-right: 10px; font-size: 16px; }
        .bc-sline i.icon-green { color: #10b981; }
        .bc-sline i.icon-gold { color: #fbbf24; }
        .bc-sline i.icon-red { color: #ef4444; }
        .bc-sline .sv { 
            font-weight: 900; color: #f8fafc; margin-left: auto; font-size: 16px;
            width: 34px; height: 34px; border-radius: 50%;
            background: linear-gradient(135deg, rgba(16,185,129,0.3) 0%, rgba(16,185,129,0.1) 100%);
            border: 2px solid rgba(16,185,129,0.8);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 10px rgba(16,185,129,0.2), inset 0 0 8px rgba(16,185,129,0.3);
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
        }
        
        /* Specific elements inside stats block based on image */
        .bc-sline.subtitle { margin-top: -8px; color: #94a3b8; font-size: 13px; margin-left: 32px; font-style: italic;}
        .bc-sline.ranked-row { align-items: flex-end; justify-content: flex-end; width: 100%; }
        .bc-sline.ranked-row .sg { color: #f8fafc; font-weight: 900; font-size: 15px; letter-spacing: 0.5px;} /* White text for RANKED | */
        .bc-sline.ranked-row .sg span.val-up { color: #fbbf24; } /* Gold for +XP */
        .bc-sline.ranked-row .sg span.val-dn { color: #ef4444; } /* Red for -XP */
        
        /* The XP Pill */
        .bc-xp-pill { display: inline-flex; align-items: center; background: rgba(16,185,129,0.15); border: 1px solid #10b981; color: #10b981; padding: 6px 14px; border-radius: 50px; font-size: 14px; font-weight: 900; margin-left: 0;}
        .bc-xp-pill.lose-pill { background: rgba(225,29,72,0.15); border: 1px solid #e11d48; color: #fda4af; }
        .bc-xp-miss { display: inline-block; font-size: 12px; color: #64748b; font-weight: 600; margin-left: 0; }

        /* ELO Rating specific block */
        .bc-elo-val-block { display: flex; flex-direction: column; align-items: flex-end; width: 100%; margin-top: -20px; }
        .bc-elo-val-block .elo-val { font-size: 24px; font-weight: 900; color: #f8fafc; }
        .bc-elo-val-block .glob-text { font-size: 12px; color: #64748b; font-weight: 600; }

        /* CENTER COLUMN */
        .bc-center { display: flex; flex-direction: column; align-items: center; width: 250px; position: relative; margin-top: -80px; z-index: 5;}
        
        .bc-mbadge { 
            border: 2px solid rgba(16,185,129,0.8); background: rgba(15,23,42,0.9); padding: 10px 20px; 
            border-radius: 12px; text-align: center; margin-bottom: 25px; width: 100%;
            box-shadow: 0 0 25px rgba(16,185,129,0.2) inset, 0 10px 20px rgba(0,0,0,0.5);
            position: relative; z-index: 6;
        }
        .bc-mtype { font-size: 20px; font-weight: 900; color: #fbbf24; line-height: 1.1; text-shadow: 0 2px 4px rgba(0,0,0,0.8);}

        .bc-vs-badge { 
            color: #f8fafc; font-size: 75px; font-weight: 900; font-style: italic; 
            line-height: 1; border-radius: 6px; 
            margin-bottom: 20px; position: relative; z-index: 5;
            text-shadow: 0 0 10px rgba(16,185,129,0.8), 3px 3px 0 #10b981, -3px -3px 0 #10b981, 3px -3px 0 #10b981, -3px 3px 0 #10b981, 0 10px 20px rgba(0,0,0,0.8);
        }
        .bc-vs-badge::before {
            content: ''; position: absolute; top: 48%; left: 50%; width: 65px; height: 65px;
            background: #0f172a; border: 4px solid #10b981; transform: translate(-50%, -50%) rotate(45deg);
            z-index: -1; box-shadow: inset 0 0 15px rgba(16,185,129,0.3), 0 5px 15px rgba(0,0,0,0.5);
        }

        .bc-mbadge { 
            border: 2px solid rgba(16,185,129,0.8); background: rgba(15,23,42,0.9); padding: 14px 20px; 
            border-radius: 12px; text-align: center; margin-bottom: 50px; width: 100%;
            box-shadow: 0 0 25px rgba(16,185,129,0.2) inset, 0 10px 20px rgba(0,0,0,0.5);
            position: relative; z-index: 6;
        }
        .bc-mtype { font-size: 24px; font-weight: 900; color: #fbbf24; line-height: 1.1; text-shadow: 0 2px 4px rgba(0,0,0,0.8);}

        .bc-center-subject { text-align: center; width: 100%; margin-bottom: 25px; }
        .bc-sublab { font-size: 15px; font-weight: 800; color: #fbbf24; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 5px; text-shadow: 0 2px 4px rgba(0,0,0,0.5);}
        .bc-subname { font-size: 26px; font-weight: 900; color: #f8fafc; text-transform: uppercase; line-height: 1.2; word-wrap: break-word; text-shadow: 0 4px 10px rgba(0,0,0,0.8); }

        .bc-center-scorebox { display: flex; flex-direction: column; align-items: center; width: 100%; }
        .bc-scolabels { display: flex; justify-content: space-between; width: 180px; font-size: 13px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 2px; margin-bottom: -10px; z-index:2;}
        .bc-scovalues { display: flex; justify-content: center; align-items: center; gap: 8px; font-size: 90px; font-weight: 900; line-height: 1; text-shadow: 0 10px 20px rgba(0,0,0,0.5);}
        .bc-scovalues .s-win { color: #fbbf24; } 
        .bc-scovalues .s-lose { color: #f8fafc; }
        .bc-scovalues .s-colon { color: #cbd5e1; font-size: 72px; padding-bottom: 10px;}

        /* FOOTER */
        .bc-footer { display: flex; align-items: center; justify-content: center; padding: 0 48px 45px; position: relative; z-index: 2; flex-shrink: 0; }
        
        /* New Verified Stamp styling */
        .bc-stamp-new {
            display: flex; align-items: center; gap: 25px; padding: 15px 40px;
            background: rgba(15,23,42,0.8); border: 1px solid rgba(16,185,129,0.3);
            border-radius: 100px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); backdrop-filter: blur(8px);
        }
        .bc-stamp-icon {
            width: 55px; height: 55px; border-radius: 50%;
            border: 2px dashed #10b981;
            display: flex; align-items: center; justify-content: center;
            background: rgba(16,185,129,0.08); position: relative;
        }
        .bc-stamp-inner-circle {
            width: 30px; height: 30px; border-radius: 50%;
            background: #10b981; color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; box-shadow: 0 0 15px rgba(16,185,129,0.5);
        }
        .bc-stamp-content {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .bc-stamp-verified {
            font-family: 'Brush Script MT', 'Great Vibes', cursive, sans-serif;
            color: #10b981; font-size: 34px; line-height: 1;
            margin-bottom: 5px; transform: rotate(-3deg);
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
        }
        .bc-stamp-divider {
            width: 140%; height: 1px; background: rgba(100,116,139,0.6); margin-bottom: 8px;
        }
        .bc-stamp-title {
            font-size: 15px; font-weight: 800; color: #cbd5e1;
            text-transform: uppercase; letter-spacing: 3px;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>

<body>

    <div class="result-card">

        <?php if ($status !== 'completed'): ?>
            <div class="headline-icon text-primary"><i class="fas fa-hourglass-half"></i></div>
            <h1 class="result-title text-primary">Pending</h1>
            <p class="result-subtitle">You have completed your turn. Waiting for <?php echo htmlspecialchars($oppName); ?> to strike!</p>
        <?php elseif ($isWinner): ?>
            <div class="headline-icon text-success"><i class="fas fa-crown"></i></div>
            <h1 class="result-title text-success">Victory</h1>
            <p class="result-subtitle">Exceptional performance. You dominated the arena!</p>
        <?php elseif ($isDraw): ?>
            <div class="headline-icon" style="color: #f59e0b;"><i class="fas fa-handshake"></i></div>
            <h1 class="result-title" style="color: #f59e0b;">A Draw</h1>
            <p class="result-subtitle">A perfectly matched battle. Well fought on both sides.</p>
        <?php else: ?>
            <div class="headline-icon text-danger"><i class="fas fa-skull-crossbones"></i></div>
            <h1 class="result-title text-danger">Defeat</h1>
            <p class="result-subtitle">You fought bravely, but your opponent was too strong this time.</p>
        <?php endif; ?>

        <div class="score-board">
            <div class="player-col me">
                <img src="<?php echo $myAvatar; ?>" class="avatar" alt="You">
                <div class="fw-bold mb-2">You</div>
                <div class="score-value"><?php echo (int) $myScore; ?></div>
                <div class="time-value"><i class="fas fa-stopwatch me-1"></i><?php echo gmdate("i:s", (int) $myTime); ?></div>
            </div>
            <div class="vs-divider">VS</div>
            <div class="player-col opponent">
                <img src="<?php echo $oppAvatar; ?>" class="avatar" alt="Opponent">
                <div class="fw-bold mb-2"><?php echo htmlspecialchars($oppName); ?></div>
                <div class="score-value"><?php echo $oppScore !== null ? (int) $oppScore : '?'; ?></div>
                <div class="time-value"><i class="fas fa-stopwatch me-1"></i><?php echo $oppTime !== null ? gmdate("i:s", (int) $oppTime) : '--:--'; ?></div>
            </div>
        </div>

        <?php if ($status === 'completed' && ($battle['match_type'] === 'ranked' || $xpWager > 0)): ?>
            <div class="rewards-panel">
                <?php if ($battle['match_type'] === 'ranked'): ?>
                    <div class="reward-item">
                        <div class="reward-label">ELO Rating</div>
                        <div class="reward-value">
                            <?php if ($isWinner): ?><i class="fas fa-arrow-up"></i> +30
                            <?php elseif ($isLoser): ?><i class="fas fa-arrow-down"></i> -20
                            <?php else: ?>+0<?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($xpWager > 0): ?>
                    <div class="reward-item">
                        <div class="reward-label">XP Pot</div>
                        <div class="reward-value">
                            <?php if ($isWinner): ?><i class="fas fa-arrow-up text-warning"></i> +<?php echo $xpWager; ?>
                            <?php elseif ($isLoser): ?><i class="fas fa-arrow-down text-danger"></i> -<?php echo $xpWager; ?>
                            <?php else: ?>Returned<?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($status === 'completed' && $battle['match_type'] === 'friendly'): ?>
            <div class="rewards-panel" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); border-radius: 12px; padding: 1rem;">
                <div class="small w-100 text-center" style="color: #cbd5e1;">
                    <?php if ($xpWager > 0): ?>
                        <i class="fas fa-info-circle me-1"></i> Friendly match. No ELO Rank changes occurred.
                    <?php else: ?>
                        <i class="fas fa-info-circle me-1"></i> Friendly match. No ELO Rank or XP changes occurred.
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div>
            <a href="<?php echo SITE_URL; ?>/battle_arena.php" class="btn-action">Return to Arena</a>
            <?php if ($status === 'completed'): ?>

            <!-- Card Preview & Download Section -->
            <div class="card-preview-section">
                <div class="card-preview-container" id="cardPreviewContainer">
                    <div class="card-preview-placeholder" id="cardPreviewPlaceholder">
                        <i class="fas fa-image"></i>
                        <span>Click below to generate your Battle Result Card</span>
                    </div>
                    <img id="cardPreviewImg" style="display:none;" alt="Battle Result Card Preview">
                </div>
                <div id="previewActions">
                    <button class="btn-generate-preview" id="btnGeneratePreview" onclick="generatePreview()">
                        <i class="fas fa-wand-magic-sparkles me-2"></i>Generate Card Preview
                    </button>
                </div>
                <div id="downloadActions" style="display:none;">
                    <button class="btn-download-card" id="btnDownloadCard" onclick="downloadCard()">
                        <i class="fas fa-download me-2"></i>Download Result Card
                    </button>
                </div>
            </div>

            <?php endif; ?>
        </div>

    </div>

    <?php if ($status === 'completed'): ?>
    <!-- ================================================ -->
    <!-- HIDDEN SQUARE BATTLE CARD (1080x1080)            -->
    <!-- Matches reference design: dark bg, circuit lines -->
    <!-- golden avatar rings, stat panels, score center   -->
    <!-- ================================================ -->
    <div class="battle-card-wrapper" id="battleCardWrapper">
        <div class="battle-card" id="battleCard">
            <!-- Circuit board SVG pattern -->
            <div class="bc-circuit">
                <svg viewBox="0 0 1080 1080" xmlns="http://www.w3.org/2000/svg">
                    <line x1="0" y1="180" x2="200" y2="180" stroke="rgba(16,185,129,0.12)" stroke-width="1.5"/>
                    <line x1="200" y1="180" x2="220" y2="200" stroke="rgba(16,185,129,0.12)" stroke-width="1.5"/>
                    <line x1="220" y1="200" x2="400" y2="200" stroke="rgba(16,185,129,0.08)" stroke-width="1"/>
                    <circle cx="400" cy="200" r="3" fill="rgba(16,185,129,0.2)"/>
                    <line x1="880" y1="150" x2="1080" y2="150" stroke="rgba(16,185,129,0.1)" stroke-width="1.5"/>
                    <line x1="860" y1="150" x2="880" y2="170" stroke="rgba(16,185,129,0.1)" stroke-width="1.5"/>
                    <line x1="700" y1="170" x2="860" y2="170" stroke="rgba(16,185,129,0.06)" stroke-width="1"/>
                    <circle cx="700" cy="170" r="3" fill="rgba(16,185,129,0.15)"/>
                    <line x1="0" y1="900" x2="150" y2="900" stroke="rgba(16,185,129,0.08)" stroke-width="1"/>
                    <line x1="150" y1="900" x2="170" y2="880" stroke="rgba(16,185,129,0.1)" stroke-width="1.5"/>
                    <line x1="170" y1="880" x2="350" y2="880" stroke="rgba(16,185,129,0.06)" stroke-width="1"/>
                    <circle cx="350" cy="880" r="3" fill="rgba(16,185,129,0.15)"/>
                    <line x1="780" y1="920" x2="1080" y2="920" stroke="rgba(16,185,129,0.1)" stroke-width="1.5"/>
                    <line x1="760" y1="940" x2="780" y2="920" stroke="rgba(16,185,129,0.1)" stroke-width="1.5"/>
                    <line x1="600" y1="940" x2="760" y2="940" stroke="rgba(16,185,129,0.06)" stroke-width="1"/>
                    <circle cx="600" cy="940" r="3" fill="rgba(16,185,129,0.15)"/>
                    <line x1="100" y1="0" x2="100" y2="120" stroke="rgba(16,185,129,0.08)" stroke-width="1"/>
                    <line x1="100" y1="120" x2="120" y2="140" stroke="rgba(16,185,129,0.1)" stroke-width="1.5"/>
                    <circle cx="120" cy="140" r="4" fill="rgba(16,185,129,0.2)"/>
                    <line x1="980" y1="0" x2="980" y2="100" stroke="rgba(16,185,129,0.06)" stroke-width="1"/>
                    <line x1="980" y1="100" x2="960" y2="120" stroke="rgba(16,185,129,0.08)" stroke-width="1.5"/>
                    <circle cx="960" cy="120" r="3" fill="rgba(16,185,129,0.15)"/>
                    <line x1="50" y1="960" x2="50" y2="1080" stroke="rgba(16,185,129,0.06)" stroke-width="1"/>
                    <line x1="1030" y1="980" x2="1030" y2="1080" stroke="rgba(16,185,129,0.08)" stroke-width="1"/>
                    <circle cx="40" cy="40" r="2" fill="rgba(16,185,129,0.15)"/>
                    <circle cx="1040" cy="40" r="2" fill="rgba(16,185,129,0.15)"/>
                    <circle cx="40" cy="1040" r="2" fill="rgba(16,185,129,0.1)"/>
                    <circle cx="1040" cy="1040" r="2" fill="rgba(16,185,129,0.15)"/>
                    <polygon points="540,260 548,268 540,276 532,268" fill="rgba(16,185,129,0.15)"/>
                </svg>
            </div>
            <div class="bc-glow g1"></div>
            <div class="bc-glow g2"></div>
            <div class="bc-glow g3"></div>

            <div class="bc-gold-accent"></div>

            <!-- Header: MYSTUDY BASE / BATTLE RESULT -->
            <div class="bc-header">
                <div class="bc-sitename"><?php echo strtoupper(SITE_NAME); ?></div>
                <div class="bc-title"><span class="t-battle">BATTLE</span><span class="t-result">RESULT</span></div>
            </div>

            <!-- MAIN ROW: Left Panel | Center | Right Panel -->
            <div class="bc-main-row">
                
                <!-- Left Player Panel -->
                <div class="bc-player-panel <?php echo $myLabel === 'WINNER' ? 'win' : ($myLabel === 'DEFEATED' ? 'lose' : 'draw'); ?>">
                    <div class="bc-avatar-wrapper">
                        <div class="bc-aring <?php echo $myLabel === 'WINNER' ? 'win' : ($myLabel === 'DEFEATED' ? 'lose' : 'draw'); ?>">
                            <img src="<?php echo $myAvatar; ?>" class="bc-aimg" crossorigin="anonymous">
                        </div>
                        <div class="bc-badge <?php echo $myLabel === 'WINNER' ? 'bwin' : ($myLabel === 'DEFEATED' ? 'blose' : 'bdraw'); ?>"><?php echo $myLabel; ?></div>
                    </div>
                    
                    <div class="bc-pname"><?php echo htmlspecialchars($myName); ?></div>
                    <div class="bc-plevel">Lvl <?php echo $myLevel; ?>: <?php echo htmlspecialchars($myRankName); ?></div>
                    <hr class="bc-divider">
                    
                    <div class="bc-stats-block">
                        <div class="bc-sline"><i class="fas fa-clipboard-check icon-green"></i><span class="sl">Quiz Score:</span><span class="sv"><?php echo (int)$myScore; ?></span></div>
                        
                        <?php if ($matchType === 'ranked'): ?>
                        <div class="bc-sline subtitle">(For Ranked Match)</div>
                        <div class="bc-sline ranked-row"><i class="fas fa-<?php echo $isWinner ? 'angles-up icon-gold' : 'angles-down icon-red'; ?>"></i><span class="sg">RANKED | <span class="<?php echo $isWinner ? 'val-up' : 'val-dn'; ?>"><?php echo $isWinner ? "+{$eloGain} ELO" : ($isLoser ? "-{$eloLoss} ELO" : "+0 ELO"); ?></span></span></div>
                        <?php else: ?>
                        <div style="height: 35px;"></div>
                        <?php endif; ?>

                        <div style="height:10px;"></div>

                        <?php if ($xpWager > 0): ?>
                            <?php if ($isWinner || $isDraw): ?>
                            <div class="bc-xp-pill"><i class="fas fa-coins" style="margin-right: 5px;"></i> +<?php echo $xpWager; ?> XP STAKE</div>
                            <?php else: ?>
                            <div class="bc-xp-pill lose-pill"><i class="fas fa-coins" style="margin-right: 5px;"></i> -<?php echo $xpWager; ?> XP STAKE</div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="bc-xp-miss" style="opacity: 0;">Spacer</div>
                        <?php endif; ?>

                        <div style="height:25px;"></div>

                        <div class="bc-sline"><i class="fas fa-trophy icon-gold" style="font-size:20px;"></i><span class="sl" style="margin-top:10px;">ELO Rating:</span></div>
                        <div class="bc-elo-val-block">
                            <div class="elo-val"><?php echo $myElo; ?> ELO</div>
                            <div class="glob-text">(Global Ranking)</div>
                        </div>
                    </div>
                </div>

                <!-- Center Column -->
                <div class="bc-center">
                    <div class="bc-mbadge">
                        <span class="bc-mtype"><?php echo $matchType === 'ranked' ? 'RANKED MATCH' : 'FRIENDLY MATCH'; ?></span>
                    </div>

                    <div class="bc-vs-badge">VS</div>

                    <div class="bc-center-subject">
                        <div class="bc-sublab">SUBJECT:</div>
                        <div class="bc-subname"><?php echo $quizSubject; ?></div>
                    </div>

                    <div class="bc-center-scorebox">
                        <div class="bc-scolabels">
                            <span style="color: <?php echo $myScore >= $oppScore ? '#fbbf24' : '#94a3b8'; ?>">SCORE</span>
                            <span style="color: <?php echo $oppScore >= $myScore ? '#fbbf24' : '#94a3b8'; ?>">SCORE</span>
                        </div>
                        <div class="bc-scovalues">
                            <span class="s-win" style="color: <?php echo $myScore >= $oppScore ? '#fbbf24' : '#f8fafc'; ?>"><?php echo (int)$myScore; ?></span>
                            <span class="s-colon">:</span>
                            <span class="s-lose" style="color: <?php echo $oppScore >= $myScore ? '#fbbf24' : '#f8fafc'; ?>"><?php echo $oppScore !== null ? (int)$oppScore : '?'; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Right Player Panel -->
                <div class="bc-player-panel <?php echo $oppLabel === 'WINNER' ? 'win' : ($oppLabel === 'DEFEATED' ? 'lose' : 'draw'); ?>">
                    <div class="bc-avatar-wrapper">
                        <div class="bc-aring <?php echo $oppLabel === 'WINNER' ? 'win' : ($oppLabel === 'DEFEATED' ? 'lose' : 'draw'); ?>">
                            <img src="<?php echo $oppAvatar; ?>" class="bc-aimg" crossorigin="anonymous">
                        </div>
                        <div class="bc-badge <?php echo $oppLabel === 'WINNER' ? 'bwin' : ($oppLabel === 'DEFEATED' ? 'blose' : 'bdraw'); ?>"><?php echo $oppLabel; ?></div>
                    </div>
                    
                    <div class="bc-pname"><?php echo htmlspecialchars($oppName); ?></div>
                    <div class="bc-plevel">Lvl <?php echo $oppLevel; ?>: <?php echo htmlspecialchars($oppRankName); ?></div>
                    <hr class="bc-divider">
                    
                    <div class="bc-stats-block">
                        <div class="bc-sline"><i class="fas fa-clipboard-check icon-green"></i><span class="sl">Quiz Score:</span><span class="sv"><?php echo $oppScore !== null ? (int)$oppScore : '?'; ?></span></div>
                        
                        <?php if ($matchType === 'ranked'): ?>
                        <div class="bc-sline subtitle">(For Ranked Match)</div>
                        <div class="bc-sline ranked-row"><i class="fas fa-<?php echo $isLoser ? 'angles-up icon-gold' : 'angles-down icon-red'; ?>"></i><span class="sg">RANKED | <span class="<?php echo $isLoser ? 'val-up' : 'val-dn'; ?>"><?php echo $isLoser ? "+{$eloGain} ELO" : ($isWinner ? "-{$eloLoss} ELO" : "+0 ELO"); ?></span></span></div>
                        <?php else: ?>
                        <div style="height: 35px;"></div>
                        <?php endif; ?>

                        <div style="height:10px;"></div>

                        <?php if ($xpWager > 0): ?>
                            <?php if ($isLoser || $isDraw): /* opponent wins/draws */ ?>
                            <div class="bc-xp-pill"><i class="fas fa-coins" style="margin-right: 5px;"></i> +<?php echo $xpWager; ?> XP STAKE</div>
                            <?php else: ?>
                            <div class="bc-xp-pill lose-pill"><i class="fas fa-coins" style="margin-right: 5px;"></i> -<?php echo $xpWager; ?> XP STAKE</div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="bc-xp-miss" style="opacity: 0;">Spacer</div>
                        <?php endif; ?>

                        <div style="height:25px;"></div>

                        <div class="bc-sline"><i class="fas fa-trophy icon-gold" style="font-size:20px;"></i><span class="sl" style="margin-top:10px;">ELO Rating:</span></div>
                        <div class="bc-elo-val-block">
                            <div class="elo-val"><?php echo $oppElo; ?> ELO</div>
                            <div class="glob-text">(Global Ranking)</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer: Brand Stamp -->
            <div class="bc-footer">
                <div class="bc-stamp-new">
                    <div class="bc-stamp-icon">
                        <div class="bc-stamp-inner-circle"><i class="fas fa-check"></i></div>
                    </div>
                    <div class="bc-stamp-content">
                        <div class="bc-stamp-verified">Verified</div>
                        <div class="bc-stamp-divider"></div>
                        <div class="bc-stamp-title">PLATFORM ADMINISTRATOR</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    var cardBlobUrl = null;

    function generatePreview() {
        var btn = document.getElementById('btnGeneratePreview');
        var card = document.getElementById('battleCard');
        var wrapper = document.getElementById('battleCardWrapper');
        var previewImg = document.getElementById('cardPreviewImg');
        var placeholder = document.getElementById('cardPreviewPlaceholder');

        btn.classList.add('generating');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating...';

        wrapper.style.position = 'fixed';
        wrapper.style.left = '0';
        wrapper.style.top = '0';
        wrapper.style.zIndex = '-1';
        wrapper.style.opacity = '0.01';

        html2canvas(card, {
            scale: 2, useCORS: true, allowTaint: true,
            backgroundColor: '#0a0e17', width: 1080, height: 1080, logging: false
        }).then(function(canvas) {
            canvas.toBlob(function(blob) {
                if (cardBlobUrl) URL.revokeObjectURL(cardBlobUrl);
                cardBlobUrl = URL.createObjectURL(blob);
                previewImg.src = cardBlobUrl;
                previewImg.style.display = 'block';
                placeholder.style.display = 'none';
                document.getElementById('previewActions').style.display = 'none';
                document.getElementById('downloadActions').style.display = 'block';
                hideWrapper();
                btn.classList.remove('generating');
                btn.innerHTML = '<i class="fas fa-wand-magic-sparkles me-2"></i>Generate Card Preview';
            }, 'image/jpeg', 0.95);
        }).catch(function(err) {
            console.error('Card generation error:', err);
            btn.classList.remove('generating');
            btn.innerHTML = '<i class="fas fa-wand-magic-sparkles me-2"></i>Generate Card Preview';
            hideWrapper();
            alert('Could not generate card. Please try again.');
        });
    }

    function downloadCard() {
        if (cardBlobUrl) {
            var a = document.createElement('a');
            a.href = cardBlobUrl;
            a.download = 'battle_result_<?php echo $battleId; ?>.jpg';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    }

    function hideWrapper() {
        var w = document.getElementById('battleCardWrapper');
        w.style.position = 'absolute';
        w.style.left = '-9999px';
        w.style.top = '-9999px';
        w.style.zIndex = '';
        w.style.opacity = '';
    }
    </script>
    <?php endif; ?>

</body>
</html>