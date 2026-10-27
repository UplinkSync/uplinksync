<?php
/**
 * Standalone regression test for the Quiet Light guards (MR-B, feat/quiet-light-home).
 *
 * Four legacy output-buffer mu-plugins rewrite markup the new header / front page no longer
 * contains. Each now returns the document untouched when the Quiet Light marker is present
 * (`uls-ql-header` for the site-wide header plugins, `uls-ql-home` for the homepage ones),
 * and must keep working exactly as before on legacy documents.
 *
 * Run: php wp-content/mu-plugins/tests/test-quiet-light-guards.php
 */

error_reporting( E_ALL );

define( 'ABSPATH', __DIR__ );
foreach ( array( 'add_action', 'add_filter' ) as $uls_fn ) {
	if ( ! function_exists( $uls_fn ) ) {
		eval( "function {$uls_fn}( ...\$a ) {}" ); // phpcs:ignore Squiz.PHP.Eval -- test stub.
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $u ) { return $u; }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $s ) { return $s; }
}
if ( ! function_exists( 'plugins_url' ) ) {
	function plugins_url( $p = '', $f = '' ) { return 'https://example.test/wp-content/mu-plugins/' . $p; }
}

require __DIR__ . '/../uplinksync-header-logo.php';
require __DIR__ . '/../uplinksync-primary-nav.php';
require __DIR__ . '/../uplinksync-homepage-nav-ctas.php';
require __DIR__ . '/../uplinksync-home-proof-trust.php';

$failures = 0;
function check( $name, $condition ) {
	global $failures;
	echo ( $condition ? '  PASS  ' : '  FAIL  ' ) . $name . "\n";
	if ( ! $condition ) {
		$failures++;
	}
}

// Legacy documents (the shapes each plugin was written for).
$legacy_logo  = '<html><body><p class="hostinger-ai-site-title wp-block-site-title has-large-font-size"><a href="/" rel="home">UplinkSync</a></p></body></html>';
$legacy_nav   = '<html><body><nav class="wp-block-navigation is-horizontal"><div class="wp-block-navigation__responsive-container-content"></div></nav></body></html>';
$legacy_home  = '<html><body><a href="https://uplinksync.com/services-2/">x</a></body></html>';
$legacy_proof = '<html><body><div class="wp-block-group alignfull hostinger-ai-solid-block x"></div></body></html>';

check( 'header-logo still rewrites the legacy header', uplinksync_logo_rewrite( $legacy_logo ) !== $legacy_logo );
check( 'primary-nav still injects into the legacy empty nav', uplinksync_primary_nav_rewrite( $legacy_nav ) !== $legacy_nav );
check( 'homepage-nav-ctas still rewrites legacy links', uplinksync_homepage_nav_rewrite( $legacy_home ) !== $legacy_home );
check( 'proof-trust still injects under the legacy hero', uplinksync_proof_trust_rewrite( $legacy_proof ) !== $legacy_proof );

// The same documents carrying the Quiet Light markers must pass through byte-identical.
$ql_header = '<div class="wp-block-group uls-ql-header">';
$ql_home   = '<main class="wp-block-group uls-ql-home" id="main">';
$with      = static function ( $doc, $marker ) {
	return str_replace( '<body>', '<body>' . $marker, $doc );
};

$d = $with( $legacy_logo, $ql_header );
check( 'header-logo skips the Quiet Light header', uplinksync_logo_rewrite( $d ) === $d );
$d = $with( $legacy_nav, $ql_header );
check( 'primary-nav skips the Quiet Light header', uplinksync_primary_nav_rewrite( $d ) === $d );
$d = $with( $legacy_home, $ql_home );
check( 'homepage-nav-ctas skips the Quiet Light front page', uplinksync_homepage_nav_rewrite( $d ) === $d );
$d = $with( $legacy_proof, $ql_home );
check( 'proof-trust skips the Quiet Light front page', uplinksync_proof_trust_rewrite( $d ) === $d );

// Homepage guards must NOT fire on an interior page that only has the new header.
$d = $with( $legacy_home, $ql_header );
check( 'homepage-nav-ctas is not skipped by the header marker alone', uplinksync_homepage_nav_rewrite( $d ) !== $d );

if ( $failures ) {
	echo "\n{$failures} failure(s)\n";
	exit( 1 );
}
echo "\nAll Quiet Light guard checks passed.\n";
