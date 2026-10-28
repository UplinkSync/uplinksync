<?php
/**
 * Standalone tests for the Richer Quiet Light page helpers (themes/uplinksync-child/inc/site.php):
 * hidden-until-real review slots, the template marker that gates the legacy-asset trim, the
 * phone link, nav aria-current and the header phone filter.
 *
 * Run: php wp-content/mu-plugins/tests/test-site-helpers.php
 */

error_reporting( E_ALL );

define( 'ABSPATH', __DIR__ );
$GLOBALS['uls_opts'] = array();
function add_action( ...$a ) {}
function add_filter( ...$a ) {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['uls_opts'] ) ? $GLOBALS['uls_opts'][ $k ] : $d; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function wp_kses_post( $s ) { return $s; }
function is_admin() { return false; }
function wp_unslash( $s ) { return $s; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function get_stylesheet_directory() { return __DIR__ . '/../../themes/uplinksync-child'; }
function get_stylesheet_directory_uri() { return 'https://example.test/wp-content/themes/uplinksync-child'; }

require __DIR__ . '/../uplinksync-phone.php';
require __DIR__ . '/../../themes/uplinksync-child/inc/site.php';

$failures = 0;
function site_check( $name, $cond ) {
	global $failures;
	echo ( $cond ? '  PASS  ' : '  FAIL  ' ) . $name . "\n";
	if ( ! $cond ) {
		$failures++;
	}
}

// Reviews: nothing at all until a real, permitted quote exists.
site_check( 'no reviews option -> renders nothing', '' === uplinksync_site_reviews() );
$GLOBALS['uls_opts']['uplinksync_reviews'] = array( array( 'quote' => '', 'name' => 'X' ), 'junk' );
site_check( 'incomplete review entries -> renders nothing', '' === uplinksync_site_reviews() );
$GLOBALS['uls_opts']['uplinksync_reviews'] = array( array( 'quote' => 'Fixed it <fast>', 'name' => 'Sam', 'role' => 'Owner, Co' ) );
$r = uplinksync_site_reviews();
site_check( 'real review renders, escaped', false !== strpos( $r, 'Fixed it &lt;fast&gt;' ) && false !== strpos( $r, '<b>Sam</b>' ) );
site_check( 'no placeholder copy ever', false === stripos( $r, 'slot' ) && false === stripos( $r, 'goes here' ) );

// Template marker gates the legacy-asset trim (a DB override without it keeps its CSS).
$GLOBALS['_wp_current_template_content'] = '<!-- wp:group {"className":"uls-site rq"} -->';
site_check( 'RQL template detected', uplinksync_site_is_page() );
$GLOBALS['_wp_current_template_content'] = '<!-- wp:group {"className":"uls-splith"} --> legacy DB override';
site_check( 'DB override without marker is not trimmed', ! uplinksync_site_is_page() );
$GLOBALS['_wp_current_template_content'] = null;
site_check( 'no resolved template -> not trimmed', ! uplinksync_site_is_page() );

// Phone link: hidden when empty, tel: link when published.
site_check( 'phone hidden by default', '' === uplinksync_site_phone_link() );
site_check( 'header phone filter is a no-op when empty', 'X' === uplinksync_site_header_phone( 'X', array( 'attrs' => array( 'className' => 'uls-ql-header-cta' ) ) ) );
$GLOBALS['uls_opts']['uplinksync_phone'] = '801 555 0134';
site_check( 'phone link rendered', '<a class="p" href="tel:+18015550134">(801) 555-0134</a>' === uplinksync_site_phone_link( 'p' ) );
site_check( 'header phone prepended to the CTA buttons only', 0 === strpos( uplinksync_site_header_phone( 'X', array( 'attrs' => array( 'className' => 'uls-ql-header-cta' ) ) ), '<a class="uls-ql-phone"' )
	&& 'X' === uplinksync_site_header_phone( 'X', array( 'attrs' => array( 'className' => 'other' ) ) ) );

// aria-current on the static nav links.
$link = '<li><a class="wp-block-navigation-item__content" href="/services/">Services</a></li>';
$_SERVER['REQUEST_URI'] = '/services/?x=1';
site_check( 'current section marked', false !== strpos( uplinksync_site_nav_current( $link, array( 'attrs' => array( 'url' => '/services/' ) ) ), 'aria-current="page"' ) );
$_SERVER['REQUEST_URI'] = '/services/managed-it/';
site_check( 'child page marks its section', false !== strpos( uplinksync_site_nav_current( $link, array( 'attrs' => array( 'url' => '/services/' ) ) ), 'aria-current' ) );
$_SERVER['REQUEST_URI'] = '/about/';
site_check( 'other sections untouched', $link === uplinksync_site_nav_current( $link, array( 'attrs' => array( 'url' => '/services/' ) ) ) );

// Images: srcset + intrinsic size from the built files; hero is eager/high priority.
$img = uplinksync_site_img( 'uplinksync-services-cybersecurity', UPLINKSYNC_SITE_CARD, 'Alt' );
site_check( 'card image has srcset, size and lazy', false !== strpos( $img, '1600w' ) && false !== strpos( $img, 'width="1600" height="900"' ) && false !== strpos( $img, 'loading="lazy"' ) );
$hero = uplinksync_site_img( 'uplinksync-hero-town-dusk', UPLINKSYNC_SITE_WIDE, '', '100vw', true );
site_check( 'hero image eager with fetchpriority', false !== strpos( $hero, 'fetchpriority="high"' ) && false === strpos( $hero, 'loading=' ) && false !== strpos( $hero, 'alt=""' ) );
foreach ( glob( get_stylesheet_directory() . '/assets/images/site/*.webp' ) as $f ) {
	if ( filesize( $f ) > 200 * 1024 ) {
		site_check( 'image over 200 KB: ' . basename( $f ), false );
	}
}
site_check( 'every site image <= 200 KB', true );

// Location slots (!180). Without the switch: fallback markup verbatim.
$home_fb = 'IT that just works — <em>and a person who answers.</em>';
site_check( 'slot without switch = neutral markup', $home_fb === uplinksync_site_slot( 'home_h1', $home_fb ) );
site_check( 'lede without switch unchanged', 'Lede.' === uplinksync_site_lede( 'Lede.' ) );
site_check( 'service area without switch renders nothing', '' === uplinksync_site_service_area() );
// With the switch (stubbed with !180's own neutral/utah wording).
$GLOBALS['uls_mode'] = 'neutral';
// Declared at runtime (a plain declaration would be hoisted above the 'without switch' checks).
eval( <<<'STUB'
function uplinksync_location_line( $slot ) {
	$map = array(
		'eyebrow'  => array( 'Managed IT · Websites · Aerial photo & video', 'Managed IT · Websites · Aerial photo & video · Utah County' ),
		'home_h1'  => array( 'IT that just works — and a person who answers.', 'IT that just works for Utah County businesses — and a person who answers.' ),
		'about_h1' => array( 'One team for your systems, your site, and the view from above.', 'Owner-run IT and aerial services, now in Utah County.' ),
		'intro'    => array( '', 'Serving Provo, Orem, Lehi, American Fork and the rest of Utah County, remotely and on-site.' ),
	);
	return isset( $map[ $slot ] ) ? $map[ $slot ][ 'utah' === $GLOBALS['uls_mode'] ? 1 : 0 ] : '';
}
function uplinksync_location_service_area_html() { return 'utah' === $GLOBALS['uls_mode'] ? '<section class="uplinksync-service-area">Where we work</section>' : ''; }
STUB
);
$eb_fb = 'Managed IT · Websites · Aerial photo &amp; video';
$ab_fb = 'One team for your systems, your site, and <em>the view from above.</em>';
site_check( 'neutral mode: home H1 byte-identical', $home_fb === uplinksync_site_slot( 'home_h1', $home_fb ) );
site_check( 'neutral mode: eyebrow byte-identical (entity kept)', $eb_fb === uplinksync_site_slot( 'eyebrow', $eb_fb ) );
site_check( 'neutral mode: about H1 byte-identical', $ab_fb === uplinksync_site_slot( 'about_h1', $ab_fb ) );
site_check( 'neutral mode: lede unchanged', 'Lede.' === uplinksync_site_lede( 'Lede.' ) );
site_check( 'neutral mode: no service area', '' === uplinksync_site_service_area() );
$GLOBALS['uls_mode'] = 'utah';
site_check( 'utah mode: home H1 switches, accent kept', 'IT that just works for Utah County businesses — <em>and a person who answers.</em>' === uplinksync_site_slot( 'home_h1', $home_fb ) );
site_check( 'utah mode: eyebrow switches, escaped', 'Managed IT · Websites · Aerial photo &amp; video · Utah County' === uplinksync_site_slot( 'eyebrow', $eb_fb ) );
site_check( 'utah mode: about H1 plain', 'Owner-run IT and aerial services, now in Utah County.' === uplinksync_site_slot( 'about_h1', $ab_fb ) );
site_check( 'utah mode: intro appended to lede', 'Lede. Serving Provo, Orem, Lehi, American Fork and the rest of Utah County, remotely and on-site.' === uplinksync_site_lede( 'Lede.' ) );
site_check( 'utah mode: service area wrapped', false !== strpos( uplinksync_site_service_area(), 'uplinksync-service-area' ) );
$GLOBALS['uls_mode'] = 'neutral';
// Every page pattern uses its slots; service area on Home, Services, Contact.
$pdir = get_stylesheet_directory() . '/patterns/';
foreach ( array( 'home' => 'home_h1', 'services' => 'services_h1', 'drone' => 'drone_h1', 'about' => 'about_h1', 'contact' => 'contact_h1' ) as $page => $slot ) {
	$src = file_get_contents( $pdir . "site-{$page}.php" );
	site_check( "site-{$page}: H1 slot + lede intro", false !== strpos( $src, "uplinksync_site_slot( '{$slot}'" ) && false !== strpos( $src, 'uplinksync_site_lede(' ) );
	if ( in_array( $page, array( 'home', 'services', 'contact' ), true ) ) {
		site_check( "site-{$page}: service-area slot", false !== strpos( $src, 'uplinksync_site_service_area()' ) );
	}
}

// QA M6: WooCommerce mini-cart off brochure pages (no store context in this harness).
site_check( 'hooked mini-cart removed off-store', array( 'woocommerce/customer-account' ) === uplinksync_site_hooked_blocks( array( 'woocommerce/mini-cart', 'woocommerce/customer-account' ) ) );
site_check( 'saved mini-cart block not rendered off-store', '' === uplinksync_site_mini_cart( '<div class="wc-block-mini-cart"></div>' ) );
// QA minor: mobile menu gets "Get a quote" + the published phone.
$GLOBALS['uls_opts']['uplinksync_phone'] = '801 555 0134';
$nav = uplinksync_site_menu_extras( '<nav><ul class="wp-block-navigation__container"><li>A</li></ul></nav>', array( 'attrs' => array( 'className' => 'uls-ql-nav' ) ) );
site_check( 'menu extras appended inside the list', false !== strpos( $nav, 'Get a quote</a></li><li class="wp-block-navigation-item uls-ql-menu-extra"><a class="wp-block-navigation-item__content" href="tel:+18015550134">' ) && '</ul></nav>' === substr( $nav, -11 ) );
site_check( 'menu extras idempotent', $nav === uplinksync_site_menu_extras( $nav, array( 'attrs' => array( 'className' => 'uls-ql-nav' ) ) ) );
unset( $GLOBALS['uls_opts']['uplinksync_phone'] );
site_check( 'no phone item when unpublished', 1 === substr_count( uplinksync_site_menu_extras( '<ul></ul>', array( 'attrs' => array( 'className' => 'uls-ql-nav' ) ) ), 'uls-ql-menu-extra' ) );
// QA minor 1: no place names in shipped image file names or pattern image stems.
$leaks = preg_grep( '/idaho|kaysville|palisades|pocatello|saratoga|provo|utah/i', array_map( 'basename', glob( get_stylesheet_directory() . '/assets/images/site/*' ) ) );
site_check( 'site image file names are place-free', ! $leaks );
foreach ( glob( get_stylesheet_directory() . '/patterns/site-*.php' ) as $f ) {
	site_check( basename( $f ) . ' references no place-named or watermarked-card image', ! preg_match( '/aerial-(idaho|kaysville|palisades|pocatello|saratoga)|services-drone/', file_get_contents( $f ) ) );
}

// QA minor 1: place-named share/schema images replaced on the RQL pages.
function is_front_page() { return true; }
$GLOBALS['_wp_current_template_content'] = 'uls-site rq';
$ld = uplinksync_site_schema_images( array( '@graph' => array( array( '@type' => 'WebPage', 'image' => array( 'url' => 'https://x.test/wp-content/uploads/aerial-stills/idaho-falls-full.jpg' ), 'url' => 'https://x.test/' ) ) ) );
site_check( 'schema place-named image replaced by hero', false !== strpos( $ld['@graph'][0]['image']['url'], 'uplinksync-hero-town-dusk-1280.webp' ) && 'https://x.test/' === $ld['@graph'][0]['url'] );
site_check( 'og image replaced, neutral image kept', false === strpos( uplinksync_site_og_image( '/a/palisades-hero.jpg' ), 'palisades' ) && '/a/logo.png' === uplinksync_site_og_image( '/a/logo.png' ) );
$GLOBALS['_wp_current_template_content'] = '';
site_check( 'other pages untouched', '/a/palisades-hero.jpg' === uplinksync_site_og_image( '/a/palisades-hero.jpg' ) );

if ( $failures ) {
	echo "\n{$failures} failure(s)\n";
	exit( 1 );
}
echo "\nAll site helper checks passed.\n";
