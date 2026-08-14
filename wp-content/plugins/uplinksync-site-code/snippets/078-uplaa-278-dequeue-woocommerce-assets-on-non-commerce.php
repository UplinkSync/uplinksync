<?php
/**
 * UPLAA-278 Dequeue WooCommerce assets on non-commerce pages
 *
 * Migrated from database-resident Code Snippets row id=78 (DR-004 tranche 2).
 * scope: front-end   priority: 99
 *
 * Conditionally dequeues WooCommerce-specific styles/scripts on marketing/legal pages. Early-returns on ALL commerce contexts (shop/cart/checkout/account/product/tax/category) and on any page embedding a woo block or shortcode, so /shop/ /cart/ /checkout/ /my-account/ are untouched. jQuery core is del
 *
 * Migrated VERBATIM. Any behaviour change to this snippet is a separate commit, so
 * that the migration itself can be proven byte-identical in rendered output.
 *
 * The wp_snippets row is DEACTIVATED, not deleted - re-activating it in wp-admin
 * is the rollback and needs no deploy. Exactly one copy must be active at a time.
 */
defined( 'ABSPATH' ) || exit;

/**
 * UPLAA-278 — Strip WooCommerce assets on non-commerce pages.
 *
 * Woo enqueues ~6 stylesheets + several scripts on EVERY page (incl. legal
 * exhibits). This dequeues the woo-specific handles on marketing/legal pages
 * only. It bails early on every real commerce context and on any page that
 * embeds a woo block/shortcode, so /shop/ /cart/ /checkout/ /my-account/ and
 * any product content keep their assets. jQuery core is intentionally NOT
 * touched (theme + other plugins depend on it). Deactivate to fully revert.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() ) {
		return;
	}

	// Bail on any genuine WooCommerce context.
	if ( function_exists( 'is_woocommerce' ) ) {
		if ( is_woocommerce() || is_cart() || is_checkout() || is_account_page()
			|| is_shop() || is_product() || is_product_category() || is_product_tag() ) {
			return;
		}
	}

	// Bail if the current page embeds a woo shortcode or block.
	if ( is_singular() ) {
		$post = get_post();
		if ( $post instanceof WP_Post ) {
			$c = (string) $post->post_content;
			$sc = array( 'woocommerce_cart', 'woocommerce_checkout', 'woocommerce_my_account',
				'products', 'product', 'product_page', 'product_category', 'add_to_cart',
				'add_to_cart_url', 'shop_messages', 'recent_products', 'featured_products' );
			foreach ( $sc as $tag ) {
				if ( has_shortcode( $c, $tag ) ) {
					return;
				}
			}
			if ( strpos( $c, 'wp:woocommerce/' ) !== false ) {
				return;
			}
		}
	}

	$styles = array(
		'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen',
		'woocommerce-blocktheme', 'wc-blocks-style', 'wc-blocks-packages-style',
		'wc-blocks-vendors-style', 'brands-styles', 'photoswipe', 'photoswipe-default-skin',
	);
	foreach ( $styles as $h ) {
		wp_dequeue_style( $h );
		wp_deregister_style( $h );
	}

	$scripts = array(
		'woocommerce', 'wc-cart-fragments', 'wc-add-to-cart', 'wc-blocks',
		'sourcebuster-js', 'wc-order-attribution', 'woocommerce-store-notice',
		'flexslider', 'zoom', 'photoswipe', 'photoswipe-ui-default', 'prettyPhoto',
		'wc-single-product', 'wc-password-strength-meter', 'selectWoo', 'select2',
	);
	foreach ( $scripts as $h ) {
		wp_dequeue_script( $h );
		wp_deregister_script( $h );
	}
}, 99 );
