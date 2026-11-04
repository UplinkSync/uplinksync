/**
 * Richer Quiet Light — the only page script on the five file-driven templates (replaces the
 * GSAP + ScrollTrigger motion layer there). Enhancement only: with JS off, slow or blocked,
 * every section is already visible and the gallery shows every frame.
 *
 * 1. Reveal: only items that start BELOW the fold are hidden, and only when the visitor has
 *    not asked for reduced motion; they fade in (200 ms) as they scroll into view. Anything
 *    already on screen is never touched, so a static render can never look washed out.
 * 2. Gallery filter pills (drone page).
 * 3. Silent loops (drone page vertical cuts, video[data-uls-loop]): muted, no native controls.
 *    They play only while on screen (IntersectionObserver) and pause off screen to save data.
 *    Each gets a small play/pause button (WCAG 2.2.2). Reduced motion: never auto-starts — the
 *    poster shows with the play button. If the browser refuses autoplay (e.g. iOS low-power),
 *    the same button is the way in; native controls are never added back.
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

	var loops = document.querySelectorAll( 'video[data-uls-loop]' );
	var loopIo = 'IntersectionObserver' in window ? new IntersectionObserver( function ( entries ) {
		entries.forEach( function ( e ) {
			var v = e.target;
			if ( e.isIntersecting ) {
				if ( v.paused && ! v.ulsHeld ) {
					v.ulsPlay();
				}
			} else if ( ! v.paused ) {
				v.pause();
			}
		} );
	}, { threshold: 0.25 } ) : null;
	Array.prototype.forEach.call( loops, function ( v ) {
		var name = v.getAttribute( 'aria-label' ) || 'clip';
		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'loop-btn';
		v.muted = true;
		v.defaultMuted = true;
		v.removeAttribute( 'controls' );
		v.ulsHeld = reduce; // reduced motion: wait for a press
		function sync() {
			var on = ! v.paused;
			btn.classList.toggle( 'is-playing', on );
			btn.setAttribute( 'aria-label', ( on ? 'Pause: ' : 'Play: ' ) + name );
		}
		v.ulsPlay = function () {
			var p;
			try {
				p = v.play();
			} catch ( err ) {
				sync();
				return;
			}
			if ( p && p.catch ) {
				p.catch( sync );
			}
		};
		btn.addEventListener( 'click', function () {
			if ( v.paused ) {
				v.ulsHeld = false;
				v.ulsPlay();
			} else {
				v.ulsHeld = true;
				v.pause();
			}
		} );
		v.addEventListener( 'play', sync );
		v.addEventListener( 'pause', sync );
		v.parentNode.insertBefore( btn, v.nextSibling );
		sync();
		if ( loopIo ) {
			loopIo.observe( v );
		} else if ( ! v.ulsHeld ) {
			v.ulsPlay();
		}
	} );
}() );
