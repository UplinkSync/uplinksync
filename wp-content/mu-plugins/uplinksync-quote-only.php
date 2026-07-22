<?php
/**
 * Plugin Name: UplinkSync — Quote-Only (suppress pricing & cart)
 * Description: Owner directive 2026-07-22: no pricing on the public website. Prospects request a quote instead. Removes WooCommerce price output and add-to-cart affordances everywhere on the front end, and points the resulting CTA at the contact/quote page. Uses WordPress/WooCommerce filters (render_block, woocommerce_get_price_html) rather than an output-buffer rewrite, so it cannot blank a page the way a full ob_start() rebuild can.
 * Version: 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const UPLINKSYNC_QUOTE_URL   = 'https://uplinksync.com/contact/';
const UPLINKSYNC_QUOTE_LABEL = 'Request a quote';

/**
 * Front-end only. Never touch admin, REST, AJAX or the block editor, so store
 * management and the WooCommerce admin keep working normally — this hides price
 * from visitors, it does not change product data.
 */
function uplinksync_quote_only_active() {
	if ( is_admin() || wp_doing_ajax() || wp_is_json_request() ) {
		return false;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}
	return true;
}

/**
 * Canonical "Request a quote" button markup, reused by every replacement path.
 * Carries its own class (not .wp-block-woocommerce-product-button) so the CSS
 * backstop in uplinksync_quote_only_css() does not hide it.
 */
function uplinksync_quote_only_cta_markup() {
	return '<div class="wp-block-button uplinksync-quote-cta"><a class="wp-block-button__link wp-element-button" href="'
		. esc_url( UPLINKSYNC_QUOTE_URL ) . '">' . esc_html( UPLINKSYNC_QUOTE_LABEL ) . '</a></div>';
}

/**
 * 1. Classic/PHP price output (product pages, loops, widgets, shortcodes).
 */
function uplinksync_quote_only_price_html( $html ) {
	return uplinksync_quote_only_active() ? '' : $html;
}
add_filter( 'woocommerce_get_price_html', 'uplinksync_quote_only_price_html', 99 );
add_filter( 'woocommerce_variable_price_html', 'uplinksync_quote_only_price_html', 99 );

/**
 * 2. Block-based storefront. The homepage grid renders through
 *    woocommerce/product-collection; its price block does not route through
 *    woocommerce_get_price_html, so strip it here. Where a product-button block
 *    exists, convert it into a "Request a quote" link. Where the card template
 *    has no product-button block (the case on this homepage, whose
 *    product-template holds only product-image + post-title), inject the CTA at
 *    the product-template level instead — see ***-166.
 *
 * render_block is a normal WordPress filter — no output buffering — so a failure
 * here degrades to unmodified markup rather than an empty document.
 */
function uplinksync_quote_only_render_block( $content, $block ) {
	if ( ! uplinksync_quote_only_active() ) {
		return $content;
	}
	$name = isset( $block['blockName'] ) ? $block['blockName'] : '';

	$price_blocks = array(
		'woocommerce/product-price',
		'woocommerce/product-sale-badge',
	);
	if ( in_array( $name, $price_blocks, true ) ) {
		return '';
	}

	$cart_blocks = array(
		'woocommerce/product-button',
		'woocommerce/add-to-cart-form',
		'woocommerce/single-product-add-to-cart',
	);
	if ( in_array( $name, $cart_blocks, true ) ) {
		return uplinksync_quote_only_cta_markup();
	}

	/*
	 * Attach the CTA at the product-template level for grids whose card has no
	 * product-button inner block for the cart branch above to replace. Inject
	 * one "Request a quote" button into each rendered product <li>. Covers the
	 * new Product Collection template and the legacy product-grid item block.
	 * Idempotent: skip if a CTA is already present.
	 */
	$template_blocks = array(
		'woocommerce/product-template',
		'woocommerce/product-grid',
	);
	if ( in_array( $name, $template_blocks, true ) && false === strpos( $content, 'uplinksync-quote-cta' ) ) {
		$cta = uplinksync_quote_only_cta_markup();
		// A product card <li> has no nested <li>, so a non-greedy match to the
		// first </li> reliably targets each card's own closing tag.
		$injected = preg_replace(
			'#(<li[^>]*\bwc-block-product\b[^>]*>.*?)(</li>)#s',
			'$1' . $cta . '$2',
			$content
		);
		if ( null !== $injected ) {
			return $injected;
		}
	}

	return $content;
}
add_filter( 'render_block', 'uplinksync_quote_only_render_block', 10, 2 );

/**
 * 3. Classic add-to-cart hooks (product pages / loops that are not block-based).
 */
function uplinksync_quote_only_strip_cart_hooks() {
	if ( ! uplinksync_quote_only_active() ) {
		return;
	}
	remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
	remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
}
add_action( 'wp', 'uplinksync_quote_only_strip_cart_hooks', 99 );

/**
 * 4. Belt-and-braces CSS. Any price markup produced by a path not covered above
 *    (a plugin, a cached fragment, a theme template) is hidden rather than left
 *    visible. Cheap, and it fails safe.
 */
function uplinksync_quote_only_css() {
	if ( ! uplinksync_quote_only_active() ) {
		return;
	}
	echo "<style id=\"uplinksync-quote-only\">"
		. ".woocommerce-Price-amount,.wc-block-components-product-price,"
		. ".wp-block-woocommerce-product-price,.price,.woocommerce-price-suffix,"
		. ".wc-block-grid__product-price,.wc-block-components-product-sale-badge,"
		. ".add_to_cart_button,.single_add_to_cart_button,.wp-block-woocommerce-product-button"
		. "{display:none !important;}"
		. "</style>\n";
}
add_action( 'wp_head', 'uplinksync_quote_only_css', 99 );
