/**
 * WordPress Media Carousel – Vanilla JS Controller
 * Handles slide switching, likes (AJAX/localStorage), and collapsible comments.
 */
(function () {
    'use strict';

    /**
     * Initialize one carousel wrapper element.
     * @param {HTMLElement} wrapper
     * @param {number|null} initialId - Optional attachment ID to navigate to
     * @returns {boolean} - True if deep-link was applied, false otherwise
     */
    function initCarousel(wrapper, initialId) {
        let slides = Array.from(wrapper.querySelectorAll('.wp-mc-slide'));
        const relatedPanels = Array.from(wrapper.querySelectorAll('.wp-mc-related-panel'));
        const commentPanels = Array.from(wrapper.querySelectorAll('.wp-mc-comments-panel'));
        const filterBtns = Array.from(wrapper.querySelectorAll('.wp-mc-filter-btn'));
        let thumbnails = Array.from(wrapper.querySelectorAll('.wp-mc-thumb'));
        const thumbnailsTrack = wrapper.querySelector('.wp-mc-thumbnails-track');
        const autoplay = parseInt(wrapper.dataset.autoplay, 10) || 0;

        let current = 0;
        let timer = null;

        if (slides.length === 0) return false;

        /** Activate slide by index */
        function goTo(index, wrap) {
            // Because filtering might hide some slides, we need to map the logical 'visible' index
            // back to the original slides array. 
            // In fact, it is easier to keep track of 'current' as the *actual DOM index* of the slide,
            // but when next/prev is clicked, we need to find the *next visible* index.
            const visibleSlides = slides.filter(s => s.style.display !== 'none');
            if (visibleSlides.length === 0) return;

            // If we are passing an offset (next/prev via arrows), index might not be a valid target if filtered.
            // Let's refactor `index` to always be the literal `data-index` of the target slide.
            let targetSlide = slides[index];

            // If the target slide isn't visible, or doesn't exist, we fallback to finding the next available.
            if (!targetSlide || targetSlide.style.display === 'none') {
                // Try to find it logically
                const currentVisibleIndex = visibleSlides.findIndex(s => s === slides[current]);
                let nextLogicalIndex = 0;
                if (currentVisibleIndex !== -1) {
                    const offset = index > current ? 1 : -1;
                    nextLogicalIndex = wrap ? (currentVisibleIndex + offset + visibleSlides.length) % visibleSlides.length
                        : Math.max(0, Math.min(visibleSlides.length - 1, currentVisibleIndex + offset));
                }
                targetSlide = visibleSlides[nextLogicalIndex];
                index = parseInt(targetSlide.dataset.index, 10);
            }

            // Deactivate current
            if (slides[current]) slides[current].classList.remove('wp-mc-slide--active');
            if (relatedPanels[current]) relatedPanels[current].classList.remove('wp-mc-related-panel--active');
            if (commentPanels[current]) commentPanels[current].classList.remove('wp-mc-comments-panel--active');
            if (thumbnails[current]) thumbnails[current].classList.remove('wp-mc-thumb--active');

            current = index;

            // Activate new
            if (slides[current]) slides[current].classList.add('wp-mc-slide--active');
            if (relatedPanels[current]) relatedPanels[current].classList.add('wp-mc-related-panel--active');
            if (commentPanels[current]) commentPanels[current].classList.add('wp-mc-comments-panel--active');
            if (thumbnails[current]) {
                thumbnails[current].classList.add('wp-mc-thumb--active');
                // Auto-scroll thumbnail track
                if (thumbnailsTrack) {
                    const t = thumbnails[current];
                    const tCenter = t.offsetLeft + (t.offsetWidth / 2);
                    const trackCenter = thumbnailsTrack.offsetWidth / 2;
                    thumbnailsTrack.scrollTo({ left: tCenter - trackCenter, behavior: 'smooth' });
                }
            }

            wrapper.dataset.current = current;

            resetAutoplay();
        }

        /** Autoplay */
        function resetAutoplay() {
            if (!autoplay) return;
            clearInterval(timer);
            timer = setInterval(() => goTo(current + 1, true), autoplay * 1000);
        }

        /** Main image click → open lightbox */
        wrapper.querySelectorAll('.wp-mc-main-image').forEach(function (img) {
            img.addEventListener('click', function () {
                openLightbox(img.src, img.alt);
            });
        });

        /** Arrow buttons — attached to every slide */
        wrapper.querySelectorAll('.wp-mc-arrow--prev').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                closeLightbox();
                goTo(current - 1, true);
            });
        });
        wrapper.querySelectorAll('.wp-mc-arrow--next').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                closeLightbox();
                goTo(current + 1, true);
            });
        });

        /** Keyboard navigation (only when wrapper is focused/hovered) */
        wrapper.setAttribute('tabindex', '0');
        wrapper.addEventListener('keydown', (e) => {
            const tagName = e.target.tagName.toLowerCase();
            // Don't intercept if user is typing in comment form
            if (['input', 'textarea'].includes(tagName)) return;

            if (e.key === 'ArrowLeft') { e.preventDefault(); closeLightbox(); goTo(current - 1, true); }
            if (e.key === 'ArrowRight') { e.preventDefault(); closeLightbox(); goTo(current + 1, true); }
        });

        /** Touch / swipe support on images */
        let touchStartX = null;
        const stage = wrapper.querySelector('.wp-mc-stage');
        if (stage) {
            stage.addEventListener('touchstart', (e) => {
                touchStartX = e.touches[0].clientX;
            }, { passive: true });
            stage.addEventListener('touchend', (e) => {
                if (touchStartX === null) return;
                const diff = touchStartX - e.changedTouches[0].clientX;
                if (Math.abs(diff) > 40) {
                    goTo(diff > 0 ? current + 1 : current - 1, true);
                }
                touchStartX = null;
            });
        }

        // Like buttons
        wrapper.querySelectorAll('.wp-mc-like-btn').forEach(btn => {
            const id = btn.dataset.id;
            const storageKey = 'wp_mc_liked_' + id;

            // Check localized state
            if (localStorage.getItem(storageKey)) {
                btn.classList.add('wp-mc-liked');
            }

            btn.addEventListener('click', () => {
                // Prevent duplicate likes
                if (btn.classList.contains('wp-mc-liked')) return;

                // Optimistic UI update
                btn.classList.add('wp-mc-liked');
                localStorage.setItem(storageKey, '1');
                const countSpan = btn.querySelector('.wp-mc-like-count');
                if (countSpan) {
                    countSpan.textContent = parseInt(countSpan.textContent, 10) + 1;
                }

                // AJAX call
                if (typeof wpMC !== 'undefined') {
                    const data = new URLSearchParams();
                    data.append('action', 'wp_mc_like');
                    data.append('nonce', wpMC.nonce);
                    data.append('attachment_id', id);

                    fetch(wpMC.ajaxUrl, {
                        method: 'POST',
                        body: data
                    }).catch(err => console.error('Like error:', err));
                }
            });
        });

        // Collapsible Comments
        wrapper.querySelectorAll('.wp-mc-comments-panel').forEach(panel => {
            const toggleBtn = panel.querySelector('.wp-mc-comments-toggle');
            const bodyDiv = panel.querySelector('.wp-mc-comments-body');

            if (!toggleBtn || !bodyDiv) return;

            toggleBtn.addEventListener('click', () => {
                const isExpanded = toggleBtn.getAttribute('aria-expanded') === 'true';

                if (isExpanded) {
                    toggleBtn.setAttribute('aria-expanded', 'false');
                    bodyDiv.classList.remove('wp-mc-comments-body--open');
                    bodyDiv.setAttribute('aria-hidden', 'true');
                } else {
                    toggleBtn.setAttribute('aria-expanded', 'true');
                    bodyDiv.classList.add('wp-mc-comments-body--open');
                    bodyDiv.setAttribute('aria-hidden', 'false');
                }
            });
        });

        // Thumbnails Clicks
        thumbnails.forEach(t => {
            t.addEventListener('click', (e) => {
                e.preventDefault();
                const idx = parseInt(t.dataset.index, 10);
                goTo(idx, false);
            });
        });

        // Category Filtering
        if (filterBtns.length > 0) {
            filterBtns.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();

                    // Update active button state
                    filterBtns.forEach(b => b.classList.remove('wp-mc-filter--active'));
                    btn.classList.add('wp-mc-filter--active');

                    const catFilter = btn.dataset.filter;
                    let firstVisibleSlideIndex = null;

                    // Filter Slides and Thumbnails
                    slides.forEach((slide, idx) => {
                        const slideCats = slide.dataset.cats ? slide.dataset.cats.split(',') : [];
                        const match = (catFilter === 'all') || slideCats.includes(catFilter);

                        slide.style.display = match ? '' : 'none';
                        if (thumbnails[idx]) {
                            thumbnails[idx].style.display = match ? '' : 'none';
                        }

                        if (match && firstVisibleSlideIndex === null) {
                            firstVisibleSlideIndex = idx;
                        }
                    });

                    // Jump to first matching slide
                    if (firstVisibleSlideIndex !== null) {
                        goTo(firstVisibleSlideIndex, false);
                    }
                });
            });
        }

        // Start autoplay.
        if (autoplay) resetAutoplay();

        // Deep-link: if a wp_mc_id param was provided, navigate to the matching slide.
        if (initialId !== null) {
            const target = slides.findIndex(s => s.dataset.id === initialId);
            if (target !== -1 && slides[target].style.display !== 'none') {
                goTo(target, false);
                wrapper.scrollIntoView({ behavior: 'smooth' });
                return true;
            }
        }

        return false;
    }

    /* ------------------------------------------------------------------ */
    /* Lightbox                                                             */
    /* ------------------------------------------------------------------ */

    var lightbox = null;
    var lbImg    = null;
    var lbCanvas = null;
    var lbScale  = 1;
    var lbTransX = 0;
    var lbTransY = 0;
    var lbDragging = false;
    var lbDragStartX = 0;
    var lbDragStartY = 0;
    var lbDragOriginX = 0;
    var lbDragOriginY = 0;

    function buildLightbox() {
        var el = document.createElement('div');
        el.className = 'wp-mc-lightbox';
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-modal', 'true');
        el.setAttribute('aria-label', 'Image agrandie');
        el.innerHTML =
            '<button class="wp-mc-lightbox-close" aria-label="Fermer">&#x2715;</button>' +
            '<div class="wp-mc-lightbox-controls">' +
                '<button class="wp-mc-lightbox-zoom-out" aria-label="Zoom arrière">&#x2212;</button>' +
                '<button class="wp-mc-lightbox-zoom-in"  aria-label="Zoom avant">&#x2b;</button>' +
            '</div>' +
            '<div class="wp-mc-lightbox-canvas">' +
                '<img class="wp-mc-lightbox-img" src="" alt="" draggable="false">' +
            '</div>';
        document.body.appendChild(el);

        lbImg    = el.querySelector('.wp-mc-lightbox-img');
        lbCanvas = el.querySelector('.wp-mc-lightbox-canvas');

        // Close on backdrop (canvas) click — but not on the image itself
        lbCanvas.addEventListener('click', function (e) {
            if (e.target === lbCanvas) { closeLightbox(); }
        });

        el.querySelector('.wp-mc-lightbox-close').addEventListener('click', closeLightbox);
        el.querySelector('.wp-mc-lightbox-zoom-in').addEventListener('click', function () { applyZoom(0.25); });
        el.querySelector('.wp-mc-lightbox-zoom-out').addEventListener('click', function () { applyZoom(-0.25); });

        // Scroll-wheel zoom
        lbCanvas.addEventListener('wheel', function (e) {
            e.preventDefault();
            applyZoom(e.deltaY < 0 ? 0.25 : -0.25);
        }, { passive: false });

        // Drag-to-pan
        lbImg.addEventListener('mousedown', function (e) {
            if (lbScale <= 1) return;
            e.preventDefault();
            lbDragging   = true;
            lbDragStartX = e.clientX;
            lbDragStartY = e.clientY;
            lbDragOriginX = lbTransX;
            lbDragOriginY = lbTransY;
            lbImg.style.cursor = 'grabbing';
        });
        document.addEventListener('mousemove', function (e) {
            if (!lbDragging) return;
            lbTransX = lbDragOriginX + (e.clientX - lbDragStartX);
            lbTransY = lbDragOriginY + (e.clientY - lbDragStartY);
            clampPan();
            applyTransform();
        });
        document.addEventListener('mouseup', function () {
            if (!lbDragging) return;
            lbDragging = false;
            lbImg.style.cursor = lbScale > 1 ? 'grab' : 'default';
        });

        // Touch pinch-to-zoom
        var pinchStartDist = 0;
        var pinchStartScale = 1;
        lbCanvas.addEventListener('touchstart', function (e) {
            if (e.touches.length === 2) {
                pinchStartDist  = getPinchDist(e);
                pinchStartScale = lbScale;
            }
        }, { passive: true });
        lbCanvas.addEventListener('touchmove', function (e) {
            if (e.touches.length === 2) {
                e.preventDefault();
                var dist  = getPinchDist(e);
                var ratio = dist / pinchStartDist;
                lbScale   = Math.min(5, Math.max(0.5, pinchStartScale * ratio));
                clampPan();
                applyTransform();
            }
        }, { passive: false });

        lightbox = el;
    }

    function getPinchDist(e) {
        var dx = e.touches[0].clientX - e.touches[1].clientX;
        var dy = e.touches[0].clientY - e.touches[1].clientY;
        return Math.sqrt(dx * dx + dy * dy);
    }

    function applyZoom(delta) {
        lbScale = Math.min(5, Math.max(0.5, lbScale + delta));
        if (lbScale <= 1) { lbTransX = 0; lbTransY = 0; }
        clampPan();
        applyTransform();
        lbImg.style.cursor = lbScale > 1 ? 'grab' : 'default';
    }

    function clampPan() {
        if (lbScale <= 1) { lbTransX = 0; lbTransY = 0; return; }
        var maxX = (lbImg.offsetWidth  * (lbScale - 1)) / 2;
        var maxY = (lbImg.offsetHeight * (lbScale - 1)) / 2;
        lbTransX = Math.min(maxX, Math.max(-maxX, lbTransX));
        lbTransY = Math.min(maxY, Math.max(-maxY, lbTransY));
    }

    function applyTransform() {
        lbImg.style.transform = 'translate(' + lbTransX + 'px, ' + lbTransY + 'px) scale(' + lbScale + ')';
    }

    function openLightbox(src, alt) {
        if (!lightbox) { buildLightbox(); }
        lbScale  = 1;
        lbTransX = 0;
        lbTransY = 0;
        lbImg.src = src;
        lbImg.alt = alt;
        lbImg.style.transform = '';
        lbImg.style.cursor    = 'default';
        lightbox.classList.add('wp-mc-lightbox--open');
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        if (!lightbox) return;
        lightbox.classList.remove('wp-mc-lightbox--open');
        document.body.style.overflow = '';
        // Clear src after transition to stop loading
        setTimeout(function () { if (lbImg) lbImg.src = ''; }, 300);
    }

    // Global Escape key handler
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeLightbox(); }
    });

    /* ------------------------------------------------------------------ */
    /* Boot all carousel instances on the page                             */
    /* ------------------------------------------------------------------ */

    /** Boot all carousel instances on the page */
    function bootAll() {
        const params = new URLSearchParams(window.location.search);
        const initialId = params.get('wp_mc_id') || null;
        let linked = false;
        document.querySelectorAll('.wp-mc-wrapper').forEach(function (wrapper) {
            if (initCarousel(wrapper, linked ? null : initialId)) {
                linked = true;
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootAll);
    } else {
        bootAll();
    }
}());
