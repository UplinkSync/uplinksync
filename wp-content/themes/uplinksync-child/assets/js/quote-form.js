/**
 * UplinkSync quote form behavior (***-42, quote-flow-spec.md sections 4 & 7).
 *
 * Field ID contract (must match the WP Admin form build — see
 * docs/quote-form-build-guide.md):
 *   - Service Interest field HTML id: "service-interest"
 *   - Form wrapper id: "quote-form" (master form on /contact) or
 *     "quote-form-mini" (inline mini-form on /services/managed-it)
 */
( function () {
	'use strict';

	var SERVICE_MAP = {
		'managed-it': 'Managed IT Services',
		automation: 'Business Automation',
		web: 'Web Development',
		drone: 'Drone Services',
	};

	function prefillServiceInterest() {
		var params = new URLSearchParams( window.location.search );
		var service = params.get( 'service' );
		if ( ! service || ! SERVICE_MAP.hasOwnProperty( service ) ) {
			return;
		}

		var selects = document.querySelectorAll( '#service-interest' );
		selects.forEach( function ( select ) {
			var targetLabel = SERVICE_MAP[ service ];
			for ( var i = 0; i < select.options.length; i++ ) {
				if ( select.options[ i ].text.trim() === targetLabel ) {
					select.selectedIndex = i;
					select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					break;
				}
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

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			prefillServiceInterest();
			passUtmParams();
		} );
	} else {
		prefillServiceInterest();
		passUtmParams();
	}
} )();
