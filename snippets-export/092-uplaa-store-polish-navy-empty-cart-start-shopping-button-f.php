<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 92
 * name  : UPLAA store polish: navy empty-cart "Start shopping" button (fix #4)
 * scope : front-end
 * state : ACTIVE
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

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
