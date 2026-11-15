<?php
/**
 * 093 — UPLAA-179 proofing-gallery retirement redirect
 *
 * Replaces database-resident Code Snippets row id=93 after that row is
 * deactivated. This is deliberately a retirement shim, not a port of its
 * authorization or gallery behaviour: the owner selected DroneOps portal +
 * Invoice Ninja as the replacement workflow.
 *
 * scope: front-end   priority: 10
 *
 * The row stays in wp_snippets for rollback; this file only keeps historic
 * proofing page, category, product, and diagnostic-REST URLs from becoming
 * dead links. Do not delete albums, attachments, or issue a CDN purge here.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return the configured legacy UPLAA-179 product-category slugs.
 *
 * Reading the option avoids baking client identifiers into code and means a
 * pre-existing proofing category still redirects even if its page slug was
 * not one of the two bootstrap pages.
 *
 * @return string[]
 */
function uls093_retired_category_slugs() {
	$slugs  = array();
	$albums = get_option( 'uplaa179_albums', array() );
	if ( ! is_array( $albums ) ) {
		return $slugs;
	}

	foreach ( $albums as $album ) {
		if ( ! is_array( $album ) || empty( $album['product_cat'] ) ) {
			continue;
		}
		$slugs[] = sanitize_title( (string) $album['product_cat'] );
	}

	return array_values( array_unique( array_filter( $slugs ) ) );
}

/**
 * Whether database row 93 is still active and owns the legacy routes.
 *
 * This must be checked when a hook fires, rather than while this file loads:
 * Code Snippets can load the database row later in the same request.  Its
 * entitlement function and shortcode are both owned by the original row, so
 * either one is sufficient evidence that rollback has restored it.
 *
 * @return bool
 */
function uls093_retired_row_is_active() {
	return function_exists( 'uplaa179_can_view' ) || shortcode_exists( 'uplaa_client_album' );
}

/**
 * Whether the queried front-end request was formerly handled by row 93.
 *
 * @return bool
 */
function uls093_retired_is_proofing_request() {
	if ( is_admin() ) {
		return false;
	}

	// The two pages created by the one-shot bootstraps, plus any later page
	// that contains the old shortcode.
	if ( is_page() ) {
		$page = get_queried_object();
		if ( $page && isset( $page->post_content ) && has_shortcode( $page->post_content, 'uplaa_client_album' ) ) {
			return true;
		}
		$pagename = (string) get_query_var( 'pagename' );
		if ( in_array( $pagename, array( 'aurumgrove-proofing', 'respberry-proofing' ), true ) ) {
			return true;
		}
	}

	$slugs = uls093_retired_category_slugs();
	if ( empty( $slugs ) || ! function_exists( 'is_product_category' ) ) {
		return false;
	}

	if ( is_product_category() ) {
		$term = get_queried_object();
		return $term && isset( $term->slug ) && in_array( sanitize_title( (string) $term->slug ), $slugs, true );
	}

	if ( function_exists( 'is_product' ) && is_product() ) {
		$product_id = get_queried_object_id();
		$terms      = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'slugs' ) );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( in_array( sanitize_title( (string) $term ), $slugs, true ) ) {
					return true;
				}
			}
		}
	}

	return false;
}

add_action(
	'template_redirect',
	function () {
		// During rollback row 93 resumes full ownership; never pre-empt its gate.
		if ( uls093_retired_row_is_active() ) {
			return;
		}

		if ( uls093_retired_is_proofing_request() ) {
			wp_safe_redirect( home_url( '/hub/' ), 302 );
			exit;
		}
	},
	1
);

/*
 * Preserve the one diagnostic route family as a redirect too. Registering a
 * catch-all route is required because unmatched REST routes do not reach
 * rest_pre_dispatch. This is intentionally read-only and carries no former
 * album data.
 */
add_action(
	'rest_api_init',
	function () {
		// Avoid registering a catch-all beside row 93's real diagnostic route.
		if ( uls093_retired_row_is_active() ) {
			return;
		}

		register_rest_route(
			'uplaa179/v1',
			'/(?P<legacy_path>.*)',
			array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'permission_callback' => '__return_true',
				'callback'            => static function () {
					$response = new WP_REST_Response( null, 302 );
					$response->header( 'Location', home_url( '/hub/' ) );
					return $response;
				},
			)
		);
	}
);
