<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 91
 * name  : UPLAA-366 Allow anon WooCommerce Store API (checkout/Stripe) past ASE REST lock
 * scope : global
 * state : ACTIVE
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

add_filter( 'rest_authentication_errors', function ( $result ) {
	// Only act when a prior filter (ASE) already denied an unauthenticated request.
	if ( is_wp_error( $result ) ) {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';
		$hay = $uri . '|' . $route;
		// Public WooCommerce Store API only (cart, checkout, products, payment methods).
		if ( false !== strpos( $hay, 'wc/store/' ) ) {
			return true; // allow anonymous access to the storefront API
		}
	}
	return $result;
}, PHP_INT_MAX );
