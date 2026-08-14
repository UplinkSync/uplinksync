<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 76
 * name  : UPLAA-277 redir diag (read-only)
 * scope : global
 * state : inactive
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

add_action('rest_api_init', function () {
  register_rest_route('uplaa/v1', '/redir', array(
    'methods' => 'GET',
    'permission_callback' => function () { return current_user_can('manage_options'); },
    'callback' => function ( $req ) {
      if ($req->get_param('do') === 'purge') {
        do_action('litespeed_purge_all');
        if (function_exists('wp_cache_flush')) wp_cache_flush();
        return array('purged' => true, 'at' => date('c'));
      }
      return array('ok' => true);
    },
  ));
});
