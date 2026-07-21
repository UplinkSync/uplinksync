<?php
/**
 * UplinkSync child theme bootstrap.
 * Loads parent theme styles, then the brand token/override layer from visual-system.md (***-21/***-46).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ***-99: restore correct asset resolution under a child theme.
 *
 * The parent theme (hostinger-ai-theme) was never written for child themes.
 * It builds its own asset paths with the *stylesheet* helpers, which resolve
 * to the ACTIVE theme — i.e. this child — as soon as a child theme is active.
 * That points the parent at uplinksync-child/assets/, where its files do not
 * exist, so the compiled stylesheet and JS bundle 301/404 and the live site
 * renders unstyled.
 *
 * There are two independent code paths in the parent, and each needs its own
 * fix. We cannot edit the parent — it is vendored and Hostinger updates it —
 * so both fixes live here in the child.
 *
 * 1. CONSTANTS. functions.php and includes/Admin/Assets.php build admin/editor
 *    asset URLs from HOSTINGER_AI_WEBSITES_ASSETS_URL / _THEME_PATH. The parent
 *    guards each define with `if ( ! defined( ... ) )`, and WordPress loads the
 *    child's functions.php in full BEFORE the parent's, so the child can claim
 *    those constants first and pin them to the parent (template) directory.
 */
if ( ! defined( 'HOSTINGER_AI_WEBSITES_THEME_PATH' ) ) {
	define( 'HOSTINGER_AI_WEBSITES_THEME_PATH', get_template_directory() );
}
if ( ! defined( 'HOSTINGER_AI_WEBSITES_ASSETS_URL' ) ) {
	define( 'HOSTINGER_AI_WEBSITES_ASSETS_URL', get_template_directory_uri() . '/assets' );
}

/**
 * 2. RAW HELPER CALLS. The parent's *frontend* assets are the ones that broke
 *    the public site, and they do NOT go through the constants above. They are
 *    enqueued with get_stylesheet_directory_uri() directly:
 *      - includes/Assets.php        -> assets/css/style.min.css (the 16 KB sheet)
 *      - includes/Assets.php        -> assets/js/front-scripts.min.js
 *      - includes/Elementor/WidgetManager.php uses get_template_directory_uri()
 *        (already correct — left untouched by this filter).
 *    Defining the constants alone therefore does NOT fix the homepage; that is
 *    why this filter exists in addition to the constants.
 *
 *    This filter is file-existence driven and self-correcting: for any style or
 *    script whose URL points inside THIS theme's directory, if the file is not
 *    present here but IS present in the parent theme, it is a parent-owned asset
 *    mis-resolved to the child dir, so we reroute the URL to the parent. The
 *    child's own assets (tokens.css, brand.css, quote-form.*, drone-gallery.css)
 *    exist here, so they are never touched. Any future parent asset added to a
 *    raw stylesheet-helper path is covered automatically.
 */
function uplinksync_child_reroute_parent_assets( $src ) {
	if ( empty( $src ) || ! is_string( $src ) ) {
		return $src;
	}

	$child_uri   = get_stylesheet_directory_uri();
	$parent_uri  = get_template_directory_uri();

	// Nothing to do when no child theme is active (parent === stylesheet).
	if ( $child_uri === $parent_uri ) {
		return $src;
	}

	// Only act on URLs that resolve inside this (child) theme directory.
	$src_path = strtok( $src, '?' );
	if ( 0 !== strpos( $src_path, $child_uri . '/' ) ) {
		return $src;
	}

	$relative    = substr( $src_path, strlen( $child_uri ) );
	$child_file  = get_stylesheet_directory() . $relative;
	$parent_file = get_template_directory() . $relative;

	// Ours (exists in child) -> leave alone. Parent-owned (only in parent) -> reroute.
	if ( file_exists( $child_file ) || ! file_exists( $parent_file ) ) {
		return $src;
	}

	$query = ( strlen( $src ) > strlen( $src_path ) ) ? substr( $src, strlen( $src_path ) ) : '';
	return $parent_uri . $relative . $query;
}
add_filter( 'style_loader_src', 'uplinksync_child_reroute_parent_assets', 5 );
add_filter( 'script_loader_src', 'uplinksync_child_reroute_parent_assets', 5 );

function uplinksync_child_enqueue_assets() {
	$parent_style = 'hostinger-ai-theme-style';

	wp_enqueue_style(
		$parent_style,
		get_template_directory_uri() . '/style.css',
		array(),
		wp_get_theme( get_template() )->get( 'Version' )
	);

	wp_enqueue_style(
		'uplinksync-tokens',
		get_stylesheet_directory_uri() . '/assets/css/tokens.css',
		array( $parent_style ),
		wp_get_theme()->get( 'Version' )
	);

	wp_enqueue_style(
		'uplinksync-brand',
		get_stylesheet_directory_uri() . '/assets/css/brand.css',
		array( 'uplinksync-tokens' ),
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'uplinksync_child_enqueue_assets', 20 );

/**
 * ***-69: Phase 1 drone gallery page (slug: drone-services).
 * Watermark CSS is only needed there; title tag is fixed per the
 * ***-25 strategy ("Drone Photography & Inspection Services | UplinkSync").
 */
function uplinksync_child_drone_gallery_assets() {
	if ( ! is_page( 'drone-services' ) ) {
		return;
	}
	wp_enqueue_style(
		'uplinksync-drone-gallery',
		get_stylesheet_directory_uri() . '/assets/css/drone-gallery.css',
		array( 'uplinksync-brand' ),
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'uplinksync_child_drone_gallery_assets', 21 );

function uplinksync_child_drone_gallery_title( $title_parts ) {
	if ( is_page( 'drone-services' ) ) {
		return array( 'title' => 'Drone Photography & Inspection Services | UplinkSync' );
	}
	return $title_parts;
}
add_filter( 'document_title_parts', 'uplinksync_child_drone_gallery_title' );

/**
 * ***-42: quote form styling/behaviour, only on the pages that host the form.
 * Markup is supplied by Contact Form 7, which is installed on the host rather
 * than vendored into this repo (site deploys exclude plugins/).
 */
function uplinksync_child_quote_form_assets() {
	if ( ! is_page( array( 'contact', 'services/managed-it', 'managed-it' ) ) ) {
		return;
	}
	wp_enqueue_style(
		'uplinksync-quote-form',
		get_stylesheet_directory_uri() . '/assets/css/quote-form.css',
		array( 'uplinksync-brand' ),
		wp_get_theme()->get( 'Version' )
	);

	wp_enqueue_script(
		'uplinksync-quote-form',
		get_stylesheet_directory_uri() . '/assets/js/quote-form.js',
		array(),
		wp_get_theme()->get( 'Version' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'uplinksync_child_quote_form_assets', 21 );
