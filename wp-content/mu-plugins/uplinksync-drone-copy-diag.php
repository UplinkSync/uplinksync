<?php
/**
 * Plugin Name: UplinkSync — Drone copy migration DIAGNOSTIC (UPLAA-478)
 * Description: READ-ONLY, one-file diagnostic for why the /drone-services/ body
 *   re-aim (uplinksync-drone-listing-copy.php) is not live on prod despite the
 *   file deploying cleanly. It NEVER writes: it only reports the migration's own
 *   on-host state as HTTP response headers so an agent (no SSH, no wp-cli) can
 *   read the exact failure branch with a single gated GET.
 *
 *   WHY: the whole delivery chain is green — MR !130 merged, GitHub mirror synced
 *   the byte-identical v1.2.0 file, the GitHub "Deploy to WordPress host" Action
 *   succeeded — yet a cache-MISS render of /drone-services/ still serves the OLD
 *   H1 + both OLD h3 headings. That isolates the fault to the DB migration not
 *   persisting post_content on the live host. This diag surfaces which branch the
 *   migration hits (page-absent / no-match / reverted / a number / already) plus
 *   whether the mu-plugin is even loaded and whether the resolved page carries
 *   the needle — the facts needed to fix root cause #1 without a human.
 *
 *   SAFETY:
 *     - Emits headers ONLY when ?uls_drone_diag=<token> matches the token below,
 *       so it is inert for every normal visitor and search crawler.
 *     - Registers NO output buffer, alters NO page content, performs NO DB writes.
 *     - Remove this file once RC#1 is resolved (or leave it — it is inert without
 *       the token).
 *
 * Version: 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Rotate/remove after diagnosis. Not a secret that guards anything writable —
// it only gates read-only introspection headers.
const UPLINKSYNC_DRONE_DIAG_TOKEN = 'uls478diag';

function uplinksync_drone_diag_emit() {
	if ( headers_sent() ) {
		return;
	}
	$token = isset( $_GET['uls_drone_diag'] ) ? (string) $_GET['uls_drone_diag'] : '';
	if ( ! hash_equals( UPLINKSYNC_DRONE_DIAG_TOKEN, $token ) ) {
		return;
	}

	$loaded_copy = function_exists( 'uplinksync_drone_listing_copy_run' ) ? 'yes' : 'no';
	header( 'X-Uls-Drone-Copy-Plugin-Loaded: ' . $loaded_copy );

	$version = get_option( 'uplinksync_drone_listing_copy_version', '(unset)' );
	$applied = get_option( 'uplinksync_drone_listing_copy_applied', '(unset)' );
	header( 'X-Uls-Drone-Copy-Version-Option: ' . sanitize_text_field( (string) $version ) );
	header( 'X-Uls-Drone-Copy-Applied-Option: ' . sanitize_text_field( (string) $applied ) );

	// Resolve the page the same way the migration does, and report needle state.
	$page = null;
	if ( function_exists( 'uplinksync_drone_listing_copy_resolve' ) ) {
		$page = uplinksync_drone_listing_copy_resolve();
	} else {
		$page = get_page_by_path( 'drone-services', OBJECT, 'page' );
	}

	if ( $page instanceof WP_Post ) {
		header( 'X-Uls-Drone-Page-Id: ' . (int) $page->ID );
		$c        = $page->post_content;
		$h1_old   = ( false !== strpos( $c, 'Aerial imagery, inspection stills, and site overviews' ) ) ? '1' : '0';
		$h1_new   = ( false !== strpos( $c, 'Property and listing photo and video' ) ) ? '1' : '0';
		$h3a_old  = ( false !== strpos( $c, '>Aerial imagery</h3>' ) ) ? '1' : '0';
		$h3a_new  = ( false !== strpos( $c, '>Listing photo &amp; video</h3>' ) ) ? '1' : '0';
		header( 'X-Uls-Drone-Db-H1-Old: ' . $h1_old );
		header( 'X-Uls-Drone-Db-H1-New: ' . $h1_new );
		header( 'X-Uls-Drone-Db-H3aerial-Old: ' . $h3a_old );
		header( 'X-Uls-Drone-Db-H3aerial-New: ' . $h3a_new );
		header( 'X-Uls-Drone-Db-Content-Len: ' . strlen( $c ) );
	} else {
		header( 'X-Uls-Drone-Page-Id: none' );
	}
}
// Late priority so options + the sibling mu-plugin's init pass have run.
add_action( 'wp', 'uplinksync_drone_diag_emit', 99 );
add_action( 'send_headers', 'uplinksync_drone_diag_emit', 99 );
