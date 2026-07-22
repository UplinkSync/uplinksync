<?php
/**
 * Plugin Name: UplinkSync — Cohesive Imagery System (***-133)
 * Description: Replaces the confusing/generic stock imagery baked into the WordPress DB with a cohesive, credible visual system, per the positioning & imagery-direction brief (***-130, §4). The stock image URLs live in the database (block markup) and parent-theme output, NOT in this repo, so a static file edit cannot reach them — this mu-plugin rewrites the rendered document on the way out, keeping the fix captured in-repo (deploys with wp-content) and independent of the active theme.
 *
 * The system has two moves:
 *   1. Anchor the site on the ONE real, on-brand asset: the unified "ground + air"
 *      key art (drone in the air + server rack/laptop on the ground + operations,
 *      one navy composited scene). It is the visual expression of "one company,
 *      on the ground and in the air." It replaces the generic hero and every
 *      OFF-brief stock image (a coffee shop, a "BRANDING" 3D-text photo, a
 *      generic office, a competitor's help-desk screenshot, a warm-toned laptop
 *      shot, and generic drone product stock) that made the site read as a
 *      template rather than a real firm.
 *   2. Art-direct the remaining, on-topic IT/server photography to ONE consistent
 *      navy grade (a CSS class the child theme cools into the brand palette) so
 *      the whole page reads as a single tonal set instead of mixed stock.
 *
 * Real drone/field photography is NOT present on the NAS (brief §4 gap B1), so the
 * Air side is carried by the brand key art + iconography until the owner supplies
 * real work — this plugin deliberately does NOT invent drone imagery.
 *
 * Idempotent: the output-buffer filter can run more than once; every rewrite is
 * a no-op on already-rewritten markup (replaced images no longer carry an
 * unsplash photo id, and graded images are skipped once they carry the class).
 *
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end GET HTML only. Never wp-admin, REST, AJAX, feeds, or the editor.
 * (Same scope guard shape as the contact/social-fixes mu-plugin.)
 */
function uplinksync_imagery_should_filter() {
	if ( is_admin() || wp_doing_ajax() || wp_is_json_request() ) {
		return false;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}
	if ( is_feed() || is_embed() ) {
		return false;
	}
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
		return false;
	}
	return true;
}

/**
 * URL of a child-theme asset, cache-busted by file mtime (matches the child
 * theme's own filemtime versioning so Cloudflare's edge key rotates on change).
 * Returns '' if the asset is missing so callers can bail rather than emit a 404.
 */
function uplinksync_imagery_asset_url( $relative ) {
	$path = get_stylesheet_directory() . '/' . ltrim( $relative, '/' );
	if ( ! file_exists( $path ) ) {
		return '';
	}
	$ver = @filemtime( $path );
	$url = get_stylesheet_directory_uri() . '/' . ltrim( $relative, '/' );
	return $ver ? $url . '?v=' . $ver : $url;
}

/**
 * Unsplash photo ids that are OFF-brief and must be replaced with the unified
 * key art, each mapped to a meaningful, honest alt (the key art depicts the
 * same thing everywhere, so the alt is consistent). These read as a template
 * or actively confuse the IT/drone story:
 *   - 1702967426797  generic open-plan office
 *   - 1548882656     a coffee-shop storefront ("small company coffee")
 *   - 1649015931204  a 3D "BRANDING" text render
 *   - 1610994238985  a competitor help-desk (HelpDesk Heroes) screenshot
 *   - 1694340016914  a warm yellow-wall laptop shot (tonally off the navy brand)
 *   - 1563812964340 / 1529611934128 / 1523132797263 / 1681516582806 /
 *     1603928184083  generic drone product stock (no real work exists yet — B1)
 */
function uplinksync_imagery_replace_map() {
	$alt = 'UplinkSync technology specialists — managed IT on the ground and professional drone operations in the air.';
	$ids = array(
		'1702967426797',
		'1548882656',
		'1649015931204',
		'1610994238985',
		'1694340016914',
		'1563812964340',
		'1529611934128',
		'1523132797263',
		'1681516582806',
		'1603928184083',
	);
	$map = array();
	foreach ( $ids as $id ) {
		$map[ $id ] = $alt;
	}
	return $map;
}

/** The generic hero background photo id (replaced with the key art, decorative). */
const UPLINKSYNC_IMAGERY_HERO_ID = '1506927889921';

function uplinksync_imagery_start_buffer() {
	if ( ! uplinksync_imagery_should_filter() ) {
		return;
	}
	ob_start( 'uplinksync_imagery_rewrite' );
}
add_action( 'template_redirect', 'uplinksync_imagery_start_buffer', 1 );

/**
 * Rewrite the finished HTML document.
 *
 * @param string $html
 * @return string
 */
function uplinksync_imagery_rewrite( $html ) {
	if ( ! is_string( $html ) || '' === $html ) {
		return $html;
	}
	// Only touch full HTML documents.
	if ( false === stripos( $html, '</html>' ) ) {
		return $html;
	}

	$keyart_full = uplinksync_imagery_asset_url( 'assets/images/brand/keyart-ground-air-full.png' );
	$keyart_med  = uplinksync_imagery_asset_url( 'assets/images/brand/keyart-ground-air-med.png' );
	if ( '' === $keyart_full || '' === $keyart_med ) {
		// Asset not deployed yet — do nothing rather than emit broken images.
		return $html;
	}

	$replace_map = uplinksync_imagery_replace_map();

	// Crop cycle: the key art is a wide panorama (servers/ground on the left ->
	// operations in the centre -> drone/air on the right). When several off-brief
	// images sit next to each other (e.g. a card row), swapping every one to the
	// identical image reads as a bug. Instead we cycle a crop-modifier class so
	// adjacent tiles show different regions of the SAME scene — they read as one
	// cohesive picture sliced across the row, not clones. Reset per document so
	// the sequence is deterministic and idempotent (a re-run touches no already-
	// replaced tag, so the counter never advances on a second pass).
	$crop_cycle = array( 'uls-brand-media--ground', 'uls-brand-media--ops', 'uls-brand-media--air' );
	$crop_i     = 0;

	// 1) Hero background-image url(...) -> key art (decorative, no alt on a bg).
	//    Matches any unsplash URL carrying the hero photo id, single or double
	//    quoted / HTML-entity quoted, with any query string.
	$html = preg_replace_callback(
		'#https://images\.unsplash\.com/photo-' . preg_quote( UPLINKSYNC_IMAGERY_HERO_ID, '#' ) . '[^\'")&]*(?:&(?:amp;)?[^\'")]*)?#i',
		function () use ( $keyart_full ) {
			return $keyart_full;
		},
		$html
	);

	// 2) Per <img> rewrite: replace off-brief stock with key art (meaningful alt,
	//    srcset dropped, brand class added); grade the remaining on-topic IT
	//    stock into one navy tone via a class.
	$html = preg_replace_callback(
		'#<img\b[^>]*>#i',
		function ( $m ) use ( $replace_map, $keyart_med, $crop_cycle, &$crop_i ) {
			$tag = $m[0];

			// Which unsplash photo id (if any) does this img reference?
			if ( ! preg_match( '#images\.unsplash\.com/photo-([a-z0-9]+)#i', $tag, $idm ) ) {
				return $tag; // not an unsplash image — leave it (logo, testimonials, etc.)
			}
			$id = $idm[1];

			if ( isset( $replace_map[ $id ] ) ) {
				// OFF-brief -> unified key art.
				$alt = $replace_map[ $id ];

				// Point every src at the key art; drop srcset (it would override src).
				$tag = preg_replace( '#\ssrcset="[^"]*"#i', '', $tag );
				$tag = preg_replace( '#\ssizes="[^"]*"#i', '', $tag );
				$tag = preg_replace(
					'#\ssrc="[^"]*"#i',
					' src="' . esc_attr( $keyart_med ) . '"',
					$tag,
					1
				);

				// Replace the alt with an honest, meaningful one.
				if ( preg_match( '#\salt="#i', $tag ) ) {
					$tag = preg_replace( '#\salt="[^"]*"#i', ' alt="' . esc_attr( $alt ) . '"', $tag, 1 );
				} else {
					$tag = preg_replace( '#<img\b#i', '<img alt="' . esc_attr( $alt ) . '"', $tag, 1 );
				}

				$tag  = uplinksync_imagery_add_class( $tag, 'uls-brand-media' );
				$crop = $crop_cycle[ $crop_i % count( $crop_cycle ) ];
				$crop_i++;
				$tag = uplinksync_imagery_add_class( $tag, $crop );
				return $tag;
			}

			// ON-topic IT/server stock -> unify tone with one navy grade.
			$tag = uplinksync_imagery_add_class( $tag, 'uls-graded-media' );
			return $tag;
		},
		$html
	);

	return $html;
}

/**
 * Add a class to an <img> tag once (idempotent). If the class is already present
 * the tag is returned unchanged.
 */
function uplinksync_imagery_add_class( $tag, $class ) {
	if ( preg_match( '#\bclass="[^"]*\b' . preg_quote( $class, '#' ) . '\b#i', $tag ) ) {
		return $tag; // already tagged
	}
	if ( preg_match( '#\sclass="#i', $tag ) ) {
		return preg_replace( '#\sclass="#i', ' class="' . $class . ' ', $tag, 1 );
	}
	return preg_replace( '#<img\b#i', '<img class="' . $class . '"', $tag, 1 );
}
