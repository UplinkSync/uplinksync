<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 100
 * name  : UPLAA-361 Header UX: cart off-store + Request-a-Quote CTA + sticky shrink header
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
 * UPLAA-361 — Header UX (header chrome only). Reversible: deactivate.
 * Inline wp_head CSS + wp_footer JS so LiteSpeed UCSS/CCSS cannot strip it.
 */
add_action( 'wp_head', function () {
	echo <<<'CSS'
<style id="uplaa-header-ux">
/* (1) CART VISIBILITY — hide the header mini-cart everywhere, then reveal it
 * only under WooCommerce/store body classes. /prints/ (page-id-964) is a
 * brochure page that is also a store landing and carries no woo body class,
 * so it is whitelisted explicitly. */
.site-header .wc-block-mini-cart { display: none !important; }
body.woocommerce-page          .site-header .wc-block-mini-cart,
body.post-type-archive-product .site-header .wc-block-mini-cart,
body.single-product            .site-header .wc-block-mini-cart,
body[class*="tax-product_cat"] .site-header .wc-block-mini-cart,
body.woocommerce-cart          .site-header .wc-block-mini-cart,
body.woocommerce-checkout      .site-header .wc-block-mini-cart,
body.woocommerce-account       .site-header .wc-block-mini-cart,
body.page-id-964               .site-header .wc-block-mini-cart {
	display: flex !important;
}

/* (2) REQUEST-A-QUOTE CTA — plain nav link -> filled teal pill (navy label) so
 * it reads as the primary action. Navy-on-teal ~7.9:1 on both the navy bar and
 * the white mobile overlay sheet. Matched by href so no markup/nav-post change
 * and the quote link/modal behaviour is untouched. */
.site-header .hostinger-ai-menu .wp-block-navigation a[href*="/request-quote"] {
	display: inline-flex !important;
	align-items: center !important;
	background-color: #95D5DD !important;
	color: #173258 !important;
	border-radius: 999px !important;
	padding: 0.5rem 1.15rem !important;
	font-weight: 700 !important;
	line-height: 1.1 !important;
	box-shadow: 0 1px 2px rgba(10,10,10,.25) !important;
}
.site-header .hostinger-ai-menu .wp-block-navigation a[href*="/request-quote"] .wp-block-navigation-item__label {
	color: #173258 !important;
}
.site-header .hostinger-ai-menu .wp-block-navigation a[href*="/request-quote"]:hover {
	background-color: #B4E4EA !important;
	box-shadow: 0 3px 8px rgba(10,10,10,.28) !important;
}
.site-header .hostinger-ai-menu .wp-block-navigation a[href*="/request-quote"]:focus-visible {
	outline: 3px solid #5697F3 !important;
	outline-offset: 2px !important;
}
@media (prefers-reduced-motion: no-preference) {
	.site-header .hostinger-ai-menu .wp-block-navigation a[href*="/request-quote"] {
		transition: background-color .18s ease, transform .18s ease, box-shadow .18s ease !important;
	}
	.site-header .hostinger-ai-menu .wp-block-navigation a[href*="/request-quote"]:hover {
		transform: translateY(-1px) !important;
	}
}

/* (3) STICKY SHRINK HEADER — bar sticks to top (sticky = in normal flow, no
 * load CLS) and compacts once scrolled. JS adds .uplaa-shrunk past a small
 * threshold; only inner padding + logo height animate. Admin-bar offset kept
 * for logged-in views. */
.site-header {
	position: -webkit-sticky !important;
	position: sticky !important;
	top: 0 !important;
	z-index: 999 !important;
}
body.admin-bar .site-header { top: 32px !important; }
@media screen and (max-width: 782px) {
	body.admin-bar .site-header { top: 46px !important; }
}
.site-header.uplaa-shrunk .hostinger-ai-menu-wrapper {
	padding-top: 0.45rem !important;
	padding-bottom: 0.45rem !important;
}
.site-header.uplaa-shrunk .hostinger-ai-site-title img {
	max-height: 30px !important;
}
.site-header.uplaa-shrunk .wp-block-group.hostinger-ai-menu.has-color-1-background-color {
	box-shadow: 0 3px 14px rgba(10,10,10,.30) !important;
}
@media (prefers-reduced-motion: no-preference) {
	.site-header .hostinger-ai-menu-wrapper { transition: padding-top .25s ease, padding-bottom .25s ease !important; }
	.site-header .hostinger-ai-site-title img { transition: max-height .25s ease !important; }
	.site-header .wp-block-group.hostinger-ai-menu { transition: box-shadow .25s ease !important; }
}
</style>
CSS;
}, 101 );

add_action( 'wp_footer', function () {
	echo <<<'JS'
<script id="uplaa-header-ux-js">
(function () {
	var h = document.querySelector('header.site-header');
	if (!h) return;
	var THRESH = 32, ticking = false;
	function update() {
		if (window.pageYOffset > THRESH) { h.classList.add('uplaa-shrunk'); }
		else { h.classList.remove('uplaa-shrunk'); }
		ticking = false;
	}
	function onScroll() { if (!ticking) { ticking = true; window.requestAnimationFrame(update); } }
	window.addEventListener('scroll', onScroll, { passive: true });
	update();
})();
</script>
JS;
}, 101 );
