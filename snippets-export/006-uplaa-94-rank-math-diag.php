<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 6
 * name  : UPLAA-94 rank math diag
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
  register_rest_route('uplaa/v1', '/rmdiag', array(
    'methods' => 'GET',
    'permission_callback' => function () { return current_user_can('edit_posts'); },
    'callback' => function () {
      return array(
        'wizard_completed' => get_option('rank_math_wizard_completed'),
        'registration_skip' => get_option('rank_math_registration_skip'),
        'titles_isset' => (bool) get_option('rank_math_options_titles'),
        'general_isset' => (bool) get_option('rank_math_options_general'),
        'titles_home' => is_array(get_option('rank_math_options_titles')) ? array_intersect_key(get_option('rank_math_options_titles'), array_flip(array('homepage_title','homepage_description','disable_author_archives'))) : null,
      );
    },
  ));
});
