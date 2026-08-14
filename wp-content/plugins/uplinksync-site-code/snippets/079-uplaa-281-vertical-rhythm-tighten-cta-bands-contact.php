<?php
/**
 * UPLAA-281 Vertical rhythm: tighten CTA bands + contact rail balance
 *
 * Migrated from database-resident Code Snippets row id=79 (DR-004 tranche 1).
 * scope: front-end   priority: 21
 *
 * CSS-only vertical-rhythm pass. (1) Site-wide: trims the .uls-cta-band vertical padding from spacing-80 to spacing-60 (band is now a single primary+outline pair, so 80/80 left dead space below the buttons). (2) Contact page only, wide screens only (>=782px): vertically centers the two columns so the 
 *
 * The wp_snippets row is DEACTIVATED, not deleted - re-activating it in wp-admin
 * is the rollback and needs no deploy. Exactly one copy must be active at a time.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () {
	echo '<style id="uls-vrhythm-281">'
	   . '.uls-cta-band{padding-top:var(--wp--preset--spacing--60)!important;padding-bottom:var(--wp--preset--spacing--60)!important}'
	   . '@media(min-width:782px){.page-id-385 .wp-block-columns.alignwide{align-items:center}}'
	   . '</style>';
}, 21 );
