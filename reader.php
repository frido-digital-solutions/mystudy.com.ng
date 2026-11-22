<?php
/**
 * The reader: a material's pages, drawn on the screen instead of handed over as
 * a file, and how far the student got is remembered.
 *
 * Three kinds of visitor arrive here and they are treated the same way:
 *   - a signed-in browser with a session cookie;
 *   - an anonymous browser, only for free files (progress is then not kept,
 *     because there is nobody to keep it for);
 *   - the mobile app, which has no cookie. The app asks api/v1 for a ten-minute
 *     ticket scoped to this one file and puts it in the link; every request the
 *     page makes carries that ticket back, so the reader knows who to save
 *     progress for without ever seeing the account's real token.
 *
 * Nothing about access is decided twice. material_can_access() is the same rule
 * the download gate uses, so a file that is locked on the site is locked here,
 * and the bytes only ever leave through the streaming helper below.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Material.php';
require_once __DIR__ . '/includes/materials_paid.php';
require_once __DIR__ . '/includes/app_ticket.php';
require_once __DIR__ . '/includes/material_progress.php';

$id = (int)($_GET['id'] ?? 0);
$act = strtolower((string)($_GET['act'] ?? ''));
$ticket = trim((string)($_GET['t'] ?? ''));
$appMode = (($_GET['app'] ?? '') === '1');
// The app has its own light/dark switch, which need not agree with the phone's,
// so it says which one to draw. With no word from the app, the OS decides.
$darkMode = isset($_GET['dark']) && $_GET['dark'] !== '' ? ($_GET['dark'] === '1') : null;

$materialModel = new Material($pdo);
$material = $id > 0 ? $materialModel->getById($id) : null;

if (!$material || ($material['status'] ?? '') !== 'approved') {
    http_response_code(404);
    if ($act !== '') {
        header('Content-Type: application/json');
        exit(json_encode(['ok' => false, 'error' => 'not_found']));
    }
    set_flash_message('error', 'That material is not available.');
    header('Location: ' . SITE_URL . '/materials.php');
    exit;
}

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$scope = 'read:' . $id;
if ($ticket !== '') {
    $ticketUser = app_ticket_resolve($pdo, $ticket, $scope);
    if ($ticketUser) {
        // The ticket is fresher proof of who is holding the device than a cookie
        // this browser may have picked up for someone else.
        $userId = (int)$ticketUser['id'];
    }
}
$mayAccess = material_can_access($pdo, $userId > 0 ? $userId : null, $material);

$json = static function (array $payload, int $code = 200) use ($act): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    exit(json_encode($payload));
};

if ($act === 'file') {
    if (!$mayAccess) {
        $json(['ok' => false, 'error' => 'locked'], 403);
    }
    $path = material_resolve_file($material);
    if ($path === null) {
        $json(['ok' => false, 'error' => 'gone'], 404);
    }
    // Reading a page is not downloading a file, so the download counter is left alone.
    material_stream($path, material_download_name($material), $material['mime_type'] ?? null, true);
    exit;
}

if ($act === 'state') {
    $stored = $mayAccess ? material_progress_for($pdo, $userId, [$id]) : [];
    $json(['ok' => true, 'can_read' => $mayAccess] + ($stored[$id] ?? ['percent' => 0, 'page' => 1, 'pages' => 0]));
}

if ($act === 'save') {
    // A form posted from another site carries no marker; this rules out somebody
    // scribbling over another student's reading progress with a crafted request.
    if (strcasecmp((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'XMLHttpRequest') !== 0) {
        $json(['ok' => false, 'error' => 'bad_request'], 400);
    }
    if (!$mayAccess) {
        $json(['ok' => false, 'error' => 'locked'], 403);
    }
    $percent = material_progress_save($pdo, $userId, $id, (int)($_POST['page'] ?? 1), (int)($_POST['pages'] ?? 1));
    $json(['ok' => true, 'percent' => $percent]);
}

$page_title = htmlspecialchars($material['title']) . ' - Reader';
$backUrl = SITE_URL . '/material.php?id=' . $id;
$downloadUrl = SITE_URL . '/material_download.php?id=' . $id . ($ticket !== '' ? '&t=' . rawurlencode($ticket) : '');
$fileUrl = SITE_URL . '/reader.php?act=file&id=' . $id . ($ticket !== '' ? '&t=' . rawurlencode($ticket) : '');
$stateUrl = SITE_URL . '/reader.php?act=state&id=' . $id . ($ticket !== '' ? '&t=' . rawurlencode($ticket) : '');
$saveUrl = SITE_URL . '/reader.php?act=save&id=' . $id . ($ticket !== '' ? '&t=' . rawurlencode($ticket) : '');
$mime = strtolower((string)($material['mime_type'] ?? ''));
$isPdf = strpos($mime, 'application/pdf') !== false || strtolower(pathinfo((string)$material['file_path'], PATHINFO_EXTENSION)) === 'pdf';
$resume = $mayAccess ? (material_progress_for($pdo, $userId, [$id])[$id] ?? ['percent' => 0, 'page' => 1, 'pages' => 0]) : ['percent' => 0, 'page' => 1, 'pages' => 0];
$sizeLabel = isset($material['file_size']) ? round(((int)$material['file_size']) / 1024) . ' KB' : '';
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $darkMode === null ? '' : ($darkMode ? 'rp-dark' : 'rp-light'); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title><?php echo htmlspecialchars($material['title']); ?> | Reader</title>
<meta name="theme-color" content="#10b981">
<meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
  *,*::before,*::after{box-sizing:border-box}
  /* Three states, in this order of authority: what the app asked for (dark=1/0,
     because the app has its own switch that need not agree with the phone), then
     what the operating system wants, then light. */
  :root{
    --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; --brand:#10b981; --brand-dark:#059669;
    --paper:#e9edf2; --sheet:#fff; --bar:rgba(255,255,255,.92); --topbar:rgba(255,255,255,.92);
    --kicker:#059669; --chip-a:#ecfdf5; --chip-b:#d1fae5; --chip-line:#a7f3d0; --chip-ink:#065f46;
    --mark-a:#ecfdf5; --mark-b:#dbeafe;
  }
  @media (prefers-color-scheme:dark){
    :root:not(.rp-light){
      --ink:#e6edf6; --muted:#93a4bb; --line:#26303f; --paper:#0d1420; --sheet:#141d2b; --bar:rgba(13,20,32,.94); --topbar:rgba(13,20,32,.92);
      --kicker:#34d399; --chip-a:#052e26; --chip-b:#064e3b; --chip-line:#065f46; --chip-ink:#a7f3d0;
      --mark-a:#052e26; --mark-b:#0b2545;
    }
  }
  html.rp-dark{
    --ink:#e6edf6; --muted:#93a4bb; --line:#26303f; --paper:#0d1420; --sheet:#141d2b; --bar:rgba(13,20,32,.94); --topbar:rgba(13,20,32,.92);
    --kicker:#34d399; --chip-a:#052e26; --chip-b:#064e3b; --chip-line:#065f46; --chip-ink:#a7f3d0;
    --mark-a:#052e26; --mark-b:#0b2545;
  }
  /* Nothing on this page may be scaled by the browser itself. touch-action is
     read from the touched element up to the root, so setting it here keeps a
     pinch from dragging or blurring the fixed bars as well; the spread gesture
     is caught further down and answered by drawing the page bigger. */
  html,body{margin:0;height:100%;touch-action:pan-x pan-y}
  body{background:var(--paper);color:var(--ink);font-family:Poppins,-apple-system,Segoe UI,Roboto,sans-serif;-webkit-text-size-adjust:100%;overflow-x:hidden}
  button{font:inherit;color:inherit;background:none;border:0;cursor:pointer;-webkit-tap-highlight-color:transparent}

  /* One bar, always on screen: the way back, what is being read, how far through
     it the reader is, and the two tools. Nothing else is pinned, so the page
     never ends up with two heads and the student never wonders which back arrow
     belongs to the app. */
  .rp-top{position:fixed;top:0;left:0;right:0;z-index:20;display:flex;align-items:center;gap:.6rem;padding:calc(env(safe-area-inset-top) + .5rem) .75rem .55rem;
    background:linear-gradient(180deg,rgba(16,185,129,.14),rgba(16,185,129,.04)),var(--topbar);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border-bottom:1px solid var(--line)}
  .rp-back{flex:0 0 auto;width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:var(--sheet);border:1px solid var(--line);font-size:.85rem;color:var(--ink);text-decoration:none}
  .rp-back:active{transform:scale(.94)}
  .rp-head{flex:1 1 auto;min-width:0}
  .rp-kicker{display:block;font-size:.62rem;letter-spacing:.13em;text-transform:uppercase;color:var(--kicker);font-weight:600}
  .rp-name{display:block;font-size:.94rem;font-weight:600;line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .rp-icon{flex:0 0 auto;width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:var(--sheet);border:1px solid var(--line);color:var(--brand-dark);font-size:.85rem;text-decoration:none}
  .rp-icon:active{transform:scale(.94)}

  /* The pill carries how far through the file the reader is and doubles as the way
     in to "go to page", which is why it is a button and not a label. */
  .rp-jump{flex:0 0 auto;min-width:64px;padding:.3rem .6rem;border-radius:999px;text-align:center;line-height:1.2;
    background:linear-gradient(135deg,var(--chip-a),var(--chip-b));border:1px solid var(--chip-line);color:var(--chip-ink)}
  .rp-jump b{display:block;font-size:.8rem;font-weight:700;font-variant-numeric:tabular-nums}
  .rp-jump small{display:block;font-size:.55rem;letter-spacing:.05em;text-transform:uppercase;font-weight:600;opacity:.85;white-space:nowrap}
  .rp-jump small i{font-size:.48rem;margin-left:2px;opacity:.7}
  .rp-jump:active{transform:scale(.96)}

  .rp-rule{position:absolute;left:0;bottom:0;height:2px;width:100%;background:transparent}
  .rp-rule i{display:block;height:100%;width:0;background:linear-gradient(90deg,var(--brand),#34d399);transition:width .25s ease}

  /* Panning stays with the browser, pinch does not. A browser pinch scales the
     whole layout: the fixed bars slide and grow with it and an already drawn
     page gets stretched, which is exactly the soft, moving-toolbar reading the
     pages complained about. Here the two-finger spread re-draws the page at the
     new size instead, so the chrome never moves and the text stays sharp. */
  .rp-doc{padding:calc(var(--rp-top,62px) + .75rem) .5rem 6.5rem;overflow-x:auto;-webkit-overflow-scrolling:touch;touch-action:pan-x pan-y;--z:1}
  /* The page box is a size, not a rebuild: zoom is one number on the container
     and the browser rescales every page from it. Re-making the pages was what
     threw away the picture already on the screen, which is the black moment the
     reader saw before the zoom appeared. */
  .rp-page{position:relative;margin:0 auto .75rem;background:var(--sheet);border:1px solid var(--line);border-radius:10px;box-shadow:0 6px 18px rgba(15,23,42,.07);overflow:hidden;
    width:min(calc(var(--w0,320px) * var(--z,1)), var(--wcap,2400px));
    height:calc(min(calc(var(--w0,320px) * var(--z,1)), var(--wcap,2400px)) * var(--ar,1.4142))}
  .rp-page canvas{display:block;width:100%;height:auto}
  /* The page the reader was already looking at, stretched over the new box
     until the sharper drawing of it arrives. A slightly soft page for a frame
     is what every other reader shows; a blank one is what made ours look slow. */
  .rp-page canvas.rp-ghost{position:absolute;left:0;top:0;width:100%;height:100%}
  .rp-num{position:absolute;right:8px;bottom:8px;padding:.2rem .5rem;border-radius:999px;background:rgba(15,23,42,.55);color:#fff;font-size:.66rem;font-weight:600;opacity:0;transition:opacity .2s}
  .rp-page.shown .rp-num{opacity:1}
  .rp-skeleton{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;gap:.45rem;color:var(--muted);font-size:.78rem;background:linear-gradient(180deg,rgba(148,163,184,.10),rgba(148,163,184,.02))}
  .rp-skeleton i{animation:rp-spin 1s linear infinite}
  @keyframes rp-spin{to{transform:rotate(360deg)}}

  .rp-bar{position:fixed;right:0;left:0;bottom:0;z-index:20;display:flex;align-items:center;gap:.55rem;
    padding:.6rem .7rem calc(env(safe-area-inset-bottom) + .6rem);
    background:var(--bar);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-top:1px solid var(--line)}
  .rp-nav{width:40px;height:40px;border-radius:50%;background:var(--sheet);border:1px solid var(--line);display:flex;align-items:center;justify-content:center;font-size:.8rem;color:var(--brand-dark)}
  .rp-nav:disabled{opacity:.35}
  .rp-mid{flex:1 1 auto;min-width:0}
  .rp-mid span{display:block;height:4px;border-radius:999px;background:var(--line);overflow:hidden}
  .rp-mid span i{display:block;height:100%;width:0;border-radius:999px;background:linear-gradient(90deg,var(--brand),#34d399);transition:width .25s ease}
  .rp-zoom{display:flex;align-items:center;gap:.35rem}
  .rp-fit{height:40px;min-width:52px;padding:0 .55rem;border-radius:999px;background:var(--sheet);border:1px solid var(--line);
    display:flex;align-items:center;justify-content:center;gap:.3rem;font-size:.7rem;font-weight:700;color:var(--brand-dark);letter-spacing:.02em}
  .rp-fit:active{transform:scale(.96)}
  .rp-fit.on{background:var(--brand);border-color:var(--brand);color:#fff}

  /* Two tools a long file needs: jump to a page, and find a word in it. Going to
     a page belongs under the pill that opens it, at the top; the list of found
     words needs the height, so it keeps the bottom. */
  .rp-sheet{position:fixed;left:0;right:0;bottom:var(--rp-bar,62px);z-index:22;
    transform:translateY(calc(100% + var(--rp-bar,62px) + 12px));transition:transform .24s ease;
    background:var(--sheet);border-top:1px solid var(--line);border-radius:16px 16px 0 0;box-shadow:0 -12px 30px rgba(15,23,42,.20);padding:.7rem .8rem .85rem}
  .rp-sheet.up{transform:translateY(0)}
  .rp-sheet.top{top:var(--rp-top,62px);bottom:auto;border-top:0;border-bottom:1px solid var(--line);border-radius:0 0 16px 16px;
    box-shadow:0 12px 30px rgba(15,23,42,.20);transform:translateY(calc(-100% - var(--rp-top,62px) - 12px))}
  .rp-sheet.top.up{transform:translateY(0)}
  .rp-sheet-h{display:flex;align-items:center;gap:.5rem;margin-bottom:.6rem}
  .rp-sheet-h .t{font-size:.68rem;letter-spacing:.13em;text-transform:uppercase;color:var(--kicker);font-weight:600}
  .rp-sheet-h .x{margin-left:auto;width:31px;height:31px;border-radius:50%;background:var(--paper);border:1px solid var(--line);color:var(--muted);font-size:.7rem}
  .rp-field{display:flex;align-items:center;gap:.45rem;padding:.25rem .25rem .25rem .75rem;border:1px solid var(--chip-line);border-radius:12px;
    background:linear-gradient(135deg,var(--chip-a),var(--chip-b))}
  .rp-field input{flex:1 1 auto;min-width:0;background:none;border:0;color:var(--ink);font:inherit;font-size:.92rem;outline:none;padding:.5rem 0}
  .rp-field input::placeholder{color:var(--muted)}
  .rp-field .pre{color:var(--chip-ink);font-size:.78rem;opacity:.75}
  .rp-go{flex:0 0 auto;padding:.5rem .95rem;border-radius:9px;background:var(--brand);color:#fff;font-size:.84rem;font-weight:600}
  .rp-go:active{transform:scale(.96)}
  .rp-note{margin:.55rem .1rem 0;font-size:.78rem;line-height:1.55;color:var(--muted)}
  .rp-note.bad{color:#92400e}
  html.rp-dark .rp-note.bad{color:#fbbf24}
  .rp-hits{margin:.5rem -.15rem 0;max-height:44vh;overflow-y:auto;-webkit-overflow-scrolling:touch}
  .rp-hit{display:flex;width:100%;gap:.6rem;align-items:flex-start;text-align:left;padding:.55rem .35rem;border-bottom:1px solid var(--line)}
  .rp-hit:last-child{border-bottom:0}
  .rp-hit:active{background:var(--paper)}
  .rp-hit .p{flex:0 0 auto;min-width:36px;text-align:center;padding:.2rem .4rem;border-radius:7px;background:linear-gradient(135deg,var(--mark-a),var(--mark-b));
    border:1px solid var(--chip-line);color:var(--chip-ink);font-size:.74rem;font-weight:700;font-variant-numeric:tabular-nums}
  .rp-hit .w{flex:1 1 auto;min-width:0;font-size:.82rem;line-height:1.55;color:var(--muted)}
  .rp-hit .w b{color:var(--ink);font-weight:600;background:rgba(250,204,21,.5);border-radius:3px;padding:0 1px}

  /* The word the reader asked for, laid over the page it sits on. */
  .rp-mark{position:absolute;inset:0;pointer-events:none;z-index:3}
  .rp-mark span{position:absolute;background:rgba(250,204,21,.4);border:1.5px solid rgba(217,119,6,.9);border-radius:3px;animation:rp-flash .85s ease 2}
  @keyframes rp-flash{0%,100%{box-shadow:0 0 0 0 rgba(250,204,21,0)}50%{box-shadow:0 0 0 7px rgba(250,204,21,.3)}}

  .rp-state{max-width:34rem;margin:16vh auto 0;padding:0 1.1rem;text-align:center}
  .rp-state .mark{width:60px;height:60px;margin:0 auto 1rem;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.35rem;
    background:linear-gradient(135deg,var(--mark-a),var(--mark-b));color:var(--kicker);border:1px solid var(--chip-line)}
  .rp-state h1{margin:0 0 .5rem;font-size:1.15rem;font-weight:600}
  .rp-state p{margin:0 0 1.2rem;color:var(--muted);font-size:.9rem;line-height:1.65}
  .rp-cta{display:inline-flex;align-items:center;gap:.5rem;padding:.72rem 1.25rem;border-radius:999px;background:var(--brand);color:#fff;font-weight:600;font-size:.9rem;text-decoration:none}
  .rp-cta.ghost{background:var(--sheet);color:var(--ink);border:1px solid var(--line)}
  .rp-lock .mark{background:linear-gradient(135deg,#fffbeb,#fef3c7);color:#92400e;border-color:#fde68a}
</style>
</head>
<body>
<header class="rp-top" id="rpTop">
  <?php if ($appMode): ?>
    <button class="rp-back" id="rpBack" type="button" aria-label="Leave the reader"><i class="fa-solid fa-chevron-left"></i></button>
  <?php else: ?>
    <a class="rp-back" id="rpBack" href="<?php echo htmlspecialchars($backUrl); ?>" aria-label="Go back" onclick="if(history.length>1){event.preventDefault();history.back()}"><i class="fa-solid fa-chevron-left"></i></a>
  <?php endif; ?>
  <div class="rp-head">
    <span class="rp-kicker">Reading</span>
    <span class="rp-name"><?php echo htmlspecialchars($material['title']); ?></span>
  </div>
  <?php if ($isPdf && $mayAccess): ?>
    <button class="rp-jump" id="rpJump" type="button" aria-label="Go to a page" title="Go to a page">
      <b id="rpCount">0%</b>
      <small><span id="rpCountSub">Go to page</span><i class="fa-solid fa-angle-down"></i></small>
    </button>
    <button class="rp-icon" id="rpFindBtn" type="button" aria-label="Find words in this material" title="Find in this material"><i class="fa-solid fa-magnifying-glass"></i></button>
  <?php endif; ?>
  <div class="rp-rule"><i id="rpRule"></i></div>
</header>

<script>
/* The bar is pinned over the document, so the document has to start below it, and
   only the browser knows how tall the bar really drew. The app has no header of its
   own over this page either, so the way back is handed to it as a message rather
   than a URL the WebView would follow. */
(function () {
  var top = document.getElementById('rpTop'), back = document.getElementById('rpBack');
  function measure() {
    document.documentElement.style.setProperty('--rp-top', (top ? top.offsetHeight : 0) + 'px');
  }
  measure();
  window.addEventListener('resize', measure);
  if (back && <?php echo $appMode ? 'true' : 'false'; ?>) {
    back.addEventListener('click', function () {
      try {
        if (window.ReactNativeWebView) { window.ReactNativeWebView.postMessage('msb:back'); }
      } catch (e) {}
    });
  }
})();
</script>

<main class="rp-doc" id="rpDoc"></main>

<div class="rp-bar" id="rpBar" hidden>
  <button class="rp-nav" id="rpPrev" aria-label="Previous page"><i class="fa-solid fa-chevron-up"></i></button>
  <div class="rp-mid"><span><i id="rpFill"></i></span></div>
  <div class="rp-zoom">
    <button class="rp-nav" id="rpOut" aria-label="Zoom out"><i class="fa-solid fa-minus"></i></button>
    <button class="rp-fit" id="rpFit" aria-label="Reset zoom to fit the page" title="Double-tap a page to fit it back"><i class="fa-solid fa-compress"></i><span id="rpZoomTxt">100%</span></button>
    <button class="rp-nav" id="rpIn" aria-label="Zoom in"><i class="fa-solid fa-plus"></i></button>
  </div>
  <button class="rp-nav" id="rpNext" aria-label="Next page"><i class="fa-solid fa-chevron-down"></i></button>
</div>

<?php if ($isPdf && $mayAccess): ?>
  <div class="rp-sheet" id="rpSheet" role="dialog" aria-label="Reading tools">
    <div class="rp-sheet-h">
      <span class="t" id="rpSheetTitle">Go to page</span>
      <button class="x" id="rpSheetX" type="button" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <div id="rpJumpPane">
      <div class="rp-field">
        <input id="rpJumpIn" type="text" inputmode="numeric" autocomplete="off" maxlength="6" placeholder="Page number" enterkeyhint="go">
        <button class="rp-go" id="rpJumpGo" type="button">Go</button>
      </div>
      <p class="rp-note" id="rpJumpNote"></p>
    </div>

    <div id="rpFindPane" hidden>
      <div class="rp-field">
        <i class="fa-solid fa-magnifying-glass pre"></i>
        <input id="rpFindIn" type="text" autocomplete="off" placeholder="Word or phrase" enterkeyhint="search">
        <button class="rp-go" id="rpFindGo" type="button">Find</button>
      </div>
      <p class="rp-note" id="rpFindNote"></p>
      <div class="rp-hits" id="rpHits"></div>
    </div>
  </div>
<?php endif; ?>

<?php if (!$isPdf): ?>
  <div class="rp-state">
    <div class="mark"><i class="fa-solid fa-file"></i></div>
    <h1>This one has no pages to read here</h1>
    <p>It is not a PDF, so the reader cannot draw it. Download it and open it in an app on your phone.</p>
    <a class="rp-cta" href="<?php echo htmlspecialchars($downloadUrl); ?>"><i class="fa-solid fa-download"></i> Download</a>
  </div>
<?php elseif (!$mayAccess): ?>
  <div class="rp-state rp-lock">
    <div class="mark"><i class="fa-solid fa-lock"></i></div>
    <h1>This material is locked</h1>
    <p>It is a paid file and this account has not bought it yet. Buy it and the reader opens it on every device you sign in on.</p>
    <a class="rp-cta" href="<?php echo htmlspecialchars(SITE_URL . '/material.php?id=' . $id); ?>"><i class="fa-solid fa-wallet"></i> Open the material</a>
  </div>
<?php endif; ?>

<?php if ($isPdf && $mayAccess): ?>
<script src="<?php echo SITE_URL; ?>/assets/js/pdfjs/pdf.min.js"></script>
<script>
(function () {
  'use strict';
  var FILE = <?php echo json_encode($fileUrl); ?>,
      STATE = <?php echo json_encode($stateUrl); ?>,
      SAVE = <?php echo json_encode($saveUrl); ?>,
      RESUME_PAGE = <?php echo (int)($resume['page'] ?? 1); ?>,
      doc = null, pages = 0, sizes = [],
      slots = [], drawn = {}, stale = {}, busy = {},
      zoom = 1, current = 1, deepest = Math.max(1, RESUME_PAGE),
      saveTimer = 0, dirty = false,
      texts = [],
      sheetOn = '', searchRun = 0, mark = null;

  var elDoc = document.getElementById('rpDoc'),
      elTop = document.getElementById('rpTop'),
      elBar = document.getElementById('rpBar'),
      elCount = document.getElementById('rpCount'),
      elSub = document.getElementById('rpCountSub'),
      elFill = document.getElementById('rpFill'),
      elRule = document.getElementById('rpRule'),
      elPrev = document.getElementById('rpPrev'),
      elNext = document.getElementById('rpNext'),
      elIn = document.getElementById('rpIn'),
      elOut = document.getElementById('rpOut'),
      elSheet = document.getElementById('rpSheet'),
      elSheetTitle = document.getElementById('rpSheetTitle'),
      elJumpPane = document.getElementById('rpJumpPane'),
      elFindPane = document.getElementById('rpFindPane'),
      elJumpIn = document.getElementById('rpJumpIn'),
      elJumpNote = document.getElementById('rpJumpNote'),
      elFindIn = document.getElementById('rpFindIn'),
      elFindNote = document.getElementById('rpFindNote'),
      elHits = document.getElementById('rpHits');

  function fail(title, note, cta) {
    elDoc.innerHTML = '';
    var box = document.createElement('div');
    box.className = 'rp-state';
    box.innerHTML = '<div class="mark"><i class="fa-solid fa-triangle-exclamation"></i></div>'
      + '<h1></h1><p></p>';
    box.querySelector('h1').textContent = title;
    box.querySelector('p').textContent = note;
    if (cta) {
      var a = document.createElement('a');
      a.className = 'rp-cta';
      a.href = <?php echo json_encode($downloadUrl); ?>;
      a.innerHTML = '<i class="fa-solid fa-download"></i> Download instead';
      box.appendChild(a);
    }
    elDoc.appendChild(box);
  }

  /** The file arrives as one buffer so the byte count can drive the first bar. */
  function loadBytes() {
    return fetch(FILE, {credentials: 'same-origin'}).then(function (res) {
      if (!res.ok) { throw {locked: res.status === 403}; }
      var total = parseInt(res.headers.get('Content-Length') || '0', 10);
      if (!res.body || !total) { return res.arrayBuffer(); }
      var reader = res.body.getReader(), got = 0, chunks = [];
      var pump = function () {
        return reader.read().then(function (r) {
          if (r.done) {
            var buf = new Uint8Array(got), off = 0;
            for (var i = 0; i < chunks.length; i++) { buf.set(chunks[i], off); off += chunks[i].length; }
            return buf.buffer;
          }
          chunks.push(r.value); got += r.value.length;
          paint(Math.min(100, Math.round(got / total * 100)), 'Opening');
          return pump();
        });
      };
      return pump();
    });
  }

  function paint(pct, label) {
    pct = Math.max(0, Math.min(100, pct | 0));
    elCount.textContent = label || (pct + '%');
    elFill.style.width = pct + '%';
    elRule.style.width = pct + '%';
  }

  // The same arithmetic the server keeps, so the chip on this page, the number
  // saved to the account and the one the app lists can never disagree.
  function pctOf(page) {
    if (!pages) { return 0; }
    return page >= pages ? 100 : Math.floor((page - 1) / pages * 100);
  }

  function slot(n) {
    var page = document.createElement('div');
    page.className = 'rp-page';
    page.dataset.page = n;
    // A page may grow well past its own point size: a vector PDF has detail at
    // every scale, so this is only a ceiling for very small originals - never a
    // cap on how far the reader can zoom.
    page.style.setProperty('--wcap', Math.round(Math.max(480, sizes[n].width * 6)) + 'px');
    page.style.setProperty('--ar', (sizes[n].height / sizes[n].width).toFixed(5));
    page.innerHTML = '<div class="rp-skeleton"><i class="fa-solid fa-spinner"></i> Page ' + n + '</div><span class="rp-num">' + n + '</span>';
    return page;
  }

  /** The width a page is fitted to when it is not zoomed: one number, shared. */
  function fitWidth() {
    elDoc.style.setProperty('--w0', Math.max(240, elDoc.clientWidth - 4) + 'px');
  }

  function layout() {
    clearMark();
    elDoc.innerHTML = '';
    slots = [];
    drawn = {};
    stale = {};
    for (var n = 1; n <= pages; n++) {
      slots[n] = slot(n);
      elDoc.appendChild(slots[n]);
    }
    fitWidth();
    observe();
  }

  var io = null;
  function observe() {
    if (io) { io.disconnect(); }
    io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { render(+e.target.dataset.page); }
      });
    }, {rootMargin: '1400px 0px'});
    for (var n = 1; n <= pages; n++) { io.observe(slots[n]); }
  }

  function render(n) {
    if (busy[n] || !slots[n] || (drawn[n] && !stale[n])) { return; }
    busy[n] = 1;
    doc.getPage(n).then(function (page) {
      var base = page.getViewport({scale: 1});
      var css = sizes[n];
      var scale = (slots[n].clientWidth || css.width) / base.width;
      // One canvas pixel per physical pixel of the size the page is shown at.
      // Capping this at 2 is what made a zoomed page go soft on a 3x phone: the
      // picture was stretched to fill a slot it had never been drawn for.
      var dpr = Math.min(3, window.devicePixelRatio || 1);
      var want = scale * dpr;
      // A phone GPU will not hold a texture past 4096 on a side. Bring the long
      // edge inside that, but never below the CSS size - below that the page
      // really is being upscaled and no cap can hide it.
      var probe = page.getViewport({scale: want});
      var longEdge = Math.max(probe.width, probe.height);
      if (longEdge > 4096) { want = Math.max(scale, want * (4096 / longEdge)); }
      var vp = page.getViewport({scale: want});
      var canvas = document.createElement('canvas');
      canvas.width = Math.round(vp.width);
      canvas.height = Math.round(vp.height);
      canvas.style.width = '100%';
      canvas.style.height = '100%';
      var ctx = canvas.getContext('2d', {alpha: false});
      return page.render({canvasContext: ctx, viewport: vp}).promise.then(function () {
        delete busy[n];
        // Fast scrolling can finish a page the reader has long left behind.
        // Keeping those pictures is how a phone runs out of memory.
        if (Math.abs(n - current) > 8) {
          canvas.width = canvas.height = 0;
          return;
        }
        drawn[n] = 1;
        delete stale[n];
        var sk = slots[n].querySelector('.rp-skeleton');
        if (sk) { sk.remove(); }
        // The page is sharp now, so the stretched one it was sitting on can go.
        var g = slots[n].querySelector('canvas.rp-ghost');
        if (g) { g.width = g.height = 0; g.remove(); }
        slots[n].classList.add('shown');
        slots[n].insertBefore(canvas, slots[n].firstChild);
      });
    }).catch(function () {
      delete busy[n];
      delete stale[n];
      if (!slots[n]) { return; }
      var keepOld = slots[n].querySelector('canvas.rp-ghost');
      if (keepOld) {
        // The redraw failed, not the page. Leave the softer picture the reader
        // could already read rather than replacing it with an error.
        keepOld.classList.remove('rp-ghost');
        drawn[n] = 1;
        return;
      }
      var sk = slots[n].querySelector('.rp-skeleton');
      if (sk) { sk.innerHTML = '<i class="fa-solid fa-exclamation"></i> Page ' + n + ' did not draw'; }
    });
  }

  /** Only a handful of full-size canvases are kept warm at a time. */
  function trim(n) {
    // A zoomed page is many times the pixels of a fitted one, so the warm
    // window narrows as the zoom grows - the same memory, sharper pictures.
    var back = zoom > 1.8 ? 1 : zoom > 1.3 ? 2 : 3;
    var ahead = zoom > 1.8 ? 2 : zoom > 1.3 ? 3 : 5;
    var keep = {};
    for (var i = Math.max(1, n - back); i <= Math.min(pages, n + ahead); i++) { keep[i] = 1; }
    Object.keys(drawn).forEach(function (k) {
      if (!keep[k]) {
        var c = slots[k] && slots[k].querySelector('canvas');
        if (c) { c.width = c.height = 0; c.remove(); }
        delete drawn[k];
        delete stale[k];
        var sk = slots[k] && slots[k].querySelector('.rp-skeleton');
        if (slots[k] && !sk) {
          slots[k].insertAdjacentHTML('afterbegin', '<div class="rp-skeleton"><i class="fa-solid fa-spinner"></i> Page ' + k + '</div>');
        }
      }
    });
    // A page that was already on screen when its picture was released will not
    // trip the observer again, so what is in view now is asked for directly.
    // The rest of the warm window can wait for the next scroll rather than queue
    // ahead of the page under the reader's eye.
    for (var j = Math.max(1, n - 1); j <= Math.min(pages, n + 2); j++) { render(j); }
  }

  function clampZoom(v) {
    return Math.max(1, Math.min(3, Math.round(v * 100) / 100));
  }

  function clamp01(v) {
    return Math.max(0, Math.min(1, v));
  }

  /**
   * The point the reader is holding on to, kept as a page number and a
   * fraction across and down it. A fraction survives the resize that a pixel
   * would not, which is why a zoom lands on the words that were under the
   * finger instead of somewhere else in the file.
   */
  function anchorAt(cx, cy) {
    if (!pages || !slots[1]) { return null; }
    if (cy == null) { cy = window.innerHeight * 0.42; cx = window.innerWidth / 2; }
    var best = 1;
    for (var n = 1; n <= pages; n++) {
      var r = slots[n].getBoundingClientRect();
      if (r.height > 1 && r.top <= cy) {
        best = n;
        if (cy <= r.bottom) {
          return {n: n, fx: clamp01((cx - r.left) / r.width), fy: (cy - r.top) / r.height, cx: cx, cy: cy};
        }
      }
    }
    var rr = slots[best].getBoundingClientRect();
    return {n: best, fx: clamp01((cx - rr.left) / rr.width), fy: cy > rr.bottom ? 1 : 0, cx: cx, cy: cy};
  }

  /** Put that point back under the eye, or under the finger, it was under. */
  function reseat(a) {
    if (!a || !slots[a.n]) { return; }
    var r = slots[a.n].getBoundingClientRect();
    if (r.height > 1) {
      window.scrollTo(0, Math.max(0, Math.round(window.pageYOffset + r.top + a.fy * r.height - a.cy)));
    }
    if (elDoc.scrollWidth > elDoc.clientWidth) {
      var dl = elDoc.getBoundingClientRect().left;
      var contentX = elDoc.scrollLeft + (r.left + a.fx * r.width) - dl;
      elDoc.scrollLeft = Math.max(0, Math.round(contentX - (a.cx - dl)));
    }
  }

  /**
   * Sizing is one property on the document, so it costs a paint and nothing
   * else: the browser stretches the page already on the screen to the new box.
   * That is the whole reason a zoom no longer opens on a black rectangle.
   */
  function setSize(v) {
    zoom = clampZoom(v);
    elDoc.style.setProperty('--z', zoom);
    clearMark();
    paintZoom();
  }

  /** Every warm page is now the wrong resolution: hold the old picture, ask
   *  for a new one, and let the observer do the pages further down the file. */
  function markStale() {
    Object.keys(drawn).forEach(function (k) {
      var c = slots[k] && slots[k].querySelector('canvas:not(.rp-ghost)');
      if (c) { c.classList.add('rp-ghost'); stale[k] = 1; }
    });
  }

  function pinchTo(v, cx, cy) {
    var a = anchorAt(cx, cy);
    setSize(v);
    reseat(a);
  }

  /** When the fingers stop, or the button is pressed: redraw what is showing. */
  function commitZoom() {
    markStale();
    track();
  }

  function setZoom(next, cx, cy) {
    next = clampZoom(next);
    if (next === zoom) { paintZoom(); return; }
    pinchTo(next, cx, cy);
    commitZoom();
  }

  /** The chip between the two zoom buttons says where the page actually is. */
  function paintZoom() {
    var txt = document.getElementById('rpZoomTxt');
    var fit = document.getElementById('rpFit');
    if (txt) { txt.textContent = Math.round(zoom * 100) + '%'; }
    if (fit) { fit.classList.toggle('on', zoom > 1); }
  }

  /** Where the reader is looking: the page crossing the middle of the screen. */
  function track() {
    if (!pages) { return; }
    var mid = window.innerHeight * 0.42, best = 1, top = 0;
    for (var n = 1; n <= pages; n++) {
      var r = slots[n].getBoundingClientRect();
      if (r.bottom > mid - 40) { best = n; top = r.top; break; }
    }
    if (top > window.innerHeight * 0.7 && best > 1) { best--; }
    current = best;
    if (best > deepest) { deepest = best; dirty = true; }
    elSub.textContent = best + ' / ' + pages;
    // The percentage sits right next to the page number, so it has to describe the
    // same place. The account keeps the furthest page reached, but this chip follows
    // the eyes; otherwise jumping back with Find leaves "26%" beside "page 1".
    paint(pctOf(best));
    elPrev.disabled = best <= 1;
    elNext.disabled = best >= pages;
    trim(best);
    if (dirty) {
      clearTimeout(saveTimer);
      dirty = false;
      saveTimer = setTimeout(save, 1200);
    }
  }

  function save() {
    if (deepest < 2) { return; }
    var body = 'page=' + deepest + '&pages=' + pages;
    fetch(SAVE, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
      body: body
    }).catch(function () {});
  }

  function goTo(n) {
    n = Math.max(1, Math.min(pages, n));
    var r = slots[n].getBoundingClientRect();
    // The bar is pinned over the top of the page, so a jump stops below it rather
    // than sliding the page header out of sight.
    window.scrollTo({top: window.pageYOffset + r.top - elTop.offsetHeight - 10, behavior: 'smooth'});
  }

  /* ------------------------------------------------------------------ *
   * Go to a page, and find a word through the whole file.
   *
   * Both work on the text the file itself carries, so a jump lands on the
   * page the reader asked for and a found word is ringed where it is drawn,
   * not somewhere near it. Nothing is searched on the server: the bytes are
   * already in memory here, so a 200-page file costs no data.
   * ------------------------------------------------------------------ */

  /** pdf.js' own matrix product, with a local copy if this build keeps it private. */
  function xform(m, n) {
    if (pdfjsLib.Util && typeof pdfjsLib.Util.transform === 'function') {
      return pdfjsLib.Util.transform(m, n);
    }
    return [
      m[0] * n[0] + m[1] * n[2],
      m[0] * n[1] + m[1] * n[3],
      m[2] * n[0] + m[3] * n[2],
      m[2] * n[1] + m[3] * n[3],
      m[4] * n[0] + m[5] * n[2] + n[4],
      m[4] * n[1] + m[5] * n[3] + n[5]
    ];
  }

  function say(el, text, bad) {
    el.textContent = text;
    el.className = 'rp-note' + (bad ? ' bad' : '');
  }

  /** Both sheets hang off a bar whose height only the browser knows. */
  function sizeChrome() {
    var root = document.documentElement.style;
    root.setProperty('--rp-bar', (elBar.hidden ? 0 : elBar.offsetHeight) + 'px');
    root.setProperty('--rp-top', elTop.offsetHeight + 'px');
  }

  function openSheet(which) {
    if (!pages) { return; }
    sheetOn = which;
    elJumpPane.hidden = which !== 'jump';
    elFindPane.hidden = which !== 'find';
    elSheetTitle.textContent = which === 'jump' ? 'Go to page' : 'Find in this material';
    elSheet.classList.toggle('top', which === 'jump');
    if (which === 'jump') {
      elJumpIn.value = String(current);
      say(elJumpNote, 'This file has ' + pages + ' page' + (pages === 1 ? '' : 's') + ', numbered 1 to ' + pages + '.');
    } else if (!elFindIn.value.trim()) {
      say(elFindNote, 'Searches the words on every page, one page at a time.');
    }
    elSheet.classList.add('up');
    var field = which === 'jump' ? elJumpIn : elFindIn;
    setTimeout(function () { field.focus(); field.select(); }, 230);
  }

  function closeSheet() {
    sheetOn = '';
    searchRun++;
    elSheet.classList.remove('up');
  }

  /** A wrong number is refused and explained, never quietly moved inside range. */
  function tryJump() {
    var raw = elJumpIn.value.replace(/[^0-9]/g, '');
    if (raw === '') { say(elJumpNote, 'Type the page number you want.', true); return; }
    var n = parseInt(raw, 10);
    if (n < 1) { say(elJumpNote, 'The first page of this file is page 1.', true); return; }
    if (n > pages) {
      say(elJumpNote, 'Page ' + pages + ' is the last page of this file, so there is no page ' + raw + '.', true);
      return;
    }
    closeSheet();
    goTo(n);
  }

  /**
   * One page's readable text: a plain string, and for each character the text
   * run it came from. A PDF often splits one word across two runs, so runs that
   * sit side by side on a line are joined without a space - that is how a search
   * for "university" still finds a file that drew it as "univer" + "sity".
   */
  function textOf(n) {
    if (texts[n]) { return Promise.resolve(texts[n]); }
    return doc.getPage(n).then(function (page) {
      return page.getTextContent().then(function (tc) {
        var runs = [], out = '', map = [], starts = [], prev = null;
        for (var i = 0; i < tc.items.length; i++) {
          var it = tc.items[i];
          if (typeof it.str !== 'string' || it.str === '') { continue; }
          if (prev) {
            var gap = it.transform[4] - (prev.transform[4] + prev.width);
            var sameLine = Math.abs(it.transform[5] - prev.transform[5]) < Math.max(0.8, prev.height * 0.5);
            if (!sameLine || gap > prev.height * 0.2) { out += ' '; map.push(-1); }
          }
          var at = runs.length;
          runs.push(it);
          starts.push(out.length);
          for (var c = 0; c < it.str.length; c++) { out += it.str.charAt(c); map.push(at); }
          prev = it;
        }
        texts[n] = {s: out, l: out.toLowerCase(), map: map, runs: runs, starts: starts};
        return texts[n];
      });
    });
  }

  var CAP = 300;

  function clearHits() {
    while (elHits.firstChild) { elHits.removeChild(elHits.firstChild); }
  }

  function runSearch(phrase) {
    searchRun++;
    var run = searchRun;
    clearHits();
    clearMark();
    var needle = phrase.trim().toLowerCase();
    if (needle.length < 2) { say(elFindNote, 'Type at least two letters or digits.', true); return; }

    var n = 1, found = 0, listed = 0, onPages = 0, letters = 0;
    var pagesHit = {};
    say(elFindNote, 'Reading every page for "' + phrase.trim() + '"…');

    var step = function () {
      if (run !== searchRun) { return; }
      if (n > pages) { finish(); return; }
      var want = n;
      textOf(want).then(function (t) {
        if (run !== searchRun) { return; }
        letters += t.s.length;
        var from = 0, at;
        while ((at = t.l.indexOf(needle, from)) !== -1) {
          from = at + needle.length;
          found++;
          if (!pagesHit[want]) { pagesHit[want] = 1; onPages++; }
          if (listed < CAP) { listed++; addHit(want, t, at, needle.length, phrase.trim()); }
        }
        n++;
        if (n % 10 === 0 || n === pages) {
          say(elFindNote, 'Searching… page ' + Math.min(n, pages) + ' of ' + pages +
            (found ? ', ' + found + ' found' : ''));
        }
        setTimeout(step, 0);
      })['catch'](function () { if (run === searchRun) { n++; setTimeout(step, 0); } });
    };

    function finish() {
      if (!found) {
        say(elFindNote, letters < pages * 25
          ? 'This file is pictures of pages rather than typed words, so there is nothing to search.'
          : 'Nothing in this material says "' + phrase.trim() + '".');
        return;
      }
      say(elFindNote, '"' + phrase.trim() + '" appears ' + found + (found === 1 ? ' time' : ' times') +
        ' on ' + onPages + ' page' + (onPages === 1 ? '' : 's') +
        (found > listed ? ', the first ' + listed + ' are listed' : '') + '. Tap one to go there.');
    }

    step();
  }

  /** Built from text nodes only: a PDF's own words are never read as markup. */
  function addHit(page, t, at, len, phrase) {
    var s = t.s;
    var from = Math.max(0, s.lastIndexOf(' ', at - 34) + 1);
    var to = s.length;
    var tail = s.indexOf(' ', at + len + 46);
    if (tail !== -1) { to = tail; }

    var row = document.createElement('button');
    row.type = 'button';
    row.className = 'rp-hit';

    var num = document.createElement('span');
    num.className = 'p';
    num.textContent = page;

    var words = document.createElement('span');
    words.className = 'w';
    if (from > 0) { words.appendChild(document.createTextNode('… ')); }
    words.appendChild(document.createTextNode(flat(s.slice(from, at))));
    var ring = document.createElement('b');
    ring.textContent = flat(s.slice(at, at + len));
    words.appendChild(ring);
    words.appendChild(document.createTextNode(flat(s.slice(at + len, to))));
    if (to < s.length) { words.appendChild(document.createTextNode(' …')); }

    row.appendChild(num);
    row.appendChild(words);
    row.addEventListener('click', function () { jumpToHit(page, at, len); });
    elHits.appendChild(row);
  }

  function flat(x) { return x.replace(/\s+/g, ' '); }

  function jumpToHit(page, at, len) {
    closeSheet();
    goTo(page);
    waitDrawn(page, function () { ringWord(page, at, len); });
  }

  /** Rendering a page is async, and a jump can beat it to the same page. */
  function waitDrawn(n, fn) {
    if (drawn[n]) { fn(); return; }
    render(n);
    var tries = 0;
    var poll = setInterval(function () {
      if (drawn[n]) { clearInterval(poll); fn(); }
      else if (++tries > 60) { clearInterval(poll); }
    }, 90);
  }

  function clearMark() {
    if (mark && mark.parentNode) { mark.parentNode.removeChild(mark); }
    mark = null;
  }

  /** Roughly how wide one letter is next to its neighbours, in the same font. */
  function weight(ch) {
    if ('mwMW@%&'.indexOf(ch) !== -1) { return 1.7; }
    if ('iljtfryI!.,:;|()[]{}\'`- '.indexOf(ch) !== -1) { return 0.5; }
    return 1;
  }

  function advance(str, from, to) {
    var sum = 0;
    for (var i = from; i < to; i++) { sum += weight(str.charAt(i)); }
    return sum;
  }

  /** Draw a box over exactly the letters the found word was made of. */
  function ringWord(n, at, len) {
    var t = texts[n], slot = slots[n];
    if (!t || !slot) { return; }
    clearMark();
    var ids = [], seen = {};
    for (var i = at; i < at + len; i++) {
      var r = t.map[i];
      if (r >= 0 && !seen[r]) { seen[r] = 1; ids.push(r); }
    }
    if (!ids.length) { return; }
    doc.getPage(n).then(function (page) {
      var base = page.getViewport({scale: 1});
      var css = (slot.clientWidth || base.width) / base.width;
      var vp = page.getViewport({scale: css});
      var box = document.createElement('div');
      box.className = 'rp-mark';
      ids.forEach(function (idx) {
        var it = t.runs[idx];
        var tx = xform(vp.transform, it.transform);
        var fh = Math.hypot(tx[2], tx[3]) || it.height * css;
        // One run can carry a whole sentence, so the word's share of it is
        // worked out from its letters rather than boxing all of the run.
        var str = it.str, first = Math.max(0, at - t.starts[idx]);
        var last = Math.min(str.length, at + len - t.starts[idx]);
        var all = advance(str, 0, str.length);
        var pre = advance(str, 0, first);
        var cov = advance(str, first, last);
        var w = it.width * css;
        var left = tx[4], wide = w;
        if (all > 0 && cov > 0) {
          left = tx[4] + w * (pre / all);
          wide = w * (cov / all);
        }
        if (wide < 3) { wide = 3; }
        var d = document.createElement('span');
        d.style.left = (left - 1.5) + 'px';
        d.style.top = (tx[5] - fh - 1) + 'px';
        d.style.width = (wide + 3) + 'px';
        d.style.height = (fh + 3) + 'px';
        box.appendChild(d);
      });
      slot.appendChild(box);
      mark = box;
    })['catch'](function () {});
  }

  elPrev.addEventListener('click', function () { goTo(current - 1); });
  elNext.addEventListener('click', function () { goTo(current + 1); });
  elIn.addEventListener('click', function () { setZoom(zoom + 0.3); });
  elOut.addEventListener('click', function () { setZoom(zoom - 0.3); });
  document.getElementById('rpFit').addEventListener('click', function () { setZoom(1); });

  // Double tap anywhere on a page puts it back to the size it opened at. Two
  // taps close together in the same spot are the gesture; a scroll, a drag or a
  // tap on the number badge is not, so those are ignored rather than guessed at.
  var lastTap = 0, lastX = 0, lastY = 0, pinching = false;
  elDoc.addEventListener('touchend', function (e) {
    if (pinching) { lastTap = 0; return; }
    if (e.touches.length > 0 || !e.changedTouches.length) { lastTap = 0; return; }
    var t = e.changedTouches[0];
    var now = Date.now();
    var near = Math.abs(t.clientX - lastX) < 44 && Math.abs(t.clientY - lastY) < 44;
    if (lastTap && now - lastTap < 420 && near) {
      lastTap = 0;
      if (zoom !== 1) { e.preventDefault(); setZoom(1, t.clientX, t.clientY); }
      return;
    }
    lastTap = now; lastX = t.clientX; lastY = t.clientY;
  }, {passive: false});
  elDoc.addEventListener('dblclick', function (e) {
    if (e.target.closest && e.target.closest('button, a, input')) { return; }
    setZoom(1, e.clientX, e.clientY);
  });

  // The pinch is ours, not the browser's: fingers apart re-sizes the pages
  // under the spot they are spread over, and the page already drawn is
  // stretched to the new box the whole way. Nothing is re-made mid-gesture -
  // the heavier redraw waits for the lift, which is how the zoom keeps up with
  // the fingers instead of arriving after them.
  var spread0 = 0, zoom0 = 1;
  function dist(a, b) {
    var dx = a.clientX - b.clientX, dy = a.clientY - b.clientY;
    return Math.sqrt(dx * dx + dy * dy) || 1;
  }
  function midPoint(a, b) {
    return {x: (a.clientX + b.clientX) / 2, y: (a.clientY + b.clientY) / 2};
  }
  elDoc.addEventListener('touchstart', function (e) {
    if (e.touches.length === 2) {
      pinching = true; lastTap = 0;
      spread0 = dist(e.touches[0], e.touches[1]); zoom0 = zoom;
    }
  }, {passive: true});
  elDoc.addEventListener('touchmove', function (e) {
    if (!pinching || e.touches.length !== 2) { return; }
    e.preventDefault();
    var m = midPoint(e.touches[0], e.touches[1]);
    pinchTo(zoom0 * (dist(e.touches[0], e.touches[1]) / spread0), m.x, m.y);
  }, {passive: false});
  function endPinch(e) {
    if (!pinching || (e && e.touches && e.touches.length >= 2)) { return; }
    pinching = false;
    var a = e && e.changedTouches ? e.changedTouches[0] : null;
    var b = null;
    if (e && e.touches && e.touches.length === 1) { b = e.touches[0]; }
    else if (e && e.changedTouches && e.changedTouches.length >= 2) { b = e.changedTouches[1]; }
    if (a && b) { var m = midPoint(a, b); pinchTo(zoom0 * (dist(a, b) / spread0), m.x, m.y); }
    commitZoom();
  }
  elDoc.addEventListener('touchend', endPinch, {passive: true});
  elDoc.addEventListener('touchcancel', endPinch, {passive: true});
  document.getElementById('rpJump').addEventListener('click', function () { openSheet('jump'); });
  document.getElementById('rpJumpGo').addEventListener('click', tryJump);
  document.getElementById('rpFindGo').addEventListener('click', function () { runSearch(elFindIn.value); });
  document.getElementById('rpSheetX').addEventListener('click', closeSheet);
  elJumpIn.addEventListener('input', function () { elJumpIn.value = elJumpIn.value.replace(/[^0-9]/g, ''); });
  elJumpIn.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); tryJump(); } });
  elFindIn.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); runSearch(elFindIn.value); } });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && sheetOn) { closeSheet(); }
  });
  var elFindBtn = document.getElementById('rpFindBtn');
  if (elFindBtn) { elFindBtn.addEventListener('click', function () { openSheet('find'); }); }

  var ticking = false;
  window.addEventListener('scroll', function () {
    if (ticking) { return; }
    ticking = true;
    requestAnimationFrame(function () { ticking = false; track(); });
  }, {passive: true});
  var resizeTimer = 0;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
      fitWidth();
      markStale();
      track();
      sizeChrome();
    }, 220);
  });

  if (!window.pdfjsLib) { fail('The reader library did not load', 'Check your connection and open this page again.', true); return; }
  pdfjsLib.GlobalWorkerOptions.workerSrc = <?php echo json_encode(SITE_URL . '/assets/js/pdfjs/pdf.worker.min.js'); ?>;

  elCount.textContent = 'Opening';
  paint(0, 'Opening');
  loadBytes().then(function (buffer) {
    return pdfjsLib.getDocument({data: new Uint8Array(buffer), disableFontFace: false, isEvalSupported: false}).promise;
  }).then(function (pdf) {
    doc = pdf;
    pages = pdf.numPages;
    // Every page is measured before anything is drawn, so the scrollbar is the
    // right length from the first moment and a jump to page 40 lands in the spot
    // that stays put after the pictures arrive.
    var n = 1;
    var measure = function () {
      if (n > pages) { return Promise.resolve(); }
      var want = n;
      return pdf.getPage(want).then(function (page) {
        var vp = page.getViewport({scale: 1});
        sizes[want] = {width: vp.width, height: vp.height};
        page.cleanup();
        n++;
        return measure();
      });
    };
    return measure();
  }).then(function () {
    elBar.hidden = false;
    sizeChrome();
    layout();
    paintZoom();
    paint(pctOf(deepest));
    if (deepest > 1) { setTimeout(function () { goTo(deepest); }, 60); }
    track();
  }).catch(function (err) {
    if (err && err.locked) { fail('This material is locked', 'It is a paid file and this account has not bought it yet.', false); return; }
    fail('The pages would not open', 'The file may be damaged, or the connection dropped part way through.', true);
  });
})();
</script>
<?php endif; ?>
</body>
</html>
