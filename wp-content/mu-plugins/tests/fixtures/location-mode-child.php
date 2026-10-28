<?php
/**
 * Child process for test-location-mode.php (one process per scenario because the
 * mode is decided at mu-plugin load time).
 *
 * Usage: php location-mode-child.php <scenario-json> <neutral-file> [utah-file]
 * Prints a JSON report of the registered hooks and of sample outputs.
 */
error_reporting( E_ALL );
$scn = json_decode( $argv[1], true );
define( 'ABSPATH', __DIR__ );
if ( isset( $scn['const'] ) ) {
	foreach ( $scn['const'] as $k => $v ) {
		define( $k, $v );
	}
}
$GLOBALS['hooks']      = array();
$GLOBALS['shortcodes'] = array();
$GLOBALS['actions']    = array();
$GLOBALS['options']    = $scn['options'] ?? array();
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = array( $prio, $cb ); }
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['actions'][ $hook ][] = $cb; }
function add_shortcode( $tag, $cb ) { $GLOBALS['shortcodes'][ $tag ] = $cb; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['options'] ) ? $GLOBALS['options'][ $k ] : $d; }
function apply_filters( $hook, $value, ...$rest ) {
	$list = $GLOBALS['hooks'][ $hook ] ?? array();
	usort( $list, static function ( $a, $b ) { return $a[0] <=> $b[0]; } );
	foreach ( $list as $f ) {
		$value = $f[1]( $value );
	}
	return $value;
}
function home_url( $p = '/' ) { return 'https://uplinksync.com' . $p; }
function shortcode_atts( $d, $a ) { return array_merge( $d, array_intersect_key( (array) $a, $d ) ); }
function is_front_page() { return 'home' === ( $GLOBALS['scn']['page'] ?? '' ); }
function get_queried_object() { $s = $GLOBALS['scn']['page'] ?? ''; return $s ? (object) array( 'post_name' => $s ) : null; }
function current_user_can() { return true; }
$GLOBALS['scn'] = $scn;

require $argv[2];
if ( ! empty( $argv[3] ) ) {
	require $argv[3];
}

$graph = array(
	'@context' => 'https://schema.org',
	'@graph'   => array(
		array( '@type' => array( 'Organization', 'LocalBusiness' ), '@id' => 'https://uplinksync.com/#organization', 'name' => 'UplinkSync', 'url' => 'https://uplinksync.com/', 'telephone' => '+12089952704', 'areaServed' => array( array( '@type' => 'City', 'name' => 'Idaho Falls' ) ), 'address' => array( '@type' => 'PostalAddress', 'streetAddress' => '3100 N Lakeharbor Lane', 'addressLocality' => 'Boise' ), 'logo' => array( '@type' => 'ImageObject', 'url' => 'https://uplinksync.com/logo.svg' ) ),
		array( '@type' => 'WebSite', '@id' => 'https://uplinksync.com/#website', 'name' => 'UplinkSync', 'publisher' => array( '@id' => 'https://uplinksync.com/#organization' ) ),
		array( '@type' => 'WebPage', '@id' => 'https://uplinksync.com/#webpage', 'name' => 'Local IT Support & Managed Services | UplinkSync Idaho Falls' ),
	),
);
$fragment = '<section><h1>Cybersecurity for Eastern Idaho businesses</h1><p>Call (208) 995-2704 &#183; UplinkSync LLC, 3100 N Lakeharbor Lane, Suite 176 #508, Boise, ID 83703, United States.</p><a href="tel:+12089952704">Call</a></section>';
$title    = 'Local IT Support & Managed Services | UplinkSync Idaho Falls';
$desc     = 'Contact UplinkSync for local IT support, managed services & drone work in Idaho Falls, Ammon & Rexburg. Free quote — a human replies in one business day.';

$pages = array( 'home', 'services', 'drone-services', 'about', 'contact', 'other' );
$seo   = array();
foreach ( $pages as $p ) {
	$GLOBALS['scn']['page'] = $p;
	$seo[ $p ] = array(
		'title'   => apply_filters( 'rank_math/frontend/title', $title ),
		'desc'    => apply_filters( 'rank_math/frontend/description', $desc ),
		'og'      => apply_filters( 'rank_math/opengraph/facebook/og_title', $title ),
		'tw_desc' => apply_filters( 'rank_math/opengraph/twitter/twitter_description', $desc ),
		'doc'     => apply_filters( 'pre_get_document_title', $title ),
	);
}
$GLOBALS['scn']['page'] = 'home';

$slots = array();
foreach ( array( 'eyebrow', 'home_h1', 'services_h1', 'drone_h1', 'about_h1', 'contact_h1', 'intro', 'bogus' ) as $slot ) {
	$slots[ $slot ] = isset( $GLOBALS['shortcodes']['uplinksync_location_line'] ) ? $GLOBALS['shortcodes']['uplinksync_location_line']( array( 'slot' => $slot ) ) : null;
}
$out = array(
	'hooks'     => array_map(
		static function ( $l ) { return count( $l ); },
		$GLOBALS['hooks']
	),
	'graph'     => apply_filters( 'rank_math/json_ld', $graph ),
	'fragment'  => apply_filters( 'the_content', $fragment ),
	'block'     => apply_filters( 'render_block', $fragment ),
	'seo'       => $seo,
	'slots'     => $slots,
	'area'      => isset( $GLOBALS['shortcodes']['uplinksync_service_area'] ) ? $GLOBALS['shortcodes']['uplinksync_service_area']() : null,
	'shortcodes' => array_keys( $GLOBALS['shortcodes'] ),
	'mode'      => function_exists( 'uplinksync_location_mode' ) ? uplinksync_location_mode() : null,
	'notice'    => (function () {
		if ( empty( $GLOBALS['actions']['admin_notices'] ) ) { return ''; }
		ob_start();
		foreach ( $GLOBALS['actions']['admin_notices'] as $cb ) { $cb(); }
		return ob_get_clean();
	})(),
	'pre_update' => (function () {
		$list = $GLOBALS['hooks']['pre_update_option_uplinksync_location_mode'] ?? array();
		return $list ? $list[0][1]( 'utah', 'neutral' ) : null;
	})(),
);
echo json_encode( $out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
