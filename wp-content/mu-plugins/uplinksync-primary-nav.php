<?php
/**
 * Plugin Name: UplinkSync — Site-wide Primary Nav
 * Description: Injects a consistent primary navigation (About / Services / Contact) into the empty Hostinger AI theme header nav on EVERY front-end page (***-126). The theme renders a horizontal wp-block-navigation whose responsive-container-content is empty (the site was built with no menu assigned), so only the home page ever carried links (previously injected by uplinksync-homepage-nav-ctas.php, front-page-only). That left `/about/`, `/contact/`, `/services/` with a blank nav. Like the other UplinkSync fixes the header markup is produced by the theme + saved block content in the WP DB, NOT tracked files, so we rewrite the rendered document on the way out. This plugin is the single source of truth for the primary-nav item list; the homepage plugin now only handles hero-CTA/dupe-product rewrites.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical primary-nav destinations (IA §3, ***-101). Owner-authoritative.
 * Contact renders as the single accent-filled nav CTA (brand accent-600
 * #2F6FC4, white text — the WCAG-AA interactive fill from the locked palette).
 */
const UPLINKSYNC_PRIMARY_NAV_ABOUT    = 'https://uplinksync.com/about/';
const UPLINKSYNC_PRIMARY_NAV_SERVICES = 'https://uplinksync.com/services/';
const UPLINKSYNC_PRIMARY_NAV_CONTACT  = 'https://uplinksync.com/contact/';

/**
 * Scope guard: front-end GET requests only. Skip admin, AJAX, REST, JSON, feeds
 * and embeds so we never rewrite non-document responses.
 */
function uplinksync_primary_nav_should_filter() {
	if ( is_admin() || wp_doing_ajax() || wp_is_json_request() ) {
		return false;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}
	if ( is_feed() || is_embed() ) {
		return false;
	}
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
		return false;
	}
	return true;
}

function uplinksync_primary_nav_start_buffer() {
	if ( ! uplinksync_primary_nav_should_filter() ) {
		return;
	}
	ob_start( 'uplinksync_primary_nav_rewrite' );
}
// Priority 2 so this buffer opens AFTER the contact/social (0) and homepage
// nav/CTA (1) buffers. Nested output buffers unwind LIFO, so ours closes first
// and the homepage CTA/dupe rewrites still see the finished document. The nav
// item list this plugin injects and the CTA hrefs the homepage plugin rewrites
// touch disjoint markup, so order between them is not otherwise critical.
add_action( 'template_redirect', 'uplinksync_primary_nav_start_buffer', 2 );

function uplinksync_primary_nav_rewrite( $html ) {
	if ( ! is_string( $html ) || false === stripos( $html, '</body>' ) ) {
		return $html;
	}
	return uplinksync_primary_nav_inject( $html );
}

/**
 * Build the primary-nav <ul>. The current page (if it is one of the nav
 * targets) gets aria-current="page" and a marker class so the theme/brand CSS
 * can highlight it. The marker class `uplinksync-primary-nav-injected` doubles
 * as the idempotency guard.
 */
function uplinksync_primary_nav_items_markup() {
	// Normalise the current request path for active-item detection.
	$current = '';
	if ( isset( $_SERVER['REQUEST_URI'] ) ) {
		$current = strtok( $_SERVER['REQUEST_URI'], '?' );
		$current = '/' . trim( $current, '/' );
		if ( '/' !== substr( $current, -1 ) ) {
			$current .= '/';
		}
	}

	$items = array(
		array(
			'url'   => UPLINKSYNC_PRIMARY_NAV_ABOUT,
			'label' => 'About',
			'path'  => '/about/',
			'cta'   => false,
		),
		array(
			'url'   => UPLINKSYNC_PRIMARY_NAV_SERVICES,
			'label' => 'Services',
			'path'  => '/services/',
			'cta'   => false,
		),
		array(
			'url'   => UPLINKSYNC_PRIMARY_NAV_CONTACT,
			'label' => 'Contact',
			'path'  => '/contact/',
			'cta'   => true,
		),
	);

	$lis = '';
	foreach ( $items as $item ) {
		$classes = 'wp-block-navigation-item wp-block-navigation-link';
		if ( $item['cta'] ) {
			$classes .= ' uplinksync-nav-cta';
		}
		// Active when the current path is the item path or a descendant of it
		// (e.g. /services/managed-it/ keeps Services active).
		$is_active = ( $current === $item['path'] )
			|| ( '/services/' === $item['path'] && 0 === strpos( $current, '/services/' ) );

		$aria = $is_active ? ' aria-current="page"' : '';
		if ( $is_active ) {
			$classes .= ' current-menu-item';
		}

		$style = $item['cta']
			? ' style="background-color:#2F6FC4;color:#FFFFFF;border-radius:999px;padding:0.4em 1.25em;"' // ***-315: single pill value (was 50px)
			: '';

		$lis .= '<li class="' . esc_attr( $classes ) . '">'
			. '<a class="wp-block-navigation-item__content" href="' . esc_url( $item['url'] ) . '"' . $aria . $style . '>'
			. '<span class="wp-block-navigation-item__label">' . esc_html( $item['label'] ) . '</span></a></li>';
	}

	return '<ul class="wp-block-navigation__container is-layout-flex wp-block-navigation-is-layout-flex uplinksync-primary-nav-injected">'
		. $lis
		. '</ul>';
}

/**
 * Locate the FIRST is-horizontal nav (the desktop primary bar) and fill its
 * empty responsive-container-content div with the primary-nav item list.
 *
 * Idempotent: if the marker class is already present the document is returned
 * unchanged (output buffers can run more than once; and if the homepage plugin
 * ever injects first, we defer to it).
 */
function uplinksync_primary_nav_inject( $html ) {
	if ( false !== stripos( $html, 'uplinksync-primary-nav-injected' ) ) {
		return $html; // already injected
	}

	$items = uplinksync_primary_nav_items_markup();

	// Match the first is-horizontal nav, then its (empty) responsive content
	// div, and drop the item list inside it. Non-greedy up to the content div so
	// we bind to the correct nav.
	$pattern = '#(<nav\b[^>]*\bis-horizontal\b[^>]*>.*?<div class="wp-block-navigation__responsive-container-content"[^>]*>)(\s*)(</div>)#is';

	$replaced = preg_replace(
		$pattern,
		'${1}' . $items . '${3}',
		$html,
		1,
		$count
	);

	if ( $count && null !== $replaced ) {
		return $replaced;
	}

	// Fallback: the horizontal nav's content div was not found in the expected
	// (empty) shape. Leave the document untouched rather than risk a mangled
	// header.
	return $html;
}
