<?php
/**
 * Location switch tests (neutral default / utah pack / readiness gate).
 *
 * Each scenario runs in its own PHP process (fixtures/location-mode-child.php)
 * because the mode is decided when the mu-plugin loads.
 *
 * Run: php wp-content/mu-plugins/tests/test-location-mode.php
 */
error_reporting( E_ALL );
$failures = 0;
function ulm_check( $name, $cond, $detail = '' ) {
	global $failures;
	if ( $cond ) {
		echo "  PASS  {$name}\n";
		return;
	}
	$failures++;
	echo "  FAIL  {$name}" . ( $detail ? " ({$detail})" : '' ) . "\n";
}
function ulm_run( array $scn ) {
	$dir = dirname( __DIR__ );
	$cmd = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/location-mode-child.php' ) . ' ' . escapeshellarg( json_encode( $scn ) ) . ' ' . escapeshellarg( $dir . '/uplinksync-location-neutral.php' ) . ' ' . escapeshellarg( $dir . '/uplinksync-location-utah.php' ) . ' 2>&1';
	$raw = shell_exec( $cmd );
	$out = json_decode( (string) $raw, true );
	if ( ! is_array( $out ) ) {
		echo "  FAIL  scenario did not produce JSON: " . substr( (string) $raw, 0, 400 ) . "\n";
		exit( 1 );
	}
	return $out;
}
function ulm_org( $out ) {
	foreach ( $out['graph']['@graph'] as $node ) {
		if ( false !== strpos( (string) ( $node['@id'] ?? '' ), '#organization' ) && count( $node ) > 1 ) {
			return $node;
		}
	}
	return array();
}
$idaho = '/Idaho|Boise|Rexburg|Ammon|Blackfoot|Eastern|Lakeharbor|208\)? ?995/i';

// ---- 1. Neutral (default: no option, no constant) -------------------------------------
echo "neutral (default)\n";
$n = ulm_run( array() );
$base = json_decode( file_get_contents( __DIR__ . '/fixtures/location-neutral-baseline.json' ), true );
ulm_check( 'mode is neutral', 'neutral' === $n['mode'] );
ulm_check( 'neutral filters registered', ( $n['hooks']['render_block'] ?? 0 ) === 1 && ( $n['hooks']['the_content'] ?? 0 ) === 1 && ( $n['hooks']['rank_math/frontend/title'] ?? 0 ) === 1 );
ulm_check( 'no utah SEO filter on rank_math/frontend/title (only the neutraliser)', 1 === $n['hooks']['rank_math/frontend/title'] );
ulm_check( 'content output byte-identical to pre-switch baseline', $base['fragment'] === $n['fragment'] && $base['block'] === $n['block'] );
ulm_check( 'title/meta/OG output byte-identical to baseline', $base['seo'] === $n['seo'] );
$org = ulm_org( $n );
$stripped = $n['graph'];
foreach ( $stripped['@graph'] as $i => $node ) {
	unset( $stripped['@graph'][ $i ]['founder'] );
}
ulm_check( 'schema identical to baseline except founder', $base['graph'] === $stripped );
ulm_check( 'neutral schema adds founder Doug Irwin', 'Doug Irwin' === ( $org['founder']['name'] ?? '' ) );
ulm_check( 'no telephone when UPLINKSYNC_PHONE unset', ! isset( $org['telephone'] ) );
ulm_check( 'neutral schema has no location data', ! isset( $org['areaServed'] ) && ! isset( $org['address'] ) && 'Organization' === ( $org['@type'][0] ?? '' ) );
ulm_check( 'service-area slot renders nothing', '' === $n['area'] );
ulm_check( 'location-line slots are neutral wording', 'Managed IT · Websites · Aerial photo &amp; video' === $n['slots']['eyebrow'] && '' === $n['slots']['intro'] && '' === $n['slots']['bogus'] );
ulm_check( 'no Utah string in any neutral slot', ! preg_match( '/Utah/i', json_encode( $n['slots'] ) ) );
ulm_check( 'shortcodes registered even in neutral mode', array( 'uplinksync_service_area', 'uplinksync_location_line' ) === $n['shortcodes'] );

// ---- 2. Neutral + phone ------------------------------------------------------------------
echo "neutral + UPLINKSYNC_PHONE\n";
$np  = ulm_run( array( 'const' => array( 'UPLINKSYNC_PHONE' => '+1 801 555 0100' ) ) );
$orgp = ulm_org( $np );
ulm_check( 'telephone added when set', '+1 801 555 0100' === ( $orgp['telephone'] ?? '' ) );
ulm_check( 'phone does not add location data', ! isset( $orgp['areaServed'] ) && ! isset( $orgp['address'] ) );

// ---- 3. Utah requested but NOT ready -> refuses -------------------------------------------
echo "utah requested, not ready\n";
foreach ( array(
	'option'   => array( 'options' => array( 'uplinksync_location_mode' => 'utah' ) ),
	'constant' => array( 'const' => array( 'UPLINKSYNC_LOCATION_MODE' => 'utah' ) ),
) as $label => $scn ) {
	$r = ulm_run( $scn );
	ulm_check( "{$label}: stays neutral", 'neutral' === $r['mode'] );
	ulm_check( "{$label}: output identical to neutral baseline", $base['seo'] === $r['seo'] && $base['fragment'] === $r['fragment'] );
	ulm_check( "{$label}: admin notice explains refusal", false !== strpos( $r['notice'], 'NOT active' ) && false !== strpos( $r['notice'], 'uplinksync_location_utah_ready' ) );
	ulm_check( "{$label}: service area empty", '' === $r['area'] );
}
$r = ulm_run( array( 'options' => array() ) );
ulm_check( 'saving utah while not ready is rejected (old value kept)', 'neutral' === $r['pre_update'] );
ulm_check( 'no admin notice in plain neutral', '' === $r['notice'] );
foreach ( array( 'Utah', 'UTAH', 'true', '1', '', null ) as $bad ) {
	$r = ulm_run( array( 'options' => array( 'uplinksync_location_mode' => $bad, 'uplinksync_location_utah_ready' => '1' ) ) );
	if ( 'Utah' === $bad || 'UTAH' === $bad || 'true' === $bad || '1' === $bad || '' === $bad || null === $bad ) {
		ulm_check( 'junk mode value ' . var_export( $bad, true ) . ' fails closed to neutral', 'neutral' === $r['mode'] );
	}
}
$r = ulm_run( array( 'options' => array( 'uplinksync_location_mode' => 'utah', 'uplinksync_location_utah_ready' => '0' ) ) );
ulm_check( 'ready=0 does not activate', 'neutral' === $r['mode'] );

// ---- 4. Utah active (option + ready) ------------------------------------------------------
echo "utah active\n";
$u = ulm_run( array( 'options' => array( 'uplinksync_location_mode' => 'utah', 'uplinksync_location_utah_ready' => '1', 'uplinksync_phone' => '', 'uplinksync_location_base_city' => '' ) ) );
ulm_check( 'mode is utah', 'utah' === $u['mode'] );
ulm_check( 'neutral filters NOT registered in utah mode', ! isset( $u['hooks']['render_block'] ) && ! isset( $u['hooks']['the_content'] ) && ! isset( $u['hooks']['document_title_parts'] ) && ! isset( $u['hooks']['rank_math/opengraph/facebook/og_image_alt'] ) );
ulm_check( 'utah json_ld filter is the only json_ld filter', 1 === $u['hooks']['rank_math/json_ld'] );
ulm_check( 'content passes through untouched by the neutraliser', false !== strpos( $u['fragment'], 'Eastern Idaho' ) );
$seo_pages = array( 'home' => 'Utah County IT Support', 'services' => 'Managed IT, Web & Cybersecurity in Utah County', 'drone-services' => 'Utah County Drone Photo', 'about' => 'About UplinkSync', 'contact' => 'Contact UplinkSync' );
foreach ( $seo_pages as $page => $needle ) {
	$s = $u['seo'][ $page ];
	ulm_check( "{$page}: Utah title", 0 === strpos( $s['title'], $needle ) && $s['title'] === $s['og'] && $s['title'] === $s['doc'], $s['title'] );
	ulm_check( "{$page}: title <= 60, description <= 155", strlen( $s['title'] ) <= 60 && strlen( $s['desc'] ) <= 155 && $s['desc'] === $s['tw_desc'], strlen( $s['title'] ) . '/' . strlen( $s['desc'] ) );
	ulm_check( "{$page}: no Idaho string in title/meta", ! preg_match( $idaho, $s['title'] . $s['desc'] . $s['og'] . $s['tw_desc'] ) );
}
ulm_check( 'unmapped page keeps its own title', 'Local IT Support & Managed Services | UplinkSync Idaho Falls' === $u['seo']['other']['title'] );
$org = ulm_org( $u );
ulm_check( 'org node is ProfessionalService, same @id', 'ProfessionalService' === ( $org['@type'] ?? '' ) && 'https://uplinksync.com/#organization' === ( $org['@id'] ?? '' ) );
ulm_check( 'founder Doug Irwin', 'Doug Irwin' === ( $org['founder']['name'] ?? '' ) );
ulm_check( 'NO streetAddress / address / geo', ! isset( $org['address'] ) && ! isset( $org['geo'] ) && false === stripos( json_encode( $org ), 'streetAddress' ) );
ulm_check( 'no telephone when phone unset', ! isset( $org['telephone'] ) && ! isset( $org['contactPoint']['telephone'] ) );
$names = array_column( array_filter( $org['areaServed'] ?? array(), static function ( $a ) { return 'City' === ( $a['@type'] ?? '' ); } ), 'name' );
ulm_check( 'areaServed lists the Utah County cities', array( 'Provo', 'Orem', 'Lehi', 'American Fork', 'Pleasant Grove', 'Spanish Fork', 'Springville' ) === $names && 'Utah County' === ( $org['areaServed'][0]['name'] ?? '' ) );
ulm_check( 'reference-only publisher node untouched', array( '@id' => 'https://uplinksync.com/#organization' ) === $u['graph']['@graph'][1]['publisher'] );
ulm_check( 'only one org node in graph', 1 === count( array_filter( $u['graph']['@graph'], static function ( $x ) { return 'ProfessionalService' === ( $x['@type'] ?? '' ); } ) ) );
ulm_check( 'schema (org node) leaks no Idaho string', ! preg_match( $idaho, json_encode( $org ) ) );
ulm_check( 'unconfirmed claims omitted (credentials, priceRange, rating)', ! isset( $org['founder']['hasCredential'] ) && ! isset( $org['priceRange'] ) && ! isset( $org['aggregateRating'] ) );
ulm_check( 'service area block rendered', false !== strpos( $u['area'], 'Where we work' ) && false !== strpos( $u['area'], '<li>Lehi</li>' ) && false !== strpos( $u['area'], '<li>Eagle Mountain</li>' ) );
ulm_check( 'block makes no "based in" claim until base city is set', false === strpos( $u['area'], 'based in' ) );
ulm_check( 'quote link uses the estimator modal trigger (href*=request-quote)', false !== strpos( $u['area'], 'href="https://uplinksync.com/request-quote/"' ) )  ;
ulm_check( 'block leaks no Idaho string', ! preg_match( $idaho, $u['area'] ) );
ulm_check( 'location-line slots are Utah wording', false !== strpos( $u['slots']['eyebrow'], 'Utah County' ) && false !== strpos( $u['slots']['intro'], 'Provo, Orem, Lehi' ) && '' === $u['slots']['bogus'] );
ulm_check( 'no Idaho string in any slot', ! preg_match( $idaho, json_encode( $u['slots'] ) ) );

// ---- 5. Utah with base city, travel policy and phone ---------------------------------------------
echo "utah + owner details\n";
$ud = ulm_run( array( 'const' => array( 'UPLINKSYNC_PHONE' => '801-555-0100', 'UPLINKSYNC_LOCATION_MODE' => 'utah' ), 'options' => array( 'uplinksync_location_utah_ready' => '1', 'uplinksync_location_base_city' => 'Lehi <b>', 'uplinksync_location_travel_policy' => 'on-site anywhere in Utah County, no travel charge' ) ) );
$od = ulm_org( $ud );
ulm_check( 'constant override activates utah when ready', 'utah' === $ud['mode'] );
ulm_check( 'telephone present only because phone is set', '801-555-0100' === ( $od['telephone'] ?? '' ) && '801-555-0100' === ( $od['contactPoint']['telephone'] ?? '' ) );
ulm_check( 'still no streetAddress with phone set', ! isset( $od['address'] ) && false === stripos( json_encode( $od ), 'streetAddress' ) );
ulm_check( 'base city escaped and used', false !== strpos( $ud['area'], "We're based in Lehi &lt;b&gt;, Utah" ) && false === strpos( $ud['area'], '<b>' ) );
ulm_check( 'travel policy used', false !== strpos( $ud['area'], 'no travel charge' ) );

// Rollback: constant neutral beats option utah+ready.
$rb = ulm_run( array( 'const' => array( 'UPLINKSYNC_LOCATION_MODE' => 'neutral' ), 'options' => array( 'uplinksync_location_mode' => 'utah', 'uplinksync_location_utah_ready' => '1' ) ) );
ulm_check( 'constant neutral overrides option (emergency rollback)', 'neutral' === $rb['mode'] && $base['seo'] === $rb['seo'] );

echo $failures ? "\n{$failures} FAILURE(S)\n" : "\nAll location-mode checks passed.\n";
exit( $failures ? 1 : 0 );
