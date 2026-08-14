<?php
/**
 * UPLAA-360 Store: navy header on WooCommerce/product/collection pages (white-header fix)
 *
 * Migrated from database-resident Code Snippets row id=85 (DR-004 tranche 1).
 * scope: front-end   priority: 100
 *
 * Header rendered white (theme color-1 preset) on single-product/store/collection/WooCommerce pages because the child theme's navy header repaint (brand-blocks.css) is dropped on those cached page-types by LiteSpeed CSS optimisation. Emits the SAME repaint as INLINE wp_head CSS (immune to UCSS/CCSS st
 *
 * The wp_snippets row is DEACTIVATED, not deleted - re-activating it in wp-admin
 * is the rollback and needs no deploy. Exactly one copy must be active at a time.
 */
defined( 'ABSPATH' ) || exit;

/**
 * UPLAA-360 — force the site navy header on WooCommerce / single-product / store
 * / collection pages, where the child theme's navy header repaint
 * (brand-blocks.css: .site-header .hostinger-ai-menu.has-color-1-background-color)
 * is being dropped on those cached page-types by LiteSpeed CSS optimisation,
 * leaving the theme's near-white color-1 preset (#f4f5f8) as the header bg.
 *
 * Re-states the exact same repaint as INLINE CSS in wp_head — inline CSS is never
 * UCSS/CCSS-stripped and loads regardless of any stylesheet dequeue — so the
 * header on store pages matches the rest of the site: navy #173258 bar with a
 * white logo/nav and the inverted white Contact pill. Scoped to WooCommerce body
 * classes (.woocommerce-page / .single-product / .woocommerce /
 * .post-type-archive-product / tax-product_cat) which brochure pages do NOT
 * carry, so /services/, the homepage, etc. are unaffected.
 *
 * Reversible: deactivate this snippet to remove the fix entirely.
 */
add_action( 'wp_head', function () {
	echo <<<'CSS'
<style id="uplaa-store-header-fix">
/* Navy header bar on store pages — mirrors brand-blocks.css UPLAA-128 repaint */
body.woocommerce-page .site-header .wp-block-group.hostinger-ai-menu.has-color-1-background-color,
body.single-product .site-header .wp-block-group.hostinger-ai-menu.has-color-1-background-color,
body.woocommerce .site-header .wp-block-group.hostinger-ai-menu.has-color-1-background-color,
body.post-type-archive-product .site-header .wp-block-group.hostinger-ai-menu.has-color-1-background-color,
body[class*="tax-product_cat"] .site-header .wp-block-group.hostinger-ai-menu.has-color-1-background-color {
  background-color:#173258 !important;
  border-bottom:1px solid rgba(255,255,255,.14) !important;
  box-shadow:0 2px 10px rgba(10,10,10,.22) !important;
}
/* White logo/title/nav text on the navy bar */
body.woocommerce-page .site-header .hostinger-ai-menu,
body.woocommerce-page .site-header .hostinger-ai-menu a,
body.woocommerce-page .site-header .hostinger-ai-menu .wp-block-site-title,
body.woocommerce-page .site-header .hostinger-ai-menu .wp-block-site-title a,
body.woocommerce-page .site-header .hostinger-ai-menu .wp-block-navigation,
body.woocommerce-page .site-header .hostinger-ai-menu .wp-block-navigation a,
body.woocommerce-page .site-header .hostinger-ai-menu .wp-block-navigation__container a,
body.woocommerce-page .site-header .hostinger-ai-menu .wp-block-navigation-item__label {
  color:#FFFFFF !important;
}
body.woocommerce-page .site-header .hostinger-ai-menu .wp-block-navigation__responsive-container-open svg,
body.woocommerce-page .site-header .hostinger-ai-menu .wp-block-navigation__responsive-container-close svg {
  fill:#FFFFFF !important;
}
/* Contact CTA pill: white fill, navy label (inline desktop bar) */
body.woocommerce-page .site-header .hostinger-ai-menu .uplinksync-nav-cta a {
  background-color:#FFFFFF !important; color:#173258 !important;
}
body.woocommerce-page .site-header .hostinger-ai-menu .uplinksync-nav-cta a .wp-block-navigation-item__label {
  color:#173258 !important;
}
/* Mobile open overlay (white sheet): navy links + navy-filled pill w/ white label */
body.woocommerce-page .site-header .hostinger-ai-menu .wp-block-navigation__responsive-container.is-menu-open a,
body.woocommerce-page .site-header .hostinger-ai-menu .wp-block-navigation__responsive-container.is-menu-open .wp-block-navigation-item__label {
  color:#173258 !important;
}
body.woocommerce-page .site-header .hostinger-ai-menu .wp-block-navigation__responsive-container.is-menu-open .uplinksync-nav-cta a,
body.woocommerce-page .site-header .hostinger-ai-menu .wp-block-navigation__responsive-container.is-menu-open .uplinksync-nav-cta a .wp-block-navigation-item__label {
  background-color:#173258 !important; color:#FFFFFF !important;
}
body.woocommerce-page .site-header .hostinger-ai-menu .wp-block-navigation__responsive-container.is-menu-open .wp-block-navigation__responsive-container-close svg {
  fill:#173258 !important;
}
</style>
CSS;
}, 100 );
