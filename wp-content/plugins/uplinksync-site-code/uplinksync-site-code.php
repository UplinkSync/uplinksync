<?php
/**
 * Plugin Name:  UplinkSync Site Code
 * Description:  Version-controlled home for site behaviour that previously lived only as
 *               database rows in wp_snippets. See ecosystem-docs doc 120 (DR-004).
 * Version:      1.1.0
 * Author:       UplinkSync
 * Requires PHP: 7.4
 *
 * WHY THIS PLUGIN EXISTS
 * Until 2026-08-14 the site's real behaviour - quote flow, authorization engine, IA
 * redirects - lived as ~170 KB of PHP in wp_snippets: no version control, no MR review,
 * no secret scanning, no git revert. Every safety control this project relies on operates
 * on repo files, so none of them covered any of it (doc 117, risks R23/R24/R25).
 *
 * WHY A PLUGIN AND NOT mu-plugins
 * A plugin can be DEACTIVATED in an emergency; a mu-plugin cannot. Code Snippets catches
 * fatal errors and auto-deactivates the offending snippet - real protection this migration
 * gives up. A deactivatable plugin plus the per-snippet filter below is the replacement.
 *
 * KILL SWITCHES
 *   define( 'ULS_SITE_CODE_DISABLE', true );   // everything off
 *   add_filter( 'uls_site_code_enabled', ... ) // one snippet off, by id
 *
 * ORDER
 * Files load in the same PRIORITY order Code Snippets used, then by id. Several of these
 * emit CSS whose effect depends on which lands last, so order is behaviour, not style.
 */

defined( 'ABSPATH' ) || exit;

/**
 * The migrated set. Keep 'priority' identical to the original wp_snippets row.
 */
function uls_site_code_manifest() {
	return array(
		array( 'id' => 112, 'priority' => 6, 'scope' => 'global', 'file' => '112-uls-drm-v1-encrypted-hls-hero-reel-endpoints-player.php' ), // ULS DRM v1 — encrypted-HLS hero reel (endpoints + play
		array( 'id' => 45, 'priority' => 10, 'scope' => 'front-end', 'file' => '045-uplaa-247-store-clean-grid-thumbs-watermarked-enlarg.php' ), // UPLAA-247 Store: clean grid thumbs / watermarked enlar
		array( 'id' => 108, 'priority' => 11, 'scope' => 'front-end', 'file' => '108-uplaa-drone-dynamic-browse-license-collection-tiles.php' ), // UPLAA Drone: dynamic browse-&-license collection tiles
		array( 'id' => 77, 'priority' => 20, 'scope' => 'front-end', 'file' => '077-uplaa-277-hide-standalone-estimator-trigger-moved-in.php' ), // UPLAA-277 Hide standalone estimator trigger (moved int
		array( 'id' => 79, 'priority' => 21, 'scope' => 'front-end', 'file' => '079-uplaa-281-vertical-rhythm-tighten-cta-bands-contact.php' ), // UPLAA-281 Vertical rhythm: tighten CTA bands + contact
		array( 'id' => 83, 'priority' => 21, 'scope' => 'front-end', 'file' => '083-uplaa-277-hide-reserved-real-photo-placeholder-secti.php' ), // UPLAA-277 Hide RESERVED/Real-photo placeholder section
		array( 'id' => 78, 'priority' => 99, 'scope' => 'front-end', 'file' => '078-uplaa-278-dequeue-woocommerce-assets-on-non-commerce.php' ), // UPLAA-278 Dequeue WooCommerce assets on non-commerce p
		array( 'id' => 109, 'priority' => 99, 'scope' => 'front-end', 'file' => '109-uplaa-shop-polish-cta-text-linkedin-icon-add-to-cart.php' ), // UPLAA /shop/ polish — CTA text, LinkedIn icon, add-to-
		array( 'id' => 110, 'priority' => 99, 'scope' => 'front-end', 'file' => '110-uplaa-drone-videos-no-download-guard-vertical-auto-l.php' ), // UPLAA drone videos — no-download guard + vertical auto
		array( 'id' => 85, 'priority' => 100, 'scope' => 'front-end', 'file' => '085-uplaa-360-store-navy-header-on-woocommerce-product-c.php' ), // UPLAA-360 Store: navy header on WooCommerce/product/co
		array( 'id' => 92, 'priority' => 100, 'scope' => 'front-end', 'file' => '092-uplaa-store-polish-navy-empty-cart-start-shopping-bu.php' ), // UPLAA store polish: navy empty-cart "Start shopping" b
		array( 'id' => 114, 'priority' => 105, 'scope' => 'front-end', 'file' => '114-uplaa-shop-card-redesign-kill-wpautop-deadspace-squa.php' ), // UPLAA /shop/ card redesign — kill wpautop deadspace, s
	);
}

add_action(
	'plugins_loaded',
	function () {
		if ( defined( 'ULS_SITE_CODE_DISABLE' ) && ULS_SITE_CODE_DISABLE ) {
			return;
		}

		$entries = uls_site_code_manifest();
		usort(
			$entries,
			static function ( $a, $b ) {
				if ( $a['priority'] === $b['priority'] ) {
					return $a['id'] - $b['id'];
				}
				return $a['priority'] - $b['priority'];
			}
		);

		foreach ( $entries as $entry ) {
			// 'front-end' mirrors the Code Snippets scope: never run in wp-admin.
			if ( 'front-end' === $entry['scope'] && is_admin() ) {
				continue;
			}
			if ( ! apply_filters( 'uls_site_code_enabled', true, $entry['id'] ) ) {
				continue;
			}
			$path = __DIR__ . '/snippets/' . $entry['file'];
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	},
	0
);
