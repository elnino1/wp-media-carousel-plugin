/**
 * Inkiz Media Carousel – Vanilla JS Controller
 * Handles slide switching, likes (AJAX/localStorage), and collapsible comments.
 */
( function () {
    'use strict';

    /**
     * Initialize one carousel wrapper element.
     * @param {HTMLElement} wrapper
     */
    function initCarousel( wrapper ) {
        const slides         = Array.from( wrapper.querySelectorAll( '.inkiz-mc-slide' ) );
        const relatedPanels  = Array.from( wrapper.querySelectorAll( '.inkiz-mc-related-panel' ) );
        const commentPanels  = Array.from( wrapper.querySelectorAll( '.inkiz-mc-comments-panel' ) );
        const autoplay       = parseInt( wrapper.dataset.autoplay, 10 ) || 0;

        let current = 0;
        let timer   = null;

        if ( slides.length === 0 ) return;

        /** Activate slide by index */
        function goTo( index, wrap ) {
            if ( wrap ) {
                index = ( index + slides.length ) % slides.length;
            } else {
                index = Math.max( 0, Math.min( slides.length - 1, index ) );
            }

            // Deactivate current
            slides[ current ].classList.remove( 'inkiz-mc-slide--active' );
            if ( relatedPanels[ current ] ) relatedPanels[ current ].classList.remove( 'inkiz-mc-related-panel--active' );
            if ( commentPanels[ current ] ) commentPanels[ current ].classList.remove( 'inkiz-mc-comments-panel--active' );

            current = index;

            // Activate new
            slides[ current ].classList.add( 'inkiz-mc-slide--active' );
            if ( relatedPanels[ current ] ) relatedPanels[ current ].classList.add( 'inkiz-mc-related-panel--active' );
            if ( commentPanels[ current ] ) commentPanels[ current ].classList.add( 'inkiz-mc-comments-panel--active' );

            wrapper.dataset.current = current;

            resetAutoplay();
        }

        /** Autoplay */
        function resetAutoplay() {
            if ( ! autoplay ) return;
            clearInterval( timer );
            timer = setInterval( () => goTo( current + 1, true ), autoplay * 1000 );
        }

        /** Arrow buttons — attached to every slide */
        wrapper.querySelectorAll( '.inkiz-mc-arrow--prev' ).forEach( btn => {
            btn.addEventListener( 'click', ( e ) => {
                e.stopPropagation();
                goTo( current - 1, true );
            } );
        } );
        wrapper.querySelectorAll( '.inkiz-mc-arrow--next' ).forEach( btn => {
            btn.addEventListener( 'click', ( e ) => {
                e.stopPropagation();
                goTo( current + 1, true );
            } );
        } );

        /** Keyboard navigation (only when wrapper is focused/hovered) */
        wrapper.setAttribute( 'tabindex', '0' );
        wrapper.addEventListener( 'keydown', ( e ) => {
            const tagName = e.target.tagName.toLowerCase();
            // Don't intercept if user is typing in comment form
            if ( ['input', 'textarea'].includes( tagName ) ) return;

            if ( e.key === 'ArrowLeft' )  { e.preventDefault(); goTo( current - 1, true ); }
            if ( e.key === 'ArrowRight' ) { e.preventDefault(); goTo( current + 1, true ); }
        } );

        /** Touch / swipe support on images */
        let touchStartX = null;
        const stage = wrapper.querySelector( '.inkiz-mc-stage' );
        if ( stage ) {
            stage.addEventListener( 'touchstart', ( e ) => {
                touchStartX = e.touches[ 0 ].clientX;
            }, { passive: true } );
            stage.addEventListener( 'touchend', ( e ) => {
                if ( touchStartX === null ) return;
                const diff = touchStartX - e.changedTouches[ 0 ].clientX;
                if ( Math.abs( diff ) > 40 ) {
                    goTo( diff > 0 ? current + 1 : current - 1, true );
                }
                touchStartX = null;
            } );
        }

        // Like buttons
        wrapper.querySelectorAll( '.inkiz-mc-like-btn' ).forEach( btn => {
            const id = btn.dataset.id;
            const storageKey = 'inkiz_mc_liked_' + id;

            // Check localized state
            if ( localStorage.getItem( storageKey ) ) {
                btn.classList.add( 'inkiz-mc-liked' );
            }

            btn.addEventListener( 'click', () => {
                // Prevent duplicate likes
                if ( btn.classList.contains( 'inkiz-mc-liked' ) ) return;

                // Optimistic UI update
                btn.classList.add( 'inkiz-mc-liked' );
                localStorage.setItem( storageKey, '1' );
                const countSpan = btn.querySelector( '.inkiz-mc-like-count' );
                if ( countSpan ) {
                    countSpan.textContent = parseInt( countSpan.textContent, 10 ) + 1;
                }

                // AJAX call
                if ( typeof inkizMC !== 'undefined' ) {
                    const data = new URLSearchParams();
                    data.append( 'action', 'inkiz_mc_like' );
                    data.append( 'nonce', inkizMC.nonce );
                    data.append( 'attachment_id', id );

                    fetch( inkizMC.ajaxUrl, {
                        method: 'POST',
                        body: data
                    } ).catch( err => console.error( 'Like error:', err ) );
                }
            } );
        } );

        // Collapsible Comments
        wrapper.querySelectorAll( '.inkiz-mc-comments-panel' ).forEach( panel => {
            const toggleBtn = panel.querySelector( '.inkiz-mc-comments-toggle' );
            const bodyDiv   = panel.querySelector( '.inkiz-mc-comments-body' );

            if ( ! toggleBtn || ! bodyDiv ) return;

            toggleBtn.addEventListener( 'click', () => {
                const isExpanded = toggleBtn.getAttribute( 'aria-expanded' ) === 'true';
                
                if ( isExpanded ) {
                    toggleBtn.setAttribute( 'aria-expanded', 'false' );
                    bodyDiv.classList.remove( 'inkiz-mc-comments-body--open' );
                    bodyDiv.setAttribute( 'aria-hidden', 'true' );
                } else {
                    toggleBtn.setAttribute( 'aria-expanded', 'true' );
                    bodyDiv.classList.add( 'inkiz-mc-comments-body--open' );
                    bodyDiv.setAttribute( 'aria-hidden', 'false' );
                }
            } );
        } );

        // Start autoplay.
        if ( autoplay ) resetAutoplay();
    }

    /** Boot all carousel instances on the page */
    function bootAll() {
        document.querySelectorAll( '.inkiz-mc-wrapper' ).forEach( initCarousel );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', bootAll );
    } else {
        bootAll();
    }
}() );
