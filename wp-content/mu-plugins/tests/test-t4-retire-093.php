<?php
/**
 * Standalone regression checks for T4: retiring snippet 93 without reopening
 * REST access. Run: php wp-content/mu-plugins/tests/test-t4-retire-093.php
 */

error_reporting( E_ALL );

define( 'ABSPATH', __DIR__ );
$GLOBALS['t4_filters'] = array();
$GLOBALS['t4_actions'] = array();
$GLOBALS['t4_opts']    = array(
	'uplaa179_albums' => array(
		'legacy' => array( 'product_cat' => 'proof-demo-shoot' ),
	),
);
$GLOBALS['t4_role']    = 'anon';
$GLOBALS['t4_page']    = null;
$GLOBALS['t4_pagename'] = '';
$GLOBALS['t4_term']    = null;
$GLOBALS['t4_product_terms'] = array();
$GLOBALS['t4_routes']     = array();
$GLOBALS['t4_redirects']  = array();
$GLOBALS['wp']         = (object) array( 'query_vars' => array() );

class WP_Error {
	public $code;
	public $data;
	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code = $code;
		$this->data = $data;
	}
}

class WP_REST_Response {
	private $data;
	private $headers = array();
	public function __construct( $data = null, $status = 200 ) {
		$this->data              = $data;
		$this->headers['Status'] = $status;
	}
	public function get_data() { return $this->data; }
	public function set_data( $data ) { $this->data = $data; }
	public function get_headers() { return $this->headers; }
	public function header( $name, $value ) { $this->headers[ $name ] = $value; }
}

class WP_REST_Request {
	private $route;
	public function __construct( $route ) { $this->route = $route; }
	public function get_route() { return $this->route; }
}

function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['t4_filters'][ $tag ][] = array( $callback, $priority, $accepted_args );
}
function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['t4_actions'][ $tag ][] = array( $callback, $priority, $accepted_args );
}
function t4_do_action( $tag ) {
	$hooks = $GLOBALS['t4_actions'][ $tag ] ?? array();
	usort( $hooks, static function ( $a, $b ) { return $a[1] <=> $b[1]; } );
	foreach ( $hooks as $hook ) {
		call_user_func( $hook[0] );
	}
}
function t4_apply_filter( $tag, $value ) {
	$args = func_get_args();
	array_shift( $args );
	$hooks = $GLOBALS['t4_filters'][ $tag ] ?? array();
	usort( $hooks, static function ( $a, $b ) { return $a[1] <=> $b[1]; } );
	foreach ( $hooks as $hook ) {
		$call = array_slice( $args, 0, $hook[2] );
		$call[0] = $value;
		$value   = call_user_func_array( $hook[0], $call );
	}
	return $value;
}
function get_option( $key, $default = false ) { return $GLOBALS['t4_opts'][ $key ] ?? $default; }
function current_user_can( $capability ) { return 'admin' === $GLOBALS['t4_role']; }
function is_user_logged_in() { return 'anon' !== $GLOBALS['t4_role']; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function rest_authorization_required_code() { return is_user_logged_in() ? 403 : 401; }
function __( $text ) { return $text; }
function rest_get_url_prefix() { return 'wp-json'; }
function sanitize_title( $value ) { return strtolower( trim( (string) $value ) ); }
function is_admin() { return false; }
function is_page() { return null !== $GLOBALS['t4_page']; }
function get_queried_object() { return $GLOBALS['t4_page'] ?? $GLOBALS['t4_term']; }
function has_shortcode( $content, $tag ) { return false !== strpos( $content, '[' . $tag ); }
function get_query_var( $key ) { return 'pagename' === $key ? $GLOBALS['t4_pagename'] : ''; }
function is_product_category() { return null !== $GLOBALS['t4_term']; }
function is_product() { return false; }
function get_queried_object_id() { return 0; }
function wp_get_post_terms() { return $GLOBALS['t4_product_terms']; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function wp_safe_redirect( $location, $status ) { $GLOBALS['t4_redirects'][] = array( $location, $status ); }
function register_rest_route( $namespace, $route ) { $GLOBALS['t4_routes'][] = $namespace . $route; }
function __return_true() { return true; }
function shortcode_exists( $tag ) { return false; }
class WP_REST_Server { const ALLMETHODS = 'ALL'; }

require __DIR__ . '/../../plugins/uplinksync-site-code/snippets/091-uplaa-366-allow-anon-woocommerce-store-api-checkout.php';
require __DIR__ . '/../../plugins/uplinksync-site-code/snippets/900-uls-dr006-interim-client-media-authz.php';
require __DIR__ . '/../../plugins/uplinksync-site-code/snippets/093-uls-proofing-retired.php';

$failures = 0;
function t4_check( $name, $condition ) {
	global $failures;
	echo ( $condition ? '  PASS  ' : '  FAIL  ' ) . $name . "\n";
	if ( ! $condition ) {
		$failures++;
	}
}

function t4_rest_lock( $uri, $route ) {
	$_SERVER['REQUEST_URI']             = $uri;
	$GLOBALS['wp']->query_vars['rest_route'] = $route;
	$denied                             = new WP_Error( 'rest_disabled', 'Denied', array( 'status' => 401 ) );
	return array( $denied, t4_apply_filter( 'rest_authentication_errors', $denied ) );
}

// F1: only a genuine Store API route is exempt. Query-string and nested-path
// lookalikes remain under the REST lock.
list( $denied, $actual ) = t4_rest_lock( '/wp-json/wc/store/v1/products', '/wc/store/v1/products' );
t4_check( 'F1 permits the actual Store API route', true === $actual );
list( $denied, $actual ) = t4_rest_lock( '/wp-json/wp/v2/users?a=/wc/store/', '/wp/v2/users' );
t4_check( 'F1 ignores wc/store in a query string', $denied === $actual );
list( $denied, $actual ) = t4_rest_lock( '/wp-json/wp/v2/users/wc/store/', '/wp/v2/users/wc/store/' );
t4_check( 'F1 nested wp/v2 path remains 401, not a Store API 404', $denied === $actual );

// M1: media collection access is capability-based for anonymous, client, and admin callers.
$media_request = new WP_REST_Request( '/wp/v2/media' );
foreach ( array( 'anon' => 401, 'client' => 403 ) as $role => $status ) {
	$GLOBALS['t4_role'] = $role;
	$result             = t4_apply_filter( 'rest_pre_dispatch', null, null, $media_request );
	t4_check( "M1 {$role} wp/v2/media is denied", $result instanceof WP_Error && $status === $result->data['status'] );
}
$GLOBALS['t4_role'] = 'admin';
t4_check( 'M1 admin wp/v2/media remains available', null === t4_apply_filter( 'rest_pre_dispatch', null, null, $media_request ) );

// M2 must be observable through both REST dispatch paths. Clients are denied
// category disclosure when the old row-93 ACL helpers are absent; admins remain allowed.
$category_request = new WP_REST_Request( '/wc/store/v1/products/categories' );
foreach ( array( 'rest_request_after_callbacks', 'rest_post_dispatch' ) as $hook ) {
	$GLOBALS['t4_role'] = 'anon';
	$response           = new WP_REST_Response( array( array( 'slug' => 'proof-demo-shoot' ), array( 'slug' => 'public' ) ) );
	$filtered           = t4_apply_filter( $hook, $response, null, $category_request );
	t4_check( "M2 {$hook} hides proofing category for anonymous callers", 1 === count( $filtered->get_data() ) && 'public' === $filtered->get_data()[0]['slug'] );
}
$GLOBALS['t4_role'] = 'client';
t4_check( 'M2 fails closed without uplaa179_can_view()', false === uls_dr006_may_view_cat( 'proof-demo-shoot' ) );
$GLOBALS['t4_role'] = 'admin';
$response            = new WP_REST_Response( array( array( 'slug' => 'proof-demo-shoot' ) ) );
$filtered            = t4_apply_filter( 'rest_request_after_callbacks', $response, null, $category_request );
t4_check( 'M2 allows the proofing category for admin', 1 === count( $filtered->get_data() ) );

// Redirect routing: the shim identifies former shortcode pages and proofing categories.
$GLOBALS['t4_page']     = (object) array( 'post_content' => '[uplaa_client_album slug="legacy"]' );
$GLOBALS['t4_pagename'] = '';
t4_check( 'former proofing shortcode page redirects to /hub/', uls093_retired_is_proofing_request() );
$GLOBALS['t4_page']     = null;
$GLOBALS['t4_term']     = (object) array( 'slug' => 'proof-demo-shoot' );
t4_check( 'former proofing category redirects to /hub/', uls093_retired_is_proofing_request() );
$GLOBALS['t4_term'] = null;
t4_check( 'ordinary front-end request does not redirect', false === uls093_retired_is_proofing_request() );

// Rollback: the database row can load after the shim. Its runtime entitlement
// symbol makes both shim callbacks no-op, so row 93 retains its original
// gallery gate and /uplaa179/v1/status route without a competing catch-all.
eval( 'function uplaa179_can_view() { return true; }' );
$GLOBALS['t4_page']      = (object) array( 'post_content' => '[uplaa_client_album slug="legacy"]' );
$GLOBALS['t4_redirects'] = array();
t4_do_action( 'template_redirect' );
t4_check( 'active row 93 prevents the retirement redirect', empty( $GLOBALS['t4_redirects'] ) );
$GLOBALS['t4_routes'] = array();
t4_do_action( 'rest_api_init' );
t4_check( 'active row 93 prevents the retirement catch-all route', empty( $GLOBALS['t4_routes'] ) );

if ( $failures ) {
	echo "\n{$failures} failure(s)\n";
	exit( 1 );
}
echo "\nAll T4 retirement and DR-006 checks passed.\n";
