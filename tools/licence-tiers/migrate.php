<?php
/**
 * Licence-tier migration: 122 simple photo products -> variable products with pa_licence variations.
 *
 *   wp eval-file tools/licence-tiers/migrate.php dry  <logdir>
 *   wp eval-file tools/licence-tiers/migrate.php apply <logdir> [product_id]   (optional: one product, for a trial)
 *
 * Needs the uplinksync-licence-tiers mu-plugin (tier prices + download limits come from its config).
 * Idempotent: products already variable are skipped. Writes <logdir>/migrate-log.tsv,
 * <logdir>/content.jsonl and <logdir>/options.tsv for tools/licence-tiers/undo.php.
 * Take a mysqldump first (DB-STEPS.md). Never run on production without owner sign-off.
 */

if ( ! defined( 'ABSPATH' ) || ! function_exists( 'uls_lic_tiers' ) ) {
	fwrite( STDERR, "run with wp eval-file, uplinksync-licence-tiers mu-plugin required\n" );
	exit( 1 );
}
$mode = isset( $args[0] ) ? $args[0] : 'dry';
$log  = isset( $args[1] ) ? rtrim( $args[1], '/' ) : '';
if ( ! in_array( $mode, array( 'dry', 'apply' ), true ) || '' === $log ) {
	fwrite( STDERR, "usage: migrate.php dry|apply <logdir>\n" );
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

$old_copy = 'Personal + commercial license';
$new_copy = 'Choose a Personal, Commercial or Extended licence';

// 1. Global attribute + terms.
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
if ( $apply && ! taxonomy_exists( ULS_LIC_ATTRIBUTE ) ) {
	register_taxonomy( ULS_LIC_ATTRIBUTE, array( 'product', 'product_variation' ), array( 'hierarchical' => false, 'show_ui' => false, 'query_var' => false, 'rewrite' => false ) );
}
$term_ids = array();
$i        = 0;
foreach ( uls_lic_tiers() as $slug => $t ) {
	$term = $apply ? get_term_by( 'slug', $slug, ULS_LIC_ATTRIBUTE ) : null;
	if ( $apply && ! $term ) {
		$r = wp_insert_term( $t['label'], ULS_LIC_ATTRIBUTE, array( 'slug' => $slug ) );
		if ( is_wp_error( $r ) ) {
			fwrite( STDERR, $r->get_error_message() . "\n" );
			exit( 1 );
		}
		$term = get_term( $r['term_id'], ULS_LIC_ATTRIBUTE );
	}
	if ( $term ) {
		update_term_meta( $term->term_id, 'order', $i );
		$term_ids[] = (int) $term->term_id;
	}
	$i++;
}
echo 'terms: ' . implode( ',', $term_ids ) . "\n";

// 2. Store options (G5 download gating + feature flag), old values logged for undo.
$opts = array(
	'woocommerce_downloads_require_login'               => 'yes',
	'woocommerce_enable_guest_checkout'                 => 'no',
	'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
	'uls_licence_tiers_v2'                              => '1',
);
foreach ( $opts as $k => $v ) {
	$old = get_option( $k, '__unset__' );
	echo "option {$k}: " . var_export( $old, true ) . " -> {$v}\n";
	$w( 'options.tsv', "option\t{$k}\t" . ( is_scalar( $old ) ? $old : '__unset__' ) );
	if ( $apply ) {
		update_option( $k, $v );
	}
}

// 3. Products.
$ids  = wc_get_products( array( 'status' => 'publish', 'limit' => -1, 'return' => 'ids', 'category' => array( 'aerial-photography' ) ) );
if ( ! empty( $args[2] ) ) {
	$ids = array_values( array_intersect( $ids, array( (int) $args[2] ) ) );
}
$done = 0;
$skip = 0;
foreach ( $ids as $id ) {
	$p = wc_get_product( $id );
	if ( ! $p || ! $p->is_type( 'simple' ) || ! $p->is_downloadable() || 1 !== count( $p->get_downloads() ) ) {
		$skip++;
		continue;
	}
	$downloads = array();
	foreach ( $p->get_downloads() as $dl ) {
		$downloads[] = array( 'name' => $dl->get_name(), 'file' => $dl->get_file() );
	}
	if ( ! $apply ) {
		$done++;
		continue;
	}
	$w( 'content.jsonl', wp_json_encode( array( 'id' => $id, 'description' => $p->get_description(), 'short' => $p->get_short_description() ) ) );
	$old_price = $p->get_regular_price();

	wp_set_object_terms( $id, 'variable', 'product_type' );
	wc_delete_product_transients( $id );
	$v = new WC_Product_Variable( $id );
	$a = new WC_Product_Attribute();
	$a->set_id( $attr_id );
	$a->set_name( ULS_LIC_ATTRIBUTE );
	$a->set_options( $term_ids );
	$a->set_position( 0 );
	$a->set_visible( true );
	$a->set_variation( true );
	$v->set_attributes( array( $a ) );
	$v->set_default_attributes( array( ULS_LIC_ATTRIBUTE => 'personal' ) );
	$v->set_description( str_replace( $old_copy, $new_copy, $v->get_description() ) );
	$v->set_short_description( str_replace( $old_copy, $new_copy, $v->get_short_description() ) );
	$v->save();

	$vids = array();
	$n    = 0;
	foreach ( uls_lic_tiers() as $slug => $t ) {
		$var = new WC_Product_Variation();
		$var->set_parent_id( $id );
		$var->set_attributes( array( ULS_LIC_ATTRIBUTE => $slug ) );
		$var->set_regular_price( (string) $t['price'] );
		$var->set_virtual( true );
		$var->set_downloadable( true );
		$var->set_downloads( $downloads );
		$var->set_download_limit( ULS_LIC_DL_LIMIT );
		$var->set_download_expiry( ULS_LIC_DL_EXPIRY );
		$var->set_menu_order( $n++ );
		$var->set_status( 'publish' );
		$vids[] = $var->save();
	}
	WC_Product_Variable::sync( $id );
	wc_delete_product_transients( $id );
	$w( 'migrate-log.tsv', $id . "\t" . $old_price . "\t" . implode( ',', $vids ) );
	$done++;
}
echo ( $apply ? 'migrated' : 'would migrate' ) . " {$done}, skipped {$skip}\n";
