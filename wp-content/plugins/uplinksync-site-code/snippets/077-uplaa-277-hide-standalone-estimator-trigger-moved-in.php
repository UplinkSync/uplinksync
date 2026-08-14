<?php
/**
 * UPLAA-277 Hide standalone estimator trigger (moved into Not-sure-yet card)
 *
 * Migrated from database-resident Code Snippets row id=77 (DR-004 tranche 1).
 * scope: front-end   priority: 20
 *
 * The estimator "Estimate your project" trigger now lives inside the "Not sure yet?" self-select card (a #uls-estimator link that estimate-book.js auto-wires to open the modal). This hides the duplicate standalone JS-injected .uls-est-trigger-wrap so it doesn't render on its own. Reversible: deactivat
 *
 * The wp_snippets row is DEACTIVATED, not deleted - re-activating it in wp-admin
 * is the rollback and needs no deploy. Exactly one copy must be active at a time.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () {
	echo '<style id="uls-est-trigger-tidy">'
	   . '.wp-block-button.uls-est-trigger-wrap{display:none !important}'
	   . '</style>';
}, 99 );
