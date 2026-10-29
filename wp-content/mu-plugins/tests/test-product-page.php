<?php
/**
 * Product page redesign (2026-10-28): facts parsing, aspect names, About clean-up, collection window,
 * tier split / default pick / comparison matrix, and the licence picker + price + add-to-cart text
 * rendered against stub products in the interim (Personal only) and launched (all tiers) states.
 * Run: php wp-content/mu-plugins/tests/test-product-page.php
 */

error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );

/* ---- minimal WordPress / WooCommerce stubs ---- */
$GLOBALS['uls_hooks'] = array();
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) {
	$GLOBALS['uls_hooks'][ $hook ][] = array( $cb, $prio );
}
function add_action( $hook, $cb, $prio = 10, $args = 1 ) {
	add_filter( $hook, $cb, $prio, $args );
}
function apply_filters( $hook, $value ) {
	return $value;
}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $s ) {
	return esc_html( $s );
}
function esc_url( $s ) {
	return (string) $s;
}
function wp_kses_post( $s ) {
	return (string) $s;
}
function wp_strip_all_tags( $s ) {
	return strip_tags( (string) $s );
}
function wp_unslash( $s ) {
	return $s;
}
function home_url( $p = '' ) {
	return 'https://example.test' . $p;
}
function add_query_arg( $k, $v, $url ) {
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v;
}
function sanitize_title( $s ) {
	return strtolower( trim( preg_replace( '/[^A-Za-z0-9]+/', '-', (string) $s ), '-' ) );
}
function checked( $a, $b, $echo = true ) {
	return (string) $a === (string) $b ? " checked='checked'" : '';
}
function wc_price( $n, $args = array() ) {
	return '<span class="amount">$' . number_format( (float) $n, 0 ) . '</span>';
}
function wc_get_price_to_display( $v ) {
	return $v->price;
}
function get_option( $k, $d = '' ) {
	return $d;
}
class WC_Product {
	public $id, $type, $attrs, $children, $name = 'Test Photo No. 01';
	public function __construct( $id, $type, $attrs, $children = array() ) {
		$this->id       = $id;
		$this->type     = $type;
		$this->attrs    = $attrs;
		$this->children = $children;
	}
	public function get_id() {
		return $this->id;
	}
	public function is_type( $t ) {
		return $t === $this->type;
	}
	public function get_attributes() {
		return $this->attrs;
	}
	public function get_children() {
		return $this->children;
	}
	public function get_date_modified() {
		return $GLOBALS['uls_state_version'];
	}
	public function get_name() {
		return $this->name;
	}
	public function get_variation_default_attribute( $a ) {
		return 'personal';
	}
	public function get_variation_price( $m, $d ) {
		return 35;
	}
}
class WC_Product_Variation_Stub {
	public $slug, $price, $stock;
	public function __construct( $slug, $price, $stock ) {
		$this->slug  = $slug;
		$this->price = $price;
		$this->stock = $stock;
	}
	public function is_purchasable() {
		return true;
	}
	public function is_in_stock() {
		return $this->stock;
	}
	public function get_attributes() {
		return array( 'pa_licence' => $this->slug );
	}
}
$GLOBALS['uls_vars'] = array();
function wc_get_product( $id ) {
	return isset( $GLOBALS['uls_vars'][ $id ] ) ? $GLOBALS['uls_vars'][ $id ] : null;
}

require __DIR__ . '/../uplinksync-licence-tiers/config.php';
// The plugin file requires pdf/certificate/licence-page too; they only define functions.
require __DIR__ . '/../uplinksync-licence-tiers.php';
require __DIR__ . '/../uplinksync-product-page/helpers.php';

$failures = 0;
function check( $name, $cond, $detail = '' ) {
	global $failures;
	echo ( $cond ? '  PASS  ' : '  FAIL  ' ) . $name . ( ! $cond && '' !== $detail ? "  ({$detail})" : '' ) . "\n";
	if ( ! $cond ) {
		$failures++;
	}
}
function hook( $name, $nth = 0 ) {
	return $GLOBALS['uls_hooks'][ $name ][ $nth ][0];
}

echo "Facts (parsed from the live description copy):\n";
$desc = 'Original aerial drone photograph — Palisades Reservoir Mountain Vista No. 01. Palisades Reservoir, ID, captured 2025-09-27. 84.9 MP full-resolution JPEG, instant digital download after purchase. Licence: Personal (private, non-commercial use). Commercial licences are coming soon; for business use, <a href="/contact/?topic=licensing">contact us</a>. Need exclusive rights? <a href="/contact/?topic=licensing">Request a quote</a>. No physical item is shipped.';
$f    = uls_pp_parse_facts( $desc );
check( 'megapixels 84.9', '84.9' === $f['mp'] );
check( 'capture date 2025-09-27', '2025-09-27' === $f['captured'] );
check( 'full-resolution + drone flags', $f['full_res'] && $f['drone'] );
check( 'formatted "27 Sep 2025"', '27 Sep 2025' === uls_pp_format_date( $f['captured'] ) );
$none = uls_pp_parse_facts( 'A photo with no facts at all.' );
check( 'missing facts stay empty (cell hidden, never a placeholder)', '' === $none['mp'] && '' === $none['captured'] && ! $none['full_res'] );
check( 'impossible date rejected', '' === uls_pp_parse_facts( 'captured 2025-02-31' )['captured'] && '' === uls_pp_format_date( '2025-13-01' ) );
check( '36.6 MP parsed; "MPH" ignored', '36.6' === uls_pp_parse_facts( 'speed 40 MPH, 36.6 MP JPEG' )['mp'] );

echo "\nAspect:\n";
check( '1200x675 -> 16:9 Landscape', array( 'ratio' => '16:9', 'orient' => 'Landscape' ) === uls_pp_aspect( 1200, 675 ) );
check( '8064x6048 -> 4:3', '4:3' === uls_pp_aspect( 8064, 6048 )['ratio'] );
check( 'portrait 4000x6000 -> 2:3 Portrait', array( 'ratio' => '2:3', 'orient' => 'Portrait' ) === uls_pp_aspect( 4000, 6000 ) );
check( 'square -> 1:1 Square', array( 'ratio' => '1:1', 'orient' => 'Square' ) === uls_pp_aspect( 500, 500 ) );
check( 'odd ratio -> decimal', '1.85:1' === uls_pp_aspect( 1850, 1000 )['ratio'] );
check( 'missing dims -> hidden', array() === uls_pp_aspect( 0, 675 ) );

echo "\nAbout this photo:\n";
$about = uls_pp_about_html( '<p>' . $desc . '</p>' );
check( 'interim licence sentences removed', false === stripos( $about, 'Licence:' ) && false === stripos( $about, 'coming soon' ) && false === stripos( $about, 'Request a quote' ) );
check( 'scene + delivery copy kept', false !== strpos( $about, 'captured 2025-09-27' ) && false !== strpos( $about, 'No physical item is shipped.</p>' ) );
check( 'pre-tier licence line rewritten', 'x. No physical item is shipped.' === uls_pp_about_html( 'x. Personal + commercial license; no physical item is shipped.' ) );
check( 'owner copy without licence text untouched', '<p>Hand-written scene copy.</p>' === uls_pp_about_html( '<p>Hand-written scene copy.</p>' ) );

echo "\nCollection window:\n";
check( 'next six after index 0', array( 2, 3, 4, 5, 6, 7 ) === uls_pp_strip_window( range( 1, 83 ), 0 ) );
check( 'wraps at the end, skips current', array( 1, 2, 3, 4, 5, 6 ) === uls_pp_strip_window( range( 1, 83 ), 82 ) );
check( 'small collection: all others once', array( 3, 4, 1 ) === uls_pp_strip_window( array( 1, 2, 3, 4 ), 1 ) );
check( 'single product: empty', array() === uls_pp_strip_window( array( 9 ), 0 ) );

echo "\nTier state helpers:\n";
$interim = array(
	'personal'          => array( 'price' => 35, 'available' => true ),
	'commercial-credit' => array( 'price' => 79, 'available' => false ),
	'commercial'        => array( 'price' => 119, 'available' => false ),
	'extended'          => array( 'price' => 399, 'available' => false ),
);
check( 'split interim', array( array( 'personal' ), array( 'commercial-credit', 'commercial', 'extended' ) ) === uls_lic_split_state( $interim ) );
check( 'pick: coming-soon tier in URL falls back to Personal', 'personal' === uls_lic_pick_default( 'commercial', array( 'personal' ) ) );
check( 'pick: available request honoured', 'extended' === uls_lic_pick_default( 'extended', array( 'personal', 'extended' ) ) );
check( 'pick: none available -> empty', '' === uls_lic_pick_default( 'personal', array() ) );
$m = uls_lic_matrix();
check( 'matrix: 7 rows x 4 tiers', 7 === count( $m ) && 7 === count( array_filter( $m, fn( $r ) => array_keys( uls_lic_tiers() ) === array_keys( $r[1] ) ) ) );
check( 'matrix: credit row from config', array( 'personal' => 'Appreciated', 'commercial-credit' => 'Required', 'commercial' => 'Optional', 'extended' => 'Optional' ) === $m[6][1] );
check( 'matrix: Personal never allows business use', 'No' === $m[2][1]['personal'] && 'No' === $m[4][1]['personal'] );

echo "\nPicker / price / button (interim: only Personal in stock):\n";
function make_product( $id, $stock ) {
	$kids = array();
	$i    = $id * 10;
	foreach ( array( 'personal' => 35, 'commercial-credit' => 79, 'commercial' => 119, 'extended' => 399 ) as $slug => $p ) {
		$GLOBALS['uls_vars'][ ++$i ] = new WC_Product_Variation_Stub( $slug, $p, $stock[ $slug ] );
		$kids[]                      = $i;
	}
	$GLOBALS['uls_state_version'] = 'v' . $id;
	return new WC_Product( $id, 'variable', array( 'pa_licence' => 'x' ), $kids );
}
$p      = make_product( 1, array( 'personal' => true, 'commercial-credit' => false, 'commercial' => false, 'extended' => false ) );
$picker = hook( 'woocommerce_dropdown_variation_attribute_options_html' );
$html   = $picker( '<select></select>', array( 'attribute' => 'pa_licence', 'product' => $p, 'selected' => 'commercial' ) );
check( 'one radio only (Personal), checked', 1 === substr_count( $html, 'type="radio"' ) && false !== strpos( $html, 'value="personal" checked' ) );
check( 'coming-soon group: details, lock, pill, three names, no prices', false !== strpos( $html, '<details class="uls-lic-soon">' ) && false !== strpos( $html, 'Coming soon' ) && false !== strpos( $html, 'Commercial with credit · Commercial · Extended' ) && false === strpos( $html, '$79' ) && false === strpos( $html, '$399' ) );
check( 'no "Credit required" badge while only one tier', false === strpos( $html, 'Credit required' ) );
check( 'ask links: commercial + exclusive', false !== strpos( $html, 'Ask about commercial use' ) && false !== strpos( $html, 'Exclusive rights? Request a quote' ) );
$args = hook( 'woocommerce_dropdown_variation_attribute_options_args' );
$a    = $args( array( 'attribute' => 'pa_licence', 'product' => $p, 'options' => array( 'personal', 'commercial-credit', 'commercial', 'extended' ), 'selected' => 'commercial' ) );
check( 'native select offers Personal only; URL choice of a soon tier falls back', array( 'personal' ) === $a['options'] && 'personal' === $a['selected'] );
$price = hook( 'woocommerce_variable_price_html' );
check( 'price "$35" without "From"', '<span class="amount">$35</span>' === $price( 'x', $p ) );
$btn = hook( 'woocommerce_product_single_add_to_cart_text' );
check( 'button "Add to cart · $35"', 'Add to cart · $35' === $btn( 'Add to cart', $p ) );

echo "\nLaunched (all tiers in stock):\n";
$q    = make_product( 2, array( 'personal' => true, 'commercial-credit' => true, 'commercial' => true, 'extended' => true ) );
$html = $picker( '<select></select>', array( 'attribute' => 'pa_licence', 'product' => $q, 'selected' => '' ) );
check( 'four radios, no coming-soon group', 4 === substr_count( $html, 'type="radio"' ) && false === strpos( $html, 'uls-lic-soon' ) );
check( 'credit badge + prices shown', false !== strpos( $html, 'Credit required' ) && false !== strpos( $html, '$399' ) );
check( '"Ask about commercial use" dropped, Exclusive stays', false === strpos( $html, 'Ask about commercial use' ) && false !== strpos( $html, 'Exclusive rights?' ) );
check( 'price "From $35"', false !== strpos( $price( 'x', $q ), 'From</span> <span class="amount">$35' ) );

echo "\nPartial (Extended opens first):\n";
$r    = make_product( 3, array( 'personal' => true, 'commercial-credit' => false, 'commercial' => false, 'extended' => true ) );
$html = $picker( '<select></select>', array( 'attribute' => 'pa_licence', 'product' => $r, 'selected' => 'extended' ) );
check( 'two radios, Extended selectable and selected, two still soon', 2 === substr_count( $html, 'type="radio"' ) && false !== strpos( $html, 'value="extended" checked' ) && false !== strpos( $html, 'Commercial with credit · Commercial' ) );

echo "\nWiring (source):\n";
$src = file_get_contents( __DIR__ . '/../uplinksync-product-page.php' );
check( 'tabs removed after snippet 123 (priority 100)', (bool) preg_match( "/unset\( \\\$tabs\['description'\], \\\$tabs\['uls_licence'\].*?\n\t},\n\t120/s", $src ) );
check( 'related + upsells removed, cycling card suppressed', false !== strpos( $src, "'woocommerce_output_related_products', 20" ) && false !== strpos( $src, "'uplinksync_pcycle_card', '__return_false'" ) );
check( 'hero never lazy, high priority', false !== strpos( $src, "'fetchpriority'] = 'high'" ) && false !== strpos( $src, "'loading']       = 'eager'" ) );
check( '"certificate" only behind the v2 flag', 1 === substr_count( $src, 'Licence certificate (PDF)' ) && false !== strpos( $src, 'uls_lic_live()' ) );
$css = file_get_contents( __DIR__ . '/../../themes/uplinksync-child/assets/css/product.css' );
check( 'gallery never opacity-gated', false !== strpos( $css, '.woocommerce-product-gallery{opacity:1!important' ) );
check( 'reduced motion honoured', false !== strpos( $css, 'prefers-reduced-motion:reduce' ) );
check( 'no royal #0d266b', false === stripos( $css . $src, '#0d266b' ) );

echo "\n" . ( 0 === $failures ? "ALL PASS\n" : "$failures FAILURE(S)\n" );
exit( 0 === $failures ? 0 : 1 );
