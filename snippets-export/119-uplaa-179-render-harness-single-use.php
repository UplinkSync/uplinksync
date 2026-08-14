<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 119
 * name  : UPLAA-179 render harness (single-use)
 * scope : global
 * state : inactive
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

// UPLAA-179 render harness (single-use) — render album shortcode as a given user id.
add_action('rest_api_init', function () {
    register_rest_route('uplaa179/v1', '/render', array(
        'methods' => 'GET',
        'permission_callback' => function () { return current_user_can('manage_options'); },
        'callback' => function ($req) {
            $uid  = (int) $req->get_param('as');
            $slug = sanitize_key($req->get_param('slug'));
            $prev = get_current_user_id();
            wp_set_current_user($uid);
            $html = do_shortcode('[uplaa_client_album slug="'.$slug.'"]');
            wp_set_current_user($prev);
            $frames = substr_count($html, 'uplaa179-card"');
            // detect any master-like reference (no clean master should ever appear)
            $has_master = (strpos($html, 'master') !== false) ? 1 : 0;
            $has_wm = substr_count($html, 'rg-proof-');
            return array(
                'as' => $uid,
                'slug' => $slug,
                'frames' => $frames,
                'wm_preview_refs' => $has_wm,
                'master_refs' => $has_master,
                'gate_text' => (strpos($html,'another client') !== false) ? 'belongs-to-another' :
                               ((strpos($html,'sign in') !== false || strpos($html,'Sign in') !== false) ? 'signin-required' : 'rendered'),
                'len' => strlen($html),
            );
        },
    ));
});
