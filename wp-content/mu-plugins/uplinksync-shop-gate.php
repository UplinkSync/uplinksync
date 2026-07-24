<?php
/**
 * Plugin Name: UplinkSync — Shop Gate (de-list WooCommerce storefront)
 * Description: Implements the owner-locked shop decision (***-101 / CEO ***-25, "MSP-first, avoid WooCommerce"): the public storefront is hidden, not deleted. The WooCommerce catalogue/account pages render as an off-brand, header-only "coming soon" archive that does not match the house design system (***-287), so instead of restyling a store we are not opening, this permanently gates those URLs into the real branded pages. WooCommerce stays installed so no live-linked product URL 404s. Theme-independent mu-plugin, same template_redirect layer as uplinksync-canonical-redirects.php and uplinksync-drone-product-redirects.php.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Storefront/account paths → branded destination.
 *
 * Matched on the leading path segment (prefix), so pagination and sub-paths are
 * covered too: /shop/, /shop/page/2/, /product-category/foo/ all collapse to the
 * Drone Services page; /my-account/ and its endpoints go to Contact.
 *
 *   /shop            -> /drone-services/   (catalogue archive)
 *   /product-category -> /drone-services/  (Woo term archives)
 *   /product-tag     -> /drone-services/
 *   /my-account      -> /contact/          (account/login/register endpoints)
 *
 * Deliberately NOT redirected here:
 *   - /cart/ and /checkout/: handled by noindex + Woo's own quote-only flow
 *     (uplinksync-quote-only.php). They should not be linked publicly, but a
 *     301 on checkout can interfere with Woo session handling, so we only
 *     noindex them below rather than redirect.
 *   - Individual /product/<slug>/ pages: owned by the legacy-product redirect
 *     plugins (uplinksync-drone-product-redirects.php + the DB redirect table),
 *     which point each retired product at its correct canonical destination.
 */
function uplinksync_shop_gate_prefix_map() {
	return array(
		'/shop'             => '/drone-services/',
		'/product-category' => '/drone-services/',
		'/product-tag'      => '/drone-services/',
		'/my-account'       => '/contact/',
	);
}

/**
 * Fire the 301s early on the front end. template_redirect runs before output
 * and is skipped in admin/REST/AJAX so store management keeps working.
 */
function uplinksync_shop_gate_redirects() {
	if ( is_admin() || wp_doing_ajax() || wp_is_json_request() ) {
		return;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}

	$request_path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
	if ( ! $request_path ) {
		return;
	}
	$request_path = '/' . trim( strtolower( $request_path ), '/' );

	foreach ( uplinksync_shop_gate_prefix_map() as $prefix => $destination ) {
		if ( $request_path === $prefix || 0 === strpos( $request_path, $prefix . '/' ) ) {
			wp_safe_redirect( home_url( $destination ), 301 );
			exit;
		}
	}
}
add_action( 'template_redirect', 'uplinksync_shop_gate_redirects', 5 );

/**
 * Backstop noindex for any Woo utility page that is reachable but not redirected
 * (cart, checkout, and anything WooCommerce flags as a shop/account/cart page).
 * A restrictive robots directive keeps the hidden storefront out of the index
 * even if a stray link or cache serves it. Rank Math emits its own robots meta;
 * this runs at wp_head priority 1 and Rank Math will still add its tag, so we
 * also short-circuit via wp_robots for block themes.
 */
function uplinksync_shop_gate_is_woo_utility() {
	if ( ! function_exists( 'is_woocommerce' ) ) {
		return false;
	}
	return is_shop()
		|| is_product_category()
		|| is_product_tag()
		|| is_cart()
		|| is_checkout()
		|| is_account_page();
}

function uplinksync_shop_gate_robots( $robots ) {
	if ( uplinksync_shop_gate_is_woo_utility() ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		unset( $robots['index'], $robots['follow'] );
	}
	return $robots;
}
add_filter( 'wp_robots', 'uplinksync_shop_gate_robots', 99 );

/**
 * Rank Math override — it builds its robots string independently of wp_robots.
 * Force noindex,nofollow on the Woo utility pages so the SEO plugin agrees.
 */
function uplinksync_shop_gate_rankmath_robots( $robots ) {
	if ( uplinksync_shop_gate_is_woo_utility() ) {
		return array(
			'index'  => 'noindex',
			'follow' => 'nofollow',
		);
	}
	return $robots;
}
add_filter( 'rank_math/frontend/robots', 'uplinksync_shop_gate_rankmath_robots', 99 );
