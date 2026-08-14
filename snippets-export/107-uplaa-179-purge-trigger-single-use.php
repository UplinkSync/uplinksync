<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 107
 * name  : UPLAA-179 purge trigger (single-use)
 * scope : global
 * state : inactive
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

add_action('init', function(){
  if (!isset($_GET['uplaa179purge'])) return;
  if (!current_user_can('manage_options')) return;
  do_action('litespeed_purge_all');
  // also try cloudflare via LiteSpeed CDN mapping if present
  do_action('litespeed_purge_url', home_url('/wp-content/uploads/2026/07/'));
  wp_send_json(array('purged'=>true));
});
