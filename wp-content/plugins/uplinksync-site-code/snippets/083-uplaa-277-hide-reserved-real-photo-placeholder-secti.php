<?php
/**
 * UPLAA-277 Hide RESERVED/Real-photo placeholder sections on service pages
 *
 * Migrated from database-resident Code Snippets row id=83 (DR-004 tranche 1).
 * scope: front-end   priority: 21
 *
 * Owner Doug 2026-07-30: the blank "RESERVED · REAL PHOTO — SHOT #" placeholder blocks on the templated service pages (Managed IT 379, Automation 380, Web 382) look unfinished, so hide them until real/AI photos land. CSS-only. Each placeholder (.uls-photo-slot) is the sole child of its own dedicated .
 *
 * The wp_snippets row is DEACTIVATED, not deleted - re-activating it in wp-admin
 * is the rollback and needs no deploy. Exactly one copy must be active at a time.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () {
	echo '<style id="uls-hide-photo-slots-277">'
	   . '.page-id-379 .uls-section:has(.uls-photo-slot),'
	   . '.page-id-380 .uls-section:has(.uls-photo-slot),'
	   . '.page-id-382 .uls-section:has(.uls-photo-slot){display:none!important}'
	   . '</style>';
}, 21 );
