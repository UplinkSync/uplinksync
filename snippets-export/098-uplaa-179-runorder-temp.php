<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 98
 * name  : UPLAA-179 runorder (temp)
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
	register_rest_route( 'uplaa179/v1', '/runorder', array(
		'methods'             => 'GET',
		'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); },
		'callback'            => function () {
			$V   = array();
			$pid = 1022;
			$p   = wc_get_product( $pid );
			if ( ! $p ) { return array( 'error' => 'no product' ); }
			$dls   = $p->get_downloads();
			$first = $dls ? reset( $dls ) : null;
			$V['product'] = array(
				'price'         => $p->get_price(),
				'downloadable'  => $p->is_downloadable(),
				'virtual'       => $p->is_virtual(),
				'visibility'    => $p->get_catalog_visibility(),
				'cats'          => wp_get_post_terms( $pid, 'product_cat', array( 'fields' => 'slugs' ) ),
				'download_ct'   => count( $dls ),
				'download_file' => $first ? $first->get_file() : null,
			);
			try {
				$order = wc_create_order( array( 'customer_id' => 2 ) );
				$order->add_product( $p, 1 );
				$order->calculate_totals();
				$total = $order->get_total();
				$order->payment_complete();
				wc_downloadable_product_permissions( $order->get_id(), true );
				$order->save();
				$avail  = wc_get_customer_available_downloads( 2 );
				$firstd = $avail ? reset( $avail ) : null;
				$file   = $firstd ? $firstd['file']['file'] : null;
				$V['order'] = array(
					'order_id'             => $order->get_id(),
					'total'                => $total,
					'status'               => $order->get_status(),
					'downloads_for_client' => is_array( $avail ) ? count( $avail ) : 0,
					'download_url'         => $firstd ? $firstd['download_url'] : null,
					'download_file'        => $file,
					'is_clean_master'      => $file ? ( strpos( (string) $file, '-master-' ) !== false ) : false,
				);
				$order->delete( true );
				$V['order']['test_order_deleted'] = true;
			} catch ( \Throwable $e ) {
				$V['order_error'] = $e->getMessage() . ' @ ' . $e->getLine();
			}
			return $V;
		},
	) );
} );
