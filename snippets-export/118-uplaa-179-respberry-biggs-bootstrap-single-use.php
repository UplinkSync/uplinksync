<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 118
 * name  : UPLAA-179 Respberry/Biggs bootstrap (single-use)
 * scope : global
 * state : inactive
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

// UPLAA-179 Respberry/Biggs bootstrap (single-use) — client user + album + private page.
add_action('rest_api_init', function () {
    register_rest_route('uplaa179/v1', '/rgbuild', array(
        'methods' => 'POST',
        'permission_callback' => function () { return current_user_can('manage_options'); },
        'callback' => function () {
            $log = array();
            $u = get_user_by('login', 'biggs-client');
            if ( ! $u ) {
                $uid = wp_insert_user(array(
                    'user_login'   => 'biggs-client',
                    'user_pass'    => wp_generate_password(24, true, true),
                    'user_email'   => 'biggs-proof@uplinksync.com',
                    'display_name' => 'Biggs Group',
                    'role'         => 'customer',
                ));
                $log['user'] = is_wp_error($uid) ? $uid->get_error_message() : ('created '.$uid);
            } else {
                $uid = $u->ID;
                $log['user'] = 'exists '.$uid;
            }
            $uid = (int) $uid;

            $albums = get_option('uplaa179_albums');
            if ( ! is_array($albums) ) $albums = array();
            $albums['respberry'] = array(
                'label'       => 'Proofing Gallery — Respberry Gardens (Rexburg)',
                'client'      => 'Biggs Group',
                'clients'     => array( $uid ),
                'product_cat' => '',
                'cta_url'     => '/contact/',
                'cta_label'   => 'Request this frame',
                'intro'       => 'Review your Respberry Gardens shoot below. Previews are watermarked. Request the frames you want and we\'ll send you the clean, full-resolution files.',
                'items'       => array(
        array( 'img' => 'https://uplinksync.com/wp-content/uploads/2026/08/rg-proof-c4c2febec75c928b.jpg', 'title' => 'Respberry Gardens — Frame 01' ),
        array( 'img' => 'https://uplinksync.com/wp-content/uploads/2026/08/rg-proof-a9b50003474e5c7c.jpg', 'title' => 'Respberry Gardens — Frame 02' ),
        array( 'img' => 'https://uplinksync.com/wp-content/uploads/2026/08/rg-proof-29edaca884ad3147.jpg', 'title' => 'Respberry Gardens — Frame 03' ),
        array( 'img' => 'https://uplinksync.com/wp-content/uploads/2026/08/rg-proof-bc6f116f0ab9ccf7.jpg', 'title' => 'Respberry Gardens — Frame 04' ),
        array( 'img' => 'https://uplinksync.com/wp-content/uploads/2026/08/rg-proof-d3600d4f7e53b5d6.jpg', 'title' => 'Respberry Gardens — Frame 05' ),
        array( 'img' => 'https://uplinksync.com/wp-content/uploads/2026/08/rg-proof-8c4c6014e444e23e.jpg', 'title' => 'Respberry Gardens — Frame 06' ),
        array( 'img' => 'https://uplinksync.com/wp-content/uploads/2026/08/rg-proof-6444f4ced796b145.jpg', 'title' => 'Respberry Gardens — Frame 07' ),
        array( 'img' => 'https://uplinksync.com/wp-content/uploads/2026/08/rg-proof-7bcfa4d59f7a37be.jpg', 'title' => 'Respberry Gardens — Frame 08' ),
        array( 'img' => 'https://uplinksync.com/wp-content/uploads/2026/08/rg-proof-6be029ce368a390e.jpg', 'title' => 'Respberry Gardens — Frame 09' ),
        array( 'img' => 'https://uplinksync.com/wp-content/uploads/2026/08/rg-proof-cf6e3644a2af59e9.jpg', 'title' => 'Respberry Gardens — Frame 10' ),
        array( 'img' => 'https://uplinksync.com/wp-content/uploads/2026/08/rg-proof-bbf5143016455e5c.jpg', 'title' => 'Respberry Gardens — Frame 11' ),
        array( 'img' => 'https://uplinksync.com/wp-content/uploads/2026/08/rg-proof-85c506003283eef5.jpg', 'title' => 'Respberry Gardens — Frame 12' ),
        array( 'img' => 'https://uplinksync.com/wp-content/uploads/2026/08/rg-proof-5fc58172f529aec0.jpg', 'title' => 'Respberry Gardens — Frame 13' ),
        array( 'img' => 'https://uplinksync.com/wp-content/uploads/2026/08/rg-proof-4ffb2df7166bc397.jpg', 'title' => 'Respberry Gardens — Frame 14' ),
        array( 'img' => 'https://uplinksync.com/wp-content/uploads/2026/08/rg-proof-02aeced2874c6b77.jpg', 'title' => 'Respberry Gardens — Frame 15' ),
        array( 'img' => 'https://uplinksync.com/wp-content/uploads/2026/08/rg-proof-d3480113b115091d.jpg', 'title' => 'Respberry Gardens — Frame 16' ),
                ),
            );
            update_option('uplaa179_albums', $albums, false);
            $log['album'] = 'respberry set, items='.count($albums['respberry']['items']);

            $existing = get_page_by_path('respberry-proofing');
            if ( ! $existing ) {
                $pid = wp_insert_post(array(
                    'post_title'   => 'Respberry Gardens — Proofing Gallery',
                    'post_name'    => 'respberry-proofing',
                    'post_content' => '[uplaa_client_album slug="respberry"]',
                    'post_status'  => 'private',
                    'post_type'    => 'page',
                ));
                $log['page'] = is_wp_error($pid) ? $pid->get_error_message() : ('created '.$pid);
            } else {
                $log['page'] = 'exists '.$existing->ID;
            }
            return $log;
        },
    ));
});
