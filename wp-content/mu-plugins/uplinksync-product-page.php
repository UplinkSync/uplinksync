<?php
/**
 * Plugin Name: UplinkSync — Aerial photo product page (redesign 2026-10-28)
 * Description: Two-column product page for licence-tier photo products (portal/product-page/DESIGN.md):
 *   sticky media column (gallery, facts strip, "About this photo"), buy box (eyebrow, price, picker,
 *   add to cart, express pay, trust list), licence comparison table + FAQ + help card in place of the
 *   Description/Licence tabs, and "More from this collection" in place of the next-print card and
 *   Related products. Facts are read at render time from existing product data (description text,
 *   image dimensions, categories) and a cell is hidden when its value is missing. Licence availability
 *   comes from the variations (uplinksync-licence-tiers.php), never from a flag.
 *   Applies only to variable products with the pa_licence attribute; every other product renders as before.
 *   Supersedes the interim Code Snippet 123 on the product page (its Licence tab is removed here); the
 *   snippet's "Available soon" availability text still applies in cart/stock contexts.
 *   Rollback: revert the MR (or overwrite this file with an inert stub: the deploy rsync does not delete).
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/uplinksync-product-page/helpers.php';

/**
 * Is this request a redesigned photo product page? (single product + licence-tier product)
 *
 * @return bool
 */
function uls_pp_active() {
	static $on = null;
	if ( null !== $on ) {
		return $on;
	}
	if ( ! did_action( 'wp' ) || ! function_exists( 'is_product' ) || ! function_exists( 'uls_lic_is_tiered' ) ) {
		return false; // Not decidable yet; do not cache.
	}
	$on = is_product() && uls_lic_is_tiered( wc_get_product( get_queried_object_id() ) );
	return $on;
}

/** The page's own product (never a loop product). */
function uls_pp_product() {
	return wc_get_product( get_queried_object_id() );
}

/** Decorative inline SVG icon. */
function uls_pp_icon( $name ) {
	$paths = array(
		'res'   => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 15l3-3 3 3 2-2 3 3"/>',
		'file'  => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>',
		'cal'   => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
		'ratio' => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M7 10v4M17 10v4"/>',
		'coll'  => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
		'dl'    => '<path d="M12 3v12M7 10l5 5 5-5M4 21h16"/>',
		'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
		'mail'  => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
		'doc'   => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
		'check' => '<path d="M5 12l5 5 9-10"/>',
		'left'  => '<path d="M15 6l-6 6 6 6"/>',
		'right' => '<path d="M9 6l6 6-6 6"/>',
	);
	return '<svg class="uls-pp-ico" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/**
 * The product's collection: [term, ordered ids, index of this product] or null.
 *
 * @param WC_Product $product Product.
 * @return array|null
 */
function uls_pp_collection( $product ) {
	if ( ! function_exists( 'uplinksync_pcycle_pick_term' ) || ! function_exists( 'uplinksync_pcycle_ids' ) ) {
		return null;
	}
	$term = uplinksync_pcycle_pick_term( $product->get_id() );
	if ( ! $term || 'aerial-photography' === $term->slug ) {
		return null;
	}
	$ids = uplinksync_pcycle_ids( $term->term_id );
	$idx = array_search( $product->get_id(), $ids, true );
	return false === $idx ? null : array( $term, $ids, (int) $idx );
}

/**
 * Facts for the strip, all derived at render time. Structured meta wins when present
 * (`_uls_megapixels`, `_uls_captured` ISO date, `_uls_aspect` "16:9").
 *
 * @param WC_Product $product Product.
 * @return array
 */
function uls_pp_facts( $product ) {
	$parsed = uls_pp_parse_facts( $product->get_description() . ' ' . $product->get_short_description() );
	$mp     = (string) $product->get_meta( '_uls_megapixels' );
	$cap    = (string) $product->get_meta( '_uls_captured' );
	$facts  = array(
		'mp'       => '' !== $mp ? $mp : $parsed['mp'],
		'full_res' => $parsed['full_res'],
		'captured' => uls_pp_format_date( '' !== $cap ? $cap : $parsed['captured'] ),
		'drone'    => $parsed['drone'],
		'aspect'   => array(),
		'coll'     => uls_pp_collection( $product ),
	);
	$asp = (string) $product->get_meta( '_uls_aspect' );
	if ( preg_match( '/^(\d+):(\d+)$/', $asp, $m ) ) {
		$facts['aspect'] = uls_pp_aspect( (int) $m[1] * 1000, (int) $m[2] * 1000 );
	} else {
		$meta = wp_get_attachment_metadata( $product->get_image_id() );
		if ( is_array( $meta ) && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
			$facts['aspect'] = uls_pp_aspect( $meta['width'], $meta['height'] );
		}
	}
	return $facts;
}

/* ------------------------------------------------------------------ wiring */

add_action(
	'wp',
	function () {
		if ( ! uls_pp_active() ) {
			return;
		}
		// Buy box: no repeated paragraph, no rating, no "Categories:" (tags + breadcrumb carry them).
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
		add_action( 'woocommerce_single_product_summary', 'uls_pp_eyebrow', 4 );
		add_action( 'woocommerce_single_product_summary', 'uls_pp_price', 10 );
		add_action( 'woocommerce_single_product_summary', 'uls_pp_trust', 35 );

		// Media column: frame (gallery + chip), facts strip, about.
		add_action( 'woocommerce_before_single_product_summary', 'uls_pp_media_open', 1 );
		add_action( 'woocommerce_before_single_product_summary', 'uls_pp_frame_close', 21 );
		add_action( 'woocommerce_before_single_product_summary', 'uls_pp_media_close', 90 );

		// Below: table + FAQ band, collection strip. No upsells / related on photo products.
		remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
		remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
		add_action( 'woocommerce_after_single_product_summary', 'uls_pp_band', 12 );
		add_action( 'woocommerce_after_single_product_summary', 'uls_pp_strip', 18 );
		add_filter( 'uplinksync_pcycle_card', '__return_false' );

		add_filter( 'uls_lic_compare_url', 'uls_pp_compare_url' );
	}
);

function uls_pp_compare_url() {
	return '#uls-licences';
}

/** The comparison table, FAQ and About replace the Description / Licence / Additional information tabs. */
add_filter(
	'woocommerce_product_tabs',
	function ( $tabs ) {
		if ( uls_pp_active() ) {
			unset( $tabs['description'], $tabs['uls_licence'], $tabs['additional_information'], $tabs['reviews'] );
		}
		return $tabs;
	},
	120 // After storefront-truth (98), licence tiers (99) and the interim Code Snippet 123 (100).
);

add_filter(
	'body_class',
	function ( $classes ) {
		if ( uls_pp_active() ) {
			$classes[] = 'uls-pp';
		}
		return $classes;
	}
);

/** Hero image: the full preview with a srcset sized for the 7fr column, never lazy, high priority. */
add_filter(
	'woocommerce_gallery_image_size',
	function ( $size ) {
		return uls_pp_active() ? 'full' : $size;
	}
);
add_filter(
	'woocommerce_gallery_image_html_attachment_image_params',
	function ( $params, $attachment_id, $size, $main ) {
		if ( uls_pp_active() && $main ) {
			$params['sizes']         = '(min-width: 1240px) 680px, (min-width: 1024px) 56vw, 100vw';
			$params['fetchpriority'] = 'high';
			$params['loading']       = 'eager';
		}
		return $params;
	},
	10,
	4
);

/** Woo's lightbox trigger gets a name that contains its visible label ("View larger"). */
add_filter(
	'woocommerce_get_script_data',
	function ( $params, $handle ) {
		if ( 'wc-single-product' === $handle && is_array( $params ) && uls_pp_active() ) {
			$params['i18n_product_gallery_trigger_text'] = 'View larger image';
		}
		return $params;
	},
	10,
	2
);

/* ------------------------------------------------------------ media column */

function uls_pp_media_open() {
	echo '<div class="uls-pp-media"><div class="uls-pp-frame">';
}

function uls_pp_frame_close() {
	echo '<span class="uls-pp-wm">Watermarked preview · your file is clean</span></div>';
}

function uls_pp_media_close() {
	$product = uls_pp_product();
	if ( $product ) {
		uls_pp_facts_html( $product );
		uls_pp_about( $product );
	}
	echo '</div>';
}

function uls_pp_facts_html( $product ) {
	$f     = uls_pp_facts( $product );
	$cells = array();
	if ( '' !== $f['mp'] ) {
		$cells['res'] = array( 'Resolution', esc_html( $f['mp'] ) . ' MP', $f['full_res'] ? 'Full-resolution' : '' );
	}
	$cells['file'] = array( 'File', 'JPEG', 'Instant download' );
	if ( '' !== $f['captured'] ) {
		$cells['cal'] = array( 'Captured', esc_html( $f['captured'] ), $f['drone'] ? 'Drone, own work' : '' );
	}
	if ( $f['aspect'] ) {
		$cells['ratio'] = array( 'Aspect', esc_html( $f['aspect']['ratio'] ), $f['aspect']['orient'] );
	}
	if ( $f['coll'] ) {
		list( $term, $ids, $idx ) = $f['coll'];
		$link                     = get_term_link( $term );
		$name                     = is_wp_error( $link ) ? esc_html( $term->name ) : '<a href="' . esc_url( $link ) . '">' . esc_html( $term->name ) . '</a>';
		$cells['coll']            = array( 'Collection', $name, 'Photo ' . ( $idx + 1 ) . ' of ' . count( $ids ) );
	}
	echo '<dl class="uls-pp-facts" aria-label="Photo details">';
	foreach ( $cells as $icon => $c ) {
		echo '<div class="uls-pp-fact uls-pp-fact--' . esc_attr( $icon ) . '"><dt>' . uls_pp_icon( $icon ) . esc_html( $c[0] ) . '</dt><dd>' . $c[1] // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
			. ( '' !== $c[2] ? '<small>' . esc_html( $c[2] ) . '</small>' : '' ) . '</dd></div>';
	}
	echo '</dl>';
}

function uls_pp_about( $product ) {
	$html = uls_pp_about_html( $product->get_description() );
	if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
		$html = uls_pp_about_html( $product->get_short_description() );
	}
	$terms = get_the_terms( $product->get_id(), 'product_cat' );
	echo '<section class="uls-pp-about" aria-labelledby="uls-pp-about-h"><h2 id="uls-pp-about-h">About this photo</h2>';
	if ( '' !== trim( wp_strip_all_tags( $html ) ) ) {
		echo '<div class="uls-pp-about__body">' . wp_kses_post( wpautop( $html ) ) . '</div>';
	}
	if ( $terms && ! is_wp_error( $terms ) ) {
		echo '<p class="uls-pp-tags">';
		foreach ( $terms as $t ) {
			$link = get_term_link( $t );
			if ( ! is_wp_error( $link ) ) {
				echo '<a class="uls-pp-tag" href="' . esc_url( $link ) . '">' . esc_html( $t->name ) . '</a>';
			}
		}
		echo '</p>';
	}
	echo '</section>';
}

/* ----------------------------------------------------------------- buy box */

function uls_pp_eyebrow() {
	echo '<p class="uls-pp-eyebrow">Aerial photo licence</p>';
}

function uls_pp_price() {
	$product   = uls_pp_product();
	$state     = uls_lic_tier_state( $product );
	list( $on ) = uls_lic_split_state( $state );
	$tiers     = uls_lic_tiers();
	$note      = '';
	if ( $on ) {
		$cheapest = $on[0];
		foreach ( $on as $slug ) {
			$cheapest = $state[ $slug ]['price'] < $state[ $cheapest ]['price'] ? $slug : $cheapest;
		}
		$note = $tiers[ $cheapest ]['label'] . ' licence · digital download';
	}
	echo '<div class="uls-pp-pricebar"><p class="price">' . wp_kses_post( $product->get_price_html() ) . '</p>'
		. ( '' !== $note ? '<p class="uls-pp-pricenote">' . esc_html( $note ) . '</p>' : '' ) . '</div>';
}

function uls_pp_trust() {
	$third = function_exists( 'uls_lic_live' ) && uls_lic_live()
		? array( 'doc', 'Licence certificate (PDF)' )
		: array( 'mail', 'Receipt and download link by email' );
	echo '<ul class="uls-pp-trust">'
		. '<li>' . uls_pp_icon( 'dl' ) . 'Instant download after checkout</li>'
		. '<li>' . uls_pp_icon( 'shield' ) . 'Watermark-free, full-resolution file</li>'
		. '<li>' . uls_pp_icon( $third[0] ) . esc_html( $third[1] ) . '</li></ul>';
}

/* ------------------------------------------------------- table + FAQ band */

function uls_pp_band() {
	$product          = uls_pp_product();
	$state            = uls_lic_tier_state( $product );
	list( $on, $off ) = uls_lic_split_state( $state );
	$tiers            = uls_lic_tiers();
	$contact          = esc_url( home_url( '/contact/?topic=licensing' ) );
	$facts            = uls_pp_facts( $product );
	$cols             = $state ? array_keys( $state ) : array_keys( $tiers );

	$copy             = uls_pp_band_copy( $on, $off, array_combine( array_keys( $tiers ), array_column( $tiers, 'label' ) ) );
	$lede             = str_replace( '{contact}', $contact, $copy['lede'] );

	echo '<section class="uls-pp-band" id="uls-licences" aria-labelledby="uls-pp-cmp-h"><div class="uls-pp-two"><div class="uls-pp-cmpcol">';
	echo '<h2 class="uls-pp-h2" id="uls-pp-cmp-h">What you can do with each licence</h2><p class="uls-pp-lede">' . $lede . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
	echo '<p class="uls-pp-swipe">Swipe the table sideways to see every licence →</p>';
	echo '<div class="uls-pp-tblwrap" role="region" aria-labelledby="uls-pp-cmp-h" tabindex="0"><table class="uls-pp-cmp"><thead><tr><th scope="col">Use</th>';
	foreach ( $cols as $slug ) {
		$now = in_array( $slug, $on, true );
		echo '<th scope="col"' . ( $now ? ' class="is-now"' : '' ) . '>' . esc_html( $tiers[ $slug ]['label'] ) . '<small>'
			. ( $now ? wp_kses_post( uls_lic_money( $state[ $slug ]['price'] ) ) . ' · available' : 'Coming soon' ) . '</small></th>';
	}
	echo '</tr></thead><tbody>';
	foreach ( uls_lic_matrix() as $row ) {
		echo '<tr><th scope="row">' . esc_html( $row[0] ) . '</th>';
		foreach ( $cols as $slug ) {
			$v   = isset( $row[1][ $slug ] ) ? $row[1][ $slug ] : '';
			$now = in_array( $slug, $on, true );
			if ( $now && 'Yes' === $v ) {
				echo '<td class="is-now"><span class="uls-pp-yes">' . uls_pp_icon( 'check' ) . 'Yes</span></td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			} else {
				$cls = $now ? ( 'No' === $v ? 'is-now is-no' : 'is-now' ) : 'is-soon';
				echo '<td class="' . esc_attr( $cls ) . '">' . esc_html( $v ) . '</td>';
			}
		}
		echo '</tr>';
	}
	echo '</tbody></table></div>';
	echo '<p class="uls-pp-fine">No licence lets you resell, share or redistribute the file itself. <a href="' . esc_url( home_url( '/' . ULS_LIC_PAGE_SLUG . '/' ) ) . '">Full licence terms</a></p></div>';

	$mp  = '' !== $facts['mp'] ? ' (' . esc_html( $facts['mp'] ) . ' MP)' : '';
	$faq = array(
		array( 'What exactly do I get?', 'One full-resolution JPEG' . $mp . ' without the preview watermark, available to download straight after checkout, plus an email receipt with your licence summary.' ),
		array( 'Can I print it?', 'Yes. The Personal licence covers prints for your own home at any size. We don\'t ship a physical print.' ),
		array( 'I need it for my business', str_replace( '{contact}', $contact, $copy['business'] ) ),
		array( 'Can I get exclusive rights?', 'Exclusive use is quoted per photo. <a href="' . $contact . '">Request a quote</a>.' ),
		array( 'The download link didn\'t arrive', 'Check your spam folder, then reply to your receipt email and we\'ll re-send it within one business day.' ),
	);
	echo '<div class="uls-pp-faqcol"><h2 class="uls-pp-h2" id="uls-pp-faq-h">Questions</h2><p class="uls-pp-lede">Short answers before you buy.</p><div class="uls-pp-faq">';
	foreach ( $faq as $i => $q ) {
		echo '<details' . ( 0 === $i ? ' open' : '' ) . '><summary>' . esc_html( $q[0] ) . '</summary><p>' . wp_kses_post( $q[1] ) . '</p></details>';
	}
	echo '</div>';
	echo '<aside class="uls-pp-help" aria-labelledby="uls-pp-help-h"><h3 id="uls-pp-help-h">' . esc_html( $copy['help'][0] ) . '</h3><p>' . esc_html( $copy['help'][1] ) . '</p><a href="' . $contact . '">' . esc_html( $copy['help'][2] ) . '</a></aside>';
	echo '</div></div></section>';
}

/* --------------------------------------------------------- collection strip */

function uls_pp_strip() {
	$product = uls_pp_product();
	$c       = $product ? uls_pp_collection( $product ) : null;
	if ( ! $c || count( $c[1] ) < 2 ) {
		return;
	}
	list( $term, $ids, $idx ) = $c;
	$total = count( $ids );
	$link  = get_term_link( $term );
	$arrow = function ( $pid, $dir ) {
		$label = 'prev' === $dir ? 'Previous photo' : 'Next photo';
		$icon  = uls_pp_icon( 'prev' === $dir ? 'left' : 'right' );
		if ( ! $pid ) {
			return '<span class="uls-pp-arrow is-off" aria-hidden="true">' . $icon . '</span>';
		}
		return '<a class="uls-pp-arrow" rel="' . $dir . '" href="' . esc_url( get_permalink( $pid ) ) . '" aria-label="' . esc_attr( $label . ': ' . get_the_title( $pid ) ) . '">' . $icon . '</a>';
	};
	echo '<section class="uls-pp-coll" aria-labelledby="uls-pp-coll-h"><div class="uls-pp-coll__head"><div>'
		// Name in its own element: uplinksync-location-neutral keeps a standalone collection name as is.
		. '<p class="uls-pp-eyebrow"><span><span>' . esc_html( $term->name ) . '</span> · ' . (int) $total . ' photos</span></p>'
		. '<h2 class="uls-pp-h2" id="uls-pp-coll-h">More from this collection</h2></div>'
		. '<nav class="uls-pp-pn" aria-label="Browse this collection">'
		. $arrow( $idx > 0 ? $ids[ $idx - 1 ] : 0, 'prev' ) // phpcs:ignore WordPress.Security.EscapeOutput
		. $arrow( $idx < $total - 1 ? $ids[ $idx + 1 ] : 0, 'next' ) // phpcs:ignore WordPress.Security.EscapeOutput
		. ( is_wp_error( $link ) ? '' : '<a class="uls-pp-all" href="' . esc_url( $link ) . '">View all ' . (int) $total . ' →</a>' )
		. '</nav></div><ul class="uls-pp-strip">';
	foreach ( uls_pp_strip_window( $ids, $idx, 6 ) as $pid ) {
		$p = wc_get_product( $pid );
		if ( ! $p ) {
			continue;
		}
		$img = $p->get_image_id()
			? wp_get_attachment_image(
				$p->get_image_id(),
				'medium_large',
				false,
				array(
					'alt'     => '',
					'loading' => 'lazy',
					'sizes'   => '(min-width: 1240px) 190px, (min-width: 768px) 30vw, 42vw',
				)
			)
			: '';
		echo '<li><a href="' . esc_url( $p->get_permalink() ) . '">' . $img // phpcs:ignore WordPress.Security.EscapeOutput -- core markup.
			. '<b>' . esc_html( $p->get_name() ) . '</b><span class="uls-pp-strip__price">' . wp_kses_post( $p->get_price_html() ) . '</span></a></li>';
	}
	echo '</ul></section>';
}
