<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 45
 * name  : UPLAA-247 Store: clean grid thumbs / watermarked enlarge (Option A)
 * scope : front-end
 * state : ACTIVE
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

/**
 * UPLAA-247 — Store: clean grid thumbnails, watermarked enlarge.
 *
 * Owner rule (2026-07-23, checkbox ded52157 Option A): watermark medium+ only.
 * The 123 aerial products' featured image is the WATERMARKED preview attachment,
 * so WooCommerce derives a watermarked browse/grid thumbnail — the one rule
 * violation. Each product also has a CLEAN full-res "master" attachment already
 * uploaded server-side (used as the purchase download).
 *
 * This filter swaps ONLY the small grid/browse image sizes to the clean master,
 * while the single-product image, click-to-enlarge and zoom (medium+) keep the
 * watermarked preview. Display-layer only: no re-upload, no re-render, no theme
 * edit. Deactivate this snippet to fully revert.
 */
if ( ! function_exists( 'uplaa247_preview_to_master' ) ) {

	// preview_attachment_id => clean_master_attachment_id (123 aerial products)
	function uplaa247_preview_to_master() {
		static $m = null;
		if ( $m === null ) {
			$m = array( 436=>437,439=>440,442=>443,445=>446,450=>451,453=>454,456=>457,459=>460,462=>463,465=>466,468=>469,471=>472,474=>475,477=>478,480=>481,483=>484,486=>487,489=>490,492=>493,495=>496,498=>499,501=>502,504=>505,507=>508,510=>511,514=>515,517=>518,520=>521,523=>524,526=>527,529=>530,532=>533,535=>536,538=>539,541=>542,544=>545,547=>548,550=>551,553=>554,556=>557,559=>560,562=>563,565=>566,568=>569,571=>572,574=>575,577=>578,580=>581,583=>584,586=>587,589=>590,592=>593,595=>596,598=>599,601=>602,604=>605,608=>609,611=>612,614=>615,617=>618,620=>621,623=>624,626=>627,630=>631,633=>634,636=>637,639=>640,642=>643,645=>646,648=>649,651=>652,654=>655,657=>658,660=>661,663=>664,666=>667,669=>670,672=>673,675=>676,678=>679,681=>682,684=>685,687=>688,690=>691,693=>694,696=>697,699=>700,702=>703,705=>706,708=>709,711=>712,714=>715,717=>718,720=>721,723=>724,726=>727,729=>730,732=>733,735=>736,739=>740,743=>744,746=>747,749=>750,752=>753,755=>756,758=>759,761=>762,764=>765,767=>768,770=>771,773=>774,776=>777,779=>780,782=>783,785=>786,788=>789,791=>792,794=>795,797=>798,800=>801,803=>804,806=>807,809=>810 );
		}
		return $m;
	}

	// Sizes that are "small" browse/grid thumbs — serve CLEAN here.
	// Everything else (woocommerce_single, large, full, zoom) stays WATERMARKED.
	function uplaa247_is_clean_size( $size ) {
		$clean = array(
			'woocommerce_thumbnail',
			'woocommerce_gallery_thumbnail',
			'shop_catalog',
			'shop_thumbnail',
			'thumbnail',
		);
		if ( is_string( $size ) ) {
			return in_array( $size, $clean, true );
		}
		// array size request [w,h] — treat <= 400px longest edge as a grid thumb
		if ( is_array( $size ) && ! empty( $size ) ) {
			$long = max( (int) $size[0], (int) ( $size[1] ?? 0 ) );
			return $long > 0 && $long <= 400;
		}
		return false;
	}

	// Rewrite the resolved image src to the master attachment for clean sizes.
	add_filter( 'wp_get_attachment_image_src', function ( $image, $attachment_id, $size, $icon ) {
		$map = uplaa247_preview_to_master();
		if ( isset( $map[ $attachment_id ] ) && uplaa247_is_clean_size( $size ) ) {
			$master = $map[ $attachment_id ];
			$src = wp_get_attachment_image_src( $master, $size, $icon );
			if ( $src ) {
				return $src;
			}
		}
		return $image;
	}, 10, 4 );

	// Rewrite srcset too, so a retina browser can't pull the watermarked preview
	// at a small size. Only acts on the clean sizes.
	add_filter( 'wp_calculate_image_srcset', function ( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		$map = uplaa247_preview_to_master();
		if ( ! isset( $map[ $attachment_id ] ) ) {
			return $sources;
		}
		$long = ! empty( $size_array ) ? max( (int) $size_array[0], (int) ( $size_array[1] ?? 0 ) ) : 0;
		if ( $long > 0 && $long <= 400 ) {
			$master = $map[ $attachment_id ];
			$m_meta = wp_get_attachment_metadata( $master );
			$m_src  = wp_get_attachment_image_url( $master, 'woocommerce_thumbnail' );
			if ( $m_meta && $m_src ) {
				$m_srcset = wp_calculate_image_srcset( $size_array, $m_src, $m_meta, $master );
				if ( $m_srcset ) {
					return $m_srcset;
				}
			}
		}
		return $sources;
	}, 10, 5 );
}
