<?php
/**
 * Plugin Name: UplinkSync — Outline Navy Token Snap
 * Description: One-shot migration from the deprecated outline-button navy to navy-900.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const UPLINKSYNC_OUTLINE_NAVY_SNAP_VERSION = '1.0.0';
const UPLINKSYNC_OUTLINE_NAVY_SNAP_OPTION  = 'uplinksync_outline_navy_snap_version';
const UPLINKSYNC_OUTLINE_NAVY_SNAP_COUNTS  = 'uplinksync_outline_navy_snap_counts';

/**
 * Replace only border declarations which carry the deprecated navy.
 *
 * The first pattern handles rendered inline style declarations. The second
 * handles Gutenberg's block-attribute JSON border object. Bare text, text
 * colour, fills, and unrelated properties are deliberately untouched.
 *
 * @param string   $content Block markup.
 * @param int|null $change_count Number of replacements made.
 * @return string Migrated block markup.
 */
function uplinksync_outline_navy_snap_content( $content, &$change_count = null ) {
	$change_count = 0;
	if ( ! is_string( $content ) || '' === $content ) {
		return $content;
	}

	$patterns = array(
		'/(?<![-\w])(border-color\s*:\s*)#1e3155\b/i',
		'/("border"\s*:\s*\{[^}]*"color"\s*:\s*")#1e3155(")/i',
	);
	$replacements = array( '$1#173258', '$1#173258$2' );

	foreach ( $patterns as $index => $pattern ) {
		$next = preg_replace( $pattern, $replacements[ $index ], $content, -1, $count );
		if ( null === $next ) {
			continue;
		}
		$content       = $next;
		$change_count += $count;
	}

	return $content;
}

/**
 * Run the migration across published pages and return per-page replacement counts.
 *
 * @return array<string,int|string> Counts keyed by post ID; write errors are
 *                                  recorded as "error:<message>".
 */
function uplinksync_outline_navy_snap_run() {
	$results = array();
	$pages   = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'numberposts'    => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'suppress_filters' => false,
		)
	);

	foreach ( $pages as $page ) {
		$count   = 0;
		$content = uplinksync_outline_navy_snap_content( $page->post_content, $count );
		if ( 0 === $count ) {
			continue;
		}

		$updated = wp_update_post(
			array(
				'ID'           => $page->ID,
				'post_content' => $content,
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			$results[ (string) $page->ID ] = 'error:' . $updated->get_error_message();
			continue;
		}
		$results[ (string) $page->ID ] = $count;
	}

	return $results;
}

/**
 * Apply the migration once. The results option makes a production no-op or the
 * expected three replacements observable without shell access.
 */
function uplinksync_outline_navy_snap_maybe_run() {
	if ( UPLINKSYNC_OUTLINE_NAVY_SNAP_VERSION === get_option( UPLINKSYNC_OUTLINE_NAVY_SNAP_OPTION ) ) {
		return;
	}

	$results = uplinksync_outline_navy_snap_run();
	update_option( UPLINKSYNC_OUTLINE_NAVY_SNAP_COUNTS, $results, false );
	update_option( UPLINKSYNC_OUTLINE_NAVY_SNAP_OPTION, UPLINKSYNC_OUTLINE_NAVY_SNAP_VERSION, false );
}
add_action( 'init', 'uplinksync_outline_navy_snap_maybe_run', 20 );
