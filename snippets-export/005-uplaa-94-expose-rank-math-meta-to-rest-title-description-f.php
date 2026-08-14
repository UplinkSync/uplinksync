<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 5
 * name  : UPLAA-94 Expose Rank Math meta to REST (title/description/focus)
 * scope : global
 * state : ACTIVE
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

add_action( 'init', function () {
    $keys = array( 'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword' );
    foreach ( array( 'post', 'page' ) as $ptype ) {
        foreach ( $keys as $k ) {
            register_post_meta( $ptype, $k, array(
                'type'          => 'string',
                'single'        => true,
                'show_in_rest'  => true,
                'auth_callback' => function () {
                    return current_user_can( 'edit_posts' );
                },
            ) );
        }
    }
}, 99 );
