<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 43
 * name  : UPLAA-256 purge LS cache
 * scope : single-use
 * state : inactive
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

do_action("litespeed_purge_all"); do_action("litespeed_purge_url", home_url("/")); if (function_exists("wp_cache_flush")) wp_cache_flush(); file_put_contents(WP_CONTENT_DIR."/uploads/uplaa256_purge.txt", "purged ".date("c"));
