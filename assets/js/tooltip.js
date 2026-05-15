/**
 * Smart Footnotes — tooltip.js
 *
 * Handles all display modes: tooltip, inline expand, mobile drawer, side panel.
 * Depends on window.SFN (exported by frontend.js).
 *
 * Both scripts are deferred — load order is not guaranteed.
 * window.SFN is initialised as {} by frontend.js before its IIFE,
 * and extended by Object.assign at the end of each file.
 * All calls to window.SFN methods use live reads (not a cached local const).
 */

// Ensure the namespace exists even if this script runs before frontend.js.
window.SFN = window.SFN || {};

( function () {
    'use strict';

    const settings  = window.sfnData || {};
    const MOBILE_BP = 768;

    // ── Constants ─────────────────────────────────────────────────────────────
    const TOOLTIP_OFFSET   = 10;   // px gap between marker and tooltip
    const HOVER_DELAY_IN   = 120;  // ms before tooltip shows on hover
    const HOVER_DELAY_OUT  = 220;  // ms before tooltip hides on mouse leave
    const DRAWER_THRESHOLD = 60;   // px swipe distance to dismiss drawer

    // ── State ─────────────────────────────────────────────────────────────────
    let tooltip        = null;   // the single reusable tooltip element
    let drawer         = null;   // the single reusable drawer element
    let drawerBackdrop = null;
    let sidePanel      = null;
    let activeRef      = null;   // currently active sup.sfn-ref
    let hoverTimer     = null;
    let hideTimer      = null;
    let drawerStartY   = 0;
    let drawerCurrentY = 0;
    let drawerDragging = false;

    // ── Init ──────────────────────────────────────────────────────────────────

    function init() {
        if ( ! document.querySelector( '.sfn-footnotes' ) ) return;

        buildTooltip();
        buildDrawer();
        buildSidePanel();
        bindEvents();
        initSidePanel();
    }

    // ── Tooltip DOM ───────────────────────────────────────────────────────────

    function buildTooltip() {
        tooltip = document.createElement( 'div' );
        tooltip.className    = 'sfn-tooltip';
        tooltip.id           = 'sfn-tooltip';
        tooltip.role         = 'tooltip';
        tooltip.setAttribute( 'aria-live', 'polite' );
        tooltip.setAttribute( 'aria-hidden', 'true' );
        tooltip.innerHTML    = '<div class="sfn-tooltip__arrow"></div><div class="sfn-tooltip__body"></div>';
        document.body.appendChild( tooltip );

        // Hide on tooltip mouseleave too (so user can move into tooltip).
        tooltip.addEventListener( 'mouseenter', () => clearTimeout( hideTimer ) );
        tooltip.addEventListener( 'mouseleave', () => scheduleHide() );
    }

    // ── Drawer DOM ────────────────────────────────────────────────────────────

    function buildDrawer() {
        drawerBackdrop = document.createElement( 'div' );
        drawerBackdrop.className = 'sfn-drawer-backdrop';
        drawerBackdrop.setAttribute( 'aria-hidden', 'true' );
        document.body.appendChild( drawerBackdrop );

        drawer = document.createElement( 'div' );
        drawer.className = 'sfn-drawer';
        drawer.setAttribute( 'role', 'dialog' );
        drawer.setAttribute( 'aria-modal', 'true' );
        drawer.setAttribute( 'aria-label', sfnL10n( 'Footnote' ) );
        drawer.setAttribute( 'aria-hidden', 'true' );
        drawer.innerHTML = `
            <div class="sfn-drawer__handle" aria-hidden="true"></div>
            <div class="sfn-drawer__header">
                <span class="sfn-drawer__label"></span>
                <button class="sfn-drawer__close" aria-label="${ sfnL10n( 'Close footnote' ) }">&#x2715;</button>
            </div>
            <div class="sfn-drawer__body"></div>
        `;
        document.body.appendChild( drawer );

        // Close button.
        drawer.querySelector( '.sfn-drawer__close' ).addEventListener( 'click', closeDrawer );
        drawerBackdrop.addEventListener( 'click', closeDrawer );

        // Swipe-to-dismiss.
        drawer.addEventListener( 'touchstart', onDrawerTouchStart, { passive: true } );
        drawer.addEventListener( 'touchmove',  onDrawerTouchMove,  { passive: false } );
        drawer.addEventListener( 'touchend',   onDrawerTouchEnd,   { passive: true } );

        // Keyboard: Escape closes.
        document.addEventListener( 'keydown', ( e ) => {
            if ( e.key === 'Escape' && drawer.getAttribute( 'aria-hidden' ) === 'false' ) {
                closeDrawer();
            }
        } );
    }

    // ── Side panel DOM ────────────────────────────────────────────────────────

    function buildSidePanel() {
        sidePanel = document.createElement( 'aside' );
        sidePanel.className = 'sfn-sidepanel';
        sidePanel.setAttribute( 'aria-label', sfnL10n( 'Active footnote' ) );
        sidePanel.setAttribute( 'aria-live', 'polite' );
        sidePanel.setAttribute( 'aria-hidden', 'true' );
        sidePanel.innerHTML = `
            <div class="sfn-sidepanel__label"></div>
            <div class="sfn-sidepanel__body"></div>
        `;
        document.body.appendChild( sidePanel );
    }

    // ── Event binding ─────────────────────────────────────────────────────────

    function bindEvents() {
        // Delegate all interactions from document (frontend.js already owns click).
        // We intercept tooltip-mode refs before the scroll handler runs.
        document.addEventListener( 'click',      onDocClick,      { passive: false } );
        document.addEventListener( 'mouseover',  onDocMouseover,  { passive: true } );
        document.addEventListener( 'mouseout',   onDocMouseout,   { passive: true } );
        document.addEventListener( 'focusin',    onDocFocusin,    { passive: true } );
        document.addEventListener( 'focusout',   onDocFocusout,   { passive: true } );
    }

    // ── Click handler ─────────────────────────────────────────────────────────

    function onDocClick( e ) {
        const supEl = e.target.closest( 'sup.sfn-ref' );
        if ( ! supEl ) return;

        const mode = supEl.dataset.mode;

        // Track click regardless of mode.
        trackClick( supEl );

        if ( mode === 'tooltip' ) {
            e.preventDefault();
            e.stopPropagation();
            if ( isMobile() ) {
                openDrawer( supEl );
            } else {
                toggleTooltip( supEl );
            }
            return;
        }

        if ( mode === 'inline' ) {
            e.preventDefault();
            e.stopPropagation();
            toggleInline( supEl );
            return;
        }
        // 'classic' and 'sidepanel' fall through to frontend.js scroll handler.
    }

    // ── Hover handlers ────────────────────────────────────────────────────────

    function onDocMouseover( e ) {
        const supEl = e.target.closest( 'sup.sfn-ref[data-mode="tooltip"]' );
        if ( ! supEl || isMobile() ) return;

        clearTimeout( hideTimer );
        clearTimeout( hoverTimer );
        hoverTimer = setTimeout( () => showTooltip( supEl ), HOVER_DELAY_IN );
    }

    function onDocMouseout( e ) {
        const supEl = e.target.closest( 'sup.sfn-ref[data-mode="tooltip"]' );
        if ( ! supEl || isMobile() ) return;

        const to = e.relatedTarget;
        if ( tooltip.contains( to ) ) return; // moved into tooltip
        clearTimeout( hoverTimer );
        scheduleHide();
    }

    // ── Focus handlers (keyboard accessibility) ────────────────────────────────

    function onDocFocusin( e ) {
        const supEl = e.target.closest( 'sup.sfn-ref[data-mode="tooltip"]' );
        if ( ! supEl || isMobile() ) return;
        clearTimeout( hideTimer );
        showTooltip( supEl );
    }

    function onDocFocusout( e ) {
        const supEl = e.target.closest( 'sup.sfn-ref[data-mode="tooltip"]' );
        if ( ! supEl || isMobile() ) return;
        const to = e.relatedTarget;
        if ( tooltip.contains( to ) ) return;
        scheduleHide();
    }

    // ── Tooltip show / hide ───────────────────────────────────────────────────

    function showTooltip( supEl ) {
        const content = supEl.dataset.content || '';
        if ( ! content ) return;

        activeRef = supEl;
        tooltip.querySelector( '.sfn-tooltip__body' ).textContent = content;
        tooltip.setAttribute( 'aria-hidden', 'false' );
        tooltip.classList.add( 'sfn-tooltip--visible' );

        positionTooltip( supEl );

        // Link tooltip to the anchor for ARIA.
        const anchor = supEl.querySelector( 'a' );
        if ( anchor ) anchor.setAttribute( 'aria-expanded', 'true' );
    }

    function hideTooltip() {
        tooltip.classList.remove( 'sfn-tooltip--visible' );
        tooltip.setAttribute( 'aria-hidden', 'true' );

        if ( activeRef ) {
            const anchor = activeRef.querySelector( 'a' );
            if ( anchor ) anchor.setAttribute( 'aria-expanded', 'false' );
            activeRef = null;
        }
    }

    function toggleTooltip( supEl ) {
        if ( activeRef === supEl && tooltip.classList.contains( 'sfn-tooltip--visible' ) ) {
            hideTooltip();
        } else {
            showTooltip( supEl );
        }
    }

    function scheduleHide() {
        hideTimer = setTimeout( hideTooltip, HOVER_DELAY_OUT );
    }

    // ── Tooltip positioning ───────────────────────────────────────────────────

    function positionTooltip( supEl ) {
        // Reset first so getBoundingClientRect is accurate.
        tooltip.style.left     = '0';
        tooltip.style.top      = '0';
        tooltip.style.maxWidth = '';
        tooltip.classList.remove( 'sfn-tooltip--above', 'sfn-tooltip--below' );

        const refRect  = supEl.getBoundingClientRect();
        const tipRect  = tooltip.getBoundingClientRect();
        const vw       = window.innerWidth;
        const vh       = window.innerHeight;
        const scrollX  = window.scrollX;
        const scrollY  = window.scrollY;

        // Prefer below; flip above if not enough room.
        const spaceBelow = vh - refRect.bottom;
        const spaceAbove = refRect.top;
        const placeAbove = spaceBelow < tipRect.height + TOOLTIP_OFFSET && spaceAbove > spaceBelow;

        let top = placeAbove
            ? scrollY + refRect.top  - tipRect.height - TOOLTIP_OFFSET
            : scrollY + refRect.bottom + TOOLTIP_OFFSET;

        // Horizontal: centre on ref, clamp to viewport with margin.
        const MARGIN = 12;
        let left = scrollX + refRect.left + refRect.width / 2 - tipRect.width / 2;
        left = Math.max( scrollX + MARGIN, Math.min( left, scrollX + vw - tipRect.width - MARGIN ) );

        // Shrink if tooltip is wider than viewport.
        const maxW = vw - MARGIN * 2;
        if ( tipRect.width > maxW ) {
            tooltip.style.maxWidth = maxW + 'px';
            left = scrollX + MARGIN;
        }

        tooltip.style.left = left + 'px';
        tooltip.style.top  = top  + 'px';
        tooltip.classList.add( placeAbove ? 'sfn-tooltip--above' : 'sfn-tooltip--below' );

        // Position arrow to point at the ref marker.
        const arrow     = tooltip.querySelector( '.sfn-tooltip__arrow' );
        const arrowLeft = ( scrollX + refRect.left + refRect.width / 2 ) - left;
        arrow.style.left = Math.max( 8, Math.min( arrowLeft, tipRect.width - 8 ) ) + 'px';
    }

    // ── Inline expand ─────────────────────────────────────────────────────────
    // State map: supEl → noteEl. Tracks every created inline note.
    const inlineNotes = new Map();

    function closeAllInline( exceptSupEl = null ) {
        inlineNotes.forEach( ( noteEl, supEl ) => {
            if ( supEl === exceptSupEl ) return;
            _setInlineOpen( supEl, noteEl, false );
        } );
    }

    function toggleInline( supEl ) {
        // Get or create the note element for this marker.
        let noteEl = inlineNotes.get( supEl );
        const isOpen = noteEl && noteEl.style.display !== 'none';

        // Always close all others first — accordion.
        closeAllInline( supEl );

        if ( isOpen ) {
            // Same marker clicked again → collapse.
            _setInlineOpen( supEl, noteEl, false );
        } else {
            // Open this note.
            if ( ! noteEl ) {
                noteEl = document.createElement( 'div' );
                noteEl.className   = 'sfn-inline-note';
                noteEl.dataset.for = supEl.id;
                noteEl.setAttribute( 'role', 'note' );
                noteEl.innerHTML   = supEl.dataset.content || '';

                // Close button.
                const btn = document.createElement( 'button' );
                btn.className = 'sfn-inline-note__close';
                btn.type      = 'button';
                btn.innerHTML = '&times;';
                btn.setAttribute( 'aria-label', 'Close footnote' );
                btn.addEventListener( 'click', ( e ) => {
                    e.stopPropagation();
                    _setInlineOpen( supEl, noteEl, false );
                } );
                noteEl.appendChild( btn );

                // Insert after the sup, register in map.
                supEl.insertAdjacentElement( 'afterend', noteEl );
                inlineNotes.set( supEl, noteEl );
            }

            _setInlineOpen( supEl, noteEl, true );
            window.SFN.setActive?.( supEl, null );
        }
    }

    function _setInlineOpen( supEl, noteEl, open ) {
        const anchor = supEl?.querySelector( 'a' );
        if ( open ) {
            noteEl.style.display = 'block';
            noteEl.setAttribute( 'aria-hidden', 'false' );
            anchor?.setAttribute( 'aria-expanded', 'true' );
        } else {
            noteEl.style.display = 'none';
            noteEl.setAttribute( 'aria-hidden', 'true' );
            anchor?.setAttribute( 'aria-expanded', 'false' );
            window.SFN.clearActive?.();
        }
    }

    // Keep public API name consistent.
    function setInlineOpen( supEl, noteEl, open ) {
        _setInlineOpen( supEl, noteEl, open );
    }

    // ── Mobile drawer ─────────────────────────────────────────────────────────

    function openDrawer( supEl ) {
        const content = supEl.dataset.content || '';
        const label   = supEl.querySelector( 'a' )?.textContent || '';

        drawer.querySelector( '.sfn-drawer__label' ).textContent = `${ sfnL10n( 'Footnote' ) } ${ label }`;
        drawer.querySelector( '.sfn-drawer__body'  ).textContent = content;

        drawerBackdrop.classList.add( 'sfn-drawer-backdrop--visible' );
        drawer.classList.add( 'sfn-drawer--open' );
        drawer.setAttribute( 'aria-hidden', 'false' );
        document.body.style.overflow = 'hidden';

        // Move focus into drawer for accessibility.
        const closeBtn = drawer.querySelector( '.sfn-drawer__close' );
        setTimeout( () => closeBtn?.focus(), 50 );

        activeRef = supEl;
        supEl.querySelector( 'a' )?.setAttribute( 'aria-expanded', 'true' );
    }

    function closeDrawer() {
        drawer.classList.remove( 'sfn-drawer--open' );
        drawerBackdrop.classList.remove( 'sfn-drawer-backdrop--visible' );
        drawer.setAttribute( 'aria-hidden', 'true' );
        drawer.style.transform = '';
        document.body.style.overflow = '';

        if ( activeRef ) {
            activeRef.querySelector( 'a' )?.setAttribute( 'aria-expanded', 'false' );
            activeRef.querySelector( 'a' )?.focus();
            activeRef = null;
        }
    }

    // ── Swipe-to-dismiss ──────────────────────────────────────────────────────

    function onDrawerTouchStart( e ) {
        drawerStartY   = e.touches[0].clientY;
        drawerCurrentY = drawerStartY;
        drawerDragging = true;
    }

    function onDrawerTouchMove( e ) {
        if ( ! drawerDragging ) return;
        drawerCurrentY = e.touches[0].clientY;
        const delta = drawerCurrentY - drawerStartY;
        if ( delta < 0 ) return; // don't allow dragging upward
        e.preventDefault();
        drawer.style.transform = `translateY(${ delta }px)`;
    }

    function onDrawerTouchEnd() {
        drawerDragging = false;
        const delta = drawerCurrentY - drawerStartY;
        if ( delta > DRAWER_THRESHOLD ) {
            closeDrawer();
        } else {
            // Snap back.
            drawer.style.transform = '';
        }
    }

    // ── Side panel ────────────────────────────────────────────────────────────

    function initSidePanel() {
        // Only activate on desktop for sidepanel-mode footnotes.
        if ( isMobile() ) return;
        if ( ! document.querySelector( 'sup.sfn-ref[data-mode="sidepanel"]' ) ) return;

        sidePanel.style.display = 'block';

        // IntersectionObserver: highlight sidepanel as refs scroll into view.
        const observer = new IntersectionObserver( onRefIntersect, {
            root: null,
            rootMargin: '-40% 0px -40% 0px',
            threshold: 0,
        } );

        window.SFN.getRefs?.().forEach( ref => {
            if ( ref.dataset.mode === 'sidepanel' ) observer.observe( ref );
        } );
    }

    function onRefIntersect( entries ) {
        entries.forEach( entry => {
            if ( ! entry.isIntersecting ) return;
            const supEl  = entry.target;
            const content = supEl.dataset.content || '';
            const label   = supEl.querySelector( 'a' )?.textContent || '';

            sidePanel.querySelector( '.sfn-sidepanel__label' ).textContent = `${ sfnL10n( 'Footnote' ) } ${ label }`;
            sidePanel.querySelector( '.sfn-sidepanel__body'  ).textContent = content;
            sidePanel.setAttribute( 'aria-hidden', 'false' );
            sidePanel.classList.add( 'sfn-sidepanel--active' );

            window.SFN.setActive?.( supEl, null );
        } );
    }

    // ── Utilities ─────────────────────────────────────────────────────────────

    function isMobile() {
        return window.innerWidth <= MOBILE_BP;
    }

    function sfnL10n( key ) {
        return ( window.sfnData?.i18n || {} )[ key ] || key;
    }

    function trackClick( supEl ) {
        const analytics = window.sfnAnalytics;
        if ( ! analytics?.enabled || ! analytics?.endpoint ) return;

        const footnoteId = supEl.id || supEl.querySelector( 'a' )?.getAttribute( 'href' )?.slice( 1 );
        if ( ! footnoteId ) return;

        // Fire-and-forget — no UI impact on failure.
        fetch( analytics.endpoint, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce':   analytics.nonce,
            },
            body: JSON.stringify( {
                footnote_id: footnoteId,
                post_id:     analytics.postId,
            } ),
            keepalive: true, // survives page navigation
        } ).catch( () => {} ); // silent fail
    }

    // ── Boot ──────────────────────────────────────────────────────────────────

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

    // ── Reposition open tooltip on resize ────────────────────────────────────
    window.addEventListener( 'sfn:resize', () => {
        if ( activeRef && tooltip.classList.contains( 'sfn-tooltip--visible' ) ) {
            positionTooltip( activeRef );
        }
        // If viewport crossed mobile breakpoint, close tooltip and hide drawer.
        if ( isMobile() ) {
            hideTooltip();
        } else {
            closeDrawer();
        }
    } );

    // ── Focus trap inside drawer ──────────────────────────────────────────────
    drawer.addEventListener( 'keydown', ( e ) => {
        if ( e.key !== 'Tab' ) return;
        const focusable = Array.from(
            drawer.querySelectorAll( 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])' )
        ).filter( el => ! el.disabled );
        if ( ! focusable.length ) return;

        const first = focusable[0];
        const last  = focusable[ focusable.length - 1 ];

        if ( e.shiftKey ) {
            if ( document.activeElement === first ) {
                e.preventDefault();
                last.focus();
            }
        } else {
            if ( document.activeElement === last ) {
                e.preventDefault();
                first.focus();
            }
        }
    } );

    // ── Extend public API ─────────────────────────────────────────────────────
    Object.assign( window.SFN, {
        showTooltip,
        hideTooltip,
        openDrawer,
        closeDrawer,
        toggleInline,
        closeAllInline,
    } );

} )();
