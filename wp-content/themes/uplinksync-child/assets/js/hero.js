/*
 * ***-391 / ***-183 v2.0 — hero loop runtime.
 *
 * Enhancement-only. Without this script the hero renders a complete static
 * poster hero (every `.uls-hero__video` stays at opacity 0 per hero.css). It
 * drives ANY element with class `uls-hero__video`, wherever it lives — the
 * standalone [hero_loop] section OR a loop integrated into the existing
 * front-page hero's `.airframe` figure (consolidated single-hero placement).
 *
 * Guarantees:
 *   1. Respects prefers-reduced-motion: it never starts the video.
 *   2. Defers all video bytes (preload="none"); only loads/plays once the video
 *      is on screen (IntersectionObserver) and the tab is visible.
 *   3. Fades the video in (adds .is-playing) only after playback actually
 *      begins, so there is never a black flash or a poster/video jump.
 *   4. Optionally rotates a curated loop set (data-hero-sources); with a single
 *      <source> it just native-loops that one clip.
 *
 * Any failure (autoplay blocked, network/decode error) leaves the poster
 * showing — the hero degrades to a still image, never to a blank frame.
 */
(function () {
	'use strict';

	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	if (reduce) {
		return; // Poster-only. Do not touch any video.
	}

	function initVideo(video) {
		if (!video || video.dataset.ulsHeroInit === '1') {
			return;
		}
		video.dataset.ulsHeroInit = '1';

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
		// data-hero-asset carries only the Immich asset UUID; build the anonymous
		// playback URL from the media host + public share key handed over by
		// wp_localize_script (ulsHeroCfg) so the key never lives in a template.
		if (!sources.length) {
			var asset = (video.getAttribute('data-hero-asset') || '').trim();
			var cfg = window.ulsHeroCfg;
			if (asset && cfg && cfg.mediaBase && cfg.shareKey && /^[0-9a-f-]{36}$/i.test(asset)) {
				sources = [cfg.mediaBase + '/api/assets/' + asset + '/video/playback?key=' + cfg.shareKey];
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
				video.classList.add('is-playing');
			}
		}

		function tryPlay() {
			var p = video.play();
			if (p && typeof p.then === 'function') {
				p.then(reveal).catch(function () {
					/* Autoplay blocked/interrupted: leave the poster showing. */
				});
			} else {
				reveal();
			}
		}

		function load(i) {
			index = (i + sources.length) % sources.length;
			if (video.getAttribute('src') !== sources[index]) {
				video.setAttribute('src', sources[index]);
			}
			video.load();
			tryPlay();
		}

		// Rotate to the next loop after a couple of plays (multi-source only).
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

		document.addEventListener('visibilitychange', function () {
			if (document.hidden) {
				video.pause();
			} else if (started) {
				tryPlay();
			}
		});

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
			io.observe(video);
		} else {
			begin();
		}
	}

	function ready() {
		var vids = document.querySelectorAll('.uls-hero__video');
		for (var i = 0; i < vids.length; i++) {
			initVideo(vids[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', ready);
	} else {
		ready();
	}
})();
