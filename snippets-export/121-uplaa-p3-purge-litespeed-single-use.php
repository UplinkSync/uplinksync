<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 121
 * name  : UPLAA-P3 purge LiteSpeed (single-use)
 * scope : single-use
 * state : inactive
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

do_action( 'litespeed_purge_all' );
if ( function_exists( 'wp_cache_flush' ) ) { wp_cache_flush(); }
update_option( 'uls_p3_purge_at', gmdate( 'c' ), false );
