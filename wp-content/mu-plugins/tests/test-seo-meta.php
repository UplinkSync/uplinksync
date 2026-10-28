<?php
/**
 * Regression tests for uplinksync-seo-meta.php: the About-page schema URL corruption
 * (ASE "Obfuscate Author Slugs" + post_author 0), favicon override and share-image wiring.
 *
 * The About fixture is the live production JSON-LD captured 2026-10-28; it runs through the
 * full rank_math/json_ld chain in priority order (repair 25 -> location neutral 999 ->
 * org extras 1000 -> phone PHP_INT_MAX).
 *
 * Run: php wp-content/mu-plugins/tests/test-seo-meta.php
 */

error_reporting( E_ALL );

define( 'ABSPATH', __DIR__ );
$GLOBALS['uls_hooks'] = array();
function add_action( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['uls_hooks'][ $h ][] = array( $cb, $p ); }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['uls_hooks'][ $h ][] = array( $cb, $p ); }
$GLOBALS['uls_opts'] = array( 'uplinksync_phone' => '(208) 995-2704', 'site_icon' => 36 );
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['uls_opts'] ) ? $GLOBALS['uls_opts'][ $k ] : $d; }
$GLOBALS['uls_meta'] = array( 36 => '2026/02/cropped-generated-test.png', 99 => '2026/11/uplinksync-mark-512.png' );
function get_post_meta( $id, $k, $s = false ) { return isset( $GLOBALS['uls_meta'][ $id ] ) ? $GLOBALS['uls_meta'][ $id ] : ''; }
function home_url( $p = '' ) { return 'https://uplinksync.com' . $p; }
function esc_url( $s ) { return (string) $s; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function get_stylesheet_directory() { return __DIR__ . '/../../themes/uplinksync-child'; }
function get_stylesheet_directory_uri() { return 'https://uplinksync.com/wp-content/themes/uplinksync-child'; }

require __DIR__ . '/../uplinksync-phone.php';
require __DIR__ . '/../uplinksync-location-neutral.php';
require __DIR__ . '/../uplinksync-seo-meta.php';

$failures = 0;
function seo_check( $name, $cond, $detail = '' ) {
	global $failures;
	echo ( $cond ? '  PASS  ' : '  FAIL  ' ) . $name . ( ! $cond && $detail ? " ({$detail})" : '' ) . "\n";
	if ( ! $cond ) {
		$failures++;
	}
}

/** Run a value through every registered rank_math/json_ld callback in priority order. */
function seo_json_ld_chain( $data ) {
	$cbs = $GLOBALS['uls_hooks']['rank_math/json_ld'];
	usort( $cbs, static function ( $a, $b ) { return $a[1] <=> $b[1]; } );
	foreach ( $cbs as $cb ) {
		$data = call_user_func( $cb[0], $data );
	}
	return $data;
}

/** Every string under a URL-ish key, with its key path. */
function seo_urls( $v, $path = '' ) {
	$out = array();
	if ( is_array( $v ) ) {
		foreach ( $v as $k => $x ) {
			if ( is_string( $x ) && in_array( $k, array( '@id', 'url' ), true ) ) {
				$out[ $path . '/' . $k ] = $x;
			}
			$out += seo_urls( $x, $path . '/' . $k );
		}
	}
	return $out;
}

// ASE's real author_link filter body, as shipped in admin-site-enhancements 9.0.0.
function seo_ase_alter_author_link( $link, $author_slug, $encrypted ) {
	return str_replace( '/' . $author_slug, '/' . $encrypted, $link );
}

echo "Root cause: ASE + empty nicename\n";
$mangled = seo_ase_alter_author_link( 'https://uplinksync.com/author/', '', 'af352445a8dfb27f310b327bbf4bdef9' );
seo_check( 'ASE + empty slug reproduces the live corruption', 'https:/af352445a8dfb27f310b327bbf4bdef9/af352445a8dfb27f310b327bbf4bdef9uplinksync.com/af352445a8dfb27f310b327bbf4bdef9author/af352445a8dfb27f310b327bbf4bdef9' === $mangled );
seo_check( 'mangled URL detected as broken', uplinksync_seo_is_broken_url( $mangled ) );
seo_check( 'staging-length (16 hex) mangle detected', uplinksync_seo_is_broken_url( 'https:/b1f7876014b1ee39/b1f7876014b1ee39staging.uplinksync.com/b1f7876014b1ee39author/b1f7876014b1ee39' ) );
foreach ( array( 'https://uplinksync.com/', 'https://uplinksync.com/about/#webpage', 'https://secure.gravatar.com/avatar/?s=96', 'http://localhost:8080/x', 'https://uplinksync.com' ) as $ok ) {
	seo_check( "valid URL kept: {$ok}", ! uplinksync_seo_is_broken_url( $ok ) );
}
seo_check( 'author_link after ASE, empty slug -> home', 'https://uplinksync.com/' === uplinksync_seo_author_link( $mangled, 0, '' ) );
$real = 'https://uplinksync.com/author/4e3062d8c8626a14/';
seo_check( 'author_link for a real (obfuscated) author untouched', $real === uplinksync_seo_author_link( $real, 1, 'webmasteruplinksync-com' ) );

echo "About page: live fixture through the full json_ld chain\n";
$live = json_decode( file_get_contents( __DIR__ . '/fixtures/about-schema-live-20261028.json' ), true );
seo_check( 'fixture really carries the corruption', (bool) array_filter( seo_urls( $live ), 'uplinksync_seo_is_broken_url' ) );
$out  = seo_json_ld_chain( $live );
$urls = seo_urls( $out );
$bad  = array_filter( $urls, static function ( $u ) { return uplinksync_seo_is_broken_url( $u ) || ! preg_match( '#^https://(uplinksync\.com(/|$)|secure\.gravatar\.com/)#', $u ); } );
seo_check( 'no broken / foreign @id or url remains', ! $bad, implode( ', ', $bad ) );
seo_check( 'no hash fragment anywhere in output', false === strpos( json_encode( $out ), 'af352445' ) );
$types = array_map( static function ( $n ) { return is_array( $n['@type'] ) ? implode( ',', $n['@type'] ) : $n['@type']; }, $out['@graph'] );
seo_check( 'orphan Person node removed', ! in_array( 'Person', $types, true ) );
seo_check( 'Organization, WebSite, WebPage, Article kept', ! array_diff( array( 'Organization', 'WebSite', 'WebPage', 'Article' ), $types ) );
$article = null;
$org     = null;
foreach ( $out['@graph'] as $n ) {
	if ( 'Article' === $n['@type'] ) { $article = $n; }
	if ( 'Organization' === $n['@type'] ) { $org = $n; }
}
seo_check( 'Article.author -> Organization @id', isset( $article['author']['@id'] ) && 'https://uplinksync.com/#organization' === $article['author']['@id'] );
seo_check( 'founder Doug Irwin still present', isset( $org['founder']['name'] ) && 'Doug Irwin' === $org['founder']['name'] );
seo_check( 'published phone still present', isset( $org['telephone'] ) && '+12089952704' === $org['telephone'] );
seo_check( 'graph is a list', array_keys( $out['@graph'] ) === range( 0, count( $out['@graph'] ) - 1 ) );

echo "Contact-style page: Rank Math keyed array, Person reached via author_link fix\n";
$keyed = array(
	'publisher'   => array( '@type' => 'Organization', '@id' => 'https://uplinksync.com/#organization', 'name' => 'UplinkSync' ),
	'WebPage'     => array( '@type' => 'WebPage', '@id' => 'https://uplinksync.com/contact/#webpage', 'url' => 'https://uplinksync.com/contact/' ),
	'author'      => array( '@type' => 'Person', '@id' => uplinksync_seo_author_link( 'x', 0, '' ), 'url' => uplinksync_seo_author_link( 'x', 0, '' ), 'worksFor' => array( '@id' => 'https://uplinksync.com/#organization' ) ),
	'richSnippet' => array( '@type' => 'Article', 'author' => array( '@id' => 'https://uplinksync.com/', 'name' => '' ), 'headline' => 'Contact' ),
);
$k2 = seo_json_ld_chain( $keyed );
seo_check( 'keyed: Person removed', ! isset( $k2['author'] ) );
seo_check( 'keyed: keys preserved', isset( $k2['publisher'], $k2['WebPage'], $k2['richSnippet'] ) );
seo_check( 'keyed: Article author -> Organization', 'https://uplinksync.com/#organization' === $k2['richSnippet']['author']['@id'] );
$named = array( array( '@type' => 'Person', '@id' => 'https://uplinksync.com/#doug', 'name' => 'Doug Irwin' ) );
seo_check( 'a named, valid Person is never removed', $named === uplinksync_seo_schema_repair( $named ) );
seo_check( 'non-array passes through', 'x' === uplinksync_seo_schema_repair( 'x' ) );

echo "Favicon\n";
seo_check( 'generated-test site icon -> theme brand icon (32)', 'https://uplinksync.com/wp-content/themes/uplinksync-child/assets/images/brand/favicon/favicon-32.png' === uplinksync_seo_site_icon_url( 'https://uplinksync.com/wp-content/uploads/2026/02/cropped-generated-test-32x32.png', 32 ) );
seo_check( '180 -> apple-touch-icon', false !== strpos( uplinksync_seo_icon_file_url( 180 ), 'apple-touch-icon.png' ) );
seo_check( '192 -> icon-192', false !== strpos( uplinksync_seo_icon_file_url( 192 ), 'icon-192.png' ) );
seo_check( '270 -> icon-270', false !== strpos( uplinksync_seo_icon_file_url( 270 ), 'icon-270.png' ) );
seo_check( '512 -> icon-512', false !== strpos( uplinksync_seo_icon_file_url( 512 ), 'icon-512.png' ) );
foreach ( array( 'favicon-16.png', 'favicon-32.png', 'apple-touch-icon.png', 'icon-192.png', 'icon-270.png', 'icon-512.png', 'favicon.ico' ) as $f ) {
	$p = get_stylesheet_directory() . '/assets/images/brand/favicon/' . $f;
	seo_check( "ships {$f}", is_readable( $p ) && filesize( $p ) > 0 );
}
$sz = getimagesize( get_stylesheet_directory() . '/assets/images/brand/favicon/icon-512.png' );
seo_check( 'icon-512 is 512x512', 512 === $sz[0] && 512 === $sz[1] );
$sz = getimagesize( get_stylesheet_directory() . '/assets/images/brand/favicon/apple-touch-icon.png' );
seo_check( 'apple-touch-icon is 180x180', 180 === $sz[0] && 180 === $sz[1] );
$tags = uplinksync_seo_site_icon_tags( array() );
seo_check( 'adds 16px + .ico link tags', 2 === count( $tags ) && false !== strpos( $tags[1], 'favicon.ico' ) );

echo "Share images\n";
seo_check( 'front page -> home card', 'home' === uplinksync_seo_og_key_for( true, '' ) );
foreach ( array( 'services' => 'services', 'drone-services' => 'drone', 'about' => 'about', 'contact' => 'contact', 'terms' => '' ) as $slug => $key ) {
	seo_check( "slug {$slug} -> '{$key}'", $key === uplinksync_seo_og_key_for( false, $slug ) );
}
foreach ( array( 'home', 'services', 'drone', 'about', 'contact', 'default' ) as $key ) {
	$p  = get_stylesheet_directory() . '/assets/images/seo/og-' . $key . '.jpg';
	$im = is_readable( $p ) ? getimagesize( $p ) : array( 0, 0, 'mime' => '' );
	seo_check( "og-{$key}.jpg 1200x630 jpeg <= 300 KB", 1200 === $im[0] && 630 === $im[1] && 'image/jpeg' === $im['mime'] && filesize( $p ) <= 300 * 1024 );
	seo_check( "og-{$key} alt has no place name", ! preg_match( '/idaho|utah|boise|rexburg|ammon|pocatello|kaysville|local/i', UPLINKSYNC_SEO_OG_ALT[ $key ] ) );
}
// Key page: the card is the only accepted candidate.
$GLOBALS['uplinksync_seo_og_forced'] = uplinksync_seo_og_card( 'home' )['url'];
seo_check( 'key page: card accepted', array() !== uplinksync_seo_og_only_card( uplinksync_seo_og_card( 'home' ) ) );
seo_check( 'key page: stored place-named still dropped', array() === uplinksync_seo_og_only_card( array( 'url' => 'https://uplinksync.com/wp-content/uploads/aerial-stills/idaho-falls-full.jpg' ) ) );
$GLOBALS['uplinksync_seo_og_forced'] = '';
$a = array( 'url' => 'https://uplinksync.com/x.jpg' );
seo_check( 'other page: candidates untouched', $a === uplinksync_seo_og_only_card( $a ) );
class Seo_Fake_Image {
	public $images = array();
	public function add_image( $a ) { $this->images[ $a['url'] ] = $a; }
	public function has_images() { return ! empty( $this->images ); }
}
$img = new Seo_Fake_Image();
uplinksync_seo_og_default( $img );
seo_check( 'page with no image gets default card', 1 === count( $img->images ) && false !== strpos( key( $img->images ), 'og-default.jpg' ) );
uplinksync_seo_og_default( $img );
seo_check( 'default card not added twice / over an existing image', 1 === count( $img->images ) );
$hooks = array_keys( $GLOBALS['uls_hooks'] );
foreach ( array( 'author_link', 'rank_math/json_ld', 'get_site_icon_url', 'site_icon_meta_tags', 'rank_math/opengraph/facebook/add_images', 'rank_math/opengraph/twitter/add_images', 'rank_math/opengraph/facebook/add_additional_images', 'rank_math/opengraph/twitter/card_type' ) as $h ) {
	seo_check( "hook registered: {$h}", in_array( $h, $hooks, true ) );
}

echo $failures ? "\n{$failures} FAILURE(S)\n" : "\nALL PASS\n";
exit( $failures ? 1 : 0 );
