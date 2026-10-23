<?php
/**
 * Standalone regression tests for the UPL-944 location-neutral render layer.
 *
 * Run: php wp-content/mu-plugins/tests/test-location-neutral.php
 */

error_reporting( E_ALL );

define( 'ABSPATH', __DIR__ );
function add_action( ...$args ) {}
function add_filter( ...$args ) {}

require __DIR__ . '/../uplinksync-location-neutral.php';

$failures = 0;
function upl944_check( $name, $condition, $detail = '' ) {
	global $failures;
	if ( $condition ) {
		echo "  PASS  {$name}\n";
		return;
	}
	$failures++;
	echo "  FAIL  {$name}" . ( $detail ? " ({$detail})" : '' ) . "\n";
}

$html = <<<'HTML'
<!doctype html><html><head>
<title>Contact UplinkSync | Free IT &amp; Drone Quote — Idaho Falls</title>
<meta name="description" content="Contact UplinkSync for local IT support, managed services &amp; drone work in Idaho Falls, Ammon &amp; Rexburg. Free quote — a human replies in one business day.">
<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":["Organization","LocalBusiness"],"name":"UplinkSync","telephone":"+12089952704","areaServed":[{"@type":"City","name":"Idaho Falls"}],"address":{"@type":"PostalAddress","streetAddress":"3100 N Lakeharbor Lane","addressLocality":"Boise","addressRegion":"ID"},"url":"https://uplinksync.com/"}]}</script>
</head><body>
<h1>Cybersecurity for Eastern Idaho businesses</h1>
<p>Licensed &amp; certified · trusted by local businesses</p>
<p>Our managed services are remote-first, so we support clients across the Rexburg–Idaho Falls–Blackfoot–Pocatello corridor and the surrounding area without needing to be on-site for most work. Onsite visits are available when a job genuinely needs hands-on attention; travel outside the corridor is quoted case-by-case.</p>
<label>Distance from Idaho Falls area (miles)</label>
<img src="/wp-content/uploads/idaho-falls.jpg" alt="Aerial view over Pocatello, Idaho — Brooklyn's Playground. Aerial by UplinkSync.">
<a href="tel:+12089952704">+1 (208) 995-2704</a>
<a href="/quote/">Request a quote</a><a href="/book/">Book</a>
<script>window.galleryUrl="/product-category/idaho-falls/"; window.label="Distance from Idaho Falls:";</script>
</body></html>
HTML;

$out = uplinksync_location_neutral_rewrite( $html );

$claims_surface = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', '', $out );
$claims_surface = preg_replace( '/\s(?:href|src|srcset)=("|\').*?\1/isu', '', $claims_surface );
foreach ( array( 'Idaho', 'Utah', 'Rexburg', 'Pocatello', 'Boise' ) as $forbidden ) {
	upl944_check( "removes visible {$forbidden}", false === stripos( $claims_surface, $forbidden ) );
}
upl944_check( 'keeps media URL unchanged', false !== strpos( $out, '/wp-content/uploads/idaho-falls.jpg' ) );
upl944_check( 'keeps script URL unchanged', false !== strpos( $out, '/product-category/idaho-falls/' ) );
upl944_check( 'neutralises estimator script label', false !== strpos( $out, 'window.label="Estimated travel:"' ) );
upl944_check( 'keeps quote link', false !== strpos( $out, 'href="/quote/"' ) );
upl944_check( 'keeps booking link', false !== strpos( $out, 'href="/book/"' ) );
upl944_check( 'neutral service wording present', false !== strpos( $out, 'Serving clients remotely' ) || false !== strpos( $out, 'remote-first' ) );
upl944_check( 'neutral estimator label present', false !== strpos( $out, 'Estimated round-trip travel (miles)' ) );
upl944_check( 'visible aerial credit retained', false !== strpos( $out, 'Aerial by UplinkSync' ) );

preg_match( '#<script[^>]*application/ld\+json[^>]*>(.*?)</script>#is', $out, $schema_match );
$schema = json_decode( $schema_match[1] ?? '', true );
upl944_check( 'JSON-LD remains valid', JSON_ERROR_NONE === json_last_error(), json_last_error_msg() );
foreach ( array( 'LocalBusiness', 'areaServed', 'streetAddress', '+12089952704' ) as $forbidden ) {
	upl944_check( "removes schema {$forbidden}", false === stripos( $schema_match[1] ?? '', $forbidden ) );
}
upl944_check( 'removes rendered phone', false === strpos( $out, '208) 995-2704' ) );
upl944_check( 'schema keeps organization URL', 'https://uplinksync.com/' === ( $schema['@graph'][0]['url'] ?? '' ) );
upl944_check( 'schema type is Organization only', 'Organization' === ( $schema['@graph'][0]['@type'][0] ?? '' ) && 1 === count( $schema['@graph'][0]['@type'] ?? array() ) );

$legal = '<html><body><p>Idaho law governs these Service Terms. Disputes will be brought in courts located in&nbsp;<strong>Bonneville County, Idaho</strong>.</p><p>Business Information:<br>UplinkSync LLC<br>3100 N Lakeharbor Lane, Suite 176 #508<br>Boise, ID 83703<br>United States</p></body></html>';
$legal_out = uplinksync_location_neutral_rewrite( $legal );
upl944_check( 'legal jurisdiction is neutral', false !== strpos( $legal_out, 'applicable law' ) && false !== strpos( $legal_out, 'court of competent jurisdiction' ) );
upl944_check( 'legal address is removed', false === stripos( $legal_out, 'Lakeharbor' ) && false === stripos( $legal_out, 'Boise' ) );

$twice = uplinksync_location_neutral_rewrite( $out );
upl944_check( 'rewrite is idempotent', $twice === $out );

echo "\n" . ( 0 === $failures ? "ALL PASS\n" : "{$failures} FAILURE(S)\n" );
exit( 0 === $failures ? 0 : 1 );
