/**
 * UplinkSync — motion layer.
 *
 * Cinematic feel comes from restraint: a few well-timed reveals and slow
 * parallax, not everything moving at once. Effects are opt-in per element via
 * data attributes so nothing animates unless it was asked to.
 *
 * Accessibility is not optional here: if the visitor prefers reduced motion we
 * do not initialise GSAP at all and every element is left in its final visible
 * state. An animation library that hides content until a scroll event is an
 * accessibility failure when the animation never runs.
 */
( function () {
	'use strict';

	var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var root   = document.documentElement;

	// Mark the document so CSS can hold pre-animation state ONLY when motion is on.
	// Without this, a JS failure would leave content invisible forever.
	if ( ! reduce && window.gsap ) {
		root.classList.add( 'uls-motion' );
	}

	if ( reduce || ! window.gsap ) {
		return;
	}

	var gsap = window.gsap;
	if ( window.ScrollTrigger ) {
		gsap.registerPlugin( window.ScrollTrigger );
	}

	/**
	 * Reveal: fade + rise as the element enters the viewport.
	 * Usage: <div data-uls-reveal>  |  data-uls-reveal-delay="0.1"
	 */
	gsap.utils.toArray( '[data-uls-reveal]' ).forEach( function ( el ) {
		gsap.fromTo(
			el,
			{ opacity: 0, y: 24 },
			{
				opacity: 1,
				y: 0,
				duration: 0.9,
				ease: 'power2.out',
				delay: parseFloat( el.getAttribute( 'data-uls-reveal-delay' ) ) || 0,
				scrollTrigger: { trigger: el, start: 'top 85%', once: true }
			}
		);
	} );

	/**
	 * Stagger: reveal direct children in sequence (service cards, stat band).
	 * Usage: <div data-uls-stagger>
	 */
	gsap.utils.toArray( '[data-uls-stagger]' ).forEach( function ( group ) {
		var kids = group.children;
		if ( ! kids.length ) {
			return;
		}
		gsap.fromTo(
			kids,
			{ opacity: 0, y: 20 },
			{
				opacity: 1,
				y: 0,
				duration: 0.7,
				ease: 'power2.out',
				stagger: 0.09,
				scrollTrigger: { trigger: group, start: 'top 85%', once: true }
			}
		);
	} );

	/**
	 * Parallax: slow background drift for full-bleed aerial imagery/video.
	 * Deliberately subtle — big movement reads as cheap, not cinematic.
	 * Usage: <div data-uls-parallax="0.15">
	 */
	gsap.utils.toArray( '[data-uls-parallax]' ).forEach( function ( el ) {
		var amount = parseFloat( el.getAttribute( 'data-uls-parallax' ) ) || 0.15;
		gsap.to( el, {
			yPercent: amount * 100,
			ease: 'none',
			scrollTrigger: { trigger: el, start: 'top bottom', end: 'bottom top', scrub: true }
		} );
	} );
}() );
