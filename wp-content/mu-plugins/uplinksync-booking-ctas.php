<?php
/**
 * Plugin Name: UplinkSync — Booking CTAs + cal.com Embed (***-188)
 * Description: Adds two cal.com booking CTAs to the public site — "Book a consultation" next to the top-of-funnel "Talk to a specialist" / "Request a quote" affordances, and "Book UAV services" on the Air/drone surface. The self-hosted cal.com instance is canonical at https://book.uplinksync.com (***-187). CTAs are real <a href> links (progressive enhancement: no-JS visitors and crawlers get a working booking page), upgraded on click to an inline cal.com modal via the free Embed SDK. The SDK (book.uplinksync.com/embed/embed.js) is lazy-loaded only on first CTA click, so it never blocks page render. No pricing is surfaced anywhere in the booking flow (owner quote-only directive), and the existing "Request a quote" path is left fully intact — booking augments it, it does not replace it. Like the other UplinkSync fixes, the hero/drone markup is produced by the Hostinger AI theme + saved block content in the WP DB (not tracked files), so this mu-plugin rewrites the rendered document on the way out.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical cal.com instance (***-187 — live, verified serving book.uplinksync.com).
 */
const UPLINKSYNC_BOOK_ORIGIN = 'https://book.uplinksync.com';

/**
 * Public event-type slugs the CTAs point at.
 *
 * CONSULT — LIVE, dedicated. The dedicated IT-consultation event type landed
 * under ***-212 and is verified serving (probed HTTP 200). The "Book a
 * consultation" CTA now points at the dedicated `dirwin/it-consult` slug
 * instead of the generic `dirwin/30min`.
 *
 * UAV — LIVE, dedicated. The dedicated UAV/drone event type landed under
 * ***-212 (`dirwin/uav-service`, 60 min, probed HTTP 200) with buffers and
 * booking fields inherited from the working config. The UAV CTA is now enabled
 * and injected on the Air/drone surface. No pricing is surfaced (quote-only).
 */
const UPLINKSYNC_BOOK_CONSULT_SLUG = 'dirwin/it-consult';  // LIVE dedicated (***-212, HTTP 200)
const UPLINKSYNC_BOOK_UAV_SLUG     = 'dirwin/uav-service'; // LIVE dedicated (***-212, HTTP 200)
const UPLINKSYNC_BOOK_UAV_ENABLED  = true;                 // real UAV slug live — UAV CTA on

function uplinksync_book_url( $slug ) {
	return UPLINKSYNC_BOOK_ORIGIN . '/' . ltrim( $slug, '/' );
}

/**
 * Front-end GET only. Never touch admin, REST, AJAX, JSON, feeds or embeds, so
 * the block editor and store management keep rendering their own markup.
 */
function uplinksync_book_should_filter() {
	if ( is_admin() || wp_doing_ajax() || wp_is_json_request() ) {
		return false;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}
	if ( is_feed() || is_embed() ) {
		return false;
	}
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
		return false;
	}
	return true;
}

function uplinksync_book_start_buffer() {
	if ( ! uplinksync_book_should_filter() ) {
		return;
	}
	// Priority 2: opens after the contact/social (0) and homepage-nav (1) buffers.
	// Output buffers unwind LIFO, so this one closes first and its injected CTAs
	// are still visible to the outer passes (which touch disjoint markup).
	ob_start( 'uplinksync_book_rewrite' );
}
add_action( 'template_redirect', 'uplinksync_book_start_buffer', 2 );

/**
 * The "Book a consultation" button markup. Carries data-cal-link so the Embed
 * SDK wires the inline modal; the href is the same slug so it also works with
 * JS disabled (progressive enhancement). Distinct class from the Woo product
 * button so the quote-only CSS backstop never hides it.
 */
function uplinksync_book_consult_markup() {
	$url  = esc_url( uplinksync_book_url( UPLINKSYNC_BOOK_CONSULT_SLUG ) );
	$slug = esc_attr( UPLINKSYNC_BOOK_CONSULT_SLUG );
	return '<div class="wp-block-button uplinksync-book-cta uplinksync-book-consult">'
		. '<a class="wp-block-button__link wp-element-button uls-book-link" '
		. 'href="' . $url . '" '
		. 'data-cal-link="' . $slug . '" '
		. 'data-uls-book="consult" '
		. 'target="_blank" rel="noopener">Book a consultation</a></div>';
}

/**
 * The UAV booking block for the Air/drone surface — "consultation-first for new
 * clients" (***-243).
 *
 * cal.com cannot tell a new visitor from a returning client, so the new-vs-
 * returning rule the owner set ("UAV scheduling starts with a consultation
 * UNLESS they're a returning customer") is expressed here in the CTA weighting:
 *
 *   - PRIMARY (full-weight button) → the free consultation (it-consult slug).
 *     New clients land here first.
 *   - SECONDARY (smaller text link) → direct UAV booking (uav-service slug) for
 *     returning clients who already know the scope.
 *
 * A line of microcopy sets the weekend-default expectation without promising
 * instant weekday booking (weekday shoots are arranged manually via the
 * requiresConfirmation approval on the UAV event type). The existing "Request a
 * quote" path is untouched. The block keeps the `uplinksync-book-uav` class so
 * the injector stays idempotent, and every booking anchor keeps `uls-book-link`
 * so the lazy cal.com Embed loader wires all of them.
 */
function uplinksync_book_uav_markup() {
	$consult_url  = esc_url( uplinksync_book_url( UPLINKSYNC_BOOK_CONSULT_SLUG ) );
	$consult_slug = esc_attr( UPLINKSYNC_BOOK_CONSULT_SLUG );
	$uav_url      = esc_url( uplinksync_book_url( UPLINKSYNC_BOOK_UAV_SLUG ) );
	$uav_slug     = esc_attr( UPLINKSYNC_BOOK_UAV_SLUG );

	return '<div class="uplinksync-book-cta uplinksync-book-uav uls-book-uav-group">'
		// Primary: new clients start with a consultation.
		. '<div class="wp-block-button uls-book-uav-primary">'
		. '<a class="wp-block-button__link wp-element-button uls-book-link" '
		. 'href="' . $consult_url . '" '
		. 'data-cal-link="' . $consult_slug . '" '
		. 'data-uls-book="uav-consult" '
		. 'target="_blank" rel="noopener">New to UplinkSync? Start with a free consultation</a></div>'
		// Secondary: returning clients book UAV service directly.
		. '<p class="uls-book-uav-returning">Returning client? '
		. '<a class="uls-book-link uls-book-uav-direct" '
		. 'href="' . $uav_url . '" '
		. 'data-cal-link="' . $uav_slug . '" '
		. 'data-uls-book="uav" '
		. 'target="_blank" rel="noopener">Book UAV service</a></p>'
		// Weekend-default expectation (no instant-weekday promise).
		. '<p class="uls-book-uav-note">UAV field work is scheduled on weekends '
		. '(weekday shoots by arrangement, ~2 weeks ahead).</p>'
		. '</div>';
}

function uplinksync_book_rewrite( $html ) {
	if ( ! is_string( $html ) || false === stripos( $html, '</body>' ) ) {
		return $html;
	}

	$html = uplinksync_book_inject_consult( $html );
	if ( UPLINKSYNC_BOOK_UAV_ENABLED ) {
		$html = uplinksync_book_inject_uav( $html );
	}
	$html = uplinksync_book_inject_runtime( $html );

	return $html;
}

/**
 * Inject "Book a consultation" as a sibling button immediately after the
 * top-of-funnel "Talk to a specialist" CTA (which links to /contact/, the quote
 * path). Booking augments the quote CTA; the original anchor is untouched.
 * Idempotent — skip if the CTA is already present.
 */
function uplinksync_book_inject_consult( $html ) {
	if ( false !== strpos( $html, 'uplinksync-book-consult' ) ) {
		return $html;
	}
	// Anchor on the "Talk to a specialist" button's own closing </a></div>, and
	// insert our button div right after that wrapping .wp-block-button </div>.
	// Non-greedy so we match this specific anchor's closing tags only.
	$pattern = '#(>Talk to a specialist</a>\s*</div>)#';
	$injected = preg_replace(
		$pattern,
		'$1' . uplinksync_book_consult_markup(),
		$html,
		1,
		$count
	);
	if ( null !== $injected && $count > 0 ) {
		return $injected;
	}

	// Fallback for surfaces without that button (e.g. the /contact/ page): place
	// the consultation CTA next to the first /contact/ (quote) link's button.
	$pattern2 = '#(href="https://uplinksync\.com/contact/"[^>]*>[^<]*</a>\s*</div>)#';
	$injected2 = preg_replace(
		$pattern2,
		'$1' . uplinksync_book_consult_markup(),
		$html,
		1,
		$count2
	);
	if ( null !== $injected2 && $count2 > 0 ) {
		return $injected2;
	}
	return $html;
}

/**
 * Inject "Book UAV services" on the Air/drone surface, right after the intro
 * paragraph describing scoped UAV work. Idempotent.
 */
function uplinksync_book_inject_uav( $html ) {
	if ( false !== strpos( $html, 'uplinksync-book-uav' ) ) {
		return $html;
	}
	// Anchor on the "Scoped UAV work…" paragraph's closing </p>.
	$pattern = '#(Scoped UAV work[^<]*</p>)#';
	$injected = preg_replace(
		$pattern,
		'$1' . uplinksync_book_uav_markup(),
		$html,
		1,
		$count
	);
	if ( null !== $injected && $count > 0 ) {
		return $injected;
	}

	// Fallback: after the "Aerial Photography & Video" heading block's paragraph.
	$pattern2 = '#(Aerial Photography\s*&amp;\s*Video</h3>\s*<p[^>]*>[^<]*</p>)#';
	$injected2 = preg_replace(
		$pattern2,
		'$1' . uplinksync_book_uav_markup(),
		$html,
		1,
		$count2
	);
	if ( null !== $injected2 && $count2 > 0 ) {
		return $injected2;
	}
	return $html;
}

/**
 * Inject the lazy cal.com Embed loader + styling once, just before </body>,
 * only if a booking CTA was actually placed on this page. The SDK script is
 * NOT loaded on page load — it is fetched on the first CTA click, so it never
 * blocks render. With JS disabled the CTA remains a working booking link.
 */
function uplinksync_book_inject_runtime( $html ) {
	$has_cta = ( false !== strpos( $html, 'uls-book-link' ) );
	if ( ! $has_cta ) {
		return $html;
	}
	$origin = esc_js( UPLINKSYNC_BOOK_ORIGIN );
	$embed  = esc_js( UPLINKSYNC_BOOK_ORIGIN . '/embed/embed.js' );

	$style = '<style id="uplinksync-book-cta-css">'
		. '.uplinksync-book-cta .uls-book-link{'
		. 'display:inline-block;border-radius:50px;border:2px solid currentColor;'
		. 'background:transparent;padding:var(--wp--preset--spacing--30,12px) var(--wp--preset--spacing--70,32px);'
		. 'text-decoration:none;cursor:pointer;line-height:1.2;font-weight:600;}'
		. '.uplinksync-book-cta{display:inline-block;margin:8px 8px 8px 0;}'
		// ***-243 — consultation-first UAV block: primary button full weight,
		// returning-client link + weekend note at secondary/supporting weight.
		. '.uls-book-uav-group{display:block;margin:8px 0;}'
		. '.uls-book-uav-group .wp-block-button{display:inline-block;margin:0;}'
		. '.uls-book-uav-returning{margin:10px 0 0;font-size:0.9375rem;opacity:0.85;}'
		. '.uls-book-uav-direct{text-decoration:underline;cursor:pointer;font-weight:600;}'
		. '.uls-book-uav-note{margin:6px 0 0;font-size:0.8125rem;opacity:0.7;}'
		. '</style>';

	// Lazy loader: on the first click of any .uls-book-link, load embed.js once,
	// init Cal against the canonical origin, then open the inline modal for the
	// clicked event type. Subsequent clicks reuse the loaded SDK. If anything
	// fails, we do not preventDefault, so the browser follows the href — the
	// booking page still opens. No pricing is ever surfaced by this flow.
	$script = '<script id="uplinksync-book-cta-js">(function(){'
		. 'var loaded=false,loading=false,queue=[];'
		. 'function boot(cb){'
		. 'if(loaded){cb&&cb();return;}'
		. 'if(cb)queue.push(cb);'
		. 'if(loading)return;loading=true;'
		. '(function(C,A,L){var p=function(a,ar){a.q.push(ar);};var d=C.document;'
		. 'C.Cal=C.Cal||function(){var cal=C.Cal;var ar=arguments;'
		. 'if(!cal.loaded){cal.ns={};cal.q=cal.q||[];'
		. 'd.head.appendChild(d.createElement("script")).src=A;cal.loaded=true;}'
		. 'if(ar[0]===L){var api=function(){p(api,arguments);};var namespace=ar[1];'
		. 'api.q=api.q||[];'
		. 'if(typeof namespace==="string"){cal.ns[namespace]=cal.ns[namespace]||api;'
		. 'p(cal.ns[namespace],ar);p(cal,["initNamespace",namespace]);}'
		. 'else p(cal,ar);return;}p(cal,ar);};})(window,"' . $embed . '","init");'
		. 'window.Cal("init",{origin:"' . $origin . '"});'
		. 'loaded=true;loading=false;var q=queue.slice();queue=[];q.forEach(function(f){f();});'
		. '}'
		. 'document.addEventListener("click",function(e){'
		. 'var a=e.target&&e.target.closest?e.target.closest(".uls-book-link"):null;'
		. 'if(!a)return;'
		. 'if(e.metaKey||e.ctrlKey||e.shiftKey||e.button===1)return;'
		. 'var slug=a.getAttribute("data-cal-link");if(!slug)return;'
		. 'try{e.preventDefault();boot(function(){'
		. 'window.Cal("modal",{calLink:slug,config:{layout:"month_view"}});'
		. '});}catch(err){window.location.href=a.href;}'
		. '},false);'
		. '})();</script>';

	return str_ireplace( '</body>', $style . $script . '</body>', $html );
}
