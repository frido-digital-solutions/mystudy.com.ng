    <!-- Footer -->
    <?php if (!isset($hide_main_footer) || !$hide_main_footer): ?>
    <footer class="footer mt-auto pt-5 pb-3" style="background-color: #ffffff; border-top: 1px solid #f1f5f9; color: #475569;">
        <div class="container">
            <div class="row g-4 justify-content-between">
                <div class="col-lg-4 col-md-12 mb-4 mb-lg-0">
                    <h5 class="fw-bold mb-3 d-flex align-items-center" style="color: #10b981; font-size: 1.5rem;">
                        <i class="fas fa-graduation-cap me-2"></i> <?php echo SITE_NAME; ?>
                    </h5>
                    <p class="text-muted mb-4 pe-lg-4" style="line-height: 1.8; font-size: 0.95rem;">Empowering Nigerian students with quality educational resources, interactive quizzes, and powerful study tools to achieve academic excellence.</p>
                    <div class="social-links d-flex gap-3 flex-wrap">
                        <?php foreach (site_socials() as $network => $social): ?>
                        <a href="<?php echo htmlspecialchars($social['url']); ?>" target="_blank" rel="noopener noreferrer" class="footer-social-icon bg-light text-secondary rounded-circle d-flex align-items-center justify-content-center transition-all" aria-label="Follow us on <?php echo htmlspecialchars($social['label']); ?>"><i class="fab <?php echo $social['icon']; ?>" aria-hidden="true"></i></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-6">
                    <h6 class="fw-bold mb-4 text-dark text-uppercase" style="letter-spacing: 1px; font-size: 0.85rem;">Platform</h6>
                    <ul class="list-unstyled footer-links">
                        <li class="mb-3"><a href="<?php echo SITE_URL; ?>/" class="text-decoration-none text-muted transition-all d-inline-block">Home</a></li>
                        <li class="mb-3"><a href="<?php echo SITE_URL; ?>/about.php" class="text-decoration-none text-muted transition-all d-inline-block">About Us</a></li>
                        <li class="mb-3"><a href="<?php echo SITE_URL; ?>/contact.php" class="text-decoration-none text-muted transition-all d-inline-block">Contact Support</a></li>
                        <li class="mb-3"><a href="<?php echo SITE_URL; ?>/blog.php" class="text-decoration-none text-muted transition-all d-inline-block">Latest Blog</a></li>
                    </ul>
                </div>

                <div class="col-lg-2 col-md-4 col-6">
                    <h6 class="fw-bold mb-4 text-dark text-uppercase" style="letter-spacing: 1px; font-size: 0.85rem;">Resources</h6>
                    <ul class="list-unstyled footer-links">
                        <li class="mb-3"><a href="<?php echo SITE_URL; ?>/materials.php" class="text-decoration-none text-muted transition-all d-inline-block">Study Materials</a></li>
                        <li class="mb-3"><a href="<?php echo SITE_URL; ?>/tutorials.php" class="text-decoration-none text-muted transition-all d-inline-block">Video Tutorials</a></li>
                        <li class="mb-3"><a href="<?php echo SITE_URL; ?>/quizzes.php" class="text-decoration-none text-muted transition-all d-inline-block">Practice Quizzes</a></li>
                        <li class="mb-3"><a href="<?php echo SITE_URL; ?>/universities.php" class="text-decoration-none text-muted transition-all d-inline-block">Universities</a></li>
                    </ul>
                </div>

                <div class="col-lg-2 col-md-4 col-12 mt-4 mt-md-0">
                    <h6 class="fw-bold mb-4 text-dark text-uppercase" style="letter-spacing: 1px; font-size: 0.85rem;">Legal</h6>
                    <ul class="list-unstyled footer-links">
                        <li class="mb-3"><a href="<?php echo SITE_URL; ?>/privacy-policy.php" class="text-decoration-none text-muted transition-all d-inline-block">Privacy Policy</a></li>
                        <li class="mb-3"><a href="<?php echo SITE_URL; ?>/terms.php" class="text-decoration-none text-muted transition-all d-inline-block">Terms of Service</a></li>
                    </ul>
                </div>
            </div>
            
            <hr class="mt-5 mb-4" style="border-color: #f1f5f9; opacity: 1;">
            
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                    <p class="mb-0 text-muted small" style="font-weight: 500;">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="mb-0 text-muted small" style="font-weight: 500;">Powered by <a href="https://wa.me/2349031876172" target="_blank" class="text-decoration-none fw-bold ms-1" style="color: #10b981; transition: color 0.3s ease;">FRIDO DEV TECH</a></p>
                </div>
            </div>
        </div>
    </footer>
    <?php else: ?>
    <!-- Minimal App View Footer -->
    <?php if (!isset($hide_minimal_footer) || !$hide_minimal_footer): ?>
    <footer class="mt-auto py-3 text-center" style="background-color: transparent; border-top: 1px solid rgba(0,0,0,0.05);">
        <p class="mb-0 small fw-bold" style="color: #94a3b8; letter-spacing: 0.5px; font-size: 0.75rem;">
            &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>
        </p>
    </footer>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Back to Top Button -->
    <button id="backToTop" class="shadow-lg" style="display: none; position: fixed; bottom: 30px; right: 30px; z-index: 99; width: 45px; height: 45px; background: var(--primary, #10b981); color: white; border-radius: 50%; border: none; font-size: 18px; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center;">
        <i class="fas fa-arrow-up"></i>
    </button>

    <style>
        .footer-links a:hover {
            color: #10b981 !important;
            transform: translateX(4px);
        }
        .footer-social-icon {
            width: 40px;
            height: 40px;
            font-size: 1.1rem;
            color: #64748b !important;
            border: 1px solid #e2e8f0;
        }
        .footer-social-icon:hover {
            background-color: #10b981 !important;
            color: #ffffff !important;
            border-color: #10b981;
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }
        .transition-all { transition: all 0.3s ease; }
        #backToTop:hover { transform: translateY(-3px); background: #059669 !important; }
        
        /* Draggable FAB System */
        #quickAccessFab {
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
            cursor: grab;
            z-index: 1040;
            user-select: none;
            touch-action: none;
            transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        #quickAccessFab:active {
            cursor: grabbing;
            transform: scale(0.95);
        }

        #quickAccessFab.is-dragging {
            transition: none; /* Disable transition during drag for smoothness */
        }

        #quickAccessFab.snapped-left {
            transform: translateX(-50%) scale(0.9);
            opacity: 0.6;
        }

        #quickAccessFab.snapped-right {
            transform: translateX(50%) scale(0.9);
            opacity: 0.6;
        }

        /* Pulse Animation when static */
        #quickAccessFab:not(.is-dragging):not(.snapped-left):not(.snapped-right)::before {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            border: 2px solid #10b981;
            animation: ripple 2s infinite;
            pointer-events: none;
        }

        @keyframes ripple {
            0% { transform: scale(1); opacity: 0.8; }
            100% { transform: scale(1.4); opacity: 0; }
        }

        /* Quick Access Menu Card */
        #quickAccessMenu {
            position: fixed;
            width: 280px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            border: 1px solid rgba(255,255,255,0.5);
            z-index: 1035;
            padding: 1rem;
            opacity: 0;
            visibility: hidden;
            transform: scale(0.9) translateY(20px);
            transform-origin: bottom right;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        #quickAccessMenu.show {
            opacity: 1;
            visibility: visible;
            transform: scale(1) translateY(0);
        }

        .qa-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .qa-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            color: #1e293b;
            margin: 0;
        }

        .qa-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .qa-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 0.5rem;
            background: #f8fafc;
            border-radius: 12px;
            text-decoration: none;
            color: #475569;
            border: 1px solid transparent;
            transition: all 0.2s ease;
            gap: 0.5rem;
            text-align: center;
        }

        .qa-item:hover {
            background: #fff;
            color: #10b981;
            border-color: #10b981;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.1);
        }

        .qa-icon {
            font-size: 1.5rem;
        }
        
        .qa-label {
            font-size: 0.75rem;
            font-weight: 600;
        }
    </style>

    <!-- Draggable Quick Access FAB -->
    <div id="quickAccessFab" title="Quick Menu" role="button" aria-label="Open quick access menu" tabindex="0">
        <i class="fas fa-layer-group"></i>
    </div>

    <!-- Quick Access Menu Card -->
    <div id="quickAccessMenu">
        <div class="qa-header">
            <h6 class="qa-title"><i class="fas fa-bolt text-warning me-2"></i>Quick Access</h6>
            <button type="button" class="btn-close" aria-label="Close quick access menu" onclick="document.getElementById('quickAccessMenu').classList.remove('show');"></button>
        </div>
        <div class="qa-grid">
            <a href="<?php echo SITE_URL; ?>/quizzes.php" class="qa-item">
                <i class="fas fa-brain qa-icon text-primary"></i>
                <span class="qa-label">Quizzes</span>
            </a>
            <a href="<?php echo SITE_URL; ?>/battle_hub.php" class="qa-item">
                <i class="fas fa-khanda qa-icon text-warning"></i>
                <span class="qa-label">Battle Hub</span>
            </a>
            <a href="<?php echo SITE_URL; ?>/battle_arena.php" class="qa-item">
                <i class="fas fa-trophy qa-icon text-danger"></i>
                <span class="qa-label">Arena</span>
            </a>
            <a href="<?php echo SITE_URL; ?>/discussions.php" class="qa-item">
                <i class="fas fa-comments qa-icon text-success"></i>
                <span class="qa-label">Community</span>
            </a>
        </div>
        <div class="mt-3 pt-2 text-center" style="border-top: 1px solid rgba(0,0,0,0.05);">
            <button type="button" class="btn btn-sm text-muted fw-bold w-100" onclick="confirmHideQuickAccess()" style="font-size: 0.75rem; background: none; border: none;">
                <i class="fas fa-eye-slash me-1"></i> Hide Quick Actions
            </button>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper (deferred) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    
    <!-- SweetAlert2 (loaded during idle time - doesn't block page render but ready before user needs it) -->
    <script>
    // Load SweetAlert2 once the page is idle (not render-blocking, but available before user clicks)
    function loadSwal(){
        if(window.Swal && window.Swal.fire) return; // already loaded
        var s=document.createElement('script');
        s.src='https://cdn.jsdelivr.net/npm/sweetalert2@11';
        document.head.appendChild(s);
    }
    if('requestIdleCallback' in window){
        requestIdleCallback(loadSwal);
    } else {
        setTimeout(loadSwal, 1500); // Fallback: load after 1.5s
    }
    </script>

    <!-- MyStudy Shared Components (deferred, minified, proper cache-busting) -->
    <?php
        $jsMinPath = ROOT_PATH . '/assets/js/main.min.js';
        $jsPath = ROOT_PATH . '/assets/js/main.js';
        if (file_exists($jsMinPath)) {
            $jsFile = '/assets/js/main.min.js';
            $jsVersion = filemtime($jsMinPath);
        } else {
            $jsFile = '/assets/js/main.js';
            $jsVersion = file_exists($jsPath) ? filemtime($jsPath) : '1';
        }
    ?>
    <script src="<?php echo SITE_URL . $jsFile; ?>?v=<?php echo $jsVersion; ?>" defer></script>

    <!-- Instant Prefetch: preload pages on hover/touch for instant navigation -->
    <script>
    (function(){
        var defined={};
        var p=null;
        function prefetch(u){
            if(defined[u])return;
            defined[u]=true;
            p=document.createElement('link');
            p.rel='prefetch';
            p.href=u;
            document.head.appendChild(p);
        }
        // Desktop: prefetch on mouseover (65ms delay to avoid false triggers)
        var t;
        document.addEventListener('mouseover',function(e){
            var a=e.target.closest('a');
            if(!a||!a.href||a.href.indexOf(location.origin)!==0||a.getAttribute('target')==='_blank')return;
            clearTimeout(t);
            t=setTimeout(function(){prefetch(a.href);},65);
        },true);
        // Mobile: prefetch on touchstart (user has committed to tapping)
        document.addEventListener('touchstart',function(e){
            var a=e.target.closest('a');
            if(!a||!a.href||a.href.indexOf(location.origin)!==0||a.getAttribute('target')==='_blank')return;
            prefetch(a.href);
        },{passive:true});
    })();
    </script>

    <!-- =====================================================
         PWA Integration: Service Worker, Prefetch, Transitions, Install
         ===================================================== -->
    <style>
        /* Page transition animation */
        .pwa-page-transition { animation: pwaFadeIn 0.25s ease-out; }
        @keyframes pwaFadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
        .pwa-fade-out { opacity: 0.3; transition: opacity 0.2s ease-out; }
        
        /* Loading Spinner for fast feedback */
        .pwa-spinner-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px);
            z-index: 99999; display: flex; justify-content: center; align-items: center;
            opacity: 0; pointer-events: none; transition: opacity 0.15s ease;
        }
        .pwa-spinner-overlay.show { opacity: 1; pointer-events: auto; }
        .pwa-spinner {
            width: 42px; height: 42px;
            border: 4px solid #e2e8f0; border-top-color: #10b981;
            border-radius: 50%;
            animation: pwa-spin 0.8s linear infinite;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }
        @keyframes pwa-spin { to { transform: rotate(360deg); } }

        /* PWA Install Banner */
        .pwa-install-banner {
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 1060;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #f8fafc; padding: 1rem 1.25rem;
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.15);
            transform: translateY(100%); transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            border-top: 2px solid #10b981;
        }
        .pwa-install-banner.show { transform: translateY(0); }
        .pwa-install-banner .pwa-text { flex: 1; }
        .pwa-install-banner .pwa-title { font-weight: 700; font-size: 0.95rem; margin-bottom: 0.15rem; display: flex; align-items: center; gap: 0.5rem; }
        .pwa-install-banner .pwa-subtitle { font-size: 0.8rem; color: #94a3b8; }
        .pwa-install-btn { background: #10b981; color: #fff; border: none; padding: 0.6rem 1.25rem; border-radius: 50px; font-weight: 700; font-size: 0.85rem; cursor: pointer; white-space: nowrap; transition: all 0.2s; }
        .pwa-install-btn:hover { background: #059669; transform: translateY(-1px); }
        .pwa-dismiss-btn { background: none; border: none; color: #64748b; font-size: 1.25rem; cursor: pointer; padding: 0.25rem; line-height: 1; }
        .pwa-dismiss-btn:hover { color: #f8fafc; }
    </style>

    <!-- Global Loading Spinner Overlay -->
    <div class="pwa-spinner-overlay" id="pwaGlobalSpinner"><div class="pwa-spinner"></div></div>

    <!-- Install Banner HTML -->
    <div id="pwaInstallBanner" class="pwa-install-banner" style="display:none;">
        <div class="pwa-text">
            <div class="pwa-title"><i class="fas fa-mobile-alt"></i> Install MyStudy App</div>
            <div class="pwa-subtitle">Fast access, works offline — add to home screen</div>
        </div>
        <button class="pwa-install-btn" id="pwaInstallBtn">Install</button>
        <button class="pwa-dismiss-btn" id="pwaDismissBtn" aria-label="Dismiss">&times;</button>
    </div>

    <script>
    (function() {
        'use strict';

        // =========================================================
        // 1. SERVICE WORKER REGISTRATION
        // =========================================================
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('<?php echo SITE_URL; ?>/sw.js')
                    .then(function(reg) {
                        // Check for updates every 30 minutes
                        setInterval(function() { reg.update(); }, 30 * 60 * 1000);
                    })
                    .catch(function() {});
            });
        }

        // =========================================================
        // 2. LINK PREFETCH ON HOVER — Makes navigation feel instant
        //    When user hovers a link for 65ms, prefetch it in background
        // =========================================================
        var prefetched = {};
        var hoverTimer = null;

        document.addEventListener('pointerenter', function(e) {
            var link = e.target.closest('a[href]');
            if (!link) return;

            var href = link.getAttribute('href');
            // Only prefetch internal page links
            if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;
            if (href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0) return;
            if (link.hasAttribute('target') && link.getAttribute('target') === '_blank') return;
            if (link.hasAttribute('download')) return;

            // Check if it's an internal link
            try {
                var linkUrl = new URL(href, window.location.origin);
                if (linkUrl.origin !== window.location.origin) return;
                href = linkUrl.href;
            } catch(ex) { return; }

            // Don't prefetch if already done
            if (prefetched[href]) return;

            // Wait 65ms to confirm intentional hover (not just mouse passing over)
            clearTimeout(hoverTimer);
            hoverTimer = setTimeout(function() {
                prefetched[href] = true;
                var prefetchLink = document.createElement('link');
                prefetchLink.rel = 'prefetch';
                prefetchLink.href = href;
                prefetchLink.as = 'document';
                document.head.appendChild(prefetchLink);
            }, 65);

        }, true);

        document.addEventListener('pointerleave', function(e) {
            if (e.target.closest('a[href]')) {
                clearTimeout(hoverTimer);
            }
        }, true);

        // =========================================================
        // 3. SMOOTH PAGE TRANSITIONS — Fade effect on navigation
        // =========================================================
        var mainContent = document.querySelector('.main-content') || document.querySelector('main') || document.body;
        
        // Fade in on current page load
        if (mainContent) {
            mainContent.classList.add('pwa-page-transition');
        }

        // Fade out before navigating to a new page
        document.addEventListener('click', function(e) {
            var link = e.target.closest('a[href]');
            if (!link) return;

            var href = link.getAttribute('href');
            if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;
            if (link.hasAttribute('target')) return;
            if (link.hasAttribute('download')) return;
            if (e.ctrlKey || e.metaKey || e.shiftKey) return; // Allow open in new tab

            // Check if internal
            try {
                var linkUrl = new URL(href, window.location.origin);
                if (linkUrl.origin !== window.location.origin) return;
            } catch(ex) { return; }

            // Show global spinner overlay and dim main content
            e.preventDefault();
            var spinnerDiv = document.getElementById('pwaGlobalSpinner');
            if (spinnerDiv) spinnerDiv.classList.add('show');
            if (mainContent) mainContent.classList.add('pwa-fade-out');
            
            var destination = href;
            // Short delay so spinner renders instantly before browser locks up navigating
            setTimeout(function() {
                window.location.href = destination;
            }, 50); 
        });

        // =========================================================
        // 4. PWA INSTALL PROMPT
        // =========================================================
        var deferredPrompt = null;
        var banner = document.getElementById('pwaInstallBanner');
        var installBtn = document.getElementById('pwaInstallBtn');
        var dismissBtn = document.getElementById('pwaDismissBtn');

        // Don't show if user dismissed within last 7 days
        var dismissed = localStorage.getItem('pwa_install_dismissed');
        var canShow = !dismissed || (Date.now() - parseInt(dismissed, 10) > 7 * 24 * 60 * 60 * 1000);

        window.addEventListener('beforeinstallprompt', function(e) {
            e.preventDefault();
            deferredPrompt = e;

            if (canShow && banner) {
                banner.style.display = '';
                // Trigger animation after a small delay
                setTimeout(function() { banner.classList.add('show'); }, 100);
            }
        });

        if (installBtn) {
            installBtn.addEventListener('click', function() {
                if (!deferredPrompt) return;
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function(result) {
                    deferredPrompt = null;
                    if (banner) {
                        banner.classList.remove('show');
                        setTimeout(function() { banner.style.display = 'none'; }, 400);
                    }
                });
            });
        }

        if (dismissBtn) {
            dismissBtn.addEventListener('click', function() {
                localStorage.setItem('pwa_install_dismissed', Date.now().toString());
                if (banner) {
                    banner.classList.remove('show');
                    setTimeout(function() { banner.style.display = 'none'; }, 400);
                }
            });
        }

        // If already installed as PWA, hide banner
        if (window.matchMedia('(display-mode: standalone)').matches) {
            canShow = false;
        }

    })();
    </script>

    <?php 
    // Output any additional scripts that might be set in the page
    if (isset($page_scripts)) {
        echo $page_scripts;
    }
    ?>

    <?php if (is_logged_in() && !isset($_SESSION['greeting_shown'])): ?>
    <?php 
        // Flag to only show once per session
        $_SESSION['greeting_shown'] = true;
        $hour = (int)date('H');
        $greeting = ($hour < 12) ? 'Good morning' : (($hour < 18) ? 'Good afternoon' : 'Good evening');
        $name = htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'Scholar')[0]);
    ?>
    <div id="welcomeToast" style="
        position: fixed; 
        top: 80px; 
        right: 20px; 
        background: #fff; 
        border-radius: 12px; 
        box-shadow: 0 10px 40px rgba(0,0,0,0.15); 
        padding: 1rem 1.25rem; 
        display: flex; 
        align-items: center; 
        gap: 12px; 
        z-index: 1050; 
        border-left: 5px solid #10b981; 
        transform: translateX(120%); 
        opacity: 0; 
        transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    ">
        <div style="font-size: 1.5rem;">👋</div>
        <div>
            <h6 style="margin: 0; font-weight: 700; color: #1e293b;"><?php echo $greeting; ?>, <?php echo $name; ?>!</h6>
            <p style="margin: 0; font-size: 0.8rem; color: #64748b;">Ready to continue your learning journey?</p>
        </div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const toast = document.getElementById('welcomeToast');
            if(toast) {
                // Slide in
                setTimeout(() => {
                    toast.style.transform = 'translateX(0)';
                    toast.style.opacity = '1';
                }, 500);
                // Slide out and remove
                setTimeout(() => {
                    toast.style.transform = 'translateX(120%)';
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 500);
                }, 4500); // Disappears after 4 seconds
            }
        });
    </script>
    <?php endif; ?>

    <!-- Quick Actions Toggle Logic -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var userId = '<?php echo $_SESSION['user_id'] ?? 0; ?>';
            if (userId !== '0' && localStorage.getItem('hideQuickAccess_user_' + userId) === '1') {
                var fab = document.getElementById('quickAccessFab');
                if (fab) {
                    fab.style.display = 'none';
                }
            }
        });

        function confirmHideQuickAccess() {
            document.getElementById('quickAccessMenu').classList.remove('show');
            var userId = '<?php echo $_SESSION['user_id'] ?? 0; ?>';
            
            if (window.Swal && window.Swal.fire) {
                Swal.fire({
                    title: 'Hide Quick Actions?',
                    html: '<p style="font-size: 0.95rem; color: #475569;">This floating button gives you fast access to features. You can always turn it back on from your <strong>Profile Settings</strong>.</p>',
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Yes, hide it',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        localStorage.setItem('hideQuickAccess_user_' + userId, '1');
                        var fab = document.getElementById('quickAccessFab');
                        if (fab) fab.style.display = 'none';
                        Swal.fire({
                            icon: 'success',
                            title: 'Hidden!',
                            text: 'You can re-enable this in your Settings.',
                            confirmButtonColor: '#10b981'
                        });
                    }
                });
            } else {
                if (confirm('Hide Quick Actions? This floating button gives you fast access. You can turn it back on from Profile Settings.')) {
                    localStorage.setItem('hideQuickAccess_user_' + userId, '1');
                    var fab = document.getElementById('quickAccessFab');
                    if (fab) fab.style.display = 'none';
                }
            }
        }
    </script>

<style>
    #msbToastStack {
        position: fixed;
        top: 76px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 100000;
        display: flex;
        flex-direction: column;
        gap: 10px;
        width: min(92vw, 420px);
        pointer-events: none;
    }
    .msb-toast {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 13px 15px;
        border-radius: 12px;
        color: #fff;
        font-size: 0.95rem;
        line-height: 1.45;
        font-weight: 500;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.22);
        pointer-events: auto;
        opacity: 0;
        transform: translateY(-14px);
        transition: opacity .22s ease, transform .22s ease;
    }
    .msb-toast.is-visible { opacity: 1; transform: translateY(0); }
    .msb-toast.is-leaving { opacity: 0; transform: translateY(-14px); }
    .msb-toast .msb-toast-icon { flex: 0 0 auto; font-size: 1.05rem; margin-top: 1px; }
    .msb-toast .msb-toast-text { flex: 1 1 auto; word-break: break-word; }
    .msb-toast .msb-toast-close {
        flex: 0 0 auto;
        background: rgba(255,255,255,.18);
        border: 0;
        color: #fff;
        width: 24px; height: 24px;
        border-radius: 50%;
        font-size: .8rem;
        line-height: 1;
        cursor: pointer;
    }
    .msb-toast-type-success { background: linear-gradient(135deg, #10b981, #059669); }
    .msb-toast-type-error   { background: linear-gradient(135deg, #ef4444, #dc2626); }
    .msb-toast-type-info    { background: linear-gradient(135deg, #3b82f6, #2563eb); }
    @media (min-width: 768px) {
        #msbToastStack { top: 88px; left: auto; right: 22px; transform: none; width: 380px; }
    }
    @media (max-width: 400px) {
        #msbToastStack { top: 66px; }
    }
</style>
<div id="msbToastStack" role="status" aria-live="polite"></div>
<script>
window.MSBToast = (function () {
    var icons = { success: 'fa-circle-check', error: 'fa-circle-exclamation', info: 'fa-circle-info' };
    var hold  = { success: 4500, error: 8000, info: 6000 };
    function dismiss(el) {
        if (!el || el.dataset.leaving === '1') return;
        el.dataset.leaving = '1';
        el.classList.add('is-leaving');
        setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 240);
    }
    function show(message, type) {
        var stack = document.getElementById('msbToastStack');
        if (!stack || !message) return;
        type = (type === 'error' || type === 'info') ? type : 'success';
        var el = document.createElement('div');
        el.className = 'msb-toast msb-toast-type-' + type;
        var icon = document.createElement('i');
        icon.className = 'fas ' + (icons[type] || icons.success) + ' msb-toast-icon';
        var text = document.createElement('span');
        text.className = 'msb-toast-text';
        text.textContent = String(message); // textContent, never innerHTML
        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'msb-toast-close';
        close.setAttribute('aria-label', 'Dismiss');
        close.innerHTML = '<i class="fas fa-times"></i>';
        close.addEventListener('click', function () { dismiss(el); });
        el.appendChild(icon); el.appendChild(text); el.appendChild(close);
        stack.appendChild(el);
        requestAnimationFrame(function () { el.classList.add('is-visible'); });
        setTimeout(function () { dismiss(el); }, hold[type]);
        return el;
    }
    return { show: show };
})();
</script>

<?php
/**
 * Global action confirmation.
 *
 * Every page can report a result two ways: a flash message set before a redirect,
 * or a query parameter on the page it redirects to. Both are read here so no page
 * has to remember to render them, and the toast is position:fixed so it lands in
 * view without the user scrolling.
 *
 * Pages that already echo display_flash_message() consume the flash first, so the
 * message is never shown twice.
 */
$msbToast = null;
if (isset($_SESSION['flash']) && is_array($_SESSION['flash']) && trim((string)($_SESSION['flash']['message'] ?? '')) !== '') {
    $flashType = $_SESSION['flash']['type'] ?? 'info';
    $msbToast = [
        'message' => (string)$_SESSION['flash']['message'],
        'type' => in_array($flashType, ['success', 'error', 'warning'], true) ? ($flashType === 'warning' ? 'error' : $flashType) : 'info',
    ];
    unset($_SESSION['flash']);
} else {
    // Pages report results through redirect_with_notice(), which writes msg/msg_type.
    // battle_invite.php is excluded: it shows its ?error= as a centred modal instead.
    $paramTypes = [
        'msg' => ($_GET['msg_type'] ?? '') === 'error' ? 'error' : 'success',
    ];
    foreach ($paramTypes as $param => $type) {
        $value = trim((string)($_GET[$param] ?? ''));
        if ($value !== '') {
            $msbToast = ['message' => $value, 'type' => $type];
            break;
        }
    }
}
?>
<?php if ($msbToast): ?>
<script>
(function () {
    var m = <?php echo json_encode($msbToast['message'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var t = <?php echo json_encode($msbToast['type']); ?>;
    if (window.MSBToast) { window.MSBToast.show(m, t); }
    // Drop the message params so a refresh or a shared link does not repeat it.
    if (window.history && history.replaceState) {
        var url = new URL(location.href);
        ['msg', 'msg_type', 'success', 'error', 'battle_msg', 'toast'].forEach(function (k) { url.searchParams.delete(k); });
        history.replaceState(null, document.title, url.pathname + url.search + url.hash);
    }
})();
</script>
<?php endif; ?>

</body>
</html>
<?php 
// End output buffering and flush the buffer
ob_end_flush(); 
?>
