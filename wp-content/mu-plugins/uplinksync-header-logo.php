<?php
/**
 * Plugin Name: UplinkSync — Header Logo Lockup
 * Description: Replaces the plain-text header site-title with the owner-supplied horizontal UplinkSync logo lockup (***-101). The image ships in-repo under mu-plugins/uplinksync-header-logo/ so it deploys with wp-content and is independent of the active theme and of any wp-admin media upload (which the bot cannot perform — 401). The header currently renders "UplinkSync" as a wp-block-site-title <p>; we swap only the anchor's inner text for the logo <img>, preserving the home link and adding an accessible alt.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Asset facts — owner requirement (***-101 wake comment, 2026-07-21).
 *
 * Master: /mnt/uplinksync/Marketing/Branding/uplinksync-logo-transparent2.png
 *   802 x 132 RGBA PNG, 61,146 bytes, md5 29cc2b47d35a533ac8c4cb9e82b4c7b5.
 * The horizontal lockup on a transparent background, for the top-left of the
 * site. Serve at native aspect ratio with explicit width/height. Do NOT
 * substitute the 1024x1024 square mark, and do not re-crop a square into a
 * fake horizontal.
 */
const UPLINKSYNC_LOGO_FILE   = 'uplinksync-header-logo/uplinksync-logo-transparent2.png';
const UPLINKSYNC_LOGO_WIDTH  = 802;
const UPLINKSYNC_LOGO_HEIGHT = 132;
const UPLINKSYNC_LOGO_ALT    = 'UplinkSync';

/**
 * Public URL of the in-repo logo asset. plugins_url() resolves correctly for
 * mu-plugins, giving …/wp-content/mu-plugins/uplinksync-header-logo/… .
 */
function uplinksync_logo_url() {
	return plugins_url( UPLINKSYNC_LOGO_FILE, __FILE__ );
}

/**
 * Only run on front-end HTML GET requests — never admin, REST, feeds, AJAX or
 * cron. Mirrors the guard used by the contact/social mu-plugin so the two stay
 * consistent.
 */
function uplinksync_logo_should_filter() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return false;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}
	if ( is_feed() || is_robots() || is_trackback() ) {
		return false;
	}
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) {
		return false;
	}
	return true;
}

function uplinksync_logo_start_buffer() {
	if ( ! uplinksync_logo_should_filter() ) {
		return;
	}
	ob_start( 'uplinksync_logo_rewrite' );
}
// Priority 1 (after the contact/social buffer opens at 0) so the two output
// buffers nest predictably; they touch disjoint markup either way.
add_action( 'template_redirect', 'uplinksync_logo_start_buffer', 1 );

/**
 * Swap the header site-title text for the logo image.
 *
 * The header brand renders as:
 *   <p class="… hostinger-ai-site-title … wp-block-site-title … has-large-font-size">
 *     <a href="https://uplinksync.com" … rel="home" …>UplinkSync</a>
 *   </p>
 *
 * We match that specific <p> (the header instance carries BOTH
 * hostinger-ai-site-title AND wp-block-site-title; the footer copy has neither
 * hostinger-ai-site-title nor has-large-font-size, so it is left as text) and
 * replace only the inner content of its <a> with an <img>. The anchor, its
 * href and rel="home" are preserved, so the logo remains the home link.
 *
 * Explicit width/height attributes carry the intrinsic 802x132 so the browser
 * reserves the right box (no layout shift); inline CSS caps the rendered
 * height and keeps width:auto so the native aspect ratio is never distorted.
 */
function uplinksync_logo_rewrite( $html ) {
	if ( ! is_string( $html ) || false === stripos( $html, '</body>' ) ) {
		return $html;
	}

	$img = sprintf(
		'<img src="%s" width="%d" height="%d" alt="%s" style="display:block;height:auto;width:auto;max-height:44px;max-width:100%%" decoding="async" fetchpriority="high" />',
		esc_url( uplinksync_logo_url() ),
		UPLINKSYNC_LOGO_WIDTH,
		UPLINKSYNC_LOGO_HEIGHT,
		esc_attr( UPLINKSYNC_LOGO_ALT )
	);

	// Match the header site-title <p> (must contain hostinger-ai-site-title AND
	// wp-block-site-title) and capture its <a …> open tag; replace the anchor's
	// inner text with the logo <img>. Non-greedy, dotall for safety.
	$pattern = '#(<p\b[^>]*class="[^"]*hostinger-ai-site-title[^"]*wp-block-site-title[^"]*"[^>]*>\s*<a\b[^>]*>)(.*?)(</a>)#is';

	$replaced = preg_replace_callback(
		$pattern,
		function ( $m ) use ( $img ) {
			return $m[1] . $img . $m[3];
		},
		$html,
		1 // header only — never the footer/other site-title instances
	);

	return ( null === $replaced ) ? $html : $replaced;
}
