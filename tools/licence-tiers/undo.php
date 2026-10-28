<?php
/**
 * Undo tools/licence-tiers/migrate.php from its logs:  wp eval-file tools/licence-tiers/undo.php <logdir>
 * Deletes the variations, restores simple type, price, description/short description and the options;
 * deletes the pa_licence attribute only if migrate created it. Last resort: the mysqldump in DB-STEPS.md.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}
$log = isset( $args[0] ) ? rtrim( $args[0], '/' ) : '';
if ( '' === $log || ! is_readable( $log . '/migrate-log.tsv' ) ) {
	fwrite( STDERR, "usage: undo.php <logdir with migrate-log.tsv>\n" );
	exit( 1 );
}
$content = array();
foreach ( file( $log . '/content.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $l ) {
	$c                          = json_decode( $l, true );
	$content[ (int) $c['id'] ] = $c;
}
$n = 0;
foreach ( file( $log . '/migrate-log.tsv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $l ) {
	list( $id, $price, $vids ) = explode( "\t", $l );
	$id                        = (int) $id;
	foreach ( array_filter( explode( ',', $vids ) ) as $vid ) {
		$v = wc_get_product( (int) $vid );
		if ( $v ) {
			$v->delete( true );
		}
	}
	// Save through a WC_Product_Simple object so the data store writes product_type=simple and the prices.
	clean_post_cache( $id );
	$s = new WC_Product_Simple( $id );
	$s->set_attributes( array() );
	$s->set_default_attributes( array() );
	$s->set_regular_price( $price );
	$s->set_sale_price( '' );
	if ( isset( $content[ $id ] ) ) {
		$s->set_description( $content[ $id ]['description'] );
		$s->set_short_description( $content[ $id ]['short'] );
	}
	$s->save();
	wp_set_object_terms( $id, array(), 'pa_licence' );
	wc_delete_product_transients( $id );
	$n++;
}
// Deleting variations queues a deferred parent sync at shutdown, which would re-save the parent as
// variable and blank its price. The parents are simple again, so drop the queue.
$GLOBALS['wc_deferred_product_sync'] = array();
foreach ( file( $log . '/options.tsv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $l ) {
	$f = explode( "\t", $l );
	if ( 'option' === $f[0] ) {
		'__unset__' === $f[2] ? delete_option( $f[1] ) : update_option( $f[1], $f[2] );
		echo "restored option {$f[1]}\n";
	} elseif ( 'attribute_created' === $f[0] && '1' === $f[1] ) {
		$terms = get_terms( array( 'taxonomy' => 'pa_licence', 'hide_empty' => false, 'fields' => 'ids' ) );
		foreach ( is_array( $terms ) ? $terms : array() as $tid ) {
			wp_delete_term( $tid, 'pa_licence' );
		}
		wc_delete_attribute( (int) $f[2] );
		echo "deleted attribute {$f[2]}\n";
	}
}
echo "restored {$n} products to simple\n";
