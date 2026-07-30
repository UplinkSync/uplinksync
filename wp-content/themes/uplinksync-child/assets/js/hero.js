/*
 * ***-391 / ***-183 v2.0 — hero loop runtime.
 *
 * Enhancement-only. Without this script the hero renders a complete static
 * poster hero (the <video> stays at opacity 0 per hero.css). This script:
 *   1. Respects prefers-reduced-motion: it never starts the video.
 *   2. Defers all video bytes: the <source> is set preload="none"; we only ask
 *      the browser to load/play once the hero is on screen (IntersectionObserver)
 *      and the tab is visible.
 *   3. Fades the video in (adds .is-playing) only after playback actually
 *      begins, so there is never a black flash or a poster/video jump.
 *   4. Optionally rotates through the curated loop set (data-hero-sources) so
 *      the hero is not a single repeating clip, swapping on each loop boundary.
 *
 * Any failure (autoplay blocked, network error, decode error) leaves the poster
 * showing — the hero degrades to a still image, never to a blank frame.
 */
(function () {
	'use strict';

	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	if (reduce) {
		return; // Poster-only. Do not touch the video at all.
	}

	function initHero(hero) {
		var video = hero.querySelector('.uls-hero__video');
		if (!video) {
			return;
		}

		// Candidate loop URLs (JSON array). Fall back to the single baked <source>.
		var sources = [];
		try {
			sources = JSON.parse(video.getAttribute('data-hero-sources') || '[]');
		} catch (e) {
			sources = [];
		}
		if (!Array.isArray(sources) || !sources.length) {
			var s = video.querySelector('source');
			if (s && s.getAttribute('src')) {
				sources = [s.getAttribute('src')];
			}
		}
		if (!sources.length) {
			return; // Nothing to play — poster stays.
		}

		var index = 0;
		var started = false;

		function reveal() {
			if (!started) {
				started = true;
				hero.querySelector('.uls-hero__video').classList.add('is-playing');
			}
		}

		function tryPlay() {
			var p = video.play();
			if (p && typeof p.then === 'function') {
				p.then(reveal).catch(function () {
					/* Autoplay blocked or interrupted: leave the poster showing. */
				});
			} else {
				reveal();
			}
		}

		function load(i) {
			index = (i + sources.length) % sources.length;
			// Only assign a real src now (deferred load), then play.
			if (video.getAttribute('src') !== sources[index]) {
				video.setAttribute('src', sources[index]);
			}
			video.load();
			tryPlay();
		}

		// Rotate to the next loop when this one ends (only meaningful if we have
		// more than one). Because each clip is a seamless boomerang, we let it
		// loop a couple of times before advancing so it does not feel frantic.
		if (sources.length > 1) {
			video.loop = false;
			var plays = 0;
			video.addEventListener('ended', function () {
				plays += 1;
				if (plays >= 2) {
					plays = 0;
					load(index + 1);
				} else {
					tryPlay();
				}
			});
		}

		video.addEventListener('playing', reveal);

		// Pause work when the tab is hidden (saves battery/data).
		document.addEventListener('visibilitychange', function () {
			if (document.hidden) {
				video.pause();
			} else if (started) {
				tryPlay();
			}
		});

		// Only begin when the hero scrolls into view (it is usually above the
		// fold, so this fires immediately, but it protects hero-in-content uses).
		function begin() {
			load(0);
		}

		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (entries, obs) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) {
						obs.disconnect();
						begin();
					}
				});
			}, { rootMargin: '200px' });
			io.observe(hero);
		} else {
			begin();
		}
	}

	function ready() {
		var heroes = document.querySelectorAll('.uls-hero');
		for (var i = 0; i < heroes.length; i++) {
			initHero(heroes[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', ready);
	} else {
		ready();
	}
})();
