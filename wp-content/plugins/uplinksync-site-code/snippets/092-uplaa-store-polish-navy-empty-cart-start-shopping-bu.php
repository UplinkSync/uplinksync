<?php
/**
 * UPLAA store polish: navy empty-cart "Start shopping" button (fix #4)
 *
 * Migrated from database-resident Code Snippets row id=92 (DR-004 tranche 1).
 * scope: front-end   priority: 100
 *
 * Empty /cart/ had no visible return-to-shop CTA. A navy "Start shopping" button was added to the empty-cart block (page 281) with class uls-empty-cart-cta. This inline wp_head CSS paints it site-navy #173258 / white label (immune to LiteSpeed UCSS stripping of palette utility classes, same technique 
 *
 * The wp_snippets row is DEACTIVATED, not deleted - re-activating it in wp-admin
 * is the rollback and needs no deploy. Exactly one copy must be active at a time.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () {
	echo <<<'CSS'
<style id="uplaa-empty-cart-cta">
/* Empty-cart "Start shopping" button — force site navy. Targeted by href within
   the empty-cart block so it works whether or not the custom block className
   survives WooCommerce's JS hydration, and without touching the product-grid
   add-to-cart buttons. Immune to LiteSpeed UCSS palette-class stripping. */
.uls-empty-cart-cta .wp-block-button__link,
.wp-block-woocommerce-empty-cart-block a.wp-block-button__link[href$="/shop/"],
.wp-block-woocommerce-empty-cart-block a.wp-element-button[href$="/shop/"],
.wc-block-cart__empty-cart a.wp-element-button[href$="/shop/"] {
  background-color:#173258 !important;
  background-image:none !important;
  color:#FFFFFF !important;
  border:0 !important;
  border-radius:8px !important;
  padding:.7em 1.6em !important;
  font-weight:600 !important;
}
.uls-empty-cart-cta .wp-block-button__link:hover,
.wp-block-woocommerce-empty-cart-block a.wp-element-button[href$="/shop/"]:hover,
.wp-block-woocommerce-empty-cart-block a.wp-element-button[href$="/shop/"]:focus {
  background-color:#1F4375 !important;
  color:#FFFFFF !important;
}
.wp-block-woocommerce-empty-cart-block a.wp-element-button[href$="/shop/"]:focus-visible {
  outline:3px solid #95D5DD !important;
  outline-offset:2px !important;
}
</style>
CSS;
}, 101 );
