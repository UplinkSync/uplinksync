<?php
/**
 * Standalone tests for the single public-phone switch (uplinksync-phone.php) and its
 * interaction with the location-neutral layer.
 *
 * Run: php wp-content/mu-plugins/tests/test-phone.php
 */

error_reporting( E_ALL );

define( 'ABSPATH', __DIR__ );
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
$GLOBALS['uls_test_phone_option'] = '';
function get_option( $name, $default = false ) {
	return 'uplinksync_phone' === $name ? $GLOBALS['uls_test_phone_option'] : $default;
}

require __DIR__ . '/../uplinksync-phone.php';
require __DIR__ . '/../uplinksync-location-neutral.php';

$failures = 0;
function phone_check( $name, $condition ) {
	global $failures;
	echo ( $condition ? '  PASS  ' : '  FAIL  ' ) . $name . "\n";
	if ( ! $condition ) {
		$failures++;
	}
}

$doc = '<p><a href="tel:+18015550134">(801) 555-0134</a> <a href="tel:+12089952704">+1 (208) 995-2704</a> call +1 (208) 995-2704</p>';
$schema = array(
	'@type'        => 'Organization',
	'telephone'    => '+18015550134',
	'contactPoint' => array( '@type' => 'ContactPoint', 'telephone' => '+12089952704' ),
);

// 1. Empty (default): no phone is public; everything is stripped as before.
phone_check( 'empty option -> uplinksync_phone() is empty', '' === uplinksync_phone() );
phone_check( 'empty option -> e164 is empty', '' === uplinksync_phone_e164() );
$out = uplinksync_location_neutral_structural_rewrites( $doc );
phone_check( 'empty -> every tel: anchor stripped', false === strpos( $out, 'tel:' ) );
phone_check( 'empty -> retired 208 number stripped', false === strpos( $out, '995' ) );
$clean = uplinksync_location_neutral_schema_value( $schema );
phone_check( 'empty -> schema telephone dropped', ! isset( $clean['telephone'] ) && ! isset( $clean['contactPoint']['telephone'] ) );

// 2. Too short = treated as not set (never a broken tel: link).
$GLOBALS['uls_test_phone_option'] = '555-0134';
phone_check( 'fewer than 10 digits -> not published', '' === uplinksync_phone() );

// 3. Published number: it alone survives text, anchors and schema.
$GLOBALS['uls_test_phone_option'] = '(801) 555-0134';
phone_check( 'set -> display value kept', '(801) 555-0134' === uplinksync_phone() );
phone_check( 'set -> E.164 normalised', '+18015550134' === uplinksync_phone_e164() );
phone_check( 'matches with or without country code', uplinksync_phone_is_public( 'tel:801.555.0134' ) && uplinksync_phone_is_public( '+1 801 555 0134' ) );
$out = uplinksync_location_neutral_structural_rewrites( $doc );
phone_check( 'set -> published tel: anchor kept', false !== strpos( $out, 'href="tel:+18015550134"' ) );
phone_check( 'set -> retired 208 anchor still stripped', false === strpos( $out, 'tel:+12089952704' ) );
phone_check( 'set -> retired 208 plain text still stripped', false === strpos( $out, '995' ) );
$clean = uplinksync_location_neutral_schema_value( $schema );
phone_check( 'set -> schema telephone kept when it is the published number', isset( $clean['telephone'] ) && '+18015550134' === $clean['telephone'] );
phone_check( 'set -> other schema telephone dropped', ! isset( $clean['contactPoint']['telephone'] ) );

// 4. Markup is never trusted from the option.
$GLOBALS['uls_test_phone_option'] = '<b>801 555 0134</b>';
phone_check( 'tags stripped, display normalised', '(801) 555-0134' === uplinksync_phone() );

// 5. Validation: junk or placeholders are never published (display, links or schema).
foreach ( array( 'TBD', 'call us', '555-0100', '(801) 555-013', '123-456-7890', '+1 (801) 555-0134 ext 2' ) as $bad ) {
	$GLOBALS['uls_test_phone_option'] = $bad;
	phone_check( "invalid '{$bad}' -> nothing published", '' === uplinksync_phone() && '' === uplinksync_phone_e164() );
}
$GLOBALS['uls_test_phone_option'] = '1-801-555-0134';
phone_check( 'leading 1 accepted', '+18015550134' === uplinksync_phone_e164() && '(801) 555-0134' === uplinksync_phone() );
$GLOBALS['uls_test_phone_option'] = '+44 20 7946 0958';
phone_check( 'explicit international accepted', '+442079460958' === uplinksync_phone_e164() && '+442079460958' === uplinksync_phone() );

// 6. JSON-LD last pass: every telephone = the validated number, or removed.
$graph = array( '@graph' => array( array( '@type' => 'Organization', 'name' => 'UplinkSync', 'telephone' => 'TBD raw', 'contactPoint' => array( '@type' => 'ContactPoint', 'telephone' => '801-555-0134 raw', 'contactType' => 'customer service' ) ) ) );
$GLOBALS['uls_test_phone_option'] = '801.555.0134';
$out = uplinksync_phone_schema( $graph );
phone_check( 'schema telephones forced to validated E.164', '+18015550134' === $out['@graph'][0]['telephone'] && '+18015550134' === $out['@graph'][0]['contactPoint']['telephone'] );
$GLOBALS['uls_test_phone_option'] = 'TBD';
$out = uplinksync_phone_schema( $graph );
phone_check( 'invalid phone -> schema telephone removed', ! isset( $out['@graph'][0]['telephone'] ) );
phone_check( 'empty ContactPoint dropped', ! isset( $out['@graph'][0]['contactPoint'] ) );
phone_check( 'other schema data untouched', 'UplinkSync' === $out['@graph'][0]['name'] );

if ( $failures ) {
	echo "\n{$failures} failure(s)\n";
	exit( 1 );
}
echo "\nAll phone checks passed.\n";
