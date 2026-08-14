<?php
/**
 * UPLAA-366 Allow anon WooCommerce Store API (checkout/Stripe) past ASE REST lock
 *
 * Migrated from database-resident Code Snippets row id=91 (DR-004 tranche 3).
 * scope: global   priority: 10
 *
 * Storefront launch (UPLAA-366). ASE "Disable REST API" (disable_rest_api=true) blocks anon access to wc/store/v1, so the block checkout cannot load payment methods (Stripe) for guests. This grants anonymous access to ONLY the public WooCommerce Store API namespace (wc/store/*), le
 *
 * SECURITY / URL-AFFECTING. Verified by behaviour, not by page bytes:
 * snippet 91 against the doc 118 F1 probe set, snippet 120 against its full
 * redirect map. Migrated VERBATIM.
 *
 * The wp_snippets row is DEACTIVATED, not deleted - re-activating it in wp-admin
 * is the rollback and needs no deploy.
 */
defined( 'ABSPATH' ) || exit;

add_filter( 'rest_authentication_errors', function ( $result ) {
	// Only act when a prior filter (ASE) already denied an unauthenticated request.
	if ( is_wp_error( $result ) ) {
		// UPLAA-SEC-2026-08-14: match the request PATH and the parsed REST route only.
		//
		// The previous version built its haystack from the raw REQUEST_URI, which
		// INCLUDES THE QUERY STRING, and searched it with an unanchored strpos().
		// That let any request re-open the site-wide REST lock simply by appending
		// the needle as a junk parameter -- verified live:
		//     GET /wp-json/wp/v2/users              -> 401
		//     GET /wp-json/wp/v2/users?a=wc/store/  -> 200  (user data disclosed)
		// parse_url(..., PHP_URL_PATH) drops the query string, so the attacker no
		// longer controls the haystack. Both original signals are retained, so the
		// Store API (cart, checkout, products, payment methods) still works for
		// anonymous shoppers in both the /wp-json/ and ?rest_route= forms.
		$uri   = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$path  = parse_url( $uri, PHP_URL_PATH );
		$path  = is_string( $path ) ? $path : '';
		$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';
		$route = '/' . ltrim( $route, '/' );

		// Public WooCommerce Store API only (cart, checkout, products, payment methods).
		if ( 0 === strpos( $route, '/wc/store/' ) || false !== strpos( $path, '/wc/store/' ) ) {
			return true; // allow anonymous access to the storefront API
		}
	}
	return $result;
}, PHP_INT_MAX );
