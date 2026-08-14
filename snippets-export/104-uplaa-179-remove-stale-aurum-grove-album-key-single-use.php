<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 104
 * name  : UPLAA-179 remove stale aurum-grove album key (single-use)
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
  $a = get_option('uplaa179_albums');
  if (is_array($a) && isset($a['aurum-grove'])) { unset($a['aurum-grove']); update_option('uplaa179_albums',$a,false); }
});
