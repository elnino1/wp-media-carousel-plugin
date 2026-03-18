/**
 * Inkiz Media Carousel – Vanilla JS Controller
 * Handles slide switching, likes (AJAX/localStorage), and collapsible comments.
 */
(function () {
    'use strict';

    /**
     * Initialize one carousel wrapper element.
     * @param {HTMLElement} wrapper
     */
    function initCarousel(wrapper) {
        let slides = Array.from(wrapper.querySelectorAll('.inkiz-mc-slide'));
        const relatedPanels = Array.from(wrapper.querySelectorAll('.inkiz-mc-related-panel'));
        const commentPanels = Array.from(wrapper.querySelectorAll('.inkiz-mc-comments-panel'));
        const filterBtns = Array.from(wrapper.querySelectorAll('.inkiz-mc-filter-btn'));
        let thumbnails = Array.from(wrapper.querySelectorAll('.inkiz-mc-thumb'));
        const thumbnailsTrack = wrapper.querySelector('.inkiz-mc-thumbnails-track');
        const autoplay = parseInt(wrapper.dataset.autoplay, 10) || 0;

        let current = 0;
        let timer = null;

        if (slides.length === 0) return;

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
            if (slides[current]) slides[current].classList.remove('inkiz-mc-slide--active');
            if (relatedPanels[current]) relatedPanels[current].classList.remove('inkiz-mc-related-panel--active');
            if (commentPanels[current]) commentPanels[current].classList.remove('inkiz-mc-comments-panel--active');
            if (thumbnails[current]) thumbnails[current].classList.remove('inkiz-mc-thumb--active');

            current = index;

            // Activate new
            if (slides[current]) slides[current].classList.add('inkiz-mc-slide--active');
            if (relatedPanels[current]) relatedPanels[current].classList.add('inkiz-mc-related-panel--active');
            if (commentPanels[current]) commentPanels[current].classList.add('inkiz-mc-comments-panel--active');
            if (thumbnails[current]) {
                thumbnails[current].classList.add('inkiz-mc-thumb--active');
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

        /** Arrow buttons — attached to every slide */
        wrapper.querySelectorAll('.inkiz-mc-arrow--prev').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                // To jump prev, pass an index lower than current
                goTo(current - 1, true);
            });
        });
        wrapper.querySelectorAll('.inkiz-mc-arrow--next').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                // To jump next, pass an index higher than current
                goTo(current + 1, true);
            });
        });

        /** Keyboard navigation (only when wrapper is focused/hovered) */
        wrapper.setAttribute('tabindex', '0');
        wrapper.addEventListener('keydown', (e) => {
            const tagName = e.target.tagName.toLowerCase();
            // Don't intercept if user is typing in comment form
            if (['input', 'textarea'].includes(tagName)) return;

            if (e.key === 'ArrowLeft') { e.preventDefault(); goTo(current - 1, true); }
            if (e.key === 'ArrowRight') { e.preventDefault(); goTo(current + 1, true); }
        });

        /** Touch / swipe support on images */
        let touchStartX = null;
        const stage = wrapper.querySelector('.inkiz-mc-stage');
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
        wrapper.querySelectorAll('.inkiz-mc-like-btn').forEach(btn => {
            const id = btn.dataset.id;
            const storageKey = 'inkiz_mc_liked_' + id;

            // Check localized state
            if (localStorage.getItem(storageKey)) {
                btn.classList.add('inkiz-mc-liked');
            }

            btn.addEventListener('click', () => {
                // Prevent duplicate likes
                if (btn.classList.contains('inkiz-mc-liked')) return;

                // Optimistic UI update
                btn.classList.add('inkiz-mc-liked');
                localStorage.setItem(storageKey, '1');
                const countSpan = btn.querySelector('.inkiz-mc-like-count');
                if (countSpan) {
                    countSpan.textContent = parseInt(countSpan.textContent, 10) + 1;
                }

                // AJAX call
                if (typeof inkizMC !== 'undefined') {
                    const data = new URLSearchParams();
                    data.append('action', 'inkiz_mc_like');
                    data.append('nonce', inkizMC.nonce);
                    data.append('attachment_id', id);

                    fetch(inkizMC.ajaxUrl, {
                        method: 'POST',
                        body: data
                    }).catch(err => console.error('Like error:', err));
                }
            });
        });

        // Collapsible Comments
        wrapper.querySelectorAll('.inkiz-mc-comments-panel').forEach(panel => {
            const toggleBtn = panel.querySelector('.inkiz-mc-comments-toggle');
            const bodyDiv = panel.querySelector('.inkiz-mc-comments-body');

            if (!toggleBtn || !bodyDiv) return;

            toggleBtn.addEventListener('click', () => {
                const isExpanded = toggleBtn.getAttribute('aria-expanded') === 'true';

                if (isExpanded) {
                    toggleBtn.setAttribute('aria-expanded', 'false');
                    bodyDiv.classList.remove('inkiz-mc-comments-body--open');
                    bodyDiv.setAttribute('aria-hidden', 'true');
                } else {
                    toggleBtn.setAttribute('aria-expanded', 'true');
                    bodyDiv.classList.add('inkiz-mc-comments-body--open');
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
                    filterBtns.forEach(b => b.classList.remove('inkiz-mc-filter--active'));
                    btn.classList.add('inkiz-mc-filter--active');

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
    }

    /** Boot all carousel instances on the page */
    function bootAll() {
        document.querySelectorAll('.inkiz-mc-wrapper').forEach(initCarousel);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootAll);
    } else {
        bootAll();
    }
}());
