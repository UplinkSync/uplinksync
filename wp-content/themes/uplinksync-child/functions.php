<?php
/**
 * UplinkSync child theme bootstrap.
 * Loads parent theme styles, then the brand token/override layer from visual-system.md (***-21/***-46).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
