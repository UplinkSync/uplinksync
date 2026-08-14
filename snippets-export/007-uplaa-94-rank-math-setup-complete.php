<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 7
 * name  : UPLAA-94 rank math setup complete
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
  register_rest_route('uplaa/v1', '/rmsetup', array(
    'methods' => 'POST',
    'permission_callback' => function () { return current_user_can('manage_options') || current_user_can('edit_posts'); },
    'callback' => function () {
      $out = array();
      // Mark setup/wizard complete so frontend head emits
      update_option('rank_math_wizard_completed', true);
      update_option('rank_math_registration_skip', true);
      // Ensure general options exist with safe defaults
      $general = get_option('rank_math_options_general');
      if (!is_array($general)) $general = array();
      update_option('rank_math_options_general', $general);
      // Ensure titles options exist; enable meta output
      $titles = get_option('rank_math_options_titles');
      if (!is_array($titles)) $titles = array();
      $titles['noindex_empty_taxonomies'] = isset($titles['noindex_empty_taxonomies']) ? $titles['noindex_empty_taxonomies'] : 'on';
      $titles['title_separator'] = isset($titles['title_separator']) ? $titles['title_separator'] : '-';
      // Ensure pages post type has titles/meta support on
      $titles['pt_page_add_meta_box'] = 'on';
      $titles['pt_page_title'] = isset($titles['pt_page_title']) ? $titles['pt_page_title'] : '%title% %sep% %sitename%';
      update_option('rank_math_options_titles', $titles);
      // Flush rewrite / module state not needed; return state
      $out['wizard_completed'] = get_option('rank_math_wizard_completed');
      $out['titles_isset'] = (bool) get_option('rank_math_options_titles');
      $out['general_isset'] = (bool) get_option('rank_math_options_general');
      return $out;
    },
  ));
});
