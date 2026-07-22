<?php
/**
 * Plugin Name: UplinkSync — Homepage Nav & CTA Rewiring
 * Description: Rewires the homepage (`/`) primary nav and hero CTAs to the canonical IA pages, and drops the duplicate `-2` Woo product links (***-120, round-3 of ***-101). Like the other UplinkSync fixes, the homepage nav/hero markup is produced by the Hostinger AI theme + saved block content in the WP DB, NOT by tracked files, so a static edit cannot reach it. This mu-plugin rewrites the rendered document on the way out, keeping the fix captured in-repo (deploys with wp-content) and independent of the active theme. Server-side 301s for the legacy paths themselves live in uplinksync-canonical-redirects.php and uplinksync-drone-product-redirects.php; this plugin fixes the *links on the page* so users and crawlers never hit those redirects in the first place.
 * Version: 1.0.0
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
 *   Learn About Drones  /product/drone-photography-package/    -> /drone-services/
 *
 * Duplicate `-2` Woo product tiles on the homepage product collection point at
 * the retired gallery products; the Phase-1 strategy is gallery-only, so they
 * go to /drone-services/ (same destination the drone-product redirect plugin
 * sends the real slugs to).
 */
const UPLINKSYNC_NAV_ABOUT       = 'https://uplinksync.com/about/';
const UPLINKSYNC_NAV_CONTACT     = 'https://uplinksync.com/contact/';
const UPLINKSYNC_NAV_SERVICES    = 'https://uplinksync.com/services/';
const UPLINKSYNC_NAV_MANAGED_IT  = 'https://uplinksync.com/services/managed-it/';
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
	// Primary-nav item injection moved to the site-wide uplinksync-primary-nav.php
	// (***-126) so `/`, `/about/`, `/contact/`, `/services/` all render the same
	// nav (About / Services / Contact). This plugin no longer injects nav items;
	// it only rewrites the hero CTAs and duplicate product links on `/`.

	return $html;
}

/**
 * Rewrite the hero / body CTA hrefs to their canonical destinations.
 *
 * Most legacy hrefs appear exactly once on the page, so a whole-URL swap is
 * unambiguous. The one exception is /product/drone-inspection-service/, which
 * is BOTH the "Get Help" CTA and a product-collection tile ("Drone Inspection
 * Service"). Only the CTA should become /contact/; the product tile is left to
 * the existing 301 (drone-product-redirects -> /drone-services/). So that one
 * is matched by its anchor label, not by URL.
 */
function uplinksync_homepage_rewrite_ctas( $html ) {
	// Label-scoped first (before any broad URL swap touches the shared slug):
	// "Get Help" CTA -> /contact/.
	$html = preg_replace(
		'#(<a\b[^>]*\bhref=")https?://uplinksync\.com/product/drone-inspection-service/("[^>]*>\s*Get Help\s*</a>)#i',
		'${1}' . UPLINKSYNC_NAV_CONTACT . '${2}',
		$html
	);

	// Unambiguous whole-URL swaps (each legacy target occurs once on `/`).
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

