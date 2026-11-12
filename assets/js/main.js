/**
 * MyStudy - Shared UI Components & Filter Engine
 * ================================================
 * Reusable toggle, filter, and search components used across all content pages.
 */

(function () {
    'use strict';

    // ============================================
    // LEVEL TOGGLE COMPONENT
    // ============================================
    // Converts a hidden <select name="level"> into a visual toggle.
    // Usage: Add data-level-toggle to the <select> element.
    //        The script auto-detects and builds the toggle UI.

    function initLevelToggle() {
        const selects = document.querySelectorAll('select[name="level"]');
        selects.forEach(function (sel) {
            // Skip if already processed
            if (sel.dataset.toggleInit) return;
            sel.dataset.toggleInit = '1';

            const form = sel.closest('form');
            // Allow pages/forms to opt out of the level toggle UI
            if (form && form.hasAttribute('data-disable-level-toggle')) return;
            const currentVal = sel.value;

            // Create toggle container
            const toggle = document.createElement('div');
            toggle.className = 'level-toggle';

            const options = [
                { value: '', label: 'All', icon: 'fas fa-layer-group' },
                { value: 'Secondary', label: 'Secondary', icon: 'fas fa-school' },
                { value: 'University', label: 'University', icon: 'fas fa-university' }
            ];

            options.forEach(function (opt) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'toggle-btn' + (currentVal === opt.value ? ' active' : '');
                btn.innerHTML = '<i class="' + opt.icon + '"></i> ' + opt.label;
                btn.addEventListener('click', function () {
                    // Update select value
                    sel.value = opt.value;

                    // Update active state
                    toggle.querySelectorAll('.toggle-btn').forEach(function (b) {
                        b.classList.remove('active');
                    });
                    btn.classList.add('active');

                    // Trigger change event for sub-filter logic
                    sel.dispatchEvent(new Event('change', { bubbles: true }));

                    // Auto-submit the form so results refresh immediately
                    if (form) {
                        setTimeout(function () { form.submit(); }, 50);
                    }
                });
                toggle.appendChild(btn);
            });

            // Hide the original select and insert toggle before it
            sel.style.display = 'none';
            const wrapper = sel.closest('.col-md-6, .col-lg-2, [class*="col-"]');
            if (wrapper) {
                // Replace the entire column with the toggle
                const label = wrapper.querySelector('.form-label');
                if (label) label.style.display = 'none';
                wrapper.insertBefore(toggle, sel);
            } else {
                sel.parentNode.insertBefore(toggle, sel);
            }
        });
    }

    // ============================================
    // SUB-FILTER VISIBILITY ENGINE
    // ============================================
    // Manages conditional show/hide of sub-filter sections
    // based on the selected level (Secondary vs University).

    function initSubFilterEngine() {
        const levelSelects = document.querySelectorAll('select[name="level"]');
        levelSelects.forEach(function (sel) {
            function updateSubFilters() {
                const val = sel.value;
                const form = sel.closest('form') || document;

                // Secondary sub-filters
                const secWrap = form.querySelector('#secBoardWrap, [data-show-level="Secondary"]');
                if (secWrap) {
                    secWrap.style.display = (val === 'Secondary') ? '' : 'none';
                }

                // University sub-filters
                const uniSelWrap = form.querySelector('#uniSelectWrap, [data-show-level="University"]');
                const uniGenWrap = form.querySelector('#uniGenWrap');
                const genCheck = form.querySelector('#isUniversityGeneral');

                if (uniGenWrap) {
                    uniGenWrap.style.display = (val === 'University') ? '' : 'none';
                }

                if (uniSelWrap) {
                    if (val === 'University') {
                        uniSelWrap.style.display = (genCheck && genCheck.checked) ? 'none' : '';
                    } else {
                        uniSelWrap.style.display = 'none';
                    }
                }

                // Blog university filter
                const uniFilterWrap = form.querySelector('#uniFilterWrap');
                if (uniFilterWrap) {
                    uniFilterWrap.style.display = (val === 'University') ? '' : 'none';
                }
            }

            sel.addEventListener('change', updateSubFilters);

            // Also handle the general checkbox toggle
            var genCheck = (sel.closest('form') || document).querySelector('#isUniversityGeneral');
            if (genCheck) {
                genCheck.addEventListener('change', function () {
                    var uniSelWrap = (sel.closest('form') || document).querySelector('#uniSelectWrap');
                    if (uniSelWrap) {
                        uniSelWrap.style.display = this.checked ? 'none' : '';
                    }
                });
            }
        });
    }

    // ============================================
    // LAZY VIDEO EMBED (YouTube)
    // ============================================
    // Replaces thumbnail with iframe on click.

    function initLazyVideoEmbed() {
        document.querySelectorAll('[data-yt-embed]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                var ytid = el.getAttribute('data-yt-embed');
                var thumb = el.closest('.tut-card, .tutorial-card-warm');
                if (!thumb) return;

                var wrapper = thumb.querySelector('.tut-thumb, .tutorial-card-thumb');
                if (!wrapper) return;

                wrapper.innerHTML = '<div class="tut-embed"><iframe src="https://www.youtube.com/embed/' +
                    ytid + '?autoplay=1&rel=0" allow="autoplay; encrypted-media" allowfullscreen></iframe></div>';
            });
        });
    }

    // ============================================
    // COUNTDOWN TIMERS
    // ============================================
    // Updates challenge countdown timers every second.

    function initCountdownTimers() {
        var timers = document.querySelectorAll('[data-countdown]');
        if (timers.length === 0) return;

        function updateTimers() {
            timers.forEach(function (el) {
                // Skip timers managed by inline scripts (quizzes.php uses data-start/data-end)
                if (el.hasAttribute('data-start') || el.hasAttribute('data-end')) return;

                var target = parseInt(el.getAttribute('data-countdown'), 10) * 1000;
                if (isNaN(target)) return;
                var now = Date.now();
                var diff = target - now;

                if (diff <= 0) {
                    el.innerHTML = '<span class="text-muted small">Expired</span>';
                    return;
                }

                var days = Math.floor(diff / 86400000);
                diff %= 86400000;
                var hours = Math.floor(diff / 3600000);
                diff %= 3600000;
                var mins = Math.floor(diff / 60000);
                diff %= 60000;
                var secs = Math.floor(diff / 1000);

                var html = '<div class="timer-display">';
                if (days > 0) {
                    html += '<div class="timer-block"><div class="value">' + days + '</div><div class="unit">Days</div></div>';
                    html += '<span class="timer-separator">:</span>';
                }
                html += '<div class="timer-block"><div class="value">' + String(hours).padStart(2, '0') + '</div><div class="unit">Hours</div></div>';
                html += '<span class="timer-separator">:</span>';
                html += '<div class="timer-block"><div class="value">' + String(mins).padStart(2, '0') + '</div><div class="unit">Mins</div></div>';
                html += '<span class="timer-separator">:</span>';
                html += '<div class="timer-block"><div class="value">' + String(secs).padStart(2, '0') + '</div><div class="unit">Secs</div></div>';
                html += '</div>';
                el.innerHTML = html;
            });
        }

        updateTimers();
        setInterval(updateTimers, 1000);
    }

    // ============================================
    // BACK TO TOP BUTTON
    // ============================================
    function initBackToTop() {
        var btn = document.getElementById('backToTop');
        if (!btn) return;

        window.addEventListener('scroll', function () {
            btn.style.display = window.scrollY > 300 ? 'flex' : 'none';
        });

        btn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // ============================================
    // AUTO-DISMISS FLASH ALERTS
    // ============================================
    function initFlashAlerts() {
        document.querySelectorAll('.alert-dismissible').forEach(function (alert) {
            setTimeout(function () {
                if (typeof bootstrap !== 'undefined') {
                    var bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                    bsAlert.close();
                } else {
                    alert.style.display = 'none';
                }
            }, 5000);
        });
    }

    // ============================================
    // INITIALIZE ALL COMPONENTS ON DOM READY
    // ============================================
    function initDraggableFab() {
        const fab = document.getElementById('quickAccessFab');
        const menu = document.getElementById('quickAccessMenu');
        if (!fab || !menu) return;

        let isDragging = false;
        let startX, startY, initialLeft, initialTop;
        let hasMoved = false; // differentiates drag vs click

        // Configuration
        const SNAP_THRESHOLD = 50; // pixels near edge to trigger snapping
        const DRAG_THRESHOLD = 5;  // pixels moved before considering it a drag
        const PADDING = 10;        // minimum distance from edges when free-floating

        // Start state (we use transform translate, keep base bounds)
        let cx = 0, cy = 0;
        
        // Disable native browser scrolling on the FAB to allow clean dragging
        fab.style.touchAction = 'none';

        // Persist FAB position logic
        const PATH = window.location.pathname;
        const isHomePage = PATH === '/' || PATH.endsWith('index.php') || PATH.endsWith('/mystudy.com/');

        if (isHomePage) {
            localStorage.removeItem('mystudy_fab_pos');
        } else {
            const savedPos = localStorage.getItem('mystudy_fab_pos');
            if (savedPos) {
                try {
                    const pos = JSON.parse(savedPos);
                    cx = pos.x;
                    cy = pos.y;
                    // Apply without transition immediately
                    fab.style.transition = 'none';
                    fab.style.transform = `translate(${cx}px, ${cy}px)`;
                    if (pos.snap) {
                        fab.classList.add(pos.snap);
                    }
                    // Small delay before allowing transitions again
                    setTimeout(() => { fab.style.transition = ''; }, 50);
                } catch (e) { }
            }
        }

        function onDragStart(e) {
            // Only allow primary pointer (left mouse or touch)
            if (e.pointerType === 'mouse' && e.button !== 0) return;

            startX = e.clientX;
            startY = e.clientY;
            hasMoved = false;

            // Get current transform offsets
            const style = window.getComputedStyle(fab);
            let matrix;
            try {
                matrix = new DOMMatrixReadOnly(style.transform);
                initialLeft = matrix.m41;
                initialTop = matrix.m42;
            } catch(err) {
                initialLeft = 0;
                initialTop = 0;
            }

            // Remove snapped states when starting to drag
            fab.classList.remove('snapped-left', 'snapped-right');
            fab.style.opacity = '1';

            // Capture the pointer to track movement even outside the element
            fab.setPointerCapture(e.pointerId);

            fab.addEventListener('pointermove', onDragMove);
            fab.addEventListener('pointerup', onDragEnd);
            fab.addEventListener('pointercancel', onDragEnd);
        }

        function onDragMove(e) {
            const dx = e.clientX - startX;
            const dy = e.clientY - startY;

            // If moved beyond threshold, mark as dragging (increased threshold to prevent click jitter)
            if (!hasMoved && Math.sqrt(dx * dx + dy * dy) > 15) {
                hasMoved = true;
                isDragging = true;
                fab.classList.add('is-dragging');

                // Hide menu if open
                menu.classList.remove('show');
            }

            if (isDragging) {
                if (e.cancelable) e.preventDefault();

                let newX = initialLeft + dx;
                let newY = initialTop + dy;

                // --- Window Restraints ---
                const fabRect = fab.getBoundingClientRect();
                const baseLeft = fabRect.left - matrixX(fabRect);
                const baseTop = fabRect.top - matrixY(fabRect);

                const maxY = window.innerHeight - baseTop - fabRect.height - PADDING;
                const minY = -baseTop + PADDING;

                if (newY < minY) newY = minY;
                if (newY > maxY) newY = maxY;

                // Apply
                fab.style.transform = `translate(${newX}px, ${newY}px)`;
                cx = newX;
                cy = newY;
            }
        }

        function onDragEnd(e) {
            fab.removeEventListener('pointermove', onDragMove);
            fab.removeEventListener('pointerup', onDragEnd);
            fab.removeEventListener('pointercancel', onDragEnd);
            
            try { fab.releasePointerCapture(e.pointerId); } catch(err) {}

            if (isDragging) {
                isDragging = false;
                fab.classList.remove('is-dragging');

                // --- Unconditional Edge Snapping Logic ---
                const fabRect = fab.getBoundingClientRect();
                const centerX = fabRect.left + (fabRect.width / 2);
                const isLeftHalf = centerX < (window.innerWidth / 2);

                const baseLeft = fabRect.left - matrixX(fabRect);
                let targetX;
                const HIDE_THRESHOLD = 15;

                if (isLeftHalf) {
                    if (fabRect.left < -HIDE_THRESHOLD) {
                        targetX = -baseLeft - (fabRect.width / 2);
                        fab.classList.add('snapped-left');
                    } else {
                        targetX = -baseLeft + PADDING;
                    }
                } else {
                    if (fabRect.right > window.innerWidth + HIDE_THRESHOLD) {
                        targetX = window.innerWidth - baseLeft - (fabRect.width / 2);
                        fab.classList.add('snapped-right');
                    } else {
                        targetX = window.innerWidth - baseLeft - fabRect.width - PADDING;
                    }
                }

                fab.style.transition = 'transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
                fab.style.transform = `translate(${targetX}px, ${cy}px)`;
                cx = targetX;

                if (!isHomePage) {
                    let snapState = '';
                    if (targetX < -baseLeft) snapState = 'snapped-left';
                    else if (targetX > window.innerWidth - baseLeft - fabRect.width) snapState = 'snapped-right';

                    localStorage.setItem('mystudy_fab_pos', JSON.stringify({ x: cx, y: cy, snap: snapState }));
                }

                setTimeout(() => { fab.style.transition = ''; }, 400);
            }
        }

        // Helpers for getting base position
        function matrixX(rect) {
            const style = window.getComputedStyle(fab);
            if (style.transform === 'none') return 0;
            try { return new DOMMatrixReadOnly(style.transform).m41; } catch(e) { return 0; }
        }
        function matrixY(rect) {
            const style = window.getComputedStyle(fab);
            if (style.transform === 'none') return 0;
            try { return new DOMMatrixReadOnly(style.transform).m42; } catch(e) { return 0; }
        }

        function toggleMenu() {
            fab.classList.remove('snapped-left', 'snapped-right');
            fab.style.opacity = '1';

            if (menu.classList.contains('show')) {
                menu.classList.remove('show');
            } else {
                const rect = fab.getBoundingClientRect();
                const isLeft = rect.left < window.innerWidth / 2;
                const isTop = rect.top < window.innerHeight / 2;

                menu.style.bottom = 'auto';
                menu.style.top = 'auto';
                menu.style.left = 'auto';
                menu.style.right = 'auto';
                menu.style.transformOrigin = '';

                if (isTop) {
                    menu.style.top = (rect.bottom + 10) + 'px';
                    menu.style.transformOrigin = `top ${isLeft ? 'left' : 'right'}`;
                } else {
                    menu.style.bottom = (window.innerHeight - rect.top + 10) + 'px';
                    menu.style.transformOrigin = `bottom ${isLeft ? 'left' : 'right'}`;
                }

                if (isLeft) {
                    menu.style.left = Math.max(10, rect.left) + 'px';
                } else {
                    menu.style.right = Math.max(10, window.innerWidth - rect.right) + 'px';
                }

                menu.classList.add('show');
            }
        }

        // Attach listeners (Pointer Events handles both mouse and touch elegantly)
        fab.addEventListener('pointerdown', onDragStart);
        
        // Use native click event for toggling, block if user was dragging
        fab.addEventListener('click', (e) => {
            if (hasMoved) {
                e.preventDefault();
                e.stopPropagation();
                return;
            }
            toggleMenu();
        });

        // Hide menu on outside click
        document.addEventListener('click', (e) => {
            if (!fab.contains(e.target) && !menu.contains(e.target) && menu.classList.contains('show')) {
                menu.classList.remove('show');
            }
        });
    }

    function init() {
        initLevelToggle();
        initSubFilterEngine();
        initLazyVideoEmbed();
        initCountdownTimers();
        initBackToTop();
        initFlashAlerts();
        initDraggableFab();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
