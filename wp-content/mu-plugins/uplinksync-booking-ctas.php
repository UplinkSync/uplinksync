<?php
/**
 * Plugin Name: UplinkSync — Booking CTAs + cal.com Embed (***-188)
 * Description: Adds cal.com booking affordances to the public site — a "Book a consultation" button next to the top-of-funnel "Talk to a specialist" / "Request a quote" CTA (upgraded on click to an inline cal.com Embed modal), and a single "Book a free consultation" button on the homepage Air/drone surface that opens an accessible chooser MODAL (IT/Web vs UAV) routing to the correct scheduling form with a pre-filled scope note. The self-hosted cal.com instance is canonical at https://book.uplinksync.com (***-187). The Embed SDK is lazy-loaded only on the first embed-CTA click, so it never blocks render; the chooser modal needs no SDK (its two paths are real booking links). No pricing is surfaced anywhere (owner quote-only directive), and the "Request a quote" path is untouched. The hero/drone markup is produced by the Hostinger AI theme + saved block content in the WP DB (not tracked files), so this mu-plugin rewrites the rendered document on the way out.
 *
 * v2.0.0 (2026-07-28, owner-approved): the Air/drone block used to be a <details> CTA DROPDOWN
 * ("New to UplinkSync? Start with a free consultation") plus a separate "Returning client? Book
 * UAV service" DIRECT link. Replaced the dropdown with a single button -> accessible modal
 * (focus trap, Esc, overlay close, return focus, reduced-motion), and REMOVED the returning-client
 * direct-UAV link entirely (direct UAV booking must not be publicly linked; the UAV path now lives
 * only inside the modal, whose UAV choice routes to uav-service — requiresConfirmation=true).
 *
 * v2.1.0 (2026-07-28, *** #4): the CTA chooser paths and the estimator "Book a time" button
 *   now open the cal.com POP-UP (Cal("modal")) with prefill (scope note; estimator context) instead
 *   of navigating to a booking page. The click handler matches any [data-cal-link] element, merges
 *   data-cal-config JSON prefill, and computes an estimate prefill for data-cal-prefill="estimate".
 *   The runtime also loads on estimator pages (uls-estimator).
 *
 * Version: 2.1.0
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
 * Pre-filled "scope form" notes appended to each booking URL as ?notes=… so the
 * booking page opens with a short intake already started, guiding the first
 * conversation. Kept as small helpers so the button block and the modal share
 * one source of truth. (UTF-8 — em dash + bullets — is intentional.)
 */
function uplinksync_book_it_notes() {
	return "IT / Web services consultation \xE2\x80\x94 a few details to guide our conversation:\n"
		. "\xE2\x80\xA2 Goal (new website, systems/help-desk support, security, cloud/M365, networking):\n"
		. "\xE2\x80\xA2 Current setup & main pain points:\n"
		. "\xE2\x80\xA2 Rough timeline / any deadlines:\n"
		. "\xE2\x80\xA2 Approx. users or devices involved:\n"
		. "\xE2\x80\xA2 Anything else we should know:";
}
function uplinksync_book_uav_notes() {
	return "UAV / drone project consultation \xE2\x80\x94 a few details to guide our conversation:\n"
		. "\xE2\x80\xA2 Project type (mapping, inspection, marketing footage, progress records):\n"
		. "\xE2\x80\xA2 Site location & approx. size / acreage:\n"
		. "\xE2\x80\xA2 Deliverables needed (stills, video, orthomosaic, reports):\n"
		. "\xE2\x80\xA2 Target date / seasonal timing:\n"
		. "\xE2\x80\xA2 Anything else we should know:";
}

/** Booking URL for a slug with a pre-filled scope-form note. */
function uplinksync_book_url_with_notes( $slug, $notes ) {
	return uplinksync_book_url( $slug ) . '?notes=' . rawurlencode( $notes );
}

/**
 * The UAV/consultation block for the homepage Air/drone surface.
 *
 * *** (2026-07-28, owner-approved): the previous "New to UplinkSync? Start with
 * a free consultation" affordance was a <details> CTA DROPDOWN with an IT/Web-vs-UAV
 * path choice, plus a separate "Returning client? Book UAV service" link that
 * booked the UAV event type directly. Owner decision:
 *   1. Replace the dropdown with a SINGLE button that opens a POPUP/MODAL asking
 *      IT/Web vs UAV, then routes to the correct cal.com scheduling form.
 *   2. REMOVE the "Returning client? Book UAV service" direct link entirely —
 *      direct UAV booking must not be publicly linked. The UAV path now lives
 *      only inside the modal (its choice routes to the uav-service form, which is
 *      requiresConfirmation=true so the owner approves every booking anyway).
 *
 * This markup is now just the button (the dialog trigger). The modal itself is a
 * single page-global element injected once by uplinksync_book_inject_runtime(),
 * so it is not duplicated per surface. `uplinksync-book-uav` is kept for injector
 * idempotency. The trigger degrades: with JS off it is an inert button, and the
 * modal's two paths are real <a href> booking links (progressive enhancement for
 * the actual scheduling step, notes pre-filled).
 */
function uplinksync_book_uav_markup() {
	return '<div class="uplinksync-book-cta uplinksync-book-uav uls-consult-block" data-uls-reveal data-uls-reveal-delay="0.16">'
		. '<div class="wp-block-button uls-consult-trigger-wrap">'
		. '<button type="button" '
		. 'class="wp-block-button__link wp-element-button uls-consult-trigger" '
		. 'data-uls-book-open="uls-book-modal" aria-haspopup="dialog" aria-controls="uls-book-modal">'
		. 'Book a free consultation</button></div>'
		. '</div>';
}

/**
 * The consultation chooser modal (single, page-global). Two real booking links —
 * IT/Web -> it-consult, UAV/drone -> uav-service — each with a pre-filled scope
 * form. Hidden until the trigger opens it; accessible dialog wired by the runtime
 * JS (focus trap, Esc, overlay close, return focus, reduced-motion).
 */
function uplinksync_book_modal_markup() {
	// Real booking URLs (with the scope note) remain as the no-JS fallback href.
	$it_url  = esc_url( uplinksync_book_url_with_notes( UPLINKSYNC_BOOK_CONSULT_SLUG, uplinksync_book_it_notes() ) );
	$uav_url = esc_url( uplinksync_book_url_with_notes( UPLINKSYNC_BOOK_UAV_SLUG, uplinksync_book_uav_notes() ) );
	// *** #4: with JS, each choice opens the cal.com POP-UP (not a new page),
	// prefilled with the scope note via data-cal-config (see the click handler).
	$it_slug  = esc_attr( UPLINKSYNC_BOOK_CONSULT_SLUG );
	$uav_slug = esc_attr( UPLINKSYNC_BOOK_UAV_SLUG );
	$it_cfg   = esc_attr( wp_json_encode( array( 'notes' => uplinksync_book_it_notes() ) ) );
	$uav_cfg  = esc_attr( wp_json_encode( array( 'notes' => uplinksync_book_uav_notes() ) ) );

	return '<div class="uls-book-modal" id="uls-book-modal" role="dialog" aria-modal="true" '
		. 'aria-labelledby="uls-book-modal-title" aria-describedby="uls-book-modal-desc" hidden>'
		. '<div class="uls-book-modal__overlay" data-uls-book-close="1"></div>'
		. '<div class="uls-book-modal__panel" role="document" tabindex="-1">'
		. '<button type="button" class="uls-book-modal__close" data-uls-book-close="1" aria-label="Close">&#215;</button>'
		. '<h2 class="uls-book-modal__title" id="uls-book-modal-title">Start with a free consultation</h2>'
		. '<p class="uls-book-modal__desc" id="uls-book-modal-desc">Which kind of consultation is this? '
		. 'Pick a path and we&#8217;ll open a booking pop-up with a short scope form already started to guide the conversation.</p>'
		. '<div class="uls-consult-paths">'
		. '<a class="uls-consult-path" href="' . $it_url . '" data-cal-link="' . $it_slug . '" data-cal-config="' . $it_cfg . '" target="_blank" rel="noopener">'
		. '<span class="uls-path-title">IT / Web services</span>'
		. '<span class="uls-path-sub">Websites, systems &amp; help-desk, security, cloud / M365, networking.</span></a>'
		. '<a class="uls-consult-path" href="' . $uav_url . '" data-cal-link="' . $uav_slug . '" data-cal-config="' . $uav_cfg . '" target="_blank" rel="noopener">'
		. '<span class="uls-path-title">UAV / drone project</span>'
		. '<span class="uls-path-sub">Mapping, inspection, marketing footage, progress records.</span></a>'
		. '</div></div></div>';
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
	// Anchor immediately after the UAV capability pills group (…"Progress records"),
	// which is where the block sits today. Non-greedy, first match only.
	$pattern = '#(Progress records</p>\s*</div>)#';
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
	// Fallback: after the "Scoped UAV work…" intro paragraph.
	$pattern_intro = '#(Scoped UAV work[^<]*</p>)#';
	$injected_intro = preg_replace(
		$pattern_intro,
		'$1' . uplinksync_book_uav_markup(),
		$html,
		1,
		$count_intro
	);
	if ( null !== $injected_intro && $count_intro > 0 ) {
		return $injected_intro;
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
	// Present if any embed CTA (.uls-book-link), the consultation-modal trigger, OR
	// the estimator (whose JS-added "Book a time" button opens a cal.com pop-up) is
	// on the page. Any of them needs this runtime (the loader + click handler).
	$has_cta = ( false !== strpos( $html, 'uls-book-link' ) )
		|| ( false !== strpos( $html, 'uls-consult-trigger' ) )
		|| ( false !== strpos( $html, 'uls-estimator' ) );
	if ( ! $has_cta ) {
		return $html;
	}
	// Idempotency: never inject the runtime (or a second modal) twice.
	if ( false !== strpos( $html, 'uplinksync-book-cta-js' ) ) {
		return $html;
	}
	$origin = esc_js( UPLINKSYNC_BOOK_ORIGIN );
	$embed  = esc_js( UPLINKSYNC_BOOK_ORIGIN . '/embed/embed.js' );

	$style = '<style id="uplinksync-book-cta-css">'
		. '.uplinksync-book-cta .uls-book-link,.uplinksync-book-cta .uls-consult-trigger{'
		/* ***-315: single pill value (was 50px) */
		. 'display:inline-block;border-radius:999px;border:2px solid currentColor;'
		. 'background:transparent;padding:var(--wp--preset--spacing--30,12px) var(--wp--preset--spacing--70,32px);'
		. 'text-decoration:none;cursor:pointer;line-height:1.2;font-weight:600;font:inherit;color:inherit;}'
		. '.uplinksync-book-cta{display:inline-block;margin:8px 8px 8px 0;}'
		. '.uls-consult-block{margin:8px 0 0;}'
		// Consultation chooser modal.
		. '.uls-book-modal[hidden]{display:none;}'
		. '.uls-book-modal{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;padding:20px;}'
		. '.uls-book-modal__overlay{position:absolute;inset:0;background:rgba(6,15,32,0.72);}'
		. '.uls-book-modal__panel{position:relative;width:100%;max-width:560px;max-height:calc(100vh - 40px);overflow:auto;'
		. 'background:var(--navy-850,#14294a);color:#ffffff;border:1px solid #2a4a72;border-radius:12px;'
		. 'padding:28px 24px 24px;box-shadow:0 20px 60px rgba(0,0,0,0.45);}'
		. '.uls-book-modal__panel:focus{outline:none;}'
		. '.uls-book-modal__close{position:absolute;top:8px;right:8px;width:44px;height:44px;display:inline-flex;'
		. 'align-items:center;justify-content:center;background:transparent;border:0;color:#ffffff;font-size:26px;'
		. 'line-height:1;cursor:pointer;border-radius:8px;}'
		. '.uls-book-modal__close:hover,.uls-book-modal__close:focus-visible{color:#95D5DD;outline:none;}'
		. '.uls-book-modal__title{margin:0 8px 8px 0;font-size:1.3rem;line-height:1.2;}'
		. '.uls-book-modal__desc{margin:0 0 18px;font-size:0.9375rem;opacity:0.85;}'
		. '.uls-book-modal .uls-consult-paths{display:flex;flex-wrap:wrap;gap:12px;}'
		. '.uls-book-modal .uls-consult-path{flex:1 1 220px;display:block;border:1px solid #2a4a72;'
		. 'border-radius:var(--radius-default,8px);background:#0d1f3a;padding:16px;text-decoration:none;color:#ffffff;'
		. 'transition:border-color .2s ease,background .2s ease;}'
		. '.uls-book-modal .uls-consult-path:hover,.uls-book-modal .uls-consult-path:focus-visible{'
		. 'border-color:#3ec7d4;background:var(--navy-850,#14294a);outline:none;box-shadow:0 0 0 3px rgba(62,199,212,0.5);}'
		. '.uls-book-modal .uls-path-title{display:block;font-weight:600;font-size:1rem;margin-bottom:4px;}'
		. '.uls-book-modal .uls-path-sub{display:block;font-size:0.8125rem;opacity:0.75;line-height:1.35;}'
		. 'body.uls-book-modal-open{overflow:hidden;}'
		. '@media (prefers-reduced-motion: reduce){.uls-book-modal .uls-consult-path{transition:none;}}'
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
		// *** #4: prefill helper — read the estimator fields at click time so the
		// "Book a time" pop-up arrives with the visitor context already filled in.
		. 'function ulsEstPrefill(){'
		. 'function v(id){var el=document.getElementById(id);return el?(el.value||"").trim():"";}'
		. 'function st(id){var el=document.getElementById(id);return el&&el.selectedIndex>=0?el.options[el.selectedIndex].text.trim():"";}'
		. 'var svc=st("uls-est-line"),mi=v("uls-est-miles"),tm=st("uls-est-timing");'
		. 'var L=["Estimate request from uplinksync.com:"];'
		. 'if(svc&&svc.indexOf("Choose")===-1)L.push("• Service: "+svc);'
		. 'if(mi)L.push("• Distance from Idaho Falls: "+mi+" miles");'
		. 'if(tm&&tm.indexOf("Choose")===-1)L.push("• Timing: "+tm);'
		. 'var c={notes:L.join("\\n")};'
		. 'var nm=v("uls-est-name"),em=v("uls-est-email");if(nm)c.name=nm;if(em)c.email=em;return c;}'
		// Any [data-cal-link] element opens the cal.com POP-UP (not a new page),
		// merging: base layout, a static data-cal-config JSON prefill, and (for the
		// estimator button) the live estimate prefill. Falls back to the href if the
		// SDK throws. No pricing is ever surfaced by this flow.
		. 'document.addEventListener("click",function(e){'
		. 'var a=e.target&&e.target.closest?e.target.closest("[data-cal-link]"):null;'
		. 'if(!a)return;'
		. 'if(e.metaKey||e.ctrlKey||e.shiftKey||e.button===1)return;'
		. 'var slug=a.getAttribute("data-cal-link");if(!slug)return;'
		. 'var cfg={layout:"month_view"};'
		. 'var raw=a.getAttribute("data-cal-config");'
		. 'if(raw){try{var pc=JSON.parse(raw);for(var k in pc){cfg[k]=pc[k];}}catch(e2){}}'
		. 'if(a.getAttribute("data-cal-prefill")==="estimate"){var ep=ulsEstPrefill();for(var k2 in ep){cfg[k2]=ep[k2];}}'
		. 'try{e.preventDefault();boot(function(){'
		. 'window.Cal("modal",{calLink:slug,config:cfg});'
		. '});}catch(err){if(a.href){window.location.href=a.href;}}'
		. '},false);'
		. '})();'
		// ---- Consultation chooser modal controller (accessible dialog) ----
		// Opens on a [data-uls-book-open] trigger; focus trap; Esc + overlay +
		// close-button dismiss; returns focus to the trigger; the two path links
		// open the booking page (new tab) and close the modal. No SDK needed.
		. '(function(){'
		. 'var modal=document.getElementById("uls-book-modal");if(!modal)return;'
		. 'var panel=modal.querySelector(".uls-book-modal__panel");var lastFocus=null;'
		. 'function foci(){return Array.prototype.slice.call('
		. 'modal.querySelectorAll("a[href],button:not([disabled]),[tabindex]:not([tabindex=\'-1\'])"))'
		. '.filter(function(el){return el.offsetWidth||el.offsetHeight||el.getClientRects().length;});}'
		. 'function openM(t){lastFocus=t||document.activeElement;modal.hidden=false;'
		. 'document.body.classList.add("uls-book-modal-open");'
		. 'var p=modal.querySelector(".uls-consult-path")||panel;if(p&&p.focus)p.focus();}'
		. 'function closeM(){if(modal.hidden)return;modal.hidden=true;'
		. 'document.body.classList.remove("uls-book-modal-open");'
		. 'if(lastFocus&&lastFocus.focus){try{lastFocus.focus();}catch(e){}}}'
		. 'document.addEventListener("click",function(e){'
		. 'var o=e.target.closest?e.target.closest("[data-uls-book-open]"):null;'
		. 'if(o){e.preventDefault();openM(o);return;}'
		. 'if(e.target.closest&&e.target.closest("[data-uls-book-close]")){e.preventDefault();closeM();return;}'
		. 'if(!modal.hidden&&e.target.closest&&e.target.closest(".uls-consult-path")){setTimeout(closeM,0);}'
		. '},false);'
		. 'document.addEventListener("keydown",function(e){'
		. 'if(modal.hidden)return;'
		. 'if(e.key==="Escape"||e.keyCode===27){e.preventDefault();closeM();return;}'
		. 'if(e.key==="Tab"||e.keyCode===9){var f=foci();if(!f.length){e.preventDefault();return;}'
		. 'var first=f[0],last=f[f.length-1];'
		. 'if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}'
		. 'else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}'
		. 'else if(!modal.contains(document.activeElement)){e.preventDefault();first.focus();}}'
		. '},false);'
		. '})();</script>';

	return str_ireplace( '</body>', $style . uplinksync_book_modal_markup() . $script . '</body>', $html );
}
