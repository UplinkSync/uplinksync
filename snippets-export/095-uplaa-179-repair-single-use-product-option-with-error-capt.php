<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 95
 * name  : UPLAA-179 REPAIR (single-use) — product+option with error capture
 * scope : single-use
 * state : inactive
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

$log = array( 'ts' => date( 'c' ), 'steps' => array(), 'error' => null );
try {
	$cat_slug = 'proof-demo-shoot';
	$term = get_term_by( 'slug', $cat_slug, 'product_cat' );
	if ( ! $term ) {
		$res = wp_insert_term( 'Proofing — Demo Shoot', 'product_cat', array( 'slug' => $cat_slug ) );
		$cat_id = is_wp_error( $res ) ? 0 : (int) $res['term_id'];
	} else {
		$cat_id = (int) $term->term_id;
	}
	$log['steps'][] = 'cat_id=' . $cat_id;

	$client_id   = ( $u = get_user_by( 'login', 'proof-demo-client' ) ) ? (int) $u->ID : 0;
	$outsider_id = ( $u = get_user_by( 'login', 'proof-demo-outsider' ) ) ? (int) $u->ID : 0;
	$log['steps'][] = 'client=' . $client_id . ' outsider=' . $outsider_id;

	$prod_id = 0;
	$existing = get_posts( array( 'post_type' => 'product', 'name' => 'demo-proof-idaho-falls-01', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
	if ( $existing ) {
		$prod_id = (int) $existing[0];
		$log['steps'][] = 'product exists ' . $prod_id;
	} elseif ( class_exists( 'WC_Product_Simple' ) ) {
		$p = new WC_Product_Simple();
		$p->set_name( 'Demo Proof — Idaho Falls No. 01' );
		$p->set_slug( 'demo-proof-idaho-falls-01' );
		$p->set_status( 'publish' );
		$p->set_catalog_visibility( 'hidden' );
		$p->set_regular_price( '0' );
		$p->set_price( '0' );
		$p->set_virtual( true );
		$p->set_downloadable( true );
		$p->set_image_id( 436 );
		$dl = new WC_Product_Download();
		$dl->set_name( 'Idaho Falls No. 01 — full-resolution (clean master)' );
		$dl->set_id( 'uplaa179-demo-master' );
		$dl->set_file( 'https://uplinksync.com/wp-content/uploads/2026/07/001-city-idaho-falls-master-3c38229dc51b7f47-scaled.jpg' );
		$p->set_downloads( array( 'uplaa179-demo-master' => $dl ) );
		$p->set_download_limit( -1 );
		$p->set_download_expiry( -1 );
		$p->set_category_ids( array( $cat_id ) );
		$prod_id = (int) $p->save();
		$log['steps'][] = 'created product ' . $prod_id;
	} else {
		$log['steps'][] = 'WC_Product_Simple missing';
	}

	$albums = get_option( 'uplaa179_albums' );
	if ( ! is_array( $albums ) ) { $albums = array(); }
	$albums['demo-realestate'] = array(
		'label'       => 'Proofing Gallery — Demo Shoot',
		'client'      => 'Sample Client (DEMO)',
		'clients'     => array_values( array_filter( array( 1, $client_id ) ) ),
		'product_cat' => $cat_slug,
		'items'       => array(
			array( 'img' => 'https://uplinksync.com/wp-content/uploads/2026/07/001-city-idaho-falls-1024x576.jpg', 'title' => 'Frame 01', 'buy' => '/shop/' ),
		),
	);
	update_option( 'uplaa179_albums', $albums, false );
	$log['steps'][] = 'wrote option';

	$log['result'] = array(
		'cat_id' => $cat_id, 'product_id' => $prod_id,
		'product_link' => $prod_id ? get_permalink( $prod_id ) : '',
		'cat_link' => get_term_link( $cat_id, 'product_cat' ),
		'client_id' => $client_id, 'outsider_id' => $outsider_id,
	);
} catch ( \Throwable $e ) {
	$log['error'] = $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
}
$up = wp_upload_dir();
file_put_contents( trailingslashit( $up['basedir'] ) . 'uplaa179_repair.txt', wp_json_encode( $log, JSON_PRETTY_PRINT ) );
do_action( 'litespeed_purge_all' );
