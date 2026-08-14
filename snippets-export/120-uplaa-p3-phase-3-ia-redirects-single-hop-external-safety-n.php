<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 120
 * name  : UPLAA-P3 Phase 3 IA redirects (single-hop, external safety net)
 * scope : front-end
 * state : ACTIVE
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

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
