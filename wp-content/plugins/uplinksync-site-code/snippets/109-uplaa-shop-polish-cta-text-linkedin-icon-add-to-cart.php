<?php
/**
 * UPLAA /shop/ polish — CTA text, LinkedIn icon, add-to-cart sizing (2026-07-31)
 *
 * Migrated from database-resident Code Snippets row id=109 (DR-004 tranche 1).
 * scope: front-end   priority: 99
 *
 * Owner-reported /shop/ fixes (2026-07-31): (1) bottom "Get in touch" CTA → brand teal #95D5DD fill + navy #173258 text (was low-contrast mid-blue w/ light text on the navy band; now matches the header Request-a-Quote pill). (2) Footer LinkedIn icon → white to match Facebook/Instagram (was rendering b
 *
 * The wp_snippets row is DEACTIVATED, not deleted - re-activating it in wp-admin
 * is the rollback and needs no deploy. Exactly one copy must be active at a time.
 */
defined( 'ABSPATH' ) || exit;

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
