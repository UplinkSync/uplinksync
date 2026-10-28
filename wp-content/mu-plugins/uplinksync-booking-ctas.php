<?php
/**
 * Plugin Name: UplinkSync — Booking CTAs (triggers for the native "Book a call" flow)
 * Description: Injects the "Book a consultation" CTAs and marks every book.uplinksync.com link on the rendered page as a trigger for the Quiet Light estimator's native "Book a call" mode (mu-plugins/uplinksync-estimator.php). Since v4.0.0 this file no longer renders a dialog or loads the Cal.com embed; links stay real links to the team booking pages as the no-JS fallback. All booking targets are the company namespace `team/uplinksync/*`. The hero/drone/contact markup is partly DB content, so this mu-plugin rewrites the rendered document on the way out.
 *
 * v3.0.0 (2026-08-08, owner decision + Booking #2 / Booking #3):
 *   1. NAMESPACE. Every booking target moves from the owner's PERSONAL profile
 *      (`dirwin/it-consult`, `dirwin/uav-service`) to the company team profile
 *      `team/uplinksync/*`. UAV now points at the real consultation event type
 *      `team/uplinksync/uav-consult` (cal.com EventType 9) rather than the
 *      hidden legacy `uav-service` bypass (EventType 7, hidden 2026-08-03).
 *   2. NO CONTEXT SWITCH. The chooser modal ("which path?" -> cal.com pop-up)
 *      is replaced by a custom intake FORM that syncs its answers into an
 *      INLINE cal.com embed rendered in the same dialog. Nothing navigates to
 *      book.uplinksync.com.
 *   3. ORPHANS. Any anchor anywhere in the rendered document that points at
 *      book.uplinksync.com (e.g. the "Book a Consultation" button in the
 *      /contact/ CTA band, which lives in the WP database, not in this repo) is
 *      rewritten into a trigger for the same dialog, and its no-JS href is
 *      normalised onto the team namespace.
 *
 * Accessibility: the dialog is navy `#173258` with `#FFFFFF` text (12.86:1),
 * `#C9D8EE` helper text (8.90:1), `#1F4375` fields with white text (9.92:1) and
 * `#A8C4EA` borders (5.55:1 on the field, 7.19:1 on the panel). The house accent
 * `#5697F3` is NEVER used for text or for a meaning-bearing edge against a light
 * surface — it fails there (2.80:1 on `#F7F9FB`, 2.95:1 on `#FFFFFF`). It appears
 * only as the outer half of a two-tone focus ring whose inner half is the navy
 * panel colour, so the indicator is 4.36:1 against navy and the navy separator is
 * 12.86:1 against white — both above the 3:1 non-text floor in both directions.
 *
 * v3.0.1 (2026-10-22, UPL-884):
 *   Give the database-resident guided quote flow's hidden scheduler fallback a
 *   real server-rendered team booking URL. JavaScript still replaces it with
 *   the selected service URL and prefill values when the flow is initialized.
 *
 * v3.0.2 (2026-10-27, Quiet Light estimator MR):
 *   Cal.com moved to Cal.com CLOUD on 2026-10-13 (team "uplinksync" 467448);
 *   book.uplinksync.com now 301-redirects every path to cal.com/<same path>, so
 *   the team slugs below keep working through the redirect. Updated the stale
 *   self-host EventType ids (6 / 9) to the Cloud ids (7469726 / 7469727) and the
 *   intake option VALUES to the Cloud option values, which are the labels
 *   ("New website", not "new-website") — verified 2026-10-27 via the public
 *   GET api.cal.com/v2/teams/467448/event-types?eventSlug=… . The old slug values
 *   were silently dropped by the Cloud booker's prefill. This dialog still uses
 *   the Cal.com embed (via the redirect); the "Get a quote" estimator's step 3
 *   is native now (mu-plugins/uplinksync-estimator.php).
 *
 * v4.0.0 (2026-10-28, Quiet Light estimator MR, staging QA M2):
 *   ONE booking UI. The old dark intake dialog (#uls-book-modal) and its Cal.com
 *   embed SDK/iframe are REMOVED. Every booking trigger — the CTAs injected here
 *   and every adopted book.uplinksync.com anchor — now opens the Quiet Light
 *   estimator's "Book a call" mode (mu-plugins/uplinksync-estimator.php), which
 *   books natively through this site's REST proxy. Injected CTAs are real links
 *   now, so with JS off (or before the estimator takes over) they still go to
 *   the public booking page.
 *
 * Version: 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public booking origin. Since 2026-10-13 it 301-redirects to Cal.com Cloud
 * (cal.com/<same path>); kept as the vanity/fallback origin.
 */
const UPLINKSYNC_BOOK_ORIGIN = 'https://book.uplinksync.com';

/**
 * CANONICAL BOOKING NAMESPACE — the company team profile.
 *
 * Owner decision (2026-08-08): "Booking through the website should be through
 * team/uplinksync/*". The personal `dirwin/*` profile must not be a booking
 * target on the public site. Both team event types are live and verified
 * serving slots (Cal.com Cloud team 467448 "uplinksync").
 *
 *   team/uplinksync/it-consult   EventType 7469726 — IT / Web consultation, 30m
 *   team/uplinksync/uav-consult  EventType 7469727 — UAV / drone consultation, 30m
 *
 * `team/uplinksync/uav-service` is deliberately NOT used: it was
 * hidden on 2026-08-03 as a scheduling bypass. Drone *work* (uav-weekend /
 * uav-weekday, with their 3-day / 3-week lead times) is booked by the owner
 * after the consultation, not from the public site.
 */
const UPLINKSYNC_BOOK_CONSULT_SLUG = 'team/uplinksync/it-consult';
const UPLINKSYNC_BOOK_UAV_SLUG     = 'team/uplinksync/uav-consult';
const UPLINKSYNC_BOOK_UAV_ENABLED  = true;

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
 * The "Book a consultation" CTA: a real link to the public booking page (the
 * no-JS floor) that the estimator intercepts to open its native "Book a call"
 * mode (data-uls-book-open / data-uls-book-intent).
 */
function uplinksync_book_consult_markup() {
	return '<div class="wp-block-button uplinksync-book-cta uplinksync-book-consult">'
		. '<a class="wp-block-button__link wp-element-button uls-book-link" '
		. 'href="' . esc_url( uplinksync_book_url( UPLINKSYNC_BOOK_CONSULT_SLUG ) ) . '" '
		. 'data-uls-book-open="uls-qmodal" data-uls-book-intent="it" '
		. 'aria-haspopup="dialog" aria-controls="uls-qmodal">Book a consultation</a></div>';
}

/**
 * The UAV/consultation block for the homepage Air/drone surface — a link to the
 * UAV consult page that the estimator opens as its native "Book a call" mode
 * (drone pre-selected). `uplinksync-book-uav` is kept for injector idempotency.
 *
 * Direct UAV *service* booking is still not publicly linked (owner directive);
 * the UAV path leads to the UAV consultation event type.
 */
function uplinksync_book_uav_markup() {
	return '<div class="uplinksync-book-cta uplinksync-book-uav uls-consult-block" data-uls-reveal data-uls-reveal-delay="0.16">'
		. '<div class="wp-block-button uls-consult-trigger-wrap">'
		. '<a href="' . esc_url( uplinksync_book_url( UPLINKSYNC_BOOK_UAV_SLUG ) ) . '" '
		. 'class="wp-block-button__link wp-element-button uls-consult-trigger" '
		. 'data-uls-book-open="uls-qmodal" data-uls-book-intent="uav" aria-haspopup="dialog" aria-controls="uls-qmodal">'
		. 'Book a free consultation</a></div>'
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
	// Marks book.uplinksync.com anchors as triggers for the estimator's
	// "Book a call" mode (their hrefs stay real links: the no-JS floor).
	$html = uplinksync_book_adopt_orphan_links( $html );
	// Runs after orphan adoption so this progressive-enhancement fallback remains
	// a direct scheduler link rather than becoming another modal trigger.
	$html = uplinksync_book_repair_quote_fallback( $html );
	// v4.0.0: no dialog, styles or Cal.com embed SDK are injected any more.

	return $html;
}

/**
 * Give the guided quote flow's database-resident fallback CTA a safe href.
 *
 * Snippet 82 emits this anchor on every page because the quote flow is mounted
 * globally. Its JavaScript replaces the href with the selected IT/UAV event and
 * prefill values, but the server-rendered value must also work if that setup is
 * late or fails. Match the stable element id plus the exact placeholder href so
 * unrelated fragment links are untouched. The replacement is idempotent.
 *
 * @param string $html Rendered document.
 * @return string
 */
function uplinksync_book_repair_quote_fallback( $html ) {
	if ( false === strpos( $html, 'qf-cal-fallback-link' ) ) {
		return $html;
	}

	return preg_replace_callback(
		'#<a\b(?=[^>]*\bid=(["\'])qf-cal-fallback-link\1)[^>]*>#i',
		static function ( $matches ) {
			$anchor = $matches[0];
			if ( ! preg_match( '~\bhref=(["\'])#\1~i', $anchor ) ) {
				return $anchor;
			}

			return preg_replace(
				'~\bhref=(["\'])#\1~i',
				'href="' . esc_url( uplinksync_book_url( UPLINKSYNC_BOOK_CONSULT_SLUG ) ) . '"',
				$anchor,
				1
			);
		},
		$html
	);
}

/**
 * True on any WooCommerce commerce surface — the shop archive, single products,
 * product category/tag archives, cart, checkout and account. An IT/Web
 * "Book a consultation" CTA has no place on a stock-image licensing page, so the
 * consult injector is suppressed here: the /shop/ licensing block's own
 * "Get in touch" -> /contact/ button was being matched by the fallback below and
 * getting an IT-consult button injected beside it.
 */
function uplinksync_book_is_commerce_surface() {
	if ( function_exists( 'is_shop' ) && is_shop() ) {
		return true;
	}
	if ( function_exists( 'is_product' ) && is_product() ) {
		return true;
	}
	if ( function_exists( 'is_product_category' ) && is_product_category() ) {
		return true;
	}
	if ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
		return true;
	}
	if ( function_exists( 'is_cart' ) && is_cart() ) {
		return true;
	}
	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		return true;
	}
	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		return true;
	}
	return false;
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
	// Never inject the IT/Web consult CTA on a commerce surface. The stock-image
	// storefront is a licensing funnel, not an IT-services funnel; its own
	// "Get in touch" -> /contact/ button must not sprout a consultation CTA.
	if ( uplinksync_book_is_commerce_surface() ) {
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

	// Fallback for the /contact/ page only: place the consultation CTA next to the
	// first /contact/ (quote) link's button. Scoped to is_page('contact') so it
	// can never latch onto an incidental /contact/ link on some other page.
	if ( ! ( function_exists( 'is_page' ) && is_page( 'contact' ) ) ) {
		return $html;
	}
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
 * Inject the UAV consultation trigger on the Air/drone surface, right after the
 * UAV capability pills. Idempotent.
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
 * ORPHAN ADOPTION — the reason a "one namespace" sweep cannot be done in this
 * repo alone.
 *
 * Some booking CTAs are not produced by this plugin: the "Book a Consultation"
 * button in the /contact/ CTA band ("Rather book a time than fill in a form?")
 * is saved BLOCK CONTENT in the WordPress database. Left alone it is a plain
 * anchor that sends the visitor to book.uplinksync.com — exactly the context
 * switch the owner ruled out — and historically it has drifted onto the wrong
 * namespace.
 *
 * So: rewrite every anchor in the rendered document whose href points at the
 * cal.com origin into a trigger for the on-page dialog, and normalise the href
 * it keeps (the no-JS fallback) onto the team namespace. The dialog's own path
 * links are skipped — they are already ours and already correct.
 *
 * @param string $html Rendered document.
 * @return string
 */
function uplinksync_book_adopt_orphan_links( $html ) {
	if ( false === stripos( $html, UPLINKSYNC_BOOK_ORIGIN ) ) {
		return $html;
	}

	$origin_re = preg_quote( UPLINKSYNC_BOOK_ORIGIN, '#' );

	return preg_replace_callback(
		'#<a\b([^>]*?)href="(' . $origin_re . '/[^"]*)"([^>]*)>#i',
		function ( $m ) {
			$before = $m[1];
			$href   = $m[2];
			$after  = $m[3];
			$attrs  = $before . $after;

			// Already one of ours (dialog path link or an adopted trigger): leave it.
			if ( false !== stripos( $attrs, 'data-uls-book' ) || false !== stripos( $attrs, 'uls-consult-path' ) ) {
				return $m[0];
			}

			$path   = (string) wp_parse_url( $href, PHP_URL_PATH );
			$intent = ( false !== stripos( $path, 'uav' ) || false !== stripos( $path, 'drone' ) ) ? 'uav' : 'it';
			$slug   = ( 'uav' === $intent ) ? UPLINKSYNC_BOOK_UAV_SLUG : UPLINKSYNC_BOOK_CONSULT_SLUG;

			// Normalise the no-JS fallback href onto the canonical team namespace,
			// preserving any query string the block author added.
			$query    = (string) wp_parse_url( $href, PHP_URL_QUERY );
			$new_href = uplinksync_book_url( $slug ) . ( '' !== $query ? '?' . $query : '' );

			return '<a' . $before . 'href="' . esc_url( $new_href ) . '"' . $after
				. ' data-uls-book-open="uls-qmodal" data-uls-book-intent="' . esc_attr( $intent ) . '"'
				. ' aria-haspopup="dialog" aria-controls="uls-qmodal">';
		},
		$html
	);
}
