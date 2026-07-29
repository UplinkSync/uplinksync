/**
 * UplinkSync — Estimate-as-modal (*** #4, DOM layer).
 *
 * Owner request: the "Estimate your project" estimator should be a POP-UP MODAL
 * (overlay), not inline, and booking should be a cal.com pop-up carrying the
 * visitor's context so nothing is re-entered.
 *
 * SEPARATION OF CONCERNS: all cal.com pop-up behaviour (the lazy Embed loader, the
 * [data-cal-link] click handler, and the estimator prefill) lives in ONE place —
 * the uplinksync-booking-ctas.php mu-plugin — so there is a single Cal loader on
 * the page. THIS file only does the DOM work:
 *
 *   1. Move the DB-authored estimator (#uls-estimator) into an accessible modal
 *      opened by a trigger button ("Estimate your project"). IDs are preserved,
 *      so the estimator's own inline logic keeps working unchanged.
 *   2. Add a "Book a time" button inside the estimator that carries
 *      data-cal-link="dirwin/uav-service" + data-cal-prefill="estimate"; the
 *      mu-plugin handler opens the cal.com pop-up, prefilled from the estimator
 *      fields, when it is clicked.
 *
 * Pure progressive enhancement: with JS off the estimator stays inline exactly as
 * today. Accessibility mirrors the CTA chooser modal (role dialog + aria-modal,
 * focus trap, Esc + overlay + close, focus returns to the trigger, scroll-lock,
 * prefers-reduced-motion) and reuses the .uls-book-modal visual system.
 */
( function () {
	'use strict';

	function makeModal( id ) {
		var modal = document.createElement( 'div' );
		modal.className = 'uls-book-modal uls-est-modal';
		modal.id = id;
		modal.setAttribute( 'role', 'dialog' );
		modal.setAttribute( 'aria-modal', 'true' );
		modal.hidden = true;
		var overlay = document.createElement( 'div' );
		overlay.className = 'uls-book-modal__overlay';
		overlay.setAttribute( 'data-uls-modal-close', '1' );
		var panel = document.createElement( 'div' );
		panel.className = 'uls-book-modal__panel';
		panel.setAttribute( 'role', 'document' );
		panel.setAttribute( 'tabindex', '-1' );
		var close = document.createElement( 'button' );
		close.type = 'button';
		close.className = 'uls-book-modal__close';
		close.setAttribute( 'data-uls-modal-close', '1' );
		close.setAttribute( 'aria-label', 'Close' );
		close.innerHTML = '&#215;';
		panel.appendChild( close );
		modal.appendChild( overlay );
		modal.appendChild( panel );
		return { modal: modal, panel: panel };
	}

	function wireModal( modal ) {
		var lastFocus = null;
		function foci() {
			return Array.prototype.slice.call(
				modal.querySelectorAll( 'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])' )
			).filter( function ( el ) { return el.offsetWidth || el.offsetHeight || el.getClientRects().length; } );
		}
		function open( trigger ) {
			lastFocus = trigger || document.activeElement;
			modal.hidden = false;
			document.body.classList.add( 'uls-book-modal-open' );
			( foci()[ 0 ] || modal.querySelector( '.uls-book-modal__panel' ) ).focus();
		}
		function close() {
			if ( modal.hidden ) { return; }
			modal.hidden = true;
			document.body.classList.remove( 'uls-book-modal-open' );
			if ( lastFocus && lastFocus.focus ) { try { lastFocus.focus(); } catch ( e ) {} }
		}
		modal.addEventListener( 'click', function ( e ) {
			if ( e.target.closest && e.target.closest( '[data-uls-modal-close]' ) ) { e.preventDefault(); close(); }
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( modal.hidden ) { return; }
			if ( e.key === 'Escape' || e.keyCode === 27 ) { e.preventDefault(); close(); return; }
			if ( e.key === 'Tab' || e.keyCode === 9 ) {
				var f = foci(); if ( ! f.length ) { e.preventDefault(); return; }
				var first = f[ 0 ], last = f[ f.length - 1 ];
				if ( e.shiftKey && document.activeElement === first ) { e.preventDefault(); last.focus(); }
				else if ( ! e.shiftKey && document.activeElement === last ) { e.preventDefault(); first.focus(); }
				else if ( ! modal.contains( document.activeElement ) ) { e.preventDefault(); first.focus(); }
			}
		} );
		return { open: open };
	}

	function init() {
		var est = document.getElementById( 'uls-estimator' );
		if ( ! est || est.getAttribute( 'data-uls-modalised' ) === '1' ) { return; }
		est.setAttribute( 'data-uls-modalised', '1' );

		var wrap = document.createElement( 'div' );
		wrap.className = 'wp-block-button uls-est-trigger-wrap';
		var trigger = document.createElement( 'button' );
		trigger.type = 'button';
		trigger.className = 'wp-block-button__link wp-element-button uls-est-trigger';
		trigger.setAttribute( 'aria-haspopup', 'dialog' );
		trigger.setAttribute( 'aria-controls', 'uls-estimate-modal' );
		trigger.textContent = 'Estimate your project';
		wrap.appendChild( trigger );

		var built = makeModal( 'uls-estimate-modal' );
		var heading = est.querySelector( 'h2, h3' );
		if ( heading ) {
			if ( ! heading.id ) { heading.id = 'uls-estimate-modal-title'; }
			built.modal.setAttribute( 'aria-labelledby', heading.id );
		}

		est.parentNode.insertBefore( wrap, est );
		built.panel.appendChild( est );        // move (keeps IDs + bound listeners)
		document.body.appendChild( built.modal );

		var ctl = wireModal( built.modal );
		trigger.addEventListener( 'click', function () { ctl.open( trigger ); } );

		// "Book a time" -> cal.com pop-up (opened by the mu-plugin handler),
		// prefilled from the estimator fields (data-cal-prefill="estimate").
		// Drone projects route to the uav-service event type.
		if ( ! est.querySelector( '.uls-est-book' ) ) {
			var book = document.createElement( 'button' );
			book.type = 'button';
			book.className = 'uls-est-btn uls-est-btn--primary uls-est-book';
			book.setAttribute( 'data-cal-link', 'dirwin/uav-service' );
			book.setAttribute( 'data-cal-prefill', 'estimate' );
			book.textContent = 'Book a time';
			var actions = est.querySelector( '.uls-est-actions' ) || built.panel;
			actions.appendChild( book );
		}
	}

	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); }
	else { init(); }
}() );
