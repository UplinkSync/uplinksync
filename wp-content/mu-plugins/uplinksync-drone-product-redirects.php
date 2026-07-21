<?php
/**
 * Plugin Name: UplinkSync — Legacy Drone Product Redirects
 * Description: 301-redirects the retired WooCommerce drone product pages to the Phase 1 gallery-only Drone Services page (***-69, per ***-25 strategy). Runs as an mu-plugin so it works regardless of active theme.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Retired product slugs → destination path.
 *
 * The Phase 1 decision (drone-gallery-store-strategy.md) is gallery-only:
 * no cart, no checkout. These product pages contradict that and the
 * MSP-first brand rule, so they permanently redirect to /drone-services/.
 */
function uplinksync_drone_legacy_redirects() {
	if ( is_admin() ) {
		return;
	}

	$request_path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
	if ( ! $request_path ) {
		return;
	}
	$request_path = trailingslashit( strtolower( $request_path ) );

	$legacy_paths = array(
		'/product/drone-aerial-capture/',
		'/product/drone-inspection-service/',
		'/product/drone-surveillance-pro/',
	);

	if ( in_array( $request_path, $legacy_paths, true ) ) {
		wp_safe_redirect( home_url( '/drone-services/' ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'uplinksync_drone_legacy_redirects' );
