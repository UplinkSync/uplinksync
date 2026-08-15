<?php
/**
 * 900 — DR-006 INTERIM client-media authorization mitigations (M1 + M2)
 *
 * id:        900   (NOT a migrated wp_snippets row — native plugin code. The 9xx range is
 *                   reserved for code that was written here rather than migrated, so the
 *                   manifest's "keep priority identical to the original row" rule does not
 *                   apply to it.)
 * priority:  5     (registers filters only; early so it is visibly first in the manifest)
 * scope:     global
 *
 * WHY THIS EXISTS — see ecosystem-docs doc 125 (DR-006).
 *
 * Two live exposures were found on 2026-08-14 while specifying the F2/R28 fix. Neither is
 * addressed by the existing mitigation (product 1022 held at post_status=draft):
 *
 *   M1  Any LOGGED-IN client could enumerate every other client's album media through
 *       /wp-json/wp/v2/media. Admin Site Enhancements' REST lock authorizes on user
 *       EXISTENCE — there is no capability check anywhere in its source — and it falls back
 *       to wp_validate_auth_cookie(), so a plain login cookie passes with no REST nonce.
 *       Behind that lock, core is permissive: the album images are ordinary published
 *       attachments (post_status=inherit, post_parent=0, contiguous IDs 1177-1243), and
 *       WP_REST_Attachments_Controller::get_item_permissions_check() returns true for them
 *       even with no user set. Application Passwords are enabled, so it was scriptable.
 *
 *   M2  The Store API category endpoint disclosed the gated album's existence, slug, name
 *       and item count to anonymous callers, while the product listing correctly returned
 *       []. The mitigation had been applied to an endpoint rather than to the concept.
 *
 * THIS IS INTERIM. It closes two holes; it does not fix the architecture. The root cause is
 * that authorization is bound to a rendering surface (template_redirect on product pages)
 * rather than to the object, so every non-theme reader is authorized by default — and the
 * two REAL albums have no product_cat at all, so that gate never fires for them. DR-006 §4
 * decides the replacement. Delete this file as a unit when that lands.
 *
 * DELIBERATELY NOT DEPENDENT on snippet 93. Its uplaa179_can_view() is used when present,
 * but this file fails CLOSED without it — the whole point is that a reader must not be able
 * to see gated content because an authorization component happened not to load.
 */

defined( 'ABSPATH' ) || exit;

/**
 * product_cat slugs that belong to a gated album, from the uplaa179_albums option.
 *
 * @return string[] Lower-cased slugs. Empty array if the option is missing or malformed.
 */
function uls_dr006_gated_cat_slugs() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$cache  = array();
	$albums = get_option( 'uplaa179_albums', array() );
	if ( ! is_array( $albums ) ) {
		return $cache;
	}

	foreach ( $albums as $album ) {
		if ( ! is_array( $album ) ) {
			continue;
		}
		$slug = isset( $album['product_cat'] ) ? trim( (string) $album['product_cat'] ) : '';
		if ( '' !== $slug ) {
			$cache[] = strtolower( $slug );
		}
	}

	$cache = array_values( array_unique( $cache ) );
	return $cache;
}

/**
 * May the current user see the album that owns this product_cat slug?
 *
 * Fails CLOSED: without snippet 93's entitlement function, only shop managers pass.
 *
 * @param string $cat_slug product_cat slug.
 * @return bool
 */
function uls_dr006_may_view_cat( $cat_slug ) {
	if ( current_user_can( 'manage_woocommerce' ) ) {
		return true;
	}
	if ( ! is_user_logged_in() ) {
		return false;
	}

	// Reuse the album ACL when snippet 93 is loaded; otherwise deny.
	if ( function_exists( 'uplaa179_album_for_cat' ) && function_exists( 'uplaa179_can_view' ) ) {
		$album = uplaa179_album_for_cat( $cat_slug );
		if ( $album ) {
			return (bool) uplaa179_can_view( $album );
		}
	}

	return false;
}

/**
 * M1 — /wp/v2/media is for people who manage media, not for every account that exists.
 *
 * Matched on the PARSED ROUTE, not REQUEST_URI. That distinction is the F1 bug (doc 118):
 * an unanchored strpos over REQUEST_URI let "?a=/wc/store/" reopen a locked endpoint. The
 * route carries no query string, and the match is anchored to an exact segment boundary.
 */
add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, $request ) {
		if ( null !== $result ) {
			return $result; // Something already answered; do not override it.
		}

		$route = (string) $request->get_route();
		if ( '/wp/v2/media' !== $route && 0 !== strpos( $route, '/wp/v2/media/' ) ) {
			return $result;
		}

		// upload_files is held by administrator/editor/author/shop_manager, not by customer.
		if ( current_user_can( 'upload_files' ) ) {
			return $result;
		}

		return new WP_Error(
			'uls_dr006_media_forbidden',
			__( 'Media browsing is restricted.', 'uplinksync' ),
			array( 'status' => rest_authorization_required_code() ) // 401 anon, 403 logged-in.
		);
	},
	5,
	3
);

/**
 * M2 — do not disclose gated album categories through the Store API.
 *
 * Filters the response rather than the term query, so it cannot alter what the shop's own
 * PHP sees and cannot change admin behaviour. Covers both the collection and the single
 * item; the single item is answered 404 rather than 403, so the endpoint does not confirm
 * that a hidden category exists.
 */
function uls_dr006_filter_category_response( $response, $handler, $request ) {
		if ( ! ( $response instanceof WP_REST_Response ) ) {
			return $response;
		}
		if ( ! ( $request instanceof WP_REST_Request ) ) {
			return $response;
		}

		$route = (string) $request->get_route();
		if ( 0 !== strpos( $route, '/wc/store/' ) || false === strpos( $route, '/products/categories' ) ) {
			return $response;
		}

		$gated = uls_dr006_gated_cat_slugs();
		if ( empty( $gated ) ) {
			return $response;
		}

		$data = $response->get_data();
		if ( ! is_array( $data ) ) {
			return $response;
		}

		// Single category: /wc/store/v1/products/categories/<id>
		if ( isset( $data['slug'] ) ) {
			$slug = strtolower( (string) $data['slug'] );
			if ( in_array( $slug, $gated, true ) && ! uls_dr006_may_view_cat( $slug ) ) {
				return new WP_Error(
					'woocommerce_rest_category_invalid_id',
					__( 'Invalid category ID.', 'uplinksync' ),
					array( 'status' => 404 )
				);
			}
			return $response;
		}

		// Collection: drop gated entries the caller is not entitled to see.
		$filtered = array();
		$removed  = false;
		foreach ( $data as $item ) {
			$slug = is_array( $item ) && isset( $item['slug'] ) ? strtolower( (string) $item['slug'] ) : '';
			if ( '' !== $slug && in_array( $slug, $gated, true ) && ! uls_dr006_may_view_cat( $slug ) ) {
				$removed = true;
				continue;
			}
			$filtered[] = $item;
		}

		if ( $removed ) {
			$response->set_data( array_values( $filtered ) );
			// Keep pagination headers honest rather than silently inconsistent.
			$total = $response->get_headers();
			if ( isset( $total['X-WP-Total'] ) ) {
				$response->header( 'X-WP-Total', max( 0, (int) $total['X-WP-Total'] - 1 ) );
			}
		}

		return $response;
}

/*
 * Hooked in BOTH places deliberately.
 *
 * rest_request_after_callbacks fires inside WP_REST_Server::dispatch(), so it covers
 * internal rest_do_request() calls as well as real HTTP. rest_post_dispatch fires only in
 * serve_request(), i.e. real HTTP only.
 *
 * The first version of this file used rest_post_dispatch alone. A dispatch-based test then
 * reported the category as still visible — which read like a broken filter but was a
 * blind test harness. Covering both hooks makes the control testable by the same mechanism
 * that exercises it, which is the point: a check that cannot observe the thing it guards is
 * the failure mode this project keeps rediscovering.
 *
 * The filter is idempotent — running twice removes nothing extra.
 */
add_filter( 'rest_request_after_callbacks', 'uls_dr006_filter_category_response', 10, 3 );
add_filter(
	'rest_post_dispatch',
	static function ( $response, $server, $request ) {
		return uls_dr006_filter_category_response( $response, null, $request );
	},
	10,
	3
);
