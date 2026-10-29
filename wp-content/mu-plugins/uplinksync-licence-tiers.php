<?php
/**
 * Plugin Name: UplinkSync — Licence tiers (Personal / Commercial with credit / Commercial / Extended)
 * Description: Product-page tier picker, licence v2.0 page, checkout licensee + agreement fields, per-line
 *   licence certificate PDF (order email + My Account), licence verification. Owner decisions 2026-10-28
 *   (portal/pricing/PRICING-PROPOSAL.md). Product data (variable products, pa_licence variations) is a DB
 *   step: tools/licence-tiers/migrate.php. Licence text is DRAFT pending counsel review.
 *   Feature flag: option `uls_licence_tiers_v2` = 1 switches on the v2 licence page/tab text and checkout
 *   fields. Everything else keys off data (only variable products with pa_licence, only order lines with
 *   a licence tier), so this file is inert on a site whose products have not been migrated.
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/uplinksync-licence-tiers/config.php';
require_once __DIR__ . '/uplinksync-licence-tiers/pdf.php';
require_once __DIR__ . '/uplinksync-licence-tiers/certificate.php';
require_once __DIR__ . '/uplinksync-licence-tiers/licence-page.php';

const ULS_LIC_PAGE_SLUG = 'image-license';

/** Feature flag (staging: on; prod: off until counsel + tax + G5 gates clear). */
function uls_lic_live() {
	return '1' === (string) get_option( 'uls_licence_tiers_v2', '' );
}

/** Is this a migrated licence-tier product? */
function uls_lic_is_tiered( $product ) {
	return $product instanceof WC_Product && $product->is_type( 'variable' ) && array_key_exists( ULS_LIC_ATTRIBUTE, $product->get_attributes() );
}

/* ------------------------------------------------------------------ assets */

add_action(
	'wp_enqueue_scripts',
	function () {
		$need = false;
		if ( function_exists( 'is_product' ) && is_product() ) {
			$need = uls_lic_is_tiered( wc_get_product( get_queried_object_id() ) );
		}
		$need = $need || ( uls_lic_live() && ( is_page( ULS_LIC_PAGE_SLUG ) || ( function_exists( 'is_account_page' ) && is_account_page() ) || ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) ) );
		if ( ! $need ) {
			return;
		}
		$base = plugins_url( 'uplinksync-licence-tiers/', __FILE__ );
		$dir  = __DIR__ . '/uplinksync-licence-tiers/';
		wp_enqueue_style( 'uls-licence', $base . 'licence.css', array(), (string) filemtime( $dir . 'licence.css' ) );
		if ( function_exists( 'is_product' ) && is_product() ) {
			wp_enqueue_script( 'uls-licence', $base . 'licence.js', array( 'jquery', 'wc-add-to-cart-variation' ), (string) filemtime( $dir . 'licence.js' ), true );
		}
	}
);

/* ------------------------------------------------------------ product page */

/**
 * Every licence variation on the site, grouped by parent: [ parent_id => [ row, ... ] ] where a row is
 * vid / status / slug / stock / price straight from the posts + postmeta tables. ONE query per request,
 * so shop and collection grids (122 / 83 products) do not hydrate four WC_Product_Variation objects
 * per card (MR !190 review High-1). Never cached across requests, so a stock change shows at once.
 *
 * @param int $parent_id Parent product id.
 * @return array
 */
function uls_lic_variation_rows( $parent_id ) {
	static $all = null;
	if ( null === $all ) {
		global $wpdb;
		$all  = array();
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT v.ID AS vid, v.post_parent AS pid, v.post_status AS status, a.meta_value AS slug, s.meta_value AS stock, pr.meta_value AS price
				FROM {$wpdb->posts} v
				INNER JOIN {$wpdb->postmeta} a ON a.post_id = v.ID AND a.meta_key = %s
				LEFT JOIN {$wpdb->postmeta} s ON s.post_id = v.ID AND s.meta_key = '_stock_status'
				LEFT JOIN {$wpdb->postmeta} pr ON pr.post_id = v.ID AND pr.meta_key = '_price'
				WHERE v.post_type = 'product_variation'",
				'attribute_' . ULS_LIC_ATTRIBUTE
			),
			ARRAY_A
		);
		foreach ( (array) $rows as $r ) {
			$all[ (int) $r['pid'] ][] = $r;
		}
	}
	return isset( $all[ (int) $parent_id ] ) ? $all[ (int) $parent_id ] : array();
}

/**
 * Tier state for a variable product, in display order: slug => [ 'price' => display price,
 * 'available' => purchasable AND in stock ]. Purchasable = published variation with a price (as
 * WC_Product_Variation::is_purchasable); in stock = stock status not "outofstock" (so "onbackorder"
 * counts as on sale, exactly like Woo core: never enable backorders on licence variations).
 * Display prices come from Woo's own cached variation-prices transient (tax display included).
 *
 * @param WC_Product $product Parent product.
 * @return array
 */
function uls_lic_tier_state( $product ) {
	static $cache = array();
	if ( ! uls_lic_is_tiered( $product ) ) {
		return array();
	}
	$pid = $product->get_id();
	if ( ! isset( $cache[ $pid ] ) ) {
		$prices        = $product->get_variation_prices( true );
		$cache[ $pid ] = uls_lic_state_from_rows( uls_lic_variation_rows( $pid ), isset( $prices['price'] ) ? (array) $prices['price'] : array() );
	}
	return $cache[ $pid ];
}

/** Price per tier slug (available tiers only). */
function uls_lic_variation_prices( $product ) {
	$out = array();
	foreach ( uls_lic_tier_state( $product ) as $slug => $s ) {
		if ( $s['available'] ) {
			$out[ $slug ] = $s['price'];
		}
	}
	return $out;
}

/** "$35" with no decimals. */
function uls_lic_money( $amount ) {
	return wc_price( $amount, array( 'decimals' => 0 ) );
}

/** Inline SVG icon (decorative). */
function uls_lic_icon( $name ) {
	$paths = array(
		'lock'  => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
		'chev'  => '<path d="M6 9l6 6 6-6"/>',
		'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
	);
	return '<svg class="uls-lic-ico uls-lic-ico--' . esc_attr( $name ) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/**
 * Tier asked for in the URL/POST (attribute_pa_licence), else the product default; '' when neither.
 * Non-string input (arrays) is ignored, never passed on (review Medium-2: was an unauthenticated 500).
 *
 * @param WC_Product $product Parent product.
 * @return string
 */
function uls_lic_requested_tier( $product ) {
	$k = 'attribute_' . ULS_LIC_ATTRIBUTE;
	if ( isset( $_REQUEST[ $k ] ) && is_string( $_REQUEST[ $k ] ) && '' !== $_REQUEST[ $k ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return sanitize_title( wp_unslash( $_REQUEST[ $k ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	$d = $product->get_variation_default_attribute( ULS_LIC_ATTRIBUTE );
	return is_string( $d ) ? sanitize_title( $d ) : '';
}

/** The native select offers only tiers that can be bought now; a coming-soon tier in the URL falls back to Personal. */
add_filter(
	'woocommerce_dropdown_variation_attribute_options_args',
	function ( $args ) {
		if ( ! isset( $args['attribute'] ) || ULS_LIC_ATTRIBUTE !== $args['attribute'] || empty( $args['product'] ) || ! uls_lic_is_tiered( $args['product'] ) ) {
			return $args;
		}
		list( $on ) = uls_lic_split_state( uls_lic_tier_state( $args['product'] ) );
		if ( ! $on ) {
			return $args;
		}
		if ( ! empty( $args['options'] ) ) {
			$args['options'] = array_values( array_intersect( (array) $args['options'], $on ) );
		}
		// Woo reads the request only when 'selected' is still false after this filter, so read it here
		// (string-guarded: ?attribute_pa_licence[]=x must never reach sanitize_title).
		$want = isset( $args['selected'] ) && is_string( $args['selected'] ) ? $args['selected'] : '';
		if ( '' === $want ) {
			$want = uls_lic_requested_tier( $args['product'] );
		}
		$args['selected'] = uls_lic_pick_default( sanitize_title( $want ), $on );
		return $args;
	}
);

/**
 * Radio-card picker next to the (visually hidden) native select that Woo's variation JS drives.
 * Tiers that can be bought now are radio rows; tiers that cannot are ONE collapsed "Coming soon"
 * group with no inputs and no prices (product-page spec §4). Each tier switches to a normal row on
 * its own as soon as its variation is purchasable and in stock.
 */
add_filter(
	'woocommerce_dropdown_variation_attribute_options_html',
	function ( $html, $args ) {
		if ( ULS_LIC_ATTRIBUTE !== $args['attribute'] || empty( $args['product'] ) ) {
			return $html;
		}
		$product        = $args['product'];
		$state          = uls_lic_tier_state( $product );
		list( $on, $off ) = uls_lic_split_state( $state );
		$tiers          = uls_lic_tiers();
		$sel            = uls_lic_pick_default( isset( $args['selected'] ) ? (string) $args['selected'] : '', $on );
		$contact        = home_url( '/contact/?topic=licensing' );
		$f              = '<fieldset class="uls-lic-picker"><legend>Choose a licence</legend>'
			. '<a class="uls-lic-compare" href="' . esc_url( apply_filters( 'uls_lic_compare_url', home_url( '/' . ULS_LIC_PAGE_SLUG . '/' ) ) ) . '">Compare all</a>';
		foreach ( $on as $slug ) {
			$t  = $tiers[ $slug ];
			$id = 'uls-lic-' . $slug;
			$f .= '<label class="uls-lic-opt" for="' . esc_attr( $id ) . '">'
				. '<input type="radio" name="uls_lic_pick" id="' . esc_attr( $id ) . '" value="' . esc_attr( $slug ) . '"' . checked( $sel, $slug, false ) . '>'
				. '<span class="uls-lic-opt__name">' . esc_html( $t['label'] ) . '</span>'
				. '<span class="uls-lic-opt__price">' . wp_kses_post( uls_lic_money( $state[ $slug ]['price'] ) ) . '</span>'
				. '<span class="uls-lic-opt__sum">' . esc_html( $t['summary'] )
				. ( 'required' === $t['credit'] && count( $on ) > 1 ? ' <span class="uls-lic-badge">Credit required</span>' : '' ) . '</span>'
				. '</label>';
		}
		$soon_commercial = false;
		if ( $off ) {
			$names = array();
			$items = '';
			foreach ( $off as $slug ) {
				$names[]         = $tiers[ $slug ]['label'];
				$soon_commercial = $soon_commercial || 'personal' !== $slug;
				$items          .= '<li><b>' . esc_html( $tiers[ $slug ]['label'] ) . '</b> ' . esc_html( $tiers[ $slug ]['summary'] ) . '</li>';
			}
			$title = in_array( 'personal', $off, true ) ? 'More licences' : ( count( $off ) > 1 ? ( uls_lic_commercial_on( $on ) ? 'More commercial licences' : 'Commercial licences' ) : $tiers[ $off[0] ]['label'] . ' licence' );
			$f    .= '<details class="uls-lic-soon"><summary>' . uls_lic_icon( 'lock' )
				. '<span class="uls-lic-soon__t">' . esc_html( $title ) . ( count( $off ) > 1 ? '<small>' . esc_html( implode( ' · ', $names ) ) . '</small>' : '' ) . '</span>'
				. '<span class="uls-lic-pill">Coming soon</span>' . uls_lic_icon( 'chev' ) . '</summary>'
				. '<ul class="uls-lic-soon__list">' . $items . '</ul></details>';
		}
		$f .= '</fieldset><p class="uls-lic-ask">';
		if ( $soon_commercial && ! uls_lic_commercial_on( $on ) ) {
			$f .= '<a href="' . esc_url( $contact ) . '">Ask about commercial use ' . uls_lic_icon( 'arrow' ) . '</a>';
		}
		$f .= '<a href="' . esc_url( add_query_arg( 'subject', rawurlencode( 'Exclusive licence: ' . $product->get_name() ), $contact ) ) . '">Exclusive rights? Request a quote</a></p>';
		return '<div class="uls-lic-selwrap">' . $html . '</div>' . $f;
	},
	10,
	2
);

/** "Add to cart · $35": the button names the price of the selected tier (licence.js keeps it in step). */
add_filter(
	'woocommerce_product_single_add_to_cart_text',
	function ( $text, $product ) {
		if ( ! uls_lic_is_tiered( $product ) ) {
			return $text;
		}
		$state     = uls_lic_tier_state( $product );
		list( $on ) = uls_lic_split_state( $state );
		$sel       = uls_lic_pick_default( uls_lic_requested_tier( $product ), $on );
		return '' === $sel ? $text : $text . ' · ' . html_entity_decode( wp_strip_all_tags( uls_lic_money( $state[ $sel ]['price'] ) ), ENT_QUOTES, 'UTF-8' );
	},
	10,
	2
);

/**
 * Coming-soon tiers do not publish their prices in the variation JSON (data-product_variations): the
 * picker never offers them, so Woo's form never needs them (review Low-3).
 */
add_filter(
	'woocommerce_available_variation',
	function ( $data, $product, $variation ) {
		if ( uls_lic_is_tiered( $product ) && $variation instanceof WC_Product && ! $variation->is_in_stock() ) {
			$data['price_html']            = '';
			$data['display_price']         = null;
			$data['display_regular_price'] = null;
		}
		return $data;
	},
	10,
	3
);

/**
 * Product schema offers = the tiers on sale now (review Low-3): a single Offer at $35 while only
 * Personal can be bought, an AggregateOffer over the buyable tiers once more open. Rank Math otherwise
 * publishes lowPrice 35 / highPrice 399 / offerCount 4 / InStock, which overstates what is for sale.
 */
add_filter(
	'rank_math/json_ld',
	function ( $data ) {
		if ( ! is_array( $data ) || ! function_exists( 'is_product' ) || ! is_product() ) {
			return $data;
		}
		$product = wc_get_product( get_queried_object_id() );
		$prices  = uls_lic_is_tiered( $product ) ? uls_lic_variation_prices( $product ) : array();
		if ( ! $prices ) {
			return $data;
		}
		foreach ( $data as $k => $node ) {
			if ( ! is_array( $node ) || empty( $node['offers'] ) || ! is_array( $node['offers'] ) || ! in_array( isset( $node['@type'] ) ? $node['@type'] : '', array( 'Product', 'ProductGroup' ), true ) ) {
				continue;
			}
			$o = $node['offers'];
			unset( $o['lowPrice'], $o['highPrice'], $o['offerCount'], $o['price'] );
			if ( 1 === count( $prices ) ) {
				$o['@type'] = 'Offer';
				$o['price'] = number_format( (float) min( $prices ), 2, '.', '' );
			} else {
				$o['@type']      = 'AggregateOffer';
				$o['lowPrice']   = number_format( (float) min( $prices ), 2, '.', '' );
				$o['highPrice']  = number_format( (float) max( $prices ), 2, '.', '' );
				$o['offerCount'] = (string) count( $prices );
			}
			$o['availability']   = 'https://schema.org/InStock';
			$data[ $k ]['offers'] = $o;
		}
		return $data;
	},
	99
);

/** No "Clear" link: a licence is always chosen (Personal by default). */
add_filter(
	'woocommerce_reset_variations_link',
	function ( $link ) {
		global $product;
		return uls_lic_is_tiered( $product ) ? '' : $link;
	}
);

/** No "SKU: N/A" line on tiered products (photos have no SKUs). */
add_filter(
	'wc_product_sku_enabled',
	function ( $on ) {
		global $product;
		return ( ! is_admin() && function_exists( 'is_product' ) && is_product() && uls_lic_is_tiered( $product ) ) ? false : $on;
	}
);

/**
 * Variable price on tiered products: "From $35" only when two or more tiers can be bought, a plain
 * "$35" while only one can (orchestrator decision 2026-10-28), instead of "$35.00 – $399.00".
 */
add_filter(
	'woocommerce_variable_price_html',
	function ( $html, $product ) {
		if ( ! uls_lic_is_tiered( $product ) ) {
			return $html;
		}
		$prices = uls_lic_variation_prices( $product );
		if ( ! $prices ) {
			return '<span class="uls-lic-from">From</span> ' . uls_lic_money( $product->get_variation_price( 'min', true ) );
		}
		return ( count( $prices ) > 1 ? '<span class="uls-lic-from">From</span> ' : '' ) . uls_lic_money( min( $prices ) );
	},
	10,
	2
);

/**
 * Licences the current user already holds for a product: [ [tier, licence id], ... ] best first.
 *
 * @param int $product_id Parent product id.
 * @return array
 */
function uls_lic_owned( $product_id ) {
	if ( ! is_user_logged_in() ) {
		return array();
	}
	$own    = array();
	$orders = wc_get_orders(
		array(
			'customer_id' => get_current_user_id(),
			'status'      => array( 'wc-completed', 'wc-processing' ),
			'limit'       => 100,
		)
	);
	foreach ( $orders as $o ) {
		foreach ( $o->get_items() as $item ) {
			if ( (int) $item->get_product_id() === (int) $product_id ) {
				$tier = uls_lic_item_tier( $item );
				if ( '' !== $tier ) {
					$own[] = array( $tier, uls_lic_id( $o->get_order_number(), $item->get_id() ) );
				}
			}
		}
	}
	usort(
		$own,
		function ( $a, $b ) {
			return uls_lic_rank( $b[0] ) - uls_lic_rank( $a[0] );
		}
	);
	return $own;
}

/**
 * Credit note and upgrade path under the add-to-cart form. Shown only once two or more tiers can be
 * bought (no upgrade or credit line to talk about before then); a buyer who already holds a licence
 * for this image always sees that line. The Exclusive quote link now sits under the picker.
 */
add_action(
	'woocommerce_after_add_to_cart_form',
	function () {
		global $product;
		if ( ! uls_lic_is_tiered( $product ) ) {
			return;
		}
		$tiers    = uls_lic_tiers();
		$state    = uls_lic_tier_state( $product );
		list( $on ) = uls_lic_split_state( $state );
		$launched = count( $on ) > 1;
		$own      = uls_lic_owned( $product->get_id() );
		if ( ! $launched && ! $own ) {
			return;
		}
		echo '<div class="uls-lic-more">';
		if ( $own ) {
			list( $tier, $lid ) = $own[0];
			$ups                = array();
			foreach ( $on as $slug ) {
				$d = uls_lic_upgrade_price( $tier, $slug );
				if ( $d > 0 && uls_lic_rank( $slug ) > uls_lic_rank( $tier ) ) {
					$ups[] = esc_html( $tiers[ $slug ]['label'] ) . ' +$' . (int) $d;
				}
			}
			echo '<p class="uls-lic-owned"><strong>You hold ' . ( 'extended' === $tier ? 'an ' : 'a ' ) . esc_html( $tiers[ $tier ]['label'] ) . ' licence for this image</strong> (' . esc_html( $lid ) . ').';
			if ( $ups ) {
				echo ' Upgrade for the price difference: ' . implode( ' · ', $ups ) . '. <a href="' . esc_url( home_url( '/contact/?subject=' . rawurlencode( 'Licence upgrade ' . $lid ) ) ) . '">Request an upgrade</a>';
			}
			echo '</p>';
		}
		if ( $launched ) {
			if ( in_array( 'commercial-credit', $on, true ) ) {
				echo '<p><strong>Credit line</strong> for &ldquo;Commercial with credit&rdquo;: &ldquo;Aerial by UplinkSync&rdquo; with a link to uplinksync.com on every use (the plain URL in print).</p>';
			}
			if ( ! $own && in_array( 'personal', $on, true ) && in_array( 'commercial', $on, true ) ) {
				echo '<p><strong>Bought Personal and now need it for business?</strong> Upgrade any time for the price difference (Personal to Commercial: $' . (int) uls_lic_upgrade_price( 'personal', 'commercial' ) . ').</p>';
			}
		}
		echo '</div>';
	}
);

/** Tier-aware Licence tab (replaces storefront-truth's single-tier wording on tiered products). */
add_filter(
	'woocommerce_product_tabs',
	function ( $tabs ) {
		global $product;
		if ( uls_lic_is_tiered( $product ) ) {
			unset( $tabs['additional_information'] ); // Only repeats the tier names the picker already shows.
			if ( isset( $tabs['uls_licence'] ) ) {
				$tabs['uls_licence']['callback'] = 'uls_lic_tab';
			}
		}
		return $tabs;
	},
	99
);

function uls_lic_tab() {
	echo '<h2>Licence</h2><p>One full-resolution JPEG, delivered as a download. UplinkSync keeps the copyright; your tier sets how you may use it. Every tier is perpetual, worldwide and non-exclusive, for one licensee.</p>';
	echo '<div class="uls-lic-tablewrap"><table class="uls-lic-table"><thead><tr><th scope="col">Tier</th><th scope="col">Price</th><th scope="col">What you can do</th></tr></thead><tbody>';
	foreach ( uls_lic_tiers() as $t ) {
		echo '<tr><th scope="row"><strong>' . esc_html( $t['label'] ) . '</strong></th><td class="uls-lic-num">$' . (int) $t['price'] . '</td><td>' . esc_html( $t['allowed'] ) . '</td></tr>';
	}
	echo '</tbody></table></div><p>No tier allows reselling or sharing the file itself, logos or trademarks, AI training or NFTs. <a href="' . esc_url( home_url( '/' . ULS_LIC_PAGE_SLUG . '/' ) ) . '">Read the full image licence</a>.</p>';
}

/* ------------------------------------------------------------ licence page */

add_filter(
	'the_content',
	function ( $content ) {
		$post = get_post();
		if ( ! $post || 'page' !== $post->post_type || ULS_LIC_PAGE_SLUG !== $post->post_name || ! uls_lic_live() ) {
			return $content;
		}
		$out = '';
		if ( current_user_can( 'edit_pages' ) ) {
			$out .= '<div class="uls-lic-draft" role="note"><strong>Draft — pending legal review.</strong> Licence ' . esc_html( ULS_LIC_VERSION ) . ' text from the 2026-10-28 pricing proposal. Only editors see this notice. Do not publish to production before counsel signs off.</div>';
		}
		if ( isset( $_GET['verify'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only lookup.
			$out .= uls_lic_verify_box( sanitize_text_field( wp_unslash( $_GET['verify'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		return $out . uls_lic_page_body();
	},
	1
);

/** Public verification: tier, image and date only (never the licensee). */
function uls_lic_verify_box( $id ) {
	$p    = uls_lic_parse_id( $id );
	$ok   = false;
	$body = '';
	if ( $p && function_exists( 'wc_get_order_id_by_order_item_id' ) ) {
		$oid   = wc_get_order_id_by_order_item_id( $p[1] );
		$order = $oid ? wc_get_order( $oid ) : null;
		$item  = $order ? $order->get_item( $p[1] ) : null;
		if ( $item && (string) $order->get_order_number() === $p[0] && $item->get_meta( '_uls_lic_id' ) === $id && $order->has_status( array( 'completed', 'processing' ) ) ) {
			$t    = uls_lic_tiers();
			$tier = $item->get_meta( '_uls_lic_tier' );
			$ok   = true;
			$body = esc_html( isset( $t[ $tier ] ) ? $t[ $tier ]['label'] : $tier ) . ' licence for &ldquo;' . esc_html( $item->get_meta( '_uls_lic_image' ) ) . '&rdquo;, issued ' . esc_html( $item->get_meta( '_uls_lic_issued' ) ) . ' (' . esc_html( $item->get_meta( '_uls_lic_version' ) ) . ').';
		}
	}
	return '<div class="uls-lic-verify ' . ( $ok ? 'is-ok' : 'is-bad' ) . '" role="status"><strong>' . esc_html( $id ) . ': ' . ( $ok ? 'valid' : 'not found or no longer valid' ) . '.</strong> ' . $body . '</div>';
}

/* ---------------------------------------------------------------- checkout */

add_action(
	'woocommerce_init',
	function () {
		if ( ! uls_lic_live() || ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}
		woocommerce_register_additional_checkout_field(
			array(
				'id'       => 'uplinksync/licensee',
				'label'    => 'Licensee: the person or business the licence is for',
				'location' => 'order',
				'type'     => 'text',
				'required' => true,
			)
		);
		woocommerce_register_additional_checkout_field(
			array(
				'id'       => 'uplinksync/licence-agree',
				'label'    => 'I agree to the UplinkSync Image Licence v2.0 (uplinksync.com/image-license) for the tier on each item',
				'location' => 'order',
				'type'     => 'checkbox',
				'required' => true,
			)
		);
	}
);

/** Read a block-checkout additional field from an order. */
function uls_lic_order_field( $order, $id ) {
	if ( class_exists( '\Automattic\WooCommerce\Blocks\Package' ) && class_exists( '\Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields' ) ) {
		try {
			$cf = \Automattic\WooCommerce\Blocks\Package::container()->get( \Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class );
			$v  = $cf->get_field_from_object( $id, $order, 'other' );
			if ( null !== $v && '' !== $v ) {
				return $v;
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}
	}
	return $order->get_meta( '_wc_other/' . $id );
}

/* ----------------------------------------------------- licence snapshots */

/** Tier slug of an order line ('' when not a licence line). */
function uls_lic_item_tier( $item ) {
	$tier = (string) $item->get_meta( '_uls_lic_tier' );
	if ( '' === $tier ) {
		$tier = sanitize_title( (string) $item->get_meta( ULS_LIC_ATTRIBUTE ) );
	}
	return array_key_exists( $tier, uls_lic_tiers() ) ? $tier : '';
}

/**
 * Snapshot licence terms onto each licence line once (licence id, tier, version, licensee, image, date),
 * so later wording or price changes never alter what was granted. Idempotent.
 */
function uls_lic_stamp_order( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}
	$has = false;
	foreach ( $order->get_items() as $item ) {
		$has = $has || '' !== uls_lic_item_tier( $item );
	}
	if ( ! $has ) {
		return; // Not a licence order: write nothing.
	}
	$dirty    = false;
	$licensee = trim( (string) $order->get_meta( '_uls_licensee' ) );
	if ( '' === $licensee ) {
		$licensee = trim( (string) uls_lic_order_field( $order, 'uplinksync/licensee' ) );
		if ( '' === $licensee ) {
			$licensee = trim( $order->get_billing_company() ? $order->get_billing_company() . ' (' . $order->get_formatted_billing_full_name() . ')' : $order->get_formatted_billing_full_name() );
		}
		if ( '' !== $licensee ) {
			$order->update_meta_data( '_uls_licensee', $licensee );
			$dirty = true;
		}
		if ( uls_lic_order_field( $order, 'uplinksync/licence-agree' ) ) {
			$order->update_meta_data( '_uls_licence_agreed', ULS_LIC_VERSION . ' @ ' . gmdate( 'c' ) );
			$dirty = true;
		}
	}
	$changed = false;
	foreach ( $order->get_items() as $item ) {
		$tier = uls_lic_item_tier( $item );
		if ( '' === $tier || $item->get_meta( '_uls_lic_id' ) ) {
			continue;
		}
		$pid  = $item->get_product_id();
		$slug = (string) get_post_meta( $pid, '_uplaa_photo_slug', true );
		$paid = $order->get_date_paid() ? $order->get_date_paid() : $order->get_date_created();
		$item->update_meta_data( '_uls_lic_id', uls_lic_id( $order->get_order_number(), $item->get_id() ) );
		$item->update_meta_data( '_uls_lic_tier', $tier );
		$item->update_meta_data( '_uls_lic_version', ULS_LIC_VERSION );
		$item->update_meta_data( '_uls_lic_licensee', $licensee );
		$item->update_meta_data( '_uls_lic_image', get_the_title( $pid ) );
		$item->update_meta_data( '_uls_lic_asset', $pid . ( '' !== $slug ? ' · ' . $slug : '' ) );
		$item->update_meta_data( '_uls_lic_issued', $paid ? $paid->date_i18n( 'j F Y' ) : gmdate( 'j F Y' ) );
		$item->save();
		$changed = true;
	}
	if ( $changed || $dirty ) {
		$order->save();
	}
}
foreach ( array( 'woocommerce_order_status_processing', 'woocommerce_order_status_completed' ) as $uls_hook ) {
	add_action(
		$uls_hook,
		function ( $order_id ) {
			uls_lic_stamp_order( wc_get_order( $order_id ) );
		},
		5
	);
}

/** Licence lines of a paid order: [ item_id => WC_Order_Item_Product ]. */
function uls_lic_items( $order ) {
	$out = array();
	if ( ! $order instanceof WC_Order || ! $order->has_status( array( 'completed', 'processing' ) ) ) {
		return $out;
	}
	foreach ( $order->get_items() as $id => $item ) {
		if ( '' !== uls_lic_item_tier( $item ) ) {
			$out[ $id ] = $item;
		}
	}
	return $out;
}

/** Certificate data for one line (from the snapshot). */
function uls_lic_certificate_data( $order, $item ) {
	uls_lic_stamp_order( $order );
	$item  = $order->get_item( $item->get_id() );
	$id    = $item->get_meta( '_uls_lic_id' );
	$thumb = '';
	$tid   = get_post_thumbnail_id( $item->get_product_id() );
	if ( $tid ) {
		$mid = image_get_intermediate_size( $tid, 'medium' );
		if ( $mid && ! empty( $mid['path'] ) ) {
			$up    = wp_get_upload_dir();
			$thumb = trailingslashit( $up['basedir'] ) . $mid['path'];
		}
	}
	return array(
		'licence_id'   => $id,
		'version'      => $item->get_meta( '_uls_lic_version' ),
		'order_number' => $order->get_order_number(),
		'date'         => $item->get_meta( '_uls_lic_issued' ),
		'licensee'     => $item->get_meta( '_uls_lic_licensee' ),
		'image_title'  => $item->get_meta( '_uls_lic_image' ),
		'asset_id'     => $item->get_meta( '_uls_lic_asset' ),
		'tier'         => $item->get_meta( '_uls_lic_tier' ),
		'terms_url'    => home_url( '/' . ULS_LIC_PAGE_SLUG . '/' ),
		'verify_url'   => add_query_arg( 'verify', $id, home_url( '/' . ULS_LIC_PAGE_SLUG . '/' ) ),
		'thumb_path'   => is_readable( $thumb ) ? $thumb : '',
	);
}

/* ---------------------------------------------------------- order email */

add_filter(
	'woocommerce_email_attachments',
	function ( $attachments, $email_id, $order ) {
		if ( ! in_array( $email_id, array( 'customer_completed_order', 'customer_invoice' ), true ) || ! $order instanceof WC_Order ) {
			return $attachments;
		}
		foreach ( uls_lic_items( $order ) as $item ) {
			$d   = uls_lic_certificate_data( $order, $item );
			$dir = trailingslashit( get_temp_dir() ) . 'uls-lic-' . wp_generate_password( 12, false );
			if ( ! wp_mkdir_p( $dir ) ) {
				continue;
			}
			$file = $dir . '/UplinkSync-licence-' . $d['licence_id'] . '.pdf';
			if ( false !== file_put_contents( $file, uls_lic_certificate_pdf( $d ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
				$attachments[] = $file;
				register_shutdown_function(
					function () use ( $file, $dir ) {
						@unlink( $file ); // phpcs:ignore
						@rmdir( $dir ); // phpcs:ignore
					}
				);
			}
		}
		return $attachments;
	},
	10,
	3
);

add_action(
	'woocommerce_email_after_order_table',
	function ( $order, $sent_to_admin, $plain_text, $email = null ) {
		if ( $sent_to_admin || ! $email || ! in_array( $email->id, array( 'customer_completed_order', 'customer_invoice' ), true ) || ! uls_lic_items( $order ) ) {
			return;
		}
		$needs_credit = false;
		foreach ( uls_lic_items( $order ) as $item ) {
			$t            = uls_lic_tiers();
			$needs_credit = $needs_credit || 'required' === $t[ uls_lic_item_tier( $item ) ]['credit'];
		}
		$msg = 'Your licence certificate for each image is attached (PDF) and is always available in My Account. Downloads need you to be signed in; each licence includes ' . ULS_LIC_DL_LIMIT . ' downloads within ' . ULS_LIC_DL_EXPIRY . ' days.';
		if ( $needs_credit ) {
			$msg .= ' Your "Commercial with credit" licence requires this credit with every use: ' . ULS_LIC_CREDIT_LINE . '.';
		}
		echo $plain_text ? "\n" . esc_html( $msg ) . "\n" : '<p>' . esc_html( $msg ) . '</p>';
	},
	10,
	4
);

/* --------------------------------------------- My Account + certificate PDF */

function uls_lic_pdf_url( $item_id ) {
	return wp_nonce_url( add_query_arg( 'uls_licence_pdf', (int) $item_id, home_url( '/' ) ), 'uls_lic_pdf_' . (int) $item_id );
}

/** May the current user fetch this order's certificates? Owner (logged in) or shop manager only. */
function uls_lic_can_view( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return false;
	}
	if ( current_user_can( 'manage_woocommerce' ) ) {
		return true;
	}
	return is_user_logged_in() && (int) $order->get_customer_id() === get_current_user_id() && $order->get_customer_id() > 0;
}

add_action(
	'woocommerce_order_details_after_order_table',
	function ( $order ) {
		$items = uls_lic_items( $order );
		if ( ! $items ) {
			return;
		}
		$tiers = uls_lic_tiers();
		echo '<section class="uls-lic-certs"><h2>Licence certificates</h2>';
		if ( ! uls_lic_can_view( $order ) ) {
			echo '<p>Sign in to <a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">My Account</a> to download your licence certificates.</p></section>';
			return;
		}
		echo '<ul>';
		foreach ( $items as $id => $item ) {
			uls_lic_stamp_order( $order );
			$item = $order->get_item( $id );
			$tier = uls_lic_item_tier( $item );
			echo '<li><a class="uls-lic-pdf" href="' . esc_url( uls_lic_pdf_url( $id ) ) . '">' . esc_html( $item->get_meta( '_uls_lic_id' ) ) . ' (PDF)</a> '
				. esc_html( $item->get_meta( '_uls_lic_image' ) ) . ' · <strong>' . esc_html( $tiers[ $tier ]['label'] ) . '</strong>'
				. ( 'required' === $tiers[ $tier ]['credit'] ? '<br><span class="uls-lic-badge">Credit required</span> ' . esc_html( ULS_LIC_CREDIT_LINE ) : '' ) . '</li>';
		}
		echo '</ul><p class="uls-lic-fine">Need a higher tier? Upgrade any time for the price difference: <a href="' . esc_url( home_url( '/contact/?subject=Licence%20upgrade' ) ) . '">send us the licence ID</a>.</p></section>';
	}
);

/** Certificates also appear in My Account → Downloads, next to the image files. */
add_filter(
	'woocommerce_customer_available_downloads',
	function ( $downloads, $customer_id ) {
		if ( ! $customer_id || (int) $customer_id !== get_current_user_id() ) {
			return $downloads;
		}
		$orders = wc_get_orders(
			array(
				'customer_id' => $customer_id,
				'status'      => array( 'wc-completed', 'wc-processing' ),
				'limit'       => 100,
			)
		);
		foreach ( $orders as $order ) {
			foreach ( uls_lic_items( $order ) as $id => $item ) {
				uls_lic_stamp_order( $order );
				$item        = $order->get_item( $id );
				$downloads[] = array(
					'download_url'        => uls_lic_pdf_url( $id ),
					'download_id'         => 'uls-lic-' . $id,
					'product_id'          => $item->get_product_id(),
					'product_name'        => $item->get_meta( '_uls_lic_image' ),
					'product_url'         => get_permalink( $item->get_product_id() ),
					'download_name'       => 'Licence certificate ' . $item->get_meta( '_uls_lic_id' ) . ' (PDF)',
					'order_id'            => $order->get_id(),
					'order_key'           => $order->get_order_key(),
					'downloads_remaining' => '',
					'access_expires'      => null,
					'file'                => array(
						'name' => 'Licence certificate (PDF)',
						'file' => '',
					),
				);
			}
		}
		return $downloads;
	},
	10,
	2
);

add_action(
	'template_redirect',
	function () {
		if ( ! isset( $_GET['uls_licence_pdf'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$item_id = absint( $_GET['uls_licence_pdf'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( wc_get_page_permalink( 'myaccount' ) ), wc_get_page_permalink( 'myaccount' ) ) );
			exit;
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'uls_lic_pdf_' . $item_id ) ) {
			wp_die( 'This certificate link has expired. Open it again from My Account.', 'Licence certificate', array( 'response' => 403 ) );
		}
		$oid   = $item_id ? wc_get_order_id_by_order_item_id( $item_id ) : 0;
		$order = $oid ? wc_get_order( $oid ) : null;
		$items = $order ? uls_lic_items( $order ) : array();
		if ( ! isset( $items[ $item_id ] ) || ! uls_lic_can_view( $order ) ) {
			wp_die( 'Certificate not found for this account.', 'Licence certificate', array( 'response' => 404 ) );
		}
		$d   = uls_lic_certificate_data( $order, $items[ $item_id ] );
		$pdf = uls_lic_certificate_pdf( $d );
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="UplinkSync-licence-' . $d['licence_id'] . '.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		header( 'X-Robots-Tag: noindex' );
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}
);
