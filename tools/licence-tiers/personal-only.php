<?php
/**
 * INTERIM production step (owner instruction 2026-10-28): photo products -> variable products with the four
 * pa_licence variations, but ONLY Personal ($35) is purchasable. Commercial with credit / Commercial /
 * Extended are created at their approved prices and set OUT OF STOCK (Woo core refuses them in the cart,
 * the ?add-to-cart URL and the Store API) with an "Available soon" variation description. No store options
 * change; download limit/expiry stay as each product has them today. Standalone (no mu-plugin needed).
 *
 *   wp eval-file personal-only.php dry   <logdir>
 *   wp eval-file personal-only.php apply <logdir> [product_id]
 * Undo: wp eval-file undo.php <logdir>   (same log format as migrate.php)
 */

if ( ! defined( 'ABSPATH' ) || ! function_exists( 'wc_get_products' ) ) {
	fwrite( STDERR, "run with wp eval-file on a WooCommerce site\n" );
	exit( 1 );
}
$mode = isset( $args[0] ) ? $args[0] : 'dry';
$log  = isset( $args[1] ) ? rtrim( $args[1], '/' ) : '';
if ( ! in_array( $mode, array( 'dry', 'apply' ), true ) || '' === $log ) {
	fwrite( STDERR, "usage: personal-only.php dry|apply <logdir> [product_id]\n" );
	exit( 1 );
}
$apply = 'apply' === $mode;
if ( $apply ) {
	wp_mkdir_p( $log );
}
$w = function ( $file, $line ) use ( $apply, $log ) {
	if ( $apply ) {
		file_put_contents( $log . '/' . $file, $line . "\n", FILE_APPEND );
	}
};

$contact = '/contact/?topic=licensing';
$tiers   = array(
	'personal'          => array( 'Personal', '35', true, 'Personal licence: private, non-commercial use only (prints for your own home, gifts you don\'t sell, and personal posts that earn no money).' ),
	'commercial-credit' => array( 'Commercial with credit', '79', false, '' ),
	'commercial'        => array( 'Commercial', '119', false, '' ),
	'extended'          => array( 'Extended', '399', false, '' ),
);
$soon    = 'Commercial licences can\'t be bought online yet. <a href="' . $contact . '">Contact us for commercial use</a>.';
$old     = 'Personal + commercial license; no physical item is shipped.';
$new     = 'Licence: Personal (private, non-commercial use). Commercial licences are coming soon; for business use, <a href="' . $contact . '">contact us</a>. Need exclusive rights? <a href="' . $contact . '">Request a quote</a>. No physical item is shipped.';

$attr_id = wc_attribute_taxonomy_id_by_name( 'licence' );
$created = 0;
if ( ! $attr_id && $apply ) {
	$attr_id = wc_create_attribute( array( 'name' => 'Licence', 'slug' => 'licence', 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false ) );
	if ( is_wp_error( $attr_id ) ) {
		fwrite( STDERR, $attr_id->get_error_message() . "\n" );
		exit( 1 );
	}
	$created = 1;
}
echo "attribute pa_licence id={$attr_id}" . ( $created ? ' (created)' : '' ) . "\n";
$w( 'options.tsv', "attribute_created\t{$created}\t{$attr_id}" );
if ( $apply && ! taxonomy_exists( 'pa_licence' ) ) {
	register_taxonomy( 'pa_licence', array( 'product', 'product_variation' ), array( 'hierarchical' => false, 'show_ui' => false, 'query_var' => false, 'rewrite' => false ) );
}
$term_ids = array();
$i        = 0;
foreach ( $tiers as $slug => $t ) {
	$term = $apply ? get_term_by( 'slug', $slug, 'pa_licence' ) : null;
	if ( $apply && ! $term ) {
		$r = wp_insert_term( $t[0], 'pa_licence', array( 'slug' => $slug ) );
		if ( is_wp_error( $r ) ) {
			fwrite( STDERR, $r->get_error_message() . "\n" );
			exit( 1 );
		}
		$term = get_term( $r['term_id'], 'pa_licence' );
	}
	if ( $term ) {
		update_term_meta( $term->term_id, 'order', $i );
		$term_ids[] = (int) $term->term_id;
	}
	$i++;
}

$ids = wc_get_products( array( 'status' => 'publish', 'limit' => -1, 'return' => 'ids', 'category' => array( 'aerial-photography' ) ) );
if ( ! empty( $args[2] ) ) {
	$ids = array_values( array_intersect( $ids, array( (int) $args[2] ) ) );
}
$done = 0;
$skip = 0;
$nocopy = 0;
foreach ( $ids as $id ) {
	$p = wc_get_product( $id );
	if ( ! $p || ! $p->is_type( 'simple' ) || ! $p->is_downloadable() || 1 !== count( $p->get_downloads() ) || '' !== $p->get_sale_price() ) {
		$skip++;
		continue;
	}
	if ( false === strpos( $p->get_short_description() . $p->get_description(), $old ) ) {
		$nocopy++;
	}
	if ( ! $apply ) {
		$done++;
		continue;
	}
	$downloads = array();
	foreach ( $p->get_downloads() as $dl ) {
		$downloads[] = array( 'name' => $dl->get_name(), 'file' => $dl->get_file() );
	}
	$limit  = $p->get_download_limit();
	$expiry = $p->get_download_expiry();
	$w( 'content.jsonl', wp_json_encode( array( 'id' => $id, 'description' => $p->get_description(), 'short' => $p->get_short_description() ) ) );
	$old_price = $p->get_regular_price();

	wp_set_object_terms( $id, 'variable', 'product_type' );
	wc_delete_product_transients( $id );
	$v = new WC_Product_Variable( $id );
	$a = new WC_Product_Attribute();
	$a->set_id( $attr_id );
	$a->set_name( 'pa_licence' );
	$a->set_options( $term_ids );
	$a->set_position( 0 );
	$a->set_visible( true );
	$a->set_variation( true );
	$v->set_attributes( array( $a ) );
	$v->set_default_attributes( array( 'pa_licence' => 'personal' ) );
	$v->set_description( str_replace( $old, $new, $v->get_description() ) );
	$v->set_short_description( str_replace( $old, $new, $v->get_short_description() ) );
	$v->save();

	$vids = array();
	$n    = 0;
	foreach ( $tiers as $slug => $t ) {
		$var = new WC_Product_Variation();
		$var->set_parent_id( $id );
		$var->set_attributes( array( 'pa_licence' => $slug ) );
		$var->set_regular_price( $t[1] );
		$var->set_virtual( true );
		$var->set_downloadable( true );
		$var->set_downloads( $downloads );
		$var->set_download_limit( $limit );
		$var->set_download_expiry( $expiry );
		$var->set_stock_status( $t[2] ? 'instock' : 'outofstock' );
		$var->set_description( $t[2] ? $t[3] : $soon );
		$var->set_menu_order( $n++ );
		$var->set_status( 'publish' );
		$vids[] = $var->save();
	}
	WC_Product_Variable::sync( $id );
	wc_delete_product_transients( $id );
	$w( 'migrate-log.tsv', $id . "\t" . $old_price . "\t" . implode( ',', $vids ) );
	$done++;
}
echo ( $apply ? 'converted' : 'would convert' ) . " {$done}, skipped {$skip}, without the expected copy line {$nocopy}\n";
