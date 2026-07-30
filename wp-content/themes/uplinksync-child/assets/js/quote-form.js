/**
 * UplinkSync quote form behavior (***-42, quote-flow-spec.md sections 4 & 7).
 *
 * Field ID contract (must match the WP Admin form build — see
 * docs/quote-form-build-guide.md):
 *   - Service Interest field HTML id: "service-interest"
 *   - Form wrapper id: "quote-form" (master form on /contact) or
 *     "quote-form-mini" (inline mini-form on /services/managed-it)
 *
 * ***-300 (R8, UX/UI brief §3) — smart-defaults / progressive disclosure to
 * lower completion friction. Two additions, both fully no-JS-safe (the form is
 * complete and submittable with scripting disabled; JS only reduces friction):
 *
 *   1. Smart default for Service Interest. On top of the existing explicit
 *      `?service=` query prefill, if the visitor arrived from a service page on
 *      this same site (e.g. /services/managed-it or /drone-services) and hasn't
 *      set the field yet, preselect the matching option. The field stays fully
 *      editable — this only changes the starting point, never fabricates data.
 *
 *   2. Progressive disclosure of the single optional field ("How can we help?"
 *      textarea). Collapsed behind an accessible toggle so the initial form
 *      shows only the required fields and reads shorter. Auto-expands if the
 *      field already has content (e.g. browser restore) or on validation.
 */
( function () {
	'use strict';

	var SERVICE_MAP = {
		'managed-it': 'Managed IT Services',
		automation: 'Business Automation',
		web: 'Web Development',
		drone: 'Drone Services',
	};

	// Path fragments (this-site referrer) mapped to a Service Interest option.
	// Used only as a starting default when the visitor didn't pass ?service=.
	var REFERRER_SERVICE_MAP = [
		{ match: 'managed-it', label: 'Managed IT Services' },
		{ match: 'automation', label: 'Business Automation' },
		{ match: 'web-develop', label: 'Web Development' },
		{ match: 'drone', label: 'Drone Services' },
	];

	function selectOptionByLabel( select, targetLabel ) {
		for ( var i = 0; i < select.options.length; i++ ) {
			if ( select.options[ i ].text.trim() === targetLabel ) {
				select.selectedIndex = i;
				select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				return true;
			}
		}
		return false;
	}

	// True when the select is still on its first (default) option, i.e. the
	// visitor hasn't chosen anything and we're free to apply a smart default.
	function isSelectUntouched( select ) {
		return select.selectedIndex <= 0;
	}

	function serviceLabelFromReferrer() {
		var ref = document.referrer;
		if ( ! ref ) {
			return null;
		}
		var refUrl;
		try {
			refUrl = new URL( ref );
		} catch ( e ) {
			return null;
		}
		// Same-site only — ignore external referrers (search engines, ads).
		if ( refUrl.hostname !== window.location.hostname ) {
			return null;
		}
		var path = refUrl.pathname.toLowerCase();
		for ( var i = 0; i < REFERRER_SERVICE_MAP.length; i++ ) {
			if ( path.indexOf( REFERRER_SERVICE_MAP[ i ].match ) !== -1 ) {
				return REFERRER_SERVICE_MAP[ i ].label;
			}
		}
		return null;
	}

	function prefillServiceInterest() {
		var params = new URLSearchParams( window.location.search );
		var service = params.get( 'service' );
		var explicitLabel =
			service && SERVICE_MAP.hasOwnProperty( service )
				? SERVICE_MAP[ service ]
				: null;

		// Referrer-derived smart default (only when this-site + no explicit param).
		var referrerLabel = explicitLabel ? null : serviceLabelFromReferrer();

		var chosenLabel = explicitLabel || referrerLabel;
		if ( ! chosenLabel ) {
			return;
		}

		var selects = document.querySelectorAll( '#service-interest' );
		selects.forEach( function ( select ) {
			// Explicit ?service= always wins; a referrer default only fills an
			// untouched field so we never override a real selection.
			if ( explicitLabel || isSelectUntouched( select ) ) {
				selectOptionByLabel( select, chosenLabel );
			}
		} );
	}

	function passUtmParams() {
		var params = new URLSearchParams( window.location.search );
		var utmKeys = [ 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ];
		var utmValues = {};
		var hasUtm = false;

		utmKeys.forEach( function ( key ) {
			var value = params.get( key );
			if ( value ) {
				utmValues[ key ] = value;
				hasUtm = true;
			}
		} );

		if ( ! hasUtm ) {
			return;
		}

		document.querySelectorAll( 'form[id^="quote-form"]' ).forEach( function ( form ) {
			utmKeys.forEach( function ( key ) {
				if ( ! utmValues[ key ] ) {
					return;
				}
				var input = document.createElement( 'input' );
				input.type = 'hidden';
				input.name = key;
				input.value = utmValues[ key ];
				form.appendChild( input );
			} );
		} );
	}

	/**
	 * Progressive disclosure for the optional free-text field.
	 *
	 * Finds the field wrapper holding the `help-text` textarea and hides it
	 * behind a toggle button. Because this runs from JS, a no-JS visitor sees
	 * the textarea normally — the field is never removed, only visually
	 * collapsed and re-shown on demand.
	 */
	function collapseOptionalDetails() {
		document.querySelectorAll( 'form[id^="quote-form"]' ).forEach( function ( form ) {
			var textarea = form.querySelector( 'textarea[name="help-text"]' );
			if ( ! textarea ) {
				return;
			}
			var wrapper = textarea.closest( '.uls-quote-field' );
			if ( ! wrapper ) {
				return;
			}

			// Idempotent: if we've already wired this wrapper, just re-sync its
			// open/closed state against the current textarea content.
			var toggle = wrapper.previousElementSibling;
			var alreadyWired =
				wrapper.getAttribute( 'data-uls-disclosure' ) === 'ready' &&
				toggle &&
				toggle.classList.contains( 'uls-details-toggle' );

			if ( ! alreadyWired ) {
				wrapper.setAttribute( 'data-uls-disclosure', 'ready' );

				var regionId = 'uls-optional-details-' + Math.random().toString( 36 ).slice( 2, 8 );
				wrapper.id = regionId;
				wrapper.classList.add( 'uls-optional-details' );

				toggle = document.createElement( 'button' );
				toggle.type = 'button';
				toggle.className = 'uls-details-toggle';
				toggle.setAttribute( 'aria-controls', regionId );
				toggle.textContent = 'Add details about your needs (optional)';

				wrapper.parentNode.insertBefore( toggle, wrapper );

				toggle.addEventListener( 'click', function () {
					var willExpand = toggle.getAttribute( 'aria-expanded' ) !== 'true';
					setExpanded( toggle, wrapper, willExpand );
					if ( willExpand ) {
						textarea.focus();
					}
				} );
			}

			// Keep it open if the field already holds text (browser restore, or
			// an AJAX re-render after a validation error preserving input).
			setExpanded( toggle, wrapper, textarea.value.trim() !== '' );
		} );
	}

	function setExpanded( toggle, wrapper, expanded ) {
		toggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
		wrapper.hidden = ! expanded;
	}

	function init() {
		prefillServiceInterest();
		passUtmParams();
		collapseOptionalDetails();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	// CF7 re-renders/validates the form via AJAX; re-apply the disclosure UI so
	// the toggle survives and reopens if input was kept.
	document.addEventListener( 'wpcf7invalid', collapseOptionalDetails );
	document.addEventListener( 'wpcf7mailsent', collapseOptionalDetails );
} )();
