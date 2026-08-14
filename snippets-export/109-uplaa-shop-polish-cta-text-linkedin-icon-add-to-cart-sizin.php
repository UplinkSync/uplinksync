<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 109
 * name  : UPLAA /shop/ polish — CTA text, LinkedIn icon, add-to-cart sizing (2026-07-31)
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
	echo '<style id="uplaa-shop-polish-20260731">
/* 1) Bottom CTA "Get in touch": brand teal pill + navy text (matches header Request-a-Quote pill; high contrast on navy band) */
.uls-shop .cta .cta-actions .uls-cta-primary{background:#95D5DD!important;border-color:#95D5DD!important;color:#173258!important}
.uls-shop .cta .cta-actions .uls-cta-primary:hover,.uls-shop .cta .cta-actions .uls-cta-primary:focus{background:#ffffff!important;border-color:#ffffff!important;color:#173258!important}
/* 2) Footer LinkedIn icon: white to match logos-only siblings (was brand-blue) */
.wp-block-social-links.is-style-logos-only .wp-social-link-linkedin,
.wp-block-social-links.is-style-logos-only .wp-social-link-linkedin a,
.wp-block-social-links.is-style-logos-only .wp-social-link-linkedin .wp-block-social-link-anchor,
.wp-block-social-links.is-style-logos-only .wp-social-link-linkedin svg{color:#ffffff!important;fill:currentColor!important}
/* 3) Product "Add to cart": slimmer, less dominant on the card (>=44px tap target on mobile) */
.uls-shop .prod .addbtn{min-height:38px!important;padding:8px 12px!important;font-size:12.5px!important;margin:8px 14px 14px!important}
@media (max-width:600px){.uls-shop .prod .addbtn{min-height:44px!important}}
</style>';
}, 99 );
