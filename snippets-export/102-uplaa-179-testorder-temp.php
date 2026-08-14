<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 102
 * name  : UPLAA-179 testorder (temp)
 * scope : global
 * state : inactive
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

add_action( 'rest_api_init', function () {
	register_rest_route( 'uplaa179/v1', '/testorder', array(
		'methods'             => 'GET',
		'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); },
		'callback'            => function ( $req ) {
			$pid = (int) $req->get_param( 'product' );
			$uid = (int) $req->get_param( 'user' );
			$p   = wc_get_product( $pid );
			if ( ! $p || ! $uid ) { return array( 'error' => 'bad params' ); }
			try {
				$order = wc_create_order( array( 'customer_id' => $uid ) );
				$order->add_product( $p, 1 );
				$order->calculate_totals();
				$total = $order->get_total();
				$order->payment_complete();
				wc_downloadable_product_permissions( $order->get_id(), true );
				$order->save();
				$avail  = wc_get_customer_available_downloads( $uid );
				$mine   = array_values( array_filter( $avail, function ( $d ) use ( $pid ) { return (int) $d['product_id'] === $pid; } ) );
				$firstd = $mine ? $mine[0] : null;
				$file   = $firstd ? $firstd['file']['file'] : null;
				$res = array(
					'order_id' => $order->get_id(), 'total' => $total, 'status' => $order->get_status(),
					'download_file' => $file,
					'is_clean_master' => $file ? ( strpos( (string) $file, '-master-' ) !== false ) : false,
					'download_url' => $firstd ? $firstd['download_url'] : null,
				);
				$order->delete( true );
				$res['test_order_deleted'] = true;
				return $res;
			} catch ( \Throwable $e ) { return array( 'error' => $e->getMessage() ); }
		},
	) );
} );
