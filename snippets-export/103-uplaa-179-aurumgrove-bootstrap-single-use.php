<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 103
 * name  : UPLAA-179 AurumGrove bootstrap (single-use)
 * scope : global
 * state : inactive
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

/**
 * UPLAA-179 AurumGrove bootstrap (SINGLE-USE) — create album entry + private client page.
 * Owner-authorized 2026-07-30 (interaction 63c99325): shoot=AurumGrove, placeholder client acct (user 5).
 * Preview-then-quote (no Stripe/products); client sees ONLY their album; admin bypass.
 * Reversible: delete key 'aurumgrove' from option uplaa179_albums; trash the created page.
 */
add_action( 'init', function () {
    $albums = get_option( 'uplaa179_albums' );
    if ( ! is_array( $albums ) ) { $albums = array(); }

    $items = array(
        array( 'title' => 'Aurum Grove — Photo 01', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-f1d2808d3bd95ef6.jpg' ),
        array( 'title' => 'Aurum Grove — Photo 02', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-3bedbb567f1ce50f.jpg' ),
        array( 'title' => 'Aurum Grove — Photo 03', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-19c2cbbfeee63ec6.jpg' ),
        array( 'title' => 'Aurum Grove — Photo 04', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-d3d9e810c5dbe17b.jpg' ),
        array( 'title' => 'Aurum Grove — Photo 05', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-cde20884e14af569.jpg' ),
        array( 'title' => 'Aurum Grove — Photo 06', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-1c6ec9e61e8437b9.jpg' ),
        array( 'title' => 'Aurum Grove — Photo 07', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-47a0f7093e55651e.jpg' ),
        array( 'title' => 'Aurum Grove — Photo 08', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-6c9735aa4f8aad8e.jpg' ),
        array( 'title' => 'Aurum Grove — Photo 09', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-a042026fa9313eb4.jpg' ),
        array( 'title' => 'Aurum Grove — Photo 10', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-3128dca12e5e1ca2.jpg' ),
        array( 'title' => 'Aurum Grove — Photo 11', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-3575fb171b63e697.jpg' ),
        array( 'title' => 'Aurum Grove — Photo 12', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-bb48089c7f78add7.jpg' ),
        array( 'title' => 'Aurum Grove — Photo 13', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-225985d6032dc167.jpg' ),
        array( 'title' => 'Aurum Grove — Photo 14', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-6bd5ffaa565a1c34.jpg' ),
        array( 'title' => 'Aurum Grove — Photo 15', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-8724a983115c2669.jpg' ),
        array( 'title' => 'Aurum Grove — Photo 16', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-1b496fa41f88f4d9.jpg' ),
        array( 'title' => 'Aurum Grove — Photo 17', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-4b46ebe2854e0610.jpg' ),
        array( 'title' => 'Aurum Grove — Photo 18', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-93146c74a9910f4a.jpg' ),
        array( 'title' => 'Aurum Grove — Photo 19', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-62937cea728718df.jpg' ),
        array( 'title' => 'Aurum Grove — Photo 20', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-d2e7167961ed8e15.jpg' ),
        array( 'title' => 'Aurum Grove — Aerial 21', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-e549608f6f90a635.jpg' ),
        array( 'title' => 'Aurum Grove — Aerial 22', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-306379d25f310d72.jpg' ),
        array( 'title' => 'Aurum Grove — Aerial 23', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-46f1bc882d9b377b.jpg' ),
        array( 'title' => 'Aurum Grove — Aerial 24', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-a144b013cc44f9cb.jpg' ),
        array( 'title' => 'Aurum Grove — Aerial 25', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-80ccdcf6cfc805ca.jpg' ),
        array( 'title' => 'Aurum Grove — Aerial 26', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-38bbe2e847f7dc09.jpg' ),
        array( 'title' => 'Aurum Grove — Aerial 27', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-908d92c90c0bd089.jpg' ),
        array( 'title' => 'Aurum Grove — Aerial 28', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-fcf886ee848fd8bc.jpg' ),
        array( 'title' => 'Aurum Grove — Aerial 29', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-a0f3e9614b93a14d.jpg' ),
        array( 'title' => 'Aurum Grove — Aerial 30', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-0508cddc559bd8cf.jpg' ),
        array( 'title' => 'Aurum Grove — Aerial 31', 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/ag-proof-14de20aa97ef2f3c.jpg' ),
    );

    $albums['aurumgrove'] = array(
        'label'     => 'Proofing Gallery — Aurum Grove Boutique',
        'client'    => 'Aurum Grove Boutique (Ammon)',
        'clients'   => array( 5 ),                 // placeholder client account (owner forwards login)
        'intro'     => 'Review your shoot below. Previews are watermarked. Request the frames you want and we\'ll send you the clean, full-resolution files.',
        'cta_url'   => home_url( '/contact/' ),
        'cta_label' => 'Request this frame',
        'items'     => $items,
    );
    update_option( 'uplaa179_albums', $albums, false );

    // Create the private client page if it does not exist.
    $existing = get_page_by_path( 'aurumgrove-proofing' );
    if ( ! $existing ) {
        wp_insert_post( array(
            'post_title'   => 'Aurum Grove — Proofing Gallery',
            'post_name'    => 'aurumgrove-proofing',
            'post_status'  => 'private',
            'post_type'    => 'page',
            'post_content' => '<!-- wp:shortcode -->[uplaa_client_album slug="aurumgrove"]<!-- /wp:shortcode -->',
        ) );
    }
} );
