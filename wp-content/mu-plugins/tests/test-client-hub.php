<?php
/**
 * Standalone tests for the interim Client Hub (themes/uplinksync-child/inc/hub.php) and the
 * store-page wiring around it: destination registry, new-tab handling, /prints/ covers never
 * using a "-master-" rendition (403 under wp-content/.htaccess), templates and the Woo link fix.
 *
 * Run: php wp-content/mu-plugins/tests/test-client-hub.php
 */

error_reporting( E_ALL );

define( 'ABSPATH', __DIR__ );
$GLOBALS['uls_opts'] = array();
$GLOBALS['hub_img']  = array();
function add_action( ...$a ) {}
function add_filter( ...$a ) {}
function add_shortcode( ...$a ) {}
function is_user_logged_in() { return ! empty( $GLOBALS['hub_in'] ); }
function wp_get_current_user() { return (object) array( 'ID' => empty( $GLOBALS['hub_in'] ) ? 0 : 7, 'display_name' => 'dana.q' ); }
function get_user_meta( $id, $k, $single ) { return 'first_name' === $k ? 'Dana <b>' : ''; }
function wp_logout_url( $to ) { return '/wp-login.php?action=logout&redirect_to=' . rawurlencode( $to ); }
function is_page( $s ) { return 'hub' === $s; }
function do_action( ...$a ) { $GLOBALS['hub_actions'][] = $a[0]; }
function nocache_headers() { $GLOBALS['hub_nocache'] = true; }
function is_cart() { return ! empty( $GLOBALS['hub_cart'] ); }
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
function wp_get_attachment_image_url( $id, $size ) { return $GLOBALS['hub_img'][ $id ] ?? false; }

require __DIR__ . '/../uplinksync-phone.php';
require __DIR__ . '/../../themes/uplinksync-child/inc/site.php';
require __DIR__ . '/../../themes/uplinksync-child/inc/hub.php';

$failures = 0;
function hub_check( $name, $cond ) {
	global $failures;
	echo ( $cond ? '  PASS  ' : '  FAIL  ' ) . $name . "\n";
	if ( ! $cond ) {
		$failures++;
	}
}

// Registry: six hub destinations, correct targets.
$l = uplinksync_hub_links();
hub_check( 'six hub tiles in order', array( 'pay', 'media', 'sign', 'store', 'book', 'help' ) === UPLINKSYNC_HUB_TILES );
foreach ( UPLINKSYNC_HUB_TILES as $k ) {
	hub_check( "tile $k has label, description and href", '' !== $l[ $k ]['label'] && strlen( $l[ $k ]['desc'] ) > 20 && '' !== $l[ $k ]['href'] );
}
hub_check( 'pay links the public invoice host, not the Headscale-only billing host', 'https://invoice.uplinksync.com/client/login' === $l['pay']['href'] );
hub_check( 'sign goes to the DocuSeal sign-in entry, not its marketing root', 'https://sign.uplinksync.com/sign_in' === $l['sign']['href'] );
hub_check( 'store account is on-site', '/my-account/' === $l['store']['href'] && ! $l['store']['external'] );
hub_check( 'book is a booking link (adopted into the native modal), not external', 0 === strpos( $l['book']['href'], 'https://book.uplinksync.com/team/uplinksync/' ) && ! $l['book']['external'] );
hub_check( 'support is not the FreeScout agent login', false === strpos( $l['help']['href'], 'support.uplinksync.com/login' ) );
hub_check( 'no link to the DocuSeal marketing page anywhere', ! in_array( 'https://sign.uplinksync.com', array_column( $l, 'href' ), true ) && ! in_array( 'https://sign.uplinksync.com/', array_column( $l, 'href' ), true ) );

// New-tab handling.
$tiles = uplinksync_hub_tiles();
hub_check( 'exactly three tiles open a new tab (pay, media, sign)', 3 === substr_count( $tiles, 'target="_blank" rel="noopener"' ) && 3 === substr_count( $tiles, 'opens in a new tab' ) );
hub_check( 'book tile has no target', 1 === preg_match( '#<a class="htile" data-hub="book" href="[^"]+">#', $tiles ) );
hub_check( 'tiles render escaped labels', false !== strpos( $tiles, 'Your photos &amp; video' ) );
$foot = uplinksync_hub_footer_items();
hub_check( 'footer column: six destinations + print shop', 7 === substr_count( $foot, '<li>' ) && false !== strpos( $foot, '/prints/' ) );
hub_check( 'footer external links say so to screen readers', 3 === substr_count( $foot, '(opens in a new tab)' ) );
$band = uplinksync_site_hub();
hub_check( 'home band uses the registry (invoice host, no billing host)', false !== strpos( $band, 'invoice.uplinksync.com' ) && false === strpos( $band, 'billing.uplinksync.com' ) && false !== strpos( $band, 'href="/hub/"' ) );
hub_check( 'back bar links the hub; store context adds the print shop', false !== strpos( uplinksync_hub_backbar(), 'href="/hub/"' ) && false === strpos( uplinksync_hub_backbar(), '/prints/' ) && false !== strpos( uplinksync_hub_backbar( 'store' ), 'href="/prints/"' ) );

// /prints/ covers: never a master rendition.
foreach ( array(
	'https://uplinksync.com/wp-content/uploads/2026/07/040-moun-palisades-reservoir-master-c6fdeb6f7af4df20-1024x576.jpg',
	'https://uplinksync.com/wp-content/uploads/2026/07/001-city-idaho-falls-master-3c38229dc51b7f47.jpg',
	'https://uplinksync.com/wp-content/uploads/2026/07/011-city-pocatello-master-63a47e98bcb3e53a-scaled.jpg?x=1',
) as $u ) {
	hub_check( 'master detected: ' . basename( $u ), uplinksync_hub_is_master_url( $u ) );
}
foreach ( array( '-master-3c38229dc51b7f47-1-1024x576.jpg', '-master-3c38229dc51b7f47-1.jpg', '-master-3c38229dc51b7f47-e1729999999-1024x576.jpg', '-master-3c38229dc51b7f47-1024x576.avif', '-master-3c38229dc51b7f47.webp' ) as $sfx ) {
	hub_check( 'master variant detected: ' . $sfx, uplinksync_hub_is_master_url( 'https://uplinksync.com/wp-content/uploads/2026/07/001-city-idaho-falls' . $sfx ) );
}
hub_check( 'preview is not a master', ! uplinksync_hub_is_master_url( 'https://uplinksync.com/wp-content/uploads/2026/07/040-moun-palisades-reservoir-1024x576.jpg' ) );
$GLOBALS['hub_img'] = array(
	7 => 'https://uplinksync.com/wp-content/uploads/2026/07/040-moun-palisades-reservoir-1024x576.jpg',
	8 => 'https://uplinksync.com/wp-content/uploads/2026/07/031-city-kaysville-master-4ada613b2246c7c8-1024x576.jpg',
);
hub_check( 'cover URL = preview rendition', $GLOBALS['hub_img'][7] === uplinksync_hub_cover_url( 7 ) );
hub_check( 'cover URL refuses a master rendition', '' === uplinksync_hub_cover_url( 8 ) );
hub_check( 'no attachment -> no cover', '' === uplinksync_hub_cover_url( 0 ) );
$cards = uplinksync_hub_collection_cards(
	array(
		array( 'name' => 'Mountain reservoir landscape', 'href' => '/product-category/palisades-reservoir/', 'count' => 83, 'from' => '$39', 'cover' => $GLOBALS['hub_img'][7], 'kind' => 'Mountainscapes' ),
		array( 'name' => 'Aerial cityscape', 'href' => '/product-category/kaysville/', 'count' => 1, 'from' => '', 'cover' => '', 'kind' => 'Cityscapes' ),
	)
);
hub_check( 'cards: feature first, numbered, plural/singular counts', false !== strpos( $cards, '<li class="feature">' ) && false !== strpos( $cards, 'Collection 02' ) && false !== strpos( $cards, '83 prints · from $39' ) && false !== strpos( $cards, '>1 print<' ) );
hub_check( 'cards: no cover -> gradient fallback, never a broken <img>', 1 === substr_count( $cards, '<img' ) && false !== strpos( $cards, 'pc-fallback' ) );
hub_check( 'cards: empty list -> catalog link', false !== strpos( uplinksync_hub_collection_cards( array() ), 'href="/shop/"' ) );

// "From" price: WooCommerce API, min variation price for variable products (review L10).
class Hub_Fake_Product {
	public $t; public $p; public $m;
	public function __construct( $type, $price, $min = null ) { $this->t = $type; $this->p = $price; $this->m = $min; }
	public function is_type( $t ) { return $t === $this->t; }
	public function get_price() { return $this->p; }
	public function get_variation_price( $w ) { return 'min' === $w ? $this->m : null; }
}
hub_check( 'simple product price', 29.0 === uplinksync_hub_product_min_price( new Hub_Fake_Product( 'simple', '29' ) ) );
hub_check( 'variable product uses the min variation price, not the stored max', 19.0 === uplinksync_hub_product_min_price( new Hub_Fake_Product( 'variable', '89', '19' ) ) );
hub_check( 'no price -> null', null === uplinksync_hub_product_min_price( new Hub_Fake_Product( 'simple', '' ) ) );
hub_check( 'money: whole amounts without decimals', '$39' === uplinksync_hub_money( 39.0 ) && '$29.5' === uplinksync_hub_money( 29.5 ) );

// Store header is rendered at template time from a shortcode (review L13).
$GLOBALS['hub_cart'] = true;
$sh = uplinksync_hub_store_head();
hub_check( 'store head follows the page (cart) and carries the back bar', false !== strpos( $sh, '<h1 id="h1">Your cart</h1>' ) && false !== strpos( $sh, 'Back to Client Hub' ) );
$GLOBALS['hub_cart'] = false;
hub_check( 'html-block filter expands the token, leaves other blocks alone', false !== strpos( uplinksync_hub_store_head_block( '<div>[uls_store_head]</div>' ), 'storehero' ) && '<p>x</p>' === uplinksync_hub_store_head_block( '<p>x</p>' ) );
hub_check( 'store-head pattern defers to the render-time token', false !== strpos( file_get_contents( get_stylesheet_directory() . '/patterns/store-head.php' ), '[uls_store_head]' ) );

// Account menu labels.
$m = uplinksync_hub_account_menu( array( 'dashboard' => 'Dashboard', 'customer-logout' => 'Log out', 'x' => 'X' ) );
hub_check( 'account menu relabelled, unknown items kept', 'Overview' === $m['dashboard'] && 'Sign out' === $m['customer-logout'] && 'X' === $m['x'] );

// Templates + stylesheet wiring.
$t = get_stylesheet_directory() . '/templates/';
foreach ( array( 'page-hub', 'page-prints' ) as $f ) {
	$c = file_get_contents( $t . $f . '.html' );
	hub_check( "$f.html is a Richer Quiet Light template", false !== strpos( $c, 'uls-site rq' ) && false !== strpos( $c, 'uls-ql-site-header' ) );
}
foreach ( array( 'page-my-account', 'cart', 'checkout', 'order-confirmation', 'single-product', 'archive-product' ) as $f ) {
	$c = file_get_contents( $t . $f . '.html' );
	hub_check( "$f.html uses the child header/footer and the hub back link", false === strpos( $c, 'hostinger-ai-theme' ) && false !== strpos( $c, 'uls-ql-site-header' ) && false !== strpos( $c, 'uls-ql-site-footer' ) && 1 === preg_match( '#uplinksync-child/(store-head|hub-backbar)#', $c ) );
}
$woo = file_get_contents( get_stylesheet_directory() . '/assets/css/woocommerce.css' );
hub_check( 'woo link colour no longer reaches the header/footer', false !== strpos( $woo, ':not(:where(.uls-ql-header, .uls-ql-footer, .rq) a)' ) && 0 === preg_match( '#^\.woocommerce a:not\(\.button\):not\(\.wp-block-button__link\),$#m', $woo ) );
hub_check( 'woo link exclusions add no specificity (all extra exclusions inside :where)', 1 === preg_match( '#^\.woocommerce a:not\(\.button\):not\(\.wp-block-button__link\):where\(:not\([^)]*\)\):not\(:where\([^)]*\) a\),$#m', $woo ) );
$ap = file_get_contents( $t . 'archive-product.html' );
hub_check( 'archive back bar sits above the padded <main> (review L11)', strpos( $ap, 'hub-backbar' ) < strpos( $ap, '"tagName":"main"' ) );
$arch = file_get_contents( __DIR__ . '/../uplinksync-collection-archive.php' );
hub_check( 'collection hero no longer maps to the (403) master', false === strpos( $arch, '$hero_map' ) );

// /hub/ — one page, two states.
$GLOBALS['hub_in'] = false;
$out = uplinksync_hub_page();
hub_check( 'signed out: sign-in CTA to My Account, back to /hub/', false !== strpos( $out, 'href="/my-account/?redirect_to=%2Fhub%2F"' ) && false !== strpos( $out, 'Sign in to your client hub' ) );
hub_check( 'signed out: the six public tiles + sign-in notes, no portal', 6 === substr_count( $out, 'class="htile"' ) && false !== strpos( $out, 'How signing in works' ) && false === strpos( $out, 'hubportal' ) );
uplinksync_hub_nocache();
hub_check( 'signed out: page stays cacheable', ! defined( 'DONOTCACHEPAGE' ) && empty( $GLOBALS['hub_nocache'] ) );
$GLOBALS['hub_in'] = true;
$out = uplinksync_hub_page();
hub_check( 'signed in, no plugin: escaped welcome, tiles, no sign-in CTA', false !== strpos( $out, 'Welcome back, Dana &lt;b&gt;' ) && 6 === substr_count( $out, 'class="htile"' ) && false === strpos( $out, 'Sign in to your client hub' ) && false === strpos( $out, 'hubportal' ) );
hub_check( 'signed in: sign-out returns to /hub/', false !== strpos( $out, 'redirect_to=%2Fhub%2F' ) );
if ( ! function_exists( 'uplinkportal_render_portal' ) ) { // declared at run time (not hoisted), i.e. "plugin activates now".
	function uplinkportal_render_portal() {
		if ( 'echo' === $GLOBALS['portal_mode'] ) { echo '<div class="upl-shell">echoed</div>'; return null; }
		return '<div class="upl-shell">returned</div>';
	}
}
$GLOBALS['portal_mode'] = 'return';
$out = uplinksync_hub_page();
hub_check( 'signed in + plugin: portal shell at the top, tiles below as "Everything else"', false !== strpos( $out, 'upl-shell">returned' ) && strpos( $out, 'upl-shell' ) < strpos( $out, 'class="htile"' ) && false !== strpos( $out, '>Everything else<' ) );
$GLOBALS['portal_mode'] = 'echo';
hub_check( 'renderer that echoes is captured, not leaked', false !== strpos( uplinksync_hub_page(), 'upl-shell">echoed' ) );
uplinksync_hub_nocache();
hub_check( 'signed in: no page caching (DONOTCACHEPAGE, LiteSpeed, headers)', defined( 'DONOTCACHEPAGE' ) && ! empty( $GLOBALS['hub_nocache'] ) && in_array( 'litespeed_control_set_nocache', $GLOBALS['hub_actions'] ?? array(), true ) );
$GLOBALS['hub_in'] = false;
hub_check( 'hub pattern defers to the render-time token', false !== strpos( file_get_contents( get_stylesheet_directory() . '/patterns/site-hub.php' ), '[uls_hub_page]' ) && false !== strpos( uplinksync_hub_store_head_block( '<div>[uls_hub_page]</div>' ), 'hubhero' ) );
foreach ( array( '/hub/' => '/hub/', '/hub/?x=1' => '/hub/?x=1', '//evil.test/' => '', 'https://evil.test/' => '', '/\\evil.test' => '', "/hub/\n" => '/hub/', 'javascript:alert(1)' => '', '' => '' ) as $in => $want ) {
	hub_check( 'login redirect_to ' . json_encode( $in ) . ' -> ' . json_encode( $want ), $want === uplinksync_hub_safe_redirect( $in ) );
}

// /shop/ catalog script is a file, never inline in filtered shortcode output (QA M2).
$cat = file_get_contents( __DIR__ . '/../uplinksync-shop-catalog.php' );
$js  = __DIR__ . '/../uplinksync-assets/shop-catalog.js';
hub_check( 'shop catalog prints no inline <script> (wpautop broke it)', false === stripos( $cat, '<script' ) && false !== strpos( $cat, 'uplinksync_shop_catalog_enqueue_js();' ) );
hub_check( 'shop catalog script file exists and wires chips, search and sort', is_file( $js ) && false !== strpos( file_get_contents( $js ), "querySelectorAll('.chip[data-filter]')" ) && false !== strpos( file_get_contents( $js ), 'uls-search' ) && false === strpos( file_get_contents( $js ), '<' . '/p>' ) );
hub_check( 'chip counts use ink-muted, no opacity (QA m3)', false !== strpos( $cat, '.uls-shop .chip .n{color:#46628a;' ) );
$css = file_get_contents( get_stylesheet_directory() . '/assets/css/site.css' );
hub_check( 'closed mini-cart drawer leaves the focus order (QA m2)', false !== strpos( $css, '.wc-block-mini-cart__drawer[aria-hidden="true"] { visibility: hidden; }' ) );
hub_check( 'logo never wider than the room left by quote + menu at 360px', false !== strpos( $css, 'width: min(170px, 42vw, calc(100vw - 236px))' ) );
hub_check( 'header mini-cart has its own 44px slot; quote button yields under 600px (QA M3)', false !== strpos( $css, '.wc-block-mini-cart__button { min-width: 44px; min-height: 44px;' ) && false !== strpos( $css, '.uls-ql-bar:has(.wp-block-woocommerce-mini-cart) .uls-ql-header-cta { display: none; }' ) );

echo $failures ? "\n$failures FAILED\n" : "\nAll client hub checks passed.\n";
exit( $failures ? 1 : 0 );
