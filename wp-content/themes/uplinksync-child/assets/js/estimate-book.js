/**
 * UplinkSync — Estimate-as-modal (*** #4, DOM layer).
 *
 * Owner request: the "Estimate your project" estimator should be a POP-UP MODAL
 * (overlay), not inline, and booking should be a cal.com pop-up carrying the
 * visitor's context so nothing is re-entered.
 *
 * SEPARATION OF CONCERNS: all cal.com behaviour (the lazy Embed loader, the
 * booking dialog, and the inline embed) lives in ONE place — the
 * uplinksync-booking-ctas.php mu-plugin — so there is a single Cal loader on the
 * page. THIS file only does the DOM work:
 *
 *   1. Move the DB-authored estimator (#uls-estimator) into an accessible modal
 *      opened by a trigger button ("Estimate your project"). IDs are preserved,
 *      so the estimator's own inline logic keeps working unchanged.
 *   2. Add a "Book a time" button inside the estimator that opens the site's
 *      booking dialog on the UAV path (data-uls-book-open + data-uls-book-intent),
 *      handing the estimator's captured context across so nothing is re-entered.
 *      Booking then completes INSIDE that dialog against team/uplinksync/* — it
 *      never navigates to book.uplinksync.com.
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

		// Pre-existing on-page CTAs anchored at #uls-estimator (e.g. the lower
		// "Prefer a ballpark first? — Get your instant estimate" link) used to
		// scroll to the inline estimator. Now that the estimator lives in this
		// modal, those anchors would dead-link to a hidden element, so re-point
		// them at the pop-up: exactly one estimate flow, no broken href.
		document.querySelectorAll( 'a[href="#uls-estimator"]' ).forEach( function ( a ) {
			a.addEventListener( 'click', function ( e ) { e.preventDefault(); ctl.open( a ); } );
		} );

		// "Book a time" -> the site's booking dialog on the UAV path. The dialog
		// (mu-plugin) collects/holds the details and mounts the inline cal.com
		// embed against team/uplinksync/uav-consult, so booking finishes on this
		// page. This direct listener runs before the mu-plugin's delegated
		// document handler, so the estimator context is already in the dialog's
		// fields by the time it opens.
		if ( ! est.querySelector( '.uls-est-book' ) ) {
			var book = document.createElement( 'button' );
			book.type = 'button';
			book.className = 'uls-est-btn uls-est-btn--primary uls-est-book';
			book.setAttribute( 'data-uls-book-open', 'uls-book-modal' );
			book.setAttribute( 'data-uls-book-intent', 'uav' );
			book.setAttribute( 'aria-haspopup', 'dialog' );
			book.setAttribute( 'aria-controls', 'uls-book-modal' );
			book.textContent = 'Book a time';
			book.addEventListener( 'click', handoffEstimateContext );
			var actions = est.querySelector( '.uls-est-actions' ) || built.panel;
			actions.appendChild( book );
		}
	}

	/**
	 * Copy whatever the estimator captured into the booking dialog's fields, so
	 * the visitor never retypes it. Only fills blanks — never clobbers something
	 * the visitor already typed in the dialog.
	 */
	function handoffEstimateContext() {
		function v( id ) { var el = document.getElementById( id ); return el ? ( el.value || '' ).trim() : ''; }
		function label( id ) {
			var el = document.getElementById( id );
			if ( ! el || el.selectedIndex < 0 || ! el.options ) { return ''; }
			var t = ( el.options[ el.selectedIndex ].text || '' ).trim();
			return t.indexOf( 'Choose' ) === 0 ? '' : t;
		}
		function fill( id, value ) {
			var el = document.getElementById( id );
			if ( el && value && ! ( el.value || '' ).trim() ) { el.value = value; }
		}

		fill( 'uls-bk-name', v( 'uls-est-name' ) );
		fill( 'uls-bk-email', v( 'uls-est-email' ) );

		var bits = [];
		var line = label( 'uls-est-line' );
		var miles = v( 'uls-est-miles' );
		var timing = label( 'uls-est-timing' );
		if ( line ) { bits.push( 'Service: ' + line ); }
		if ( miles ) { bits.push( 'Distance from Idaho Falls: ' + miles + ' miles' ); }
		if ( timing ) { bits.push( 'Timing: ' + timing ); }
		if ( bits.length ) {
			fill( 'uls-bk-notes', 'Estimate from uplinksync.com — ' + bits.join( ' · ' ) );
		}

		// Map the estimator's service line onto the UAV event type's own fields.
		var lineVal = v( 'uls-est-line' );
		var siteType = {
			real_estate: 'residential-roof',
			mapping: 'land-acreage',
			inspection: 'commercial-building',
			tower: 'other'
		}[ lineVal ];
		fill( 'uls-bk-sitetype', siteType );

		var deliverables = {
			real_estate: [ 'photos', 'video' ],
			mapping: [ 'orthomosaic' ],
			inspection: [ 'inspection-report' ],
			tower: [ 'inspection-report' ]
		}[ lineVal ];
		var boxes = document.querySelectorAll( 'input[name="uls-bk-deliverables"]' );
		var anyChecked = Array.prototype.some.call( boxes, function ( b ) { return b.checked; } );
		if ( deliverables && ! anyChecked ) {
			Array.prototype.forEach.call( boxes, function ( b ) {
				if ( deliverables.indexOf( b.value ) !== -1 ) { b.checked = true; }
			} );
		}
	}

	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', init ); }
	else { init(); }
}() );
