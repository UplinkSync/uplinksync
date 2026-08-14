<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 101
 * name  : UPLAA-179 build_album (temp)
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
	register_rest_route( 'uplaa179/v1', '/build_album', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); },
		'callback'            => function ( $req ) {
			$p = $req->get_json_params();
			if ( empty( $p['frames'] ) || empty( $p['album_slug'] ) ) { return new WP_Error( 'bad', 'missing', array( 'status' => 400 ) ); }
			$out = array( 'album_slug' => $p['album_slug'] );

			// category
			$cat_slug = sanitize_title( $p['cat_slug'] );
			$term = get_term_by( 'slug', $cat_slug, 'product_cat' );
			if ( ! $term ) {
				$r = wp_insert_term( $p['cat_name'], 'product_cat', array( 'slug' => $cat_slug ) );
				$cat_id = is_wp_error( $r ) ? 0 : (int) $r['term_id'];
			} else { $cat_id = (int) $term->term_id; }
			$out['cat_id'] = $cat_id;

			// client user
			$u = get_user_by( 'login', $p['client_username'] );
			if ( $u ) {
				$uid = (int) $u->ID; $out['user_created'] = false;
			} else {
				$pw  = wp_generate_password( 20, true, false );
				$uid = wp_insert_user( array(
					'user_login'   => $p['client_username'],
					'user_email'   => $p['client_email'],
					'user_pass'    => $pw,
					'display_name' => $p['client_label'],
					'first_name'   => $p['client_label'],
					'role'         => 'customer',
				) );
				if ( is_wp_error( $uid ) ) { return new WP_Error( 'user', $uid->get_error_message(), array( 'status' => 500 ) ); }
				$uid = (int) $uid;
				$out['user_created'] = true;
				$out['generated_password'] = $pw; // captured over TLS -> Vault; never printed
			}
			$out['user_id'] = $uid;

			// products
			$ids = array();
			foreach ( $p['frames'] as $fr ) {
				$slug = sanitize_title( $fr['slug'] );
				$ex   = get_posts( array( 'post_type' => 'product', 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
				if ( $ex ) { $ids[] = (int) $ex[0]; continue; }
				$prod = new WC_Product_Simple();
				$prod->set_name( $fr['title'] );
				$prod->set_slug( $slug );
				$prod->set_status( 'publish' );
				$prod->set_catalog_visibility( 'hidden' );
				$prod->set_regular_price( '0' );
				$prod->set_price( '0' );
				$prod->set_virtual( true );
				$prod->set_downloadable( true );
				if ( ! empty( $fr['preview_id'] ) ) { $prod->set_image_id( (int) $fr['preview_id'] ); }
				$dl = new WC_Product_Download();
				$dl->set_name( $fr['title'] . ' — full-resolution (clean master)' );
				$dl->set_id( 'master-' . $slug );
				$dl->set_file( $fr['master_url'] );
				$prod->set_downloads( array( 'master-' . $slug => $dl ) );
				$prod->set_download_limit( -1 );
				$prod->set_download_expiry( -1 );
				$prod->set_category_ids( array( $cat_id ) );
				$ids[] = (int) $prod->save();
			}
			$out['product_ids'] = $ids;
			$out['product_count'] = count( $ids );

			// album entry
			$albums = get_option( 'uplaa179_albums' );
			if ( ! is_array( $albums ) ) { $albums = array(); }
			$albums[ sanitize_key( $p['album_slug'] ) ] = array(
				'label'       => $p['label'],
				'client'      => $p['client_label'],
				'clients'     => array( $uid ),
				'product_cat' => $cat_slug,
			);
			update_option( 'uplaa179_albums', $albums, false );

			do_action( 'litespeed_purge_all' );
			return $out;
		},
	) );
} );
