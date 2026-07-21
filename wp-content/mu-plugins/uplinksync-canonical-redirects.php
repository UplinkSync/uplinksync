<?php
/**
 * Plugin Name: UplinkSync — Canonical URL Redirects
 * Description: Implements the ***-101 Information Architecture §2 redirect map (***-104). Points every legacy/duplicate URL at its true canonical destination instead of letting WordPress silently collapse it to `/`. Runs as an mu-plugin so it is theme-independent and captured in-repo (deploys with wp-content). Companion to uplinksync-drone-product-redirects.php, which handles the retired Woo drone products; this plugin covers the page-level slug corrections.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IA §2 redirect map — legacy path (trailing-slashed, lowercase) => canonical path.
 *
 * Scope of THIS plugin (repo-capturable, path-level 301s):
 *   /about-4/    -> /about/      (WP auto-suffixed the original page; reclaim /about/)
 *   /services-2/ -> /services/   (same auto-suffix story for the services overview)
 *
 * Deliberately NOT here, and why:
 *   - /contact/, /about/ returning 200: those are real Pages created in the WP
 *     DB on the locked slugs (IA §1). Once the Page exists WordPress serves it
 *     directly, so no redirect rule is needed — and adding one would shadow the
 *     real page. Page creation is recorded on ***-104 (wp-admin/REST, not repo).
 *   - Reversing /drone-services/ -> /product/drone-services/ and
 *     /services/managed-it/ -> /product/managed-it-services/: those source
 *     redirects live in the WordPress DB (redirect table / Woo permalink), not
 *     in this repo. They are removed at the DB layer when the real pages ship;
 *     see the ***-104 drift note. A path rule here cannot delete a DB rule.
 *   - Retiring the legacy Woo drone products: already handled by
 *     uplinksync-drone-product-redirects.php.
 */
function uplinksync_canonical_redirect_map() {
	return array(
		'/about-4/'    => '/about/',
		'/services-2/' => '/services/',
	);
}

/**
 * Fire the 301s early on the front end. template_redirect runs before any
 * output, is skipped in admin, and is the same hook the drone-product plugin
 * uses — one consistent redirect layer for the site.
 */
function uplinksync_canonical_redirects() {
	if ( is_admin() ) {
		return;
	}

	$request_path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
	if ( ! $request_path ) {
		return;
	}
	$request_path = trailingslashit( strtolower( $request_path ) );

	$map = uplinksync_canonical_redirect_map();
	if ( isset( $map[ $request_path ] ) ) {
		wp_safe_redirect( home_url( $map[ $request_path ] ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'uplinksync_canonical_redirects' );
