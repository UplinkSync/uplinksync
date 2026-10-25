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

$service_area = '<html><body><p>UplinkSync serves the&nbsp;<strong>Rexburg–Idaho Falls–Blackfoot–Pocatello corridor and immediate surrounding areas</strong>.</p></body></html>';
$service_out  = uplinksync_location_neutral_rewrite( $service_area );
upl944_check( 'service area uses owner-directed neutral wording', false !== strpos( $service_out, 'serves clients remotely, with on-site work available by arrangement' ) );
upl944_check( 'service area city list is removed', false === stripos( $service_out, 'Rexburg' ) && false === stripos( $service_out, 'Idaho' ) );

// Regression (2026-10-23): an invalid '#'-delimited regex (unescaped "&#183;") made preg_replace return NULL and the
// fragment filter blanked EVERY rendered block on staging, while the forbidden-word checks above still passed.
upl944_check( 'rewrite returns non-empty HTML', is_string( $out ) && strlen( $out ) > 200 );
upl944_check( 'no PCRE error after rewrite', PREG_NO_ERROR === preg_last_error(), preg_last_error_msg() );
$dot     = '<html><body><main><p>Contact us anytime &#183; UplinkSync LLC, 3100 N Lakeharbor Lane, Suite 176 #508, Boise, ID 83703, United States.</p><p>Keep this sentence.</p></main></body></html>';
$dot_out = uplinksync_location_neutral_rewrite( $dot );
upl944_check( 'middot address line removed', false === stripos( $dot_out, 'Lakeharbor' ) );
upl944_check( 'content around middot address kept', false !== strpos( $dot_out, 'Keep this sentence.' ) && false !== strpos( $dot_out, '<main>' ) );
$frag = uplinksync_location_neutral_fragment( '<div class="wp-block-group"><p>Plain block with no location.</p></div>' );
upl944_check( 'render_block fragment never blanks', false !== strpos( $frag, 'Plain block with no location.' ) );

$twice = uplinksync_location_neutral_rewrite( $out );
upl944_check( 'rewrite is idempotent', $twice === $out );

// ---- Post-go-live regressions (2026-10-25) ---------------------------------------------
$all_outputs = array( $out, $legal_out, $service_out, $dot_out, $frag );

// 1a: URL-bearing JSON-LD properties are never rewritten.
$img_url = '/wp-content/themes/uplinksync-child/assets/media/aerials/stills/idaho-falls-full.jpg';
$ld_page = '<html><head><script type="application/ld+json">' . json_encode( array(
	'@context' => 'https://schema.org',
	'@graph'   => array(
		array( '@type' => 'ImageObject', '@id' => 'https://uplinksync.com/#primaryimage', 'url' => 'https://uplinksync.com' . $img_url, 'contentUrl' => $img_url, 'caption' => 'Idaho Falls, Idaho' ),
		array( '@type' => 'WebPage', '@id' => 'https://uplinksync.com/drone-services/#webpage', 'url' => 'https://uplinksync.com/drone-services/', 'image' => array( 'https://uplinksync.com' . $img_url ), 'primaryImageOfPage' => array( '@id' => 'https://uplinksync.com/#primaryimage' ), 'name' => 'Aerial work across Idaho and Utah' ),
		array( '@type' => 'Product', '@id' => 'https://uplinksync.com/product/pocatello-aerial-view-no-20/#product', 'url' => 'https://uplinksync.com/product/pocatello-aerial-view-no-20/', 'name' => 'Pocatello Aerial View No. 20', 'image' => 'https://uplinksync.com/wp-content/uploads/2026/07/030-city-pocatello-master.jpg' ),
	),
), JSON_UNESCAPED_SLASHES ) . '</script></head><body><p>x</p></body></html>';
$ld_out = uplinksync_location_neutral_rewrite( $ld_page );
$all_outputs[] = $ld_out;
preg_match( '#<script[^>]*application/ld\+json[^>]*>(.*?)</script>#is', $ld_out, $ldm );
$ld = json_decode( $ldm[1] ?? '', true );
upl944_check( 'post-live: JSON-LD parses after rewrite', JSON_ERROR_NONE === json_last_error() && is_array( $ld ), json_last_error_msg() );
upl944_check( 'post-live: ImageObject url byte-identical', ( 'https://uplinksync.com' . $img_url ) === ( $ld['@graph'][0]['url'] ?? '' ) );
upl944_check( 'post-live: ImageObject contentUrl byte-identical', $img_url === ( $ld['@graph'][0]['contentUrl'] ?? '' ) );
upl944_check( 'post-live: WebPage.image list byte-identical', array( 'https://uplinksync.com' . $img_url ) === ( $ld['@graph'][1]['image'] ?? null ) );
upl944_check( 'post-live: primaryImageOfPage @id kept', 'https://uplinksync.com/#primaryimage' === ( $ld['@graph'][1]['primaryImageOfPage']['@id'] ?? '' ) );
upl944_check( 'post-live: Product url byte-identical', 'https://uplinksync.com/product/pocatello-aerial-view-no-20/' === ( $ld['@graph'][2]['url'] ?? '' ) );
upl944_check( 'post-live: Product @id byte-identical', 'https://uplinksync.com/product/pocatello-aerial-view-no-20/#product' === ( $ld['@graph'][2]['@id'] ?? '' ) );
upl944_check( 'post-live: Product name keeps photo subject', 'Pocatello Aerial View No. 20' === ( $ld['@graph'][2]['name'] ?? '' ) );
upl944_check( 'post-live: schema caption still neutralised', 'Aerial cityscape' === ( $ld['@graph'][0]['caption'] ?? '' ) );
upl944_check( 'post-live: schema text still neutralised', false === stripos( $ld['@graph'][1]['name'] ?? 'Idaho', 'Idaho' ) );

// 1c: photo-subject product titles, collection labels and chips survive; prose does not.
foreach ( array( 'Pocatello Aerial View No. 20', 'Idaho Falls Aerial View No. 05', 'Kaysville Aerial View No. 03', 'Saratoga Springs Aerial View No. 01', 'Palisades Reservoir Mountain Vista No. 78' ) as $title ) {
	upl944_check( "post-live: product title unchanged: {$title}", $title === uplinksync_location_neutral_text( $title ) );
	$t_out = uplinksync_location_neutral_text( $title . ' - UplinkSync' );
	upl944_check( "post-live: document title unchanged: {$title}", ( $title . ' - UplinkSync' ) === $t_out );
}
foreach ( array( 'Pocatello - UplinkSync', 'Idaho Falls – UplinkSync', 'Saratoga Springs | UplinkSync' ) as $archive_title ) {
	upl944_check( "post-live: collection archive title unchanged: {$archive_title}", $archive_title === uplinksync_location_neutral_seo_text( $archive_title ) );
}
upl944_check( 'post-live: non-subject town title still neutralised', false === strpos( uplinksync_location_neutral_seo_text( 'Rexburg - UplinkSync' ), 'Rexburg' ) );
$shop = '<html><body><h1 class="product_title">Idaho Falls Aerial View No. 05</h1>'
	. '<a href="/product-category/aerial-photography/pocatello/" rel="tag">Pocatello</a>'
	. '<button type="button" class="chip" data-filter="kaysville" aria-pressed="false">Kaysville<span class="n">5</span></button>'
	. '<button class="uls-chip"> Saratoga Springs </button><span class="ulsbz-place">Idaho Falls</span>'
	. '<nav class="uls-pcyc"><div class="uls-pcyc__bar"><span>Pocatello · 4 of 20</span></div></nav>'
	. '<a aria-label="Add to cart: “Pocatello Aerial View No. 20”" href="?add-to-cart=526">Add to cart</a>'
	. '<img src="/x.jpg" alt="Aerial view — Pocatello, by UplinkSync.">'
	. '<p>Original aerial drone photograph — Idaho Falls Aerial View No. 01. Idaho Falls, ID, captured 2026-01-10.</p>'
	. '</body></html>';
$shop_out = uplinksync_location_neutral_rewrite( $shop );
$all_outputs[] = $shop_out;
upl944_check( 'post-live: product H1 keeps subject', false !== strpos( $shop_out, '<h1 class="product_title">Idaho Falls Aerial View No. 05</h1>' ) );
upl944_check( 'post-live: term link label kept', false !== strpos( $shop_out, 'rel="tag">Pocatello</a>' ) );
upl944_check( 'post-live: shop chip label kept', false !== strpos( $shop_out, '>Kaysville<span class="n">5</span>' ) );
upl944_check( 'post-live: padded chip label kept', false !== strpos( $shop_out, '> Saratoga Springs </button>' ) );
upl944_check( 'post-live: gallery place label kept', false !== strpos( $shop_out, '<span class="ulsbz-place">Idaho Falls</span>' ) );
upl944_check( 'post-live: collection position bar kept', false !== strpos( $shop_out, '<span>Pocatello · 4 of 20</span>' ) );
upl944_check( 'post-live: add-to-cart aria-label kept', false !== strpos( $shop_out, 'aria-label="Add to cart: “Pocatello Aerial View No. 20”"' ) );
// Unlisted prose deliberately falls through to the word-level safety net (kept out of
// the negative assertion below, which covers only enumerated site strings).
$prose_out = uplinksync_location_neutral_rewrite( '<html><body><p>We photograph clients in Pocatello and across Utah.</p></body></html>' );
upl944_check( 'post-live: prose place names still neutralised', false === strpos( $prose_out, 'Pocatello' ) && false === strpos( $prose_out, 'Utah' ) );
upl944_check( 'post-live: tile alt neutral and grammatical', false !== strpos( $shop_out, 'alt="Aerial view by UplinkSync."' ) );
upl944_check( 'post-live: excerpt keeps title, neutralises caption', false !== strpos( $shop_out, 'Idaho Falls Aerial View No. 01. Aerial cityscape, captured' ) );
upl944_check( 'post-live: hrefs untouched', false !== strpos( $shop_out, 'href="/product-category/aerial-photography/pocatello/"' ) );

// 1b: exact SEO outputs for the homepage and /services/.
$seo = array(
	'Local IT Support & Managed Services | UplinkSync Idaho Falls' => 'Local IT Support & Managed Services | UplinkSync',
	'Local IT Support &amp; Managed Services | UplinkSync Idaho Falls' => 'Local IT Support &amp; Managed Services | UplinkSync',
	'IT Services & Managed IT Support | UplinkSync Idaho Falls' => 'IT Services & Managed IT Support | UplinkSync',
	'UplinkSync provides local IT support, managed services, and business automation for small and midsize businesses in Idaho Falls, Ammon & Rexburg. Get a free quote today.' => 'UplinkSync provides local IT support, managed services, and business automation for small and midsize businesses, remotely and on-site by arrangement. Get a free quote today.',
	'Managed IT support, network setup, Microsoft 365, device management, and business automation for Idaho Falls-area businesses. See how UplinkSync can help. Request a quote.' => 'Managed IT support, network setup, Microsoft 365, device management, and business automation for small and growing businesses. See how UplinkSync can help. Request a quote.',
	'IT support Idaho Falls' => 'IT support',
	'managed IT services Idaho Falls' => 'managed IT services',
	'One local Eastern Idaho team runs your front desk — billing, waivers, timetable, card reader — and films your belt tests, cut per student. Two problems, one phone number.' => 'One dedicated team runs your front desk — billing, waivers, timetable, card reader — and films your belt tests, cut per student. Two problems, one phone number.',
	'Websites, hosting, day-to-day IT, automation and aerial media for small businesses and trades in Eastern Idaho — one local team, one number to call.' => 'Websites, hosting, day-to-day IT, automation and aerial media for small businesses and trades — one dedicated team, one number to call.',
	'UplinkSync works with real estate brokerages, martial arts academies, and small businesses and trades across Eastern Idaho — one local team for web, hosting, IT and aerial media.' => 'UplinkSync works with real estate brokerages, martial arts academies, and small businesses and trades — one dedicated team for web, hosting, IT and aerial media.',
	'Managed IT · Web · Aerial Photo &amp; Video · Idaho Falls' => 'Managed IT · Web · Aerial Photo &amp; Video',
	'Managed IT, your website, and photo and video from the air — one call to one certified local team, right here in eastern Idaho. We aim to respond within four business hours.' => 'Managed IT, your website, and photo and video from the air — one call to one certified team. We aim to respond within four business hours.',
	'Something down? We aim to get back to you within four business hours — a real person, here in eastern Idaho.' => 'Something down? We aim to get back to you within four business hours — a real person.',
	'We operate across the Eastern Idaho corridor and evaluate each site for airspace restrictions.' => 'We evaluate each site for airspace restrictions.',
	'Ammon, Idaho · Aerial by UplinkSync — uplinksync.com' => 'Aerial by UplinkSync — uplinksync.com',
	'Aerial view of downtown Rexburg, Idaho — Main Street and the county courthouse, the Eastern Idaho community UplinkSync is based in and serves.' => 'Aerial view of a downtown — Main Street and the county courthouse.',
);
foreach ( $seo as $in => $want ) {
	$got = uplinksync_location_neutral_seo_text( $in );
	$all_outputs[] = $got;
	upl944_check( 'post-live: exact output for "' . substr( $in, 0, 48 ) . '…"', $want === $got, $got );
}

// 1d: both no-break-space forms of the estimator label.
foreach ( array( 'Distance from Idaho&nbsp;Falls area (miles)', "Distance from Idaho\u{00A0}Falls area (miles)", 'Distance from Idaho Falls area (miles)' ) as $label ) {
	$got = uplinksync_location_neutral_text( $label );
	$all_outputs[] = $got;
	upl944_check( 'post-live: estimator label ' . bin2hex( substr( $label, 19, 6 ) ), 'Estimated round-trip travel (miles)' === $got, $got );
}

// Safety: non-empty, no PCRE error, idempotent, and the fallback phrases never surface.
upl944_check( 'post-live: shop rewrite non-empty', strlen( $shop_out ) > 200 );
upl944_check( 'post-live: no PCRE error', PREG_NO_ERROR === preg_last_error(), preg_last_error_msg() );
upl944_check( 'post-live: shop rewrite idempotent', uplinksync_location_neutral_rewrite( $shop_out ) === $shop_out );
upl944_check( 'post-live: no mask token leaks', false === strpos( implode( '', $all_outputs ), "\u{E000}" ) );
foreach ( $all_outputs as $i => $o ) {
	upl944_check( "post-live: fixture {$i} has no 'the service area' / 'remote-first service'", ! preg_match( '/the service area|remote-first service/i', $o ), substr( (string) $o, 0, 120 ) );
}

echo "\n" . ( 0 === $failures ? "ALL PASS\n" : "{$failures} FAILURE(S)\n" );
exit( 0 === $failures ? 0 : 1 );
