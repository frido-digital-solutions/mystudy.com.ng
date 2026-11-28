<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/Material.php';
require_once __DIR__ . '/includes/materials_paid.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$materialModel = new Material($pdo);
$material = $id ? $materialModel->getById($id) : null;

if (!$material || ($material['status'] ?? '') !== 'approved') {
    set_flash_message('error', 'Material not found.');
    header('Location: ' . SITE_URL . '/materials.php');
    exit;
}

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$isPaid = material_is_paid($material);

// A payment that landed while this tab was asleep is applied before we draw the page.
if ($isPaid && $userId > 0) {
    material_verify_open_payments($pdo, $userId, $material);
}
$mayAccess = material_can_access($pdo, $userId > 0 ? $userId : null, $material);

$justPaid = isset($_GET['paid']) && $_GET['paid'] === '1';
$awaitingConfirm = isset($_GET['pending']) && $_GET['pending'] === '1';

$gateUrl = SITE_URL . '/material_download.php?id=' . (int)$material['id'];
$previewUrl = $gateUrl . '&inline=1';
$downloadName = material_download_name($material);
$mime = strtolower((string)($material['mime_type'] ?? ''));
$isPdf = strpos($mime, 'application/pdf') !== false;
$isImage = strpos($mime, 'image/') === 0;

$buyerEmail = '';
if ($userId > 0) {
    $stmt = $pdo->prepare('SELECT email FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    $buyerEmail = (string)$stmt->fetchColumn();
}

$page_title = htmlspecialchars($material['title']);
include __DIR__ . '/includes/header.php';
?>
<style>
.pm-wrap{max-width:1120px}
.pm-card{background:var(--bg-white,#fff);border:1px solid var(--border,#e2e8f0);border-radius:var(--radius-lg,16px);box-shadow:var(--shadow-sm);padding:1.35rem}
.pm-head{display:flex;gap:1rem;align-items:flex-start;justify-content:space-between;flex-wrap:wrap}
.pm-head-main{min-width:0;flex:1 1 320px}
.pm-title{font-size:1.4rem;font-weight:700;line-height:1.25;margin:0 0 .5rem;color:var(--text-dark,#1e293b);letter-spacing:-.01em}
.pm-meta{display:flex;flex-wrap:wrap;gap:.35rem .9rem;font-size:.83rem;color:var(--text-muted,#64748b)}
.pm-meta i{margin-right:.3rem;opacity:.75}
.pm-price-tag{flex:0 0 auto;padding:.45rem .9rem;border-radius:var(--radius-pill,50px);font-weight:700;font-size:.95rem;background:var(--primary,#10b981);color:#fff;white-space:nowrap}
.pm-price-free{background:#eef2ff;color:#4338ca}
.pm-price-owned{background:#ecfdf5;color:#047857;border:1px solid #a7f3d0}
.pm-desc{margin:1rem 0 0;color:#475569;line-height:1.7;font-size:.95rem}
.pm-viewer{margin-top:1.1rem;border:1px solid var(--border,#e2e8f0);border-radius:var(--radius-md,12px);overflow:hidden;background:#f8fafc}
.pm-pdf{display:block;width:100%;height:70vh;border:0}
.pm-img{display:block;width:100%;height:auto}
.pm-nopreview{padding:2.5rem 1rem;text-align:center;color:var(--text-muted,#64748b)}
.pm-nopreview i{font-size:2rem;opacity:.5;display:block;margin-bottom:.6rem}
.pm-actions{display:flex;flex-wrap:wrap;gap:.6rem;margin-top:1.1rem}
.pm-btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;padding:.72rem 1.25rem;border-radius:var(--radius-pill,50px);font-weight:600;font-size:.94rem;text-decoration:none;border:1px solid transparent;cursor:pointer;transition:transform .12s ease,box-shadow .12s ease,background .12s ease;line-height:1.2}
.pm-btn:active{transform:translateY(1px)}
.pm-btn-primary{background:var(--primary,#10b981);color:#fff;box-shadow:0 6px 16px rgba(16,185,129,.24)}
.pm-btn-primary:hover{background:var(--primary-dark,#059669);color:#fff}
.pm-btn-ghost{background:#fff;color:var(--text-dark,#1e293b);border-color:var(--border,#e2e8f0)}
.pm-btn-ghost:hover{background:#f8fafc;color:var(--text-dark,#1e293b)}
.pm-btn-block{width:100%}
.pm-btn:disabled{opacity:.65;cursor:progress}
.pm-lock{margin-top:1.15rem;border:1px dashed #cbd5e1;border-radius:var(--radius-md,12px);background:linear-gradient(180deg,#f8fafc,#fff);padding:1.5rem 1.25rem;text-align:center}
.pm-lock-mark{width:52px;height:52px;margin:0 auto .8rem;border-radius:50%;background:#fff;border:1px solid var(--border,#e2e8f0);display:flex;align-items:center;justify-content:center;color:var(--accent,#f59e0b);font-size:1.15rem;box-shadow:var(--shadow-sm)}
.pm-lock-title{font-size:1.08rem;font-weight:700;margin:0 0 .35rem;color:var(--text-dark,#1e293b)}
.pm-lock-note{margin:0 auto;max-width:44ch;color:#475569;font-size:.92rem;line-height:1.65}
.pm-points{list-style:none;margin:1.1rem auto 1.25rem;padding:0;max-width:34ch;text-align:left;font-size:.9rem;color:#334155}
.pm-points li{padding:.3rem 0;display:flex;gap:.55rem;align-items:flex-start}
.pm-points i{color:var(--primary,#10b981);margin-top:.15rem}
.pm-buy{max-width:520px;margin:0 auto;text-align:left}
.pm-label{display:block;font-size:.8rem;font-weight:600;color:var(--text-muted,#64748b);margin-bottom:.35rem}
.pm-buy-row{display:flex;gap:.55rem;align-items:stretch}
.pm-input{flex:1 1 auto;min-width:0;padding:.7rem .9rem;border:1px solid var(--border,#e2e8f0);border-radius:var(--radius-md,12px);font-size:.95rem;color:var(--text-dark,#1e293b);background:#fff}
.pm-input:focus{outline:2px solid rgba(16,185,129,.35);border-color:var(--primary,#10b981)}
.pm-btn-pay{flex:0 0 auto;background:var(--primary,#10b981);color:#fff;box-shadow:0 8px 20px rgba(16,185,129,.26);white-space:nowrap}
.pm-btn-pay:hover{background:var(--primary-dark,#059669);color:#fff}
.pm-fine{margin:.7rem 0 0;font-size:.78rem;color:var(--text-light,#94a3b8);display:flex;gap:.4rem;align-items:center;justify-content:center;text-align:center}
.pm-error{margin:.7rem 0 0;font-size:.86rem;color:var(--danger,#dc2626);background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:.55rem .7rem}
.pm-side{position:relative}
.pm-side-title{font-size:.82rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted,#64748b);margin:0 0 .8rem;font-weight:700}
.pm-dl{margin:0;font-size:.9rem}
.pm-dl>div{display:flex;justify-content:space-between;gap:1rem;padding:.5rem 0;border-bottom:1px solid var(--border-light,#f1f5f9)}
.pm-dl dt{color:var(--text-muted,#64748b);font-weight:500}
.pm-dl dd{margin:0;font-weight:600;color:var(--text-dark,#1e293b);text-align:right}
.pm-dd-price{color:var(--primary-dark,#059669)}
.pm-assure{margin-top:1rem;padding:.8rem .9rem;background:#f8fafc;border:1px solid var(--border-light,#f1f5f9);border-radius:var(--radius-md,12px);font-size:.84rem;color:#475569;line-height:1.6}
.pm-assure a{font-weight:600}
.pm-banner{display:flex;gap:.75rem;align-items:flex-start;padding:.9rem 1.05rem;border-radius:var(--radius-md,12px);margin-bottom:1.1rem;font-size:.92rem;line-height:1.6}
.pm-banner-icon{flex:0 0 auto;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.75rem;color:#fff}
.pm-banner-ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}
.pm-banner-ok .pm-banner-icon{background:#059669}
.pm-banner-wait{background:#fffbeb;border:1px solid #fde68a;color:#92400e}
.pm-banner-wait .pm-banner-icon{background:#d97706}
@media (max-width:575.98px){
 .pm-card{padding:1.05rem}
 .pm-title{font-size:1.2rem}
 .pm-price-tag{width:100%;text-align:center}
 .pm-buy-row{flex-direction:column}
 .pm-btn-pay{width:100%}
 .pm-pdf{height:58vh}
 .pm-actions .pm-btn{width:100%}
}
</style>

<div class="container pm-wrap py-4">
    <?php if ($justPaid): ?>
        <div class="pm-banner pm-banner-ok" role="status">
            <span class="pm-banner-icon"><i class="fas fa-check"></i></span>
            <div>
                <strong>Payment confirmed.</strong>
                This material is unlocked on your account for good, and a copy is on its way to your email.
            </div>
        </div>
    <?php elseif ($awaitingConfirm): ?>
        <div class="pm-banner pm-banner-wait" role="status">
            <span class="pm-banner-icon"><i class="fas fa-hourglass-half"></i></span>
            <div>
                <strong>Confirming your payment with Paystack&hellip;</strong>
                Nothing is lost if this page closes — it unlocks the moment Paystack confirms, and we email you then.
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <article class="pm-card">
                <header class="pm-head">
                    <div class="pm-head-main">
                        <h1 class="pm-title"><?php echo htmlspecialchars($material['title']); ?></h1>
                        <div class="pm-meta">
                            <span><i class="far fa-folder-open"></i> <?php echo htmlspecialchars($material['category_name'] ?? 'Uncategorized'); ?></span>
                            <span><i class="far fa-calendar"></i> <?php echo htmlspecialchars(date('M j, Y', strtotime($material['created_at']))); ?></span>
                            <span><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($material['level'] ?? 'Secondary'); ?></span>
                            <?php if (!empty($material['secondary_board']) && $material['secondary_board'] !== 'General'): ?>
                                <span><i class="fas fa-school"></i> <?php echo htmlspecialchars($material['secondary_board']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="pm-price-tag<?php echo $isPaid ? ($mayAccess ? ' pm-price-owned' : '') : ' pm-price-free'; ?>">
                        <?php if (!$isPaid): ?>
                            Free
                        <?php elseif ($mayAccess): ?>
                            <i class="fas fa-unlock"></i> Unlocked
                        <?php else: ?>
                            &#8358;<?php echo number_format((float)$material['price'], 0); ?>
                        <?php endif; ?>
                    </div>
                </header>

                <?php if (!empty($material['description'])): ?>
                    <p class="pm-desc"><?php echo nl2br(htmlspecialchars($material['description'])); ?></p>
                <?php endif; ?>

                <?php if ($mayAccess): ?>
                    <div class="pm-viewer">
                        <?php if ($isPdf): ?>
                            <embed src="<?php echo htmlspecialchars($previewUrl); ?>" type="application/pdf" class="pm-pdf">
                        <?php elseif ($isImage): ?>
                            <img src="<?php echo htmlspecialchars($previewUrl); ?>" alt="<?php echo htmlspecialchars($material['title']); ?>" class="pm-img">
                        <?php else: ?>
                            <div class="pm-nopreview">
                                <i class="far fa-file-alt"></i>
                                <p>Documents open in their own app. Use the download button below.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="pm-actions">
                        <a class="pm-btn pm-btn-primary" href="<?php echo htmlspecialchars($gateUrl); ?>" download="<?php echo htmlspecialchars($downloadName); ?>">
                            <i class="fas fa-download"></i> Download material
                        </a>
                        <a class="pm-btn pm-btn-ghost" href="<?php echo SITE_URL; ?>/materials.php">
                            <i class="fas fa-arrow-left"></i> Back to materials
                        </a>
                    </div>
                <?php else: ?>
                    <div class="pm-lock">
                        <div class="pm-lock-mark"><i class="fas fa-lock"></i></div>
                        <h2 class="pm-lock-title">Protected material</h2>
                        <p class="pm-lock-note">
                            Pay once and it is yours — the download opens instantly and stays unlocked
                            on your account for as long as you have it.
                        </p>

                        <ul class="pm-points">
                            <li><i class="fas fa-bolt"></i> Instant access the moment payment confirms</li>
                            <li><i class="fas fa-envelope-open-text"></i> A copy sent to your email as well</li>
                            <li><i class="fas fa-infinity"></i> Re-download any time, on any device</li>
                        </ul>

                        <?php if ($userId > 0): ?>
                            <div class="pm-buy">
                                <label class="pm-label" for="pmEmail">Send the copy to</label>
                                <div class="pm-buy-row">
                                    <input type="email" id="pmEmail" class="pm-input" value="<?php echo htmlspecialchars($buyerEmail); ?>"
                                           placeholder="your@email.com" autocomplete="email" enterkeyhint="go">
                                    <button type="button" class="pm-btn pm-btn-pay" id="pmPayBtn"
                                            data-material="<?php echo (int)$material['id']; ?>">
                                        <i class="fas fa-lock-open"></i>
                                        <span>Pay &#8358;<?php echo number_format((float)$material['price'], 0); ?> &amp; Download</span>
                                    </button>
                                </div>
                                <p class="pm-fine">
                                    <i class="fab fa-paypal fa-xs"></i>
                                    Secured by Paystack — card, transfer or USSD. Your account is debited once.
                                </p>
                                <p class="pm-error" id="pmError" hidden></p>
                            </div>
                        <?php else: ?>
                            <div class="pm-buy">
                                <a class="pm-btn pm-btn-pay pm-btn-block" href="<?php echo htmlspecialchars(msb_auth_url('login')); ?>">
                                    <i class="fas fa-right-to-bracket"></i>
                                    <span>Log in to unlock — &#8358;<?php echo number_format((float)$material['price'], 0); ?></span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="pm-actions">
                        <a class="pm-btn pm-btn-ghost" href="<?php echo SITE_URL; ?>/materials.php">
                            <i class="fas fa-arrow-left"></i> Back to materials
                        </a>
                    </div>
                <?php endif; ?>
            </article>
        </div>

        <div class="col-lg-4">
            <aside class="pm-card pm-side">
                <h3 class="pm-side-title">Details</h3>
                <dl class="pm-dl">
                    <div><dt>Format</dt><dd><?php echo htmlspecialchars(strtoupper(pathinfo((string)$material['file_path'], PATHINFO_EXTENSION)) ?: 'File'); ?></dd></div>
                    <div><dt>Size</dt><dd><?php echo isset($material['file_size']) && $material['file_size'] ? number_format($material['file_size'] / 1024, 1) . ' KB' : 'Not listed'; ?></dd></div>
                    <div><dt>Level</dt><dd><?php echo htmlspecialchars($material['level'] ?? 'Secondary'); ?></dd></div>
                    <div><dt>Downloads</dt><dd><?php echo number_format((int)($material['download_count'] ?? 0)); ?></dd></div>
                    <?php if ($isPaid): ?>
                        <div><dt>Price</dt><dd class="pm-dd-price">&#8358;<?php echo number_format((float)$material['price'], 0); ?> · one time</dd></div>
                    <?php endif; ?>
                </dl>
                <div class="pm-assure">
                    <strong>Problem with a download?</strong>
                    <a href="<?php echo SITE_URL; ?>/contact.php">Tell us the material name</a> and we will put it right.
                </div>
            </aside>
        </div>
    </div>
</div>

<?php if ($mayAccess === false && $userId > 0): ?>
<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
(function () {
    var btn = document.getElementById('pmPayBtn');
    if (!btn) return;
    var box = document.getElementById('pmError');
    var emailField = document.getElementById('pmEmail');

    function fail(message) {
        btn.disabled = false;
        btn.querySelector('span').textContent = btn.dataset.label || 'Try again';
        if (box) { box.textContent = message; box.hidden = false; }
    }

    btn.addEventListener('click', function () {
        box && (box.hidden = true);
        btn.dataset.label = btn.querySelector('span').textContent;
        btn.disabled = true;
        btn.querySelector('span').textContent = 'Starting payment\u2026';

        var body = new URLSearchParams();
        body.set('material_id', btn.dataset.material);
        body.set('email', emailField ? emailField.value.trim() : '');
        body.set('csrf_token', <?php echo json_encode(csrf_token()); ?>);

        fetch('<?php echo SITE_URL; ?>/material_checkout.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (data.already_owned) { window.location.reload(); return; }
            if (data.error) { fail(data.error); return; }
            if (!window.PaystackPop) { fail('Payment window blocked by your browser. Please disable the ad-blocker and try again.'); return; }

            PaystackPop.setup({
                key: data.key,
                email: data.email,
                amount: data.amount,
                currency: 'NGN',
                ref: data.ref,
                metadata: data.metadata,
                callback: function () { window.location.href = data.redirect; }
            }).openIframe();
            btn.querySelector('span').textContent = 'Waiting for payment\u2026';
        }).catch(function () {
            fail('Network problem — nothing was charged. Please try again.');
        });
    });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
