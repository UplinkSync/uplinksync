<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 78
 * name  : UPLAA-278 Dequeue WooCommerce assets on non-commerce pages
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
