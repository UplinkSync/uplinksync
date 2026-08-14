<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 83
 * name  : UPLAA-277 Hide RESERVED/Real-photo placeholder sections on service pages
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
	echo '<style id="uls-hide-photo-slots-277">'
	   . '.page-id-379 .uls-section:has(.uls-photo-slot),'
	   . '.page-id-380 .uls-section:has(.uls-photo-slot),'
	   . '.page-id-382 .uls-section:has(.uls-photo-slot){display:none!important}'
	   . '</style>';
}, 21 );
