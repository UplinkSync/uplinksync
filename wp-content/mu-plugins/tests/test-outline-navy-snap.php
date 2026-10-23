<?php
/**
 * Standalone regression test for the outline-navy migration helper.
 *
 * Run: php wp-content/mu-plugins/tests/test-outline-navy-snap.php
 */

error_reporting( E_ALL );

define( 'ABSPATH', __DIR__ );
if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$args ) {}
}

require __DIR__ . '/../uplinksync-outline-navy-snap.php';

$failures = 0;
function outline_navy_check( $name, $condition, $detail = '' ) {
	global $failures;
	if ( $condition ) {
		echo "  PASS  $name\n";
		return;
	}
	$failures++;
	echo "  FAIL  $name" . ( $detail ? "  ($detail)" : '' ) . "\n";
}

$services = implode(
	"\n",
	array(
		'<a style="border-color:#1E3155">Explore IT &amp; operations</a>',
		'<a style="border-color: #1E3155">Explore aerial services</a>',
		'<a style="padding:1rem;border-color:#1E3155;color:#173258">Estimate your project</a>',
	)
);
$migrated = uplinksync_outline_navy_snap_content( $services, $count );
outline_navy_check( 'measured services fixture: three replacements', 3 === $count, 'count=' . $count );
outline_navy_check( 'measured services fixture: deprecated navy removed', false === stripos( $migrated, '#1e3155' ) );
outline_navy_check( 'measured services fixture: three navy-900 values', 3 === substr_count( $migrated, '#173258' ) - 1 );

$lowercase = uplinksync_outline_navy_snap_content( 'style="border-color:#1e3155"', $count );
outline_navy_check( 'lowercase declaration is snapped', 'style="border-color:#173258"' === $lowercase && 1 === $count );

$json = '<!-- wp:button {"style":{"border":{"width":"1px","color":"#1E3155"}}} -->';
$json_migrated = uplinksync_outline_navy_snap_content( $json, $count );
outline_navy_check( 'Gutenberg border JSON is snapped', false !== strpos( $json_migrated, '"color":"#173258"' ) && 1 === $count );

$again = uplinksync_outline_navy_snap_content( $json_migrated, $count );
outline_navy_check( 'second pass is idempotent', $again === $json_migrated && 0 === $count );

$non_borders = 'color:#1E3155;background:#1E3155;not-border-color:#1E3155;{"color":"#1E3155"}';
$untouched = uplinksync_outline_navy_snap_content( $non_borders, $count );
outline_navy_check( 'non-border uses are untouched', $untouched === $non_borders && 0 === $count );

$other = 'style="border-color:#5697F3;color:#2F6FC4"';
$other_untouched = uplinksync_outline_navy_snap_content( $other, $count );
outline_navy_check( 'unrelated hexes are untouched', $other_untouched === $other && 0 === $count );

echo "\n" . ( 0 === $failures ? "ALL PASS\n" : "$failures FAILURE(S)\n" );
exit( 0 === $failures ? 0 : 1 );
