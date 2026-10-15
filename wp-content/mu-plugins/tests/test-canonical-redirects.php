<?php
/**
 * Standalone regression test for the canonical redirect map.
 *
 * Run: php wp-content/mu-plugins/tests/test-canonical-redirects.php
 */

error_reporting( E_ALL );

define( 'ABSPATH', __DIR__ );
if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$args ) {}
}

require __DIR__ . '/../uplinksync-canonical-redirects.php';

$failures = 0;
function check( $name, $condition, $detail = '' ) {
	global $failures;
	if ( $condition ) {
		echo "  PASS  $name\n";
		return;
	}

	$failures++;
	echo "  FAIL  $name" . ( $detail ? "  ($detail)" : '' ) . "\n";
}

$map = uplinksync_canonical_redirect_map();

check(
	'quote alias points directly to the live guided quote flow',
	isset( $map['/quote/'] ) && '/request-quote/' === $map['/quote/'],
	$map['/quote/'] ?? 'missing'
);
check(
	'quote redirect is single-hop',
	! isset( $map[ $map['/quote/'] ?? '' ] )
);
check(
	'existing canonical redirects remain present',
	isset( $map['/about-4/'], $map['/services-2/'] )
);

echo "\n" . ( 0 === $failures ? "ALL PASS\n" : "$failures FAILURE(S)\n" );
exit( 0 === $failures ? 0 : 1 );
