<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 79
 * name  : UPLAA-281 Vertical rhythm: tighten CTA bands + contact rail balance
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
	echo '<style id="uls-vrhythm-281">'
	   . '.uls-cta-band{padding-top:var(--wp--preset--spacing--60)!important;padding-bottom:var(--wp--preset--spacing--60)!important}'
	   . '@media(min-width:782px){.page-id-385 .wp-block-columns.alignwide{align-items:center}}'
	   . '</style>';
}, 21 );
