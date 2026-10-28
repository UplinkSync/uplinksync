/**
 * Richer Quiet Light — the only page script on the five file-driven templates (replaces the
 * GSAP + ScrollTrigger motion layer there). Enhancement only: with JS off, slow or blocked,
 * every section is already visible and the gallery shows every frame.
 *
 * 1. Reveal: only items that start BELOW the fold are hidden, and only when the visitor has
 *    not asked for reduced motion; they fade in (200 ms) as they scroll into view. Anything
 *    already on screen is never touched, so a static render can never look washed out.
 * 2. Gallery filter pills (drone page).
 */
( function () {
	'use strict';

	var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	if ( ! reduce && 'IntersectionObserver' in window ) {
		var items = document.querySelectorAll( '.uls-site .reveal' );
		var io = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( e ) {
				if ( e.isIntersecting ) {
					e.target.classList.add( 'in' );
					io.unobserve( e.target );
				}
			} );
		}, { rootMargin: '0px 0px -40px 0px' } );
		Array.prototype.forEach.call( items, function ( el ) {
			if ( el.getBoundingClientRect().top > window.innerHeight ) {
				el.classList.add( 'pre' );
				io.observe( el );
			}
		} );
		// Printing or a page snapshot must show everything.
		window.addEventListener( 'beforeprint', function () {
			Array.prototype.forEach.call( items, function ( el ) {
				el.classList.add( 'in' );
			} );
		} );
	}

	var bar = document.querySelector( '[data-uls-filters]' );
	var gallery = document.querySelector( '[data-uls-gallery]' );
	if ( bar && gallery ) {
		bar.hidden = false;
		bar.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( 'button[data-f]' );
			if ( ! btn ) {
				return;
			}
			var f = btn.getAttribute( 'data-f' );
			bar.querySelectorAll( 'button' ).forEach( function ( b ) {
				b.setAttribute( 'aria-pressed', b === btn ? 'true' : 'false' );
			} );
			gallery.querySelectorAll( 'figure[data-cat]' ).forEach( function ( fig ) {
				fig.hidden = 'all' !== f && fig.getAttribute( 'data-cat' ) !== f;
			} );
		} );
	}
}() );
