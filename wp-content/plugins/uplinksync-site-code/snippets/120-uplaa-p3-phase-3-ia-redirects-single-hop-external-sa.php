<?php
/**
 * UPLAA-P3 Phase 3 IA redirects (single-hop, external safety net)
 *
 * Migrated from database-resident Code Snippets row id=120 (DR-004 tranche 3).
 * scope: front-end   priority: 10
 *
 * Owner-approved Phase 3 IA rebuild. 301s for MOVED paths (/for/, /for/real-estate/, /services/martial-arts-academies/, /hosting/, /web-design/), new Client Hub aliases (/client-hub/, /portal/, /account/ -> /hub/), and retired duplicate pages keyed by ID (1257,1258,1259,1260,1271,1
 *
 * SECURITY / URL-AFFECTING. Verified by behaviour, not by page bytes:
 * snippet 91 against the doc 118 F1 probe set, snippet 120 against its full
 * redirect map. Migrated VERBATIM.
 *
 * The wp_snippets row is DEACTIVATED, not deleted - re-activating it in wp-admin
 * is the rollback and needs no deploy.
 */
defined( 'ABSPATH' ) || exit;

/**
 * UPLAA-P3 - Phase 3 information-architecture redirects (single hop, no chains).
 * Owner-approved Phase 3 build. This is a SAFETY NET for EXTERNAL inbound traffic
 * (bookmarks, search results, third-party links) only - the site's own internal
 * links point at final URLs directly.
 * REVERSIBLE: deactivate this snippet to remove every redirect below.
 * NOTE: /login/ is deliberately NOT claimed - ASE login-URL hiding owns that path.
 */
add_action( 'template_redirect', function () {

	if ( is_admin() || is_preview() || wp_doing_ajax() ) {
		return;
	}
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( $_SERVER['REQUEST_METHOD'] ) : 'GET';
	if ( 'GET' !== $method && 'HEAD' !== $method ) {
		return;
	}
	// Owner escape hatch: ?noredir=1 lets an editor inspect a retired page.
	if ( isset( $_GET['noredir'] ) && current_user_can( 'edit_pages' ) ) {
		return;
	}

	$raw  = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
	$path = strtok( $raw, '?' );
	$path = '/' . trim( strtolower( rawurldecode( $path ) ), '/' );

	// 1) Moved/retired PATHS that no longer resolve, plus the new Hub aliases.
	$by_path = array(
		'/client-hub'                      => '/hub/',
		'/portal'                          => '/hub/',
		'/account'                         => '/hub/',
		'/for'                             => '/who-we-serve/',
		'/for/real-estate'                 => '/real-estate/',
		'/for/real-estate-2'               => '/real-estate/',
		'/who-we-serve/real-estate-2'      => '/real-estate/',
		'/services/martial-arts-academies' => '/martial-arts-academies/',
		'/hosting'                         => '/services/hosting/',
		'/web-design'                      => '/services/web/',
	);

	$target = isset( $by_path[ $path ] ) ? $by_path[ $path ] : '';

	// 2) Retired duplicate PAGES that still resolve - keyed by ID so they
	//    survive any future slug or parent change.
	if ( '' === $target && is_page() ) {
		$by_id = array(
			1257 => '/services/managed-it/',
			1271 => '/services/managed-it/',
			1258 => '/services/automation/',
			1272 => '/services/automation/',
			1259 => '/services/web/',
			1273 => '/services/web/',
			1274 => '/real-estate/',
			1260 => '/services/web/',
		);
		$id = (int) get_queried_object_id();
		if ( $id && isset( $by_id[ $id ] ) ) {
			$target = $by_id[ $id ];
		}
	}

	if ( '' === $target ) {
		return;
	}
	if ( rtrim( $target, '/' ) === rtrim( $path, '/' ) ) {
		return; // loop guard
	}

	wp_safe_redirect( home_url( $target ), 301 );
	exit;
}, 1 );
