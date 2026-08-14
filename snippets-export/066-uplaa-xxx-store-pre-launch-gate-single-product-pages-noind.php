<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 66
 * name  : UPLAA-XXX Store: pre-launch gate single product pages (noindex + anon 301, logged-in bypass)
 * scope : front-end
 * state : inactive
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

/**
 * UPLAA — Pre-launch store gating for single product pages (owner-approved 2026-07-28).
 * Products must not be publicly accessible or indexable before launch, but the
 * logged-in owner preview (page 923) must keep working. Reversible: DEACTIVATE
 * this snippet to launch (product pages become public again).
 */
add_action( 'template_redirect', function () {
    if ( is_singular( 'product' ) && ! is_user_logged_in() ) {
        wp_safe_redirect( home_url( '/drone-services/' ), 301 );
        exit;
    }
}, 1 );

add_filter( 'wp_robots', function ( $robots ) {
    if ( is_singular( 'product' ) ) {
        unset( $robots['index'], $robots['follow'], $robots['max-image-preview'], $robots['max-snippet'], $robots['max-video-preview'] );
        $robots['noindex']  = true;
        $robots['nofollow'] = true;
    }
    return $robots;
}, 999 );

add_filter( 'rank_math/frontend/robots', function ( $robots ) {
    if ( is_singular( 'product' ) ) {
        $robots['index']  = 'noindex';
        $robots['follow'] = 'nofollow';
    }
    return $robots;
}, 999 );
