<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 97
 * name  : UPLAA-179 VERIFY harness (single-use)
 * scope : single-use
 * state : inactive
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

$V = array( 'ts' => date( 'c' ) );

// --- product props ---
$pid = 1022;
$p = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;
if ( $p ) {
	$dls = $p->get_downloads();
	$first = $dls ? reset( $dls ) : null;
	$V['product'] = array(
		'id'          => $pid,
		'price'       => $p->get_price(),
		'downloadable'=> $p->is_downloadable(),
		'virtual'     => $p->is_virtual(),
		'visibility'  => $p->get_catalog_visibility(),
		'cats'        => wp_get_post_terms( $pid, 'product_cat', array( 'fields' => 'slugs' ) ),
		'download_ct' => count( $dls ),
		'download_file' => $first ? $first->get_file() : null,
	);
}

// --- gate predicate simulation per user (mirrors template_redirect hook) ---
$album = uplaa179_album_for_cat( 'proof-demo-shoot' ); // -> demo-realestate
$V['album_for_cat'] = $album;
$V['gate'] = array();
foreach ( array( 1, 2, 3 ) as $uid ) {
	// logged-in users: allowed iff can_view; else redirected home
	$V['gate'][ 'user_' . $uid ] = array(
		'can_view'  => $album ? uplaa179_can_view( $album, $uid ) : null,
		'decision'  => ( $album && uplaa179_can_view( $album, $uid ) ) ? 'ALLOW' : 'REDIRECT_HOME',
	);
}

// --- real $0 order for client (uid 2) -> download grant -> delivered file ---
if ( $p && function_exists( 'wc_create_order' ) ) {
	try {
		$order = wc_create_order( array( 'customer_id' => 2 ) );
		$order->add_product( $p, 1 );
		$order->calculate_totals();
		$total = $order->get_total();
		$order->payment_complete();                       // virtual+downloadable -> completed, grants downloads
		wc_downloadable_product_permissions( $order->get_id(), true );
		$order->save();
		$avail = wc_get_customer_available_downloads( 2 );
		$firstd = $avail ? reset( $avail ) : null;
		$V['order'] = array(
			'order_id'      => $order->get_id(),
			'total'         => $total,
			'status'        => $order->get_status(),
			'downloads_for_client' => is_array( $avail ) ? count( $avail ) : 0,
			'download_url'  => $firstd ? $firstd['download_url'] : null,
			'download_file' => $firstd ? $firstd['file']['file'] : null,
			'is_clean_master' => $firstd ? ( strpos( (string) $firstd['file']['file'], '-master-' ) !== false ) : false,
		);
		// cleanup: remove the test order + its permissions
		$order->delete( true );
		$V['order']['cleaned_up'] = true;
	} catch ( \Throwable $e ) {
		$V['order_error'] = $e->getMessage() . ' @ ' . $e->getLine();
	}
}

update_option( 'uplaa179_verify', $V, false );
