<?php
/**
 * Plugin Name: UplinkSync — Homepage Nav & CTA Rewiring
 * Description: Rewires the homepage (`/`) primary nav, hero/body CTAs and drone cards to the canonical IA pages, strips `/product/*` commerce links, and drops the duplicate `-2` Woo product links (***-120, ***-125). Like the other UplinkSync fixes, the homepage nav/hero markup is produced by the Hostinger AI theme + saved block content in the WP DB, NOT by tracked files, so a static edit cannot reach it. This mu-plugin rewrites the rendered document on the way out, keeping the fix captured in-repo (deploys with wp-content) and independent of the active theme. Server-side 301s for the legacy paths themselves live in uplinksync-canonical-redirects.php and uplinksync-drone-product-redirects.php; this plugin fixes the *links on the page* so users and crawlers never hit those redirects in the first place. UPLAA-452 (v1.2.0): also re-aims the homepage quote-form path-chooser drone card, whose DB-stored sub-copy still led "Aerial photography, mapping & surveying, roof & structure inspection" — the exact industrial-first framing SHIP ORDER #7 demotes. The card copy lives in the same saved block content, unreachable by a tracked-file edit and 401-locked to REST, so it is corrected here on the same `/`-only output buffer.
 * Version: 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical destinations (IA §3). Owner/IA-authoritative — do not re-derive.
 *
 *   Consult Now         /about-4/                              -> /about/
 *   Contact Us          /product/drone-surveillance-pro/       -> /contact/
 *   Start               /product/managed-it-support-2/         -> /services/managed-it/
 *   Get Help            /product/drone-inspection-service/     -> /contact/
 *   Learn More          /services-2/                           -> /services/
 *   Learn About Drones  /drone-services/                       -> /services/
 *
 * Drone destination note (***-125): the home page's drone CTAs/cards link to
 * `/drone-services/` and to three `/product/drone-*` commerce URLs. Live status:
 *   /drone-services/                       301 -> /product/drone-services/
 *   /product/drone-aerial-capture/         301 -> /drone-services/ (chains again)
 *   /product/drone-inspection-service/     301 -> /drone-services/ (chains again)
 *   /product/drone-aerial-package/         200  but still /product/*
 * So `/drone-services/` is NOT yet a clean 200 — its DB-layer reversal to the
 * gallery page (IA §2, ***-104) is still owned by the CTO and unshipped. Per
 * this issue and the "no /product/* on the home page, shop stays hidden" rule,
 * every drone CTA/card is routed to the canonical services overview `/services/`
 * (200, no redirect hop, not /product/*). When the gallery page ships on
 * /drone-services/ as a real 200, UPLINKSYNC_NAV_DRONE can point back at it.
 *
 * Duplicate `-2` Woo product tiles on the homepage product collection point at
 * the retired gallery products; the Phase-1 strategy is gallery-only, so they
 * are routed to the same canonical destination as the live drone links.
 */
const UPLINKSYNC_NAV_ABOUT       = 'https://uplinksync.com/about/';
const UPLINKSYNC_NAV_CONTACT     = 'https://uplinksync.com/contact/';
const UPLINKSYNC_NAV_SERVICES    = 'https://uplinksync.com/services/';
const UPLINKSYNC_NAV_MANAGED_IT  = 'https://uplinksync.com/services/managed-it/';
// Canonical drone destination. The gallery page now ships on /drone-services/
// as a real 200 (***-104/***-253), so — exactly as this plugin always said
// it would once that landed — the drone destination points back at it. Effect:
// the retired /product/drone-* card links (which 301 to /drone-services/) route
// straight to the live gallery with no redirect hop, and the front-page footer's
// absolute /drone-services/ link is left intact instead of being rewritten to
// /services/ (***-253: footer "Drone Services" was mis-resolving on `/`).
const UPLINKSYNC_NAV_DRONE       = 'https://uplinksync.com/drone-services/';

/**
 * Scope guard: front-end GET for the home page only. The nav/CTA defects are
 * specific to `/`; the interior pages were fixed on their own tickets and must
 * not be touched here.
 */
function uplinksync_homepage_nav_should_filter() {
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
	// Front page only — is_front_page() covers both a static Page and the posts
	// index being set as the home page.
	return is_front_page();
}

function uplinksync_homepage_nav_start_buffer() {
	if ( ! uplinksync_homepage_nav_should_filter() ) {
		return;
	}
	ob_start( 'uplinksync_homepage_nav_rewrite' );
}
// Priority 1 so this opens its buffer just after the contact/social buffer
// (priority 0); nested output buffers unwind LIFO, so ours closes first and the
// contact/social pass still sees the finished document. The two filters touch
// disjoint markup (nav/CTA hrefs vs. contact info), so order is not critical.
add_action( 'template_redirect', 'uplinksync_homepage_nav_start_buffer', 1 );

function uplinksync_homepage_nav_rewrite( $html ) {
	if ( ! is_string( $html ) || false === stripos( $html, '</body>' ) ) {
		return $html;
	}

	$html = uplinksync_homepage_rewrite_ctas( $html );
	$html = uplinksync_homepage_rewrite_dupe_products( $html );
	$html = uplinksync_homepage_reaim_quickform( $html );
	// Primary-nav item injection moved to the site-wide uplinksync-primary-nav.php
	// (***-126) so `/`, `/about/`, `/contact/`, `/services/` all render the same
	// nav (About / Services / Contact). This plugin no longer injects nav items;
	// it only rewrites the hero CTAs and duplicate product links on `/`.

	return $html;
}

/**
 * Rewrite the hero / body CTA + drone-card links to their canonical destinations.
 *
 * ***-125: the current home page's drone links are `/drone-services/` (the
 * "Learn About Drones" CTA and the Aerial Capture / Aerial Package card wrappers)
 * and three `/product/drone-*` commerce URLs (the card title links and their
 * product-collection interactivity JSON). All of them either 301 or are `/product/*`,
 * so all are routed to the canonical services overview `/services/`.
 *
 * Whole-URL replacement (not just `href="…"`) is deliberate so it also catches the
 * product-collection block's interactivity JSON (`"permalink":"…"`) — the value the
 * tile's `data-wp-on--click="viewProduct"` handler actually navigates to. Rewriting
 * only the visible href would leave a click still hitting a `/product/*` 301 and
 * would fail the self-check (`grep -c '/product/drone'` on `/` must be 0). Woo `"slug"`
 * identifiers carry no trailing slash, so they never match these trailing-slashed
 * whole-URL swaps and are left intact.
 *
 * The legacy ***-120 targets (about-4/services-2/…-support-2/…-surveillance-pro/
 * …-photography-package) no longer appear on `/` — the saved block content drifted —
 * but the swaps are kept as harmless defense-in-depth in case the DB content regresses.
 */
function uplinksync_homepage_rewrite_ctas( $html ) {
	// Current (***-125) drone CTA/card destinations — every drone link -> /services/.
	// Whole-URL swap covers both the visible href and the tile-click permalink JSON.
	$drone_urls = array(
		'https://uplinksync.com/drone-services/',
		'https://uplinksync.com/product/drone-aerial-capture/',
		'https://uplinksync.com/product/drone-aerial-package/',
		'https://uplinksync.com/product/drone-inspection-service/',
	);
	foreach ( $drone_urls as $from ) {
		$html = str_replace( $from, UPLINKSYNC_NAV_DRONE, $html );
	}

	// Legacy ***-120 whole-URL swaps — no longer on `/`, kept as defense-in-depth.
	$swaps = array(
		'https://uplinksync.com/about-4/'                        => UPLINKSYNC_NAV_ABOUT,
		'https://uplinksync.com/product/drone-surveillance-pro/' => UPLINKSYNC_NAV_CONTACT,
		'https://uplinksync.com/product/managed-it-support-2/'   => UPLINKSYNC_NAV_MANAGED_IT,
		'https://uplinksync.com/services-2/'                     => UPLINKSYNC_NAV_SERVICES,
		'https://uplinksync.com/product/drone-photography-package/' => UPLINKSYNC_NAV_DRONE,
	);
	foreach ( $swaps as $from => $to ) {
		$html = str_replace( 'href="' . $from . '"', 'href="' . $to . '"', $html );
	}

	return $html;
}

/**
 * Drop the duplicate `-2` Woo product links, pointing them at the canonical
 * gallery. These are the retired-duplicate SKUs that survived the store
 * cleanup; the Phase-1 gallery-only destination is /drone-services/.
 *
 * Uses whole-URL replacement across the document so every occurrence is caught:
 * the tile wrapper link, the title link, AND the product-collection block's
 * interactivity JSON (`"permalink":"…-2/"`), which is what the tile's
 * data-wp-on--click="viewProduct" handler actually navigates to. Rewriting only
 * the visible href would leave a click still going to the retired `-2` product
 * and would fail the self-check (`grep -c '…-2/'` must be 0). The product's
 * `"slug":"…-2"` identifier is intentionally left untouched — it carries no
 * trailing slash so it does not match the self-check, and it is the real Woo
 * object key, not a user-visible link.
 */
function uplinksync_homepage_rewrite_dupe_products( $html ) {
	$dupes = array(
		'https://uplinksync.com/product/drone-aerial-capture-2/',
		'https://uplinksync.com/product/drone-aerial-package-2/',
	);
	foreach ( $dupes as $from ) {
		$html = str_replace( $from, UPLINKSYNC_NAV_DRONE, $html );
	}
	return $html;
}

/**
 * Re-aim the homepage quote-form path-chooser drone card (UPLAA-452, SHIP ORDER #7).
 *
 * The `/` quote form ("What kind of project can we help with?") offers two path
 * cards. The drone card's sub-label still reads, verbatim from the saved block
 * content in the WP DB:
 *
 *   Drone / UAV work
 *   Aerial photography, mapping & surveying, roof & structure inspection.
 *
 * That is the industrial-first framing the ship order demotes: it leads on
 * mapping / surveying / inspection and never mentions listing or property media.
 * Every other surface was re-aimed on its own change — the drone page body
 * (uplinksync-drone-listing-copy.php), the consultation path-chooser
 * (uplinksync-booking-ctas.php: "Listing photo & video, events, progress
 * records — inspection and mapping also available."), the estimator lead option
 * ("Real-estate / property photography") — leaving this one card as the last
 * inspection-first tell on the home page (MEASURED on cache-busted prod).
 *
 * The card copy is not in any tracked file (Hostinger theme + saved block content)
 * and live REST is 401-locked to agents, so — exactly like the CTA/dupe swaps
 * above — it is corrected on the way out. Single high-confidence substring match:
 * the needle is the exact rendered bytes (including `&amp;`), so it either matches
 * and is replaced or is absent and this is a NO-OP. It cannot restructure or blank
 * the card. The wording mirrors the already-approved consultation path-chooser so
 * the two entry points read consistently: creative lead, inspection/mapping demoted
 * to one supporting clause (DEMOTE, do not delete — UPLAA-452 comment 1).
 *
 * OWNER EDITS WIN: any owner rewrite of the phrase means the needle no longer
 * matches and the swap is skipped.
 */
function uplinksync_homepage_reaim_quickform( $html ) {
	$from = 'Aerial photography, mapping &amp; surveying, roof &amp; structure inspection.';
	$to   = 'Listing photo &amp; video, events, progress records &mdash; inspection and mapping also available.';
	return str_replace( $from, $to, $html );
}

