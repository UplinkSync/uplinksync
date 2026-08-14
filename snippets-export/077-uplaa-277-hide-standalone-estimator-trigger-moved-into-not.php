<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 77
 * name  : UPLAA-277 Hide standalone estimator trigger (moved into Not-sure-yet card)
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
	echo '<style id="uls-est-trigger-tidy">'
	   . '.wp-block-button.uls-est-trigger-wrap{display:none !important}'
	   . '</style>';
}, 99 );
