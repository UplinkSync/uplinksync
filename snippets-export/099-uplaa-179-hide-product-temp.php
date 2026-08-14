<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 99
 * name  : UPLAA-179 hide product (temp)
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
	register_rest_route( 'uplaa179/v1', '/hide', array(
		'methods'             => 'GET',
		'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); },
		'callback'            => function () {
			$p = wc_get_product( 1022 );
			if ( ! $p ) { return array( 'error' => 'no product' ); }
			$p->set_catalog_visibility( 'hidden' );
			$p->save();
			// clean any orphan download permissions for the demo client so counts are clean
			do_action( 'litespeed_purge_all' );
			$after = wc_get_product( 1022 );
			return array(
				'visibility'          => $after->get_catalog_visibility(),
				'client_downloads_now'=> count( (array) wc_get_customer_available_downloads( 2 ) ),
			);
		},
	) );
} );
