/**
 * Smart Footnotes — frontend JS (Phase 1: core)
 *
 * Handles: smooth scroll between markers and footnotes,
 * active-state highlighting, keyboard accessibility,
 * and event delegation foundation for display modes.
 *
 * tooltip.js extends this via window.SFN public API.
 */

// Initialise the shared namespace immediately — before any deferred script runs.
// tooltip.js may execute before this IIFE completes due to defer load order.
window.SFN = window.SFN || {};

( function () {
    'use strict';

    /** Settings passed from PHP via wp_localize_script. */
    const sfn = window.sfnData || {};

    // ── Init ─────────────────────────────────────────────────────────────────

    function init() {
        const list = document.querySelector( '.sfn-footnotes' );
        if ( ! list ) return; // no footnotes on this page

        cacheRefs();
        bindEvents();
        handleHashOnLoad();
    }

    // ── Element cache ─────────────────────────────────────────────────────────

    let refs   = []; // sup.sfn-ref elements
    let notes  = []; // li.sfn-footnote elements

    function cacheRefs() {
        refs  = Array.from( document.querySelectorAll( 'sup.sfn-ref' ) );
        notes = Array.from( document.querySelectorAll( '.sfn-footnote' ) );
    }

    // ── Event delegation ──────────────────────────────────────────────────────

    function bindEvents() {
        // Use delegation on document so PRO JS can also listen.
        document.addEventListener( 'click', onDocClick, { passive: false } );
        document.addEventListener( 'keydown', onDocKeydown );
    }

    function onDocClick( e ) {
        // ── Ref marker clicked ────────────────────────────────────────────────
        const ref   = e.target.closest( 'sup.sfn-ref a' );
        if ( ref ) {
            const supEl = ref.closest( 'sup.sfn-ref' );
            const mode  = supEl?.dataset?.mode || 'classic';

            // PRO modes (tooltip, inline, sidepanel) handle their own click
            // behaviour in tooltip.js. Only scroll for classic mode.
            if ( mode !== 'classic' ) {
                e.preventDefault(); // still prevent anchor jump
                return;             // let tooltip.js take over
            }

            e.preventDefault();
            const targetId = ref.getAttribute( 'href' )?.slice( 1 );
            scrollToNote( targetId, supEl );
            return;
        }

        // ── Back arrow clicked → scroll back to ref ───────────────────────────
        const back = e.target.closest( '.sfn-footnote__back' );
        if ( back ) {
            e.preventDefault();
            const targetId = back.getAttribute( 'href' )?.slice( 1 );
            scrollToRef( targetId );
            return;
        }
    }

    function onDocKeydown( e ) {
        if ( e.key !== 'Enter' && e.key !== ' ' ) return;
        const anchor = document.activeElement?.closest( 'sup.sfn-ref a' );
        if ( anchor ) {
            e.preventDefault();
            // Simulate click — onDocClick will route by mode correctly.
            anchor.click();
        }
    }

    // ── Scroll helpers ────────────────────────────────────────────────────────

    function scrollToNote( id, sourceRef ) {
        const note = document.getElementById( id );
        if ( ! note ) return;

        setActive( sourceRef, note );
        smoothScroll( note );

        // Move focus to the footnote for screen readers.
        note.setAttribute( 'tabindex', '-1' );
        note.focus( { preventScroll: true } );
    }

    function scrollToRef( id ) {
        const ref = document.getElementById( id );
        if ( ! ref ) return;

        clearActive();
        smoothScroll( ref );
        ref.setAttribute( 'tabindex', '-1' );
        ref.focus( { preventScroll: true } );
    }

    function smoothScroll( el ) {
        const prefersReduced = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
        el.scrollIntoView( {
            behavior: prefersReduced ? 'auto' : 'smooth',
            block: 'center',
        } );
    }

    // ── Active state management ───────────────────────────────────────────────

    function setActive( refEl, noteEl ) {
        clearActive();
        refEl?.classList.add( 'sfn-ref--active' );
        noteEl?.classList.add( 'sfn-footnote--active' );
    }

    function clearActive() {
        document.querySelectorAll( '.sfn-ref--active' ).forEach( el => el.classList.remove( 'sfn-ref--active' ) );
        document.querySelectorAll( '.sfn-footnote--active' ).forEach( el => el.classList.remove( 'sfn-footnote--active' ) );
    }

    // ── Handle direct URL hash on page load ───────────────────────────────────

    function handleHashOnLoad() {
        const hash = window.location.hash?.slice( 1 );
        if ( ! hash ) return;

        const note = document.getElementById( hash );
        if ( note?.classList.contains( 'sfn-footnote' ) ) {
            const index = note.id.replace( 'sfn-note-', '' );
            const ref   = document.getElementById( `sfn-ref-${ index }` );
            setTimeout( () => setActive( ref, note ), 100 );
        }
    }

    // ── Boot ─────────────────────────────────────────────────────────────────

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

    // ── Viewport resize: re-cache refs on orientation change ──────────────────
    window.addEventListener( 'resize', debounce( () => {
        cacheRefs();
        // Notify PRO tooltip.js so it can reposition any open tooltip.
        window.dispatchEvent( new CustomEvent( 'sfn:resize' ) );
    }, 200 ) );

    // ── Touch: prevent double-tap zoom on ref markers ─────────────────────────
    document.addEventListener( 'touchend', ( e ) => {
        const ref = e.target.closest( 'sup.sfn-ref a' );
        if ( ref ) e.preventDefault();
    }, { passive: false } );

    // ── Utility ───────────────────────────────────────────────────────────────

    function debounce( fn, delay ) {
        let timer;
        return ( ...args ) => {
            clearTimeout( timer );
            timer = setTimeout( () => fn( ...args ), delay );
        };
    }

    // ── Public API ────────────────────────────────────────────────────────────
    // Merge — not replace — so tooltip.js extensions survive if it ran first.
    Object.assign( window.SFN, {
        setActive,
        clearActive,
        smoothScroll,
        cacheRefs: () => { cacheRefs(); },
        getRefs:   () => refs,
        getNotes:  () => notes,
        settings:  sfn,
    } );

} )();
