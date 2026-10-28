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

/** Price per tier slug for a variable product (from its live variations). */
function uls_lic_variation_prices( $product ) {
	$out = array();
	foreach ( $product->get_children() as $vid ) {
		$v = wc_get_product( $vid );
		if ( $v && $v->is_purchasable() ) {
			$at   = $v->get_attributes();
			$slug = isset( $at[ ULS_LIC_ATTRIBUTE ] ) ? sanitize_title( $at[ ULS_LIC_ATTRIBUTE ] ) : '';
			if ( '' !== $slug ) {
				$out[ $slug ] = (float) wc_get_price_to_display( $v );
			}
		}
	}
	return $out;
}

/** Radio-card picker next to the (visually hidden) native select that Woo's variation JS drives. */
add_filter(
	'woocommerce_dropdown_variation_attribute_options_html',
	function ( $html, $args ) {
		if ( ULS_LIC_ATTRIBUTE !== $args['attribute'] || empty( $args['product'] ) ) {
			return $html;
		}
		$prices = uls_lic_variation_prices( $args['product'] );
		$sel    = isset( $args['selected'] ) && '' !== $args['selected'] ? $args['selected'] : 'personal';
		$f      = '<fieldset class="uls-lic-picker"><legend>Choose a licence</legend>';
		foreach ( uls_lic_tiers() as $slug => $t ) {
			if ( ! isset( $prices[ $slug ] ) ) {
				continue;
			}
			$id = 'uls-lic-' . $slug;
			$f .= '<label class="uls-lic-opt" for="' . esc_attr( $id ) . '">'
				. '<input type="radio" name="uls_lic_pick" id="' . esc_attr( $id ) . '" value="' . esc_attr( $slug ) . '"' . checked( $sel, $slug, false ) . '>'
				. '<span class="uls-lic-opt__name">' . esc_html( $t['label'] ) . '</span>'
				. '<span class="uls-lic-opt__price">' . wp_kses_post( wc_price( $prices[ $slug ], array( 'decimals' => 0 ) ) ) . '</span>'
				. '<span class="uls-lic-opt__sum">' . esc_html( $t['summary'] )
				. ( 'required' === $t['credit'] ? ' <span class="uls-lic-badge">Credit required</span>' : '' ) . '</span>'
				. '</label>';
		}
		$f .= '</fieldset>';
		return '<div class="uls-lic-selwrap">' . $html . '</div>' . $f;
	},
	10,
	2
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

/** "From $35" instead of "$35.00 – $399.00" on tiered products. */
add_filter(
	'woocommerce_variable_price_html',
	function ( $html, $product ) {
		if ( ! uls_lic_is_tiered( $product ) ) {
			return $html;
		}
		return '<span class="uls-lic-from">From</span> ' . wc_price( $product->get_variation_price( 'min', true ), array( 'decimals' => 0 ) );
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

/** Credit note, Exclusive quote link and upgrade path, under the add-to-cart form. */
add_action(
	'woocommerce_after_add_to_cart_form',
	function () {
		global $product;
		if ( ! uls_lic_is_tiered( $product ) ) {
			return;
		}
		$title = $product->get_name();
		$tiers = uls_lic_tiers();
		echo '<div class="uls-lic-more">';
		$own = uls_lic_owned( $product->get_id() );
		if ( $own ) {
			list( $tier, $lid ) = $own[0];
			$ups                = array();
			foreach ( $tiers as $slug => $t ) {
				$d = uls_lic_upgrade_price( $tier, $slug );
				if ( $d > 0 && uls_lic_rank( $slug ) > uls_lic_rank( $tier ) ) {
					$ups[] = esc_html( $t['label'] ) . ' +$' . (int) $d;
				}
			}
			echo '<p class="uls-lic-owned"><strong>You hold ' . ( 'extended' === $tier ? 'an ' : 'a ' ) . esc_html( $tiers[ $tier ]['label'] ) . ' licence for this image</strong> (' . esc_html( $lid ) . ').';
			if ( $ups ) {
				echo ' Upgrade for the price difference: ' . implode( ' · ', $ups ) . '. <a href="' . esc_url( home_url( '/contact/?subject=' . rawurlencode( 'Licence upgrade ' . $lid ) ) ) . '">Request an upgrade</a>';
			}
			echo '</p>';
		}
		echo '<p><strong>Credit line</strong> for &ldquo;Commercial with credit&rdquo;: &ldquo;Aerial by UplinkSync&rdquo; with a link to uplinksync.com on every use (the plain URL in print).</p>';
		if ( ! $own ) {
			echo '<p><strong>Bought Personal and now need it for business?</strong> Upgrade any time for the price difference (Personal to Commercial: $' . (int) uls_lic_upgrade_price( 'personal', 'commercial' ) . ').</p>';
		}
		echo '<p class="uls-lic-links"><a href="' . esc_url( home_url( '/' . ULS_LIC_PAGE_SLUG . '/' ) ) . '">Compare licences in full</a>'
			. '<a href="' . esc_url( home_url( '/contact/?subject=' . rawurlencode( 'Exclusive licence: ' . $title ) ) ) . '">Exclusive rights? Request a quote</a></p>';
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
