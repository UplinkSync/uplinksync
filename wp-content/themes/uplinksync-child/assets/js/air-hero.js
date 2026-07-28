/**
 * UplinkSync — "See it in motion" hero: poster-over-video overlay.
 *
 * The Drone Services page ships the cinematic hero as two flow siblings inside
 * .uls-air-hero: the inline Immich <video> (.uls-air-hero__reel) and a separate
 * still image figure (.uls-air-hero__still) below/beside it. The owner wants the
 * still to sit ON TOP of the paused video as a click-to-play cover, not as a
 * second, redundant picture next to it.
 *
 * This is a pure progressive enhancement — no markup or DB change:
 *   - The still figure is relocated INTO the reel figure and turned into an
 *     absolutely-positioned cover that exactly matches the 16/9 video box.
 *   - A real <button> wraps it (keyboard-activatable, aria-label), with a play
 *     glyph affordance.
 *   - Activating it hides the cover and starts playback; the native <video>
 *     controls take over from there.
 *
 * If this script never runs, the page is unchanged: the <video> keeps its native
 * poster (the same still) + controls and plays normally, and the still figure
 * simply remains below it — i.e. today's behaviour. Nothing is hidden by JS that
 * JS alone can restore, so a failure degrades gracefully.
 *
 * Reduced motion is honoured by CSS (the play-glyph pulse is disabled); the
 * click-to-play interaction itself still works, because muting motion must not
 * remove function.
 */
( function () {
	'use strict';

	function initHero( hero ) {
		var reel  = hero.querySelector( '.uls-air-hero__reel' );
		var still = hero.querySelector( '.uls-air-hero__still' );
		if ( ! reel || ! still ) {
			return;
		}
		var video = reel.querySelector( 'video' );
		if ( ! video ) {
			return;
		}
		// Idempotent: never enhance the same reel twice.
		if ( reel.getAttribute( 'data-uls-overlaid' ) === '1' ) {
			return;
		}
		reel.setAttribute( 'data-uls-overlaid', '1' );

		// Build the click-to-play cover as a real button (keyboard + AT friendly).
		var cover = document.createElement( 'button' );
		cover.type = 'button';
		cover.className = 'uls-air-hero__cover';
		cover.setAttribute( 'aria-label', 'Play the cinematic reel' );

		// Move the existing still figure into the cover (keeps its <picture>/<img>).
		still.parentNode.removeChild( still );
		still.classList.add( 'uls-air-hero__still--overlay' );
		cover.appendChild( still );

		// Play affordance. aria-hidden: the button already carries the label.
		var play = document.createElement( 'span' );
		play.className = 'uls-air-videocard__play';
		play.setAttribute( 'aria-hidden', 'true' );
		cover.appendChild( play );

		reel.appendChild( cover );

		function start() {
			reel.classList.add( 'is-playing' );
			var p = video.play();
			if ( p && typeof p.catch === 'function' ) {
				// Autoplay/gesture edge cases: surface the native controls anyway.
				p.catch( function () {} );
			}
			// Hand keyboard focus to the now-visible native player controls.
			try {
				video.focus( { preventScroll: true } );
			} catch ( e ) {
				try { video.focus(); } catch ( e2 ) {}
			}
		}

		cover.addEventListener( 'click', start );

		// If playback starts by any other route (e.g. native controls become
		// reachable), keep the cover hidden so it can't reappear over the video.
		video.addEventListener( 'play', function () {
			reel.classList.add( 'is-playing' );
		} );
	}

	function init() {
		var heroes = document.querySelectorAll( '.uls-air-hero' );
		for ( var i = 0; i < heroes.length; i++ ) {
			initHero( heroes[ i ] );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
