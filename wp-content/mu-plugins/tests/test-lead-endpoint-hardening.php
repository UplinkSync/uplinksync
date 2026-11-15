<?php
/** Run: php wp-content/mu-plugins/tests/test-lead-endpoint-hardening.php */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
$GLOBALS['lead_options'] = array();
$GLOBALS['lead_transients'] = array();
$GLOBALS['lead_cache'] = array();
$GLOBALS['lead_ip_lookups'] = array();
$GLOBALS['lead_mail_calls'] = 0;
$GLOBALS['lead_mail_result'] = true;

class Lead_Json extends Exception {
	public $success;
	public $data;
	public $status;
	public function __construct( $success, $data, $status ) { $this->success = $success; $this->data = $data; $this->status = $status; }
}
function add_action() {}
function add_shortcode() {}
function wp_unslash( $value ) { return $value; }
function wp_send_json_success( $data ) { throw new Lead_Json( true, $data, 200 ); }
function wp_send_json_error( $data, $status ) { throw new Lead_Json( false, $data, $status ); }
function get_transient( $key ) { return $GLOBALS['lead_transients'][ $key ] ?? false; }
function set_transient( $key, $value ) { $GLOBALS['lead_transients'][ $key ] = $value; return true; }
function delete_transient( $key ) { unset( $GLOBALS['lead_transients'][ $key ] ); return true; }
function wp_cache_add( $key, $value, $group ) { $cache_key = $group . ':' . $key; if ( array_key_exists( $cache_key, $GLOBALS['lead_cache'] ) ) return false; $GLOBALS['lead_cache'][ $cache_key ] = $value; return true; }
function wp_cache_delete( $key, $group ) { unset( $GLOBALS['lead_cache'][ $group . ':' . $key ] ); return true; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_email( $value ) { return filter_var( $value, FILTER_SANITIZE_EMAIL ); }
function is_email( $value ) { return false !== filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function wp_mail() { $GLOBALS['lead_mail_calls']++; return $GLOBALS['lead_mail_result']; }
function get_bloginfo() { return 'Example'; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function uls_estimator_client_ip( $server ) {
	$GLOBALS['lead_ip_lookups'][] = $server;
	$peer = $server['REMOTE_ADDR'] ?? '';
	return '104.16.1.1' === $peer && ! empty( $server['HTTP_CF_CONNECTING_IP'] ) ? $server['HTTP_CF_CONNECTING_IP'] : $peer;
}
function esc_url_raw( $value ) { return $value; }
function admin_url( $path ) { return '/wp-admin/' . $path; }
function wp_create_nonce() { return 'valid-nonce'; }
function esc_attr( $value ) { return $value; }

require __DIR__ . '/../../plugins/uplinksync-site-code/snippets/081-uplaa-337-quote-configurator-internal-target-quote-routing.php';

$failures = 0;
function lead_check( $name, $condition ) { global $failures; echo ( $condition ? '  PASS  ' : '  FAIL  ' ) . $name . "\n"; if ( ! $condition ) $failures++; }
function lead_submit( $post ) { $_POST = $post; try { uls337_handle_target_quote(); } catch ( Lead_Json $result ) { return $result; } return null; }
function lead_payload( $key = 'abcdefghijklmnop' ) { return array( 'uls337_idempotency_key' => $key, 'email' => 'person@example.test', 'name' => 'Person', 'company_url' => '' ); }

$_SERVER = array( 'REMOTE_ADDR' => '104.16.1.1', 'HTTP_CF_CONNECTING_IP' => '198.51.100.7', 'HTTP_ORIGIN' => 'https://example.test' );
$cached_page = lead_payload();
$cached_page['uls337_nonce'] = 'a-stale-nonce-from-cached-html';
$result = lead_submit( $cached_page );
lead_check( 'old nonce from cached HTML does not reject a same-origin lead', $result && $result->success && 1 === $GLOBALS['lead_mail_calls'] );
$result = lead_submit( lead_payload() );
lead_check( 'same idempotency key suppresses duplicate mail', $result && $result->success && 1 === $GLOBALS['lead_mail_calls'] );
$bad = lead_payload( 'qrstuvwxyz012345' ); $_SERVER['HTTP_ORIGIN'] = 'https://other.example';
$result = lead_submit( $bad );
lead_check( 'mismatched origin is rejected', $result && ! $result->success && 403 === $result->status );
unset( $_SERVER['HTTP_ORIGIN'] );
$result = lead_submit( lead_payload( 'missing-origin-123' ) );
lead_check( 'missing origin and referer are rejected', $result && ! $result->success && 403 === $result->status );
$_SERVER['HTTP_REFERER'] = 'https://example.test/request-quote/';
$GLOBALS['lead_mail_result'] = false;
$result = lead_submit( lead_payload( 'second-key-123456' ) );
lead_check( 'failed mail reports success:false', $result && ! $result->success && 500 === $result->status );
$GLOBALS['lead_mail_result'] = true;
$result = lead_submit( lead_payload( 'second-key-123456' ) );
lead_check( 'failed mail releases idempotency key for a retry', $result && $result->success );
$bot = lead_payload( 'bot-key-123456789' ); $bot['company_url'] = 'https://bot.example'; $bot['uls337_nonce'] = 'invalid';
$before = $GLOBALS['lead_mail_calls']; $result = lead_submit( $bot );
lead_check( 'honeypot remains a silent no-mail success', $result && $result->success && $before === $GLOBALS['lead_mail_calls'] );
$GLOBALS['lead_transients'] = array();
$GLOBALS['lead_cache'] = array();
unset( $_SERVER['HTTP_REFERER'] ); $_SERVER['HTTP_ORIGIN'] = 'https://example.test';
$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.8';
$same_client = true;
for ( $i = 0; $i < 5; $i++ ) { $same_client = $same_client && uls337_rate_limit_allows_request(); }
lead_check( 'five requests from one Cloudflare client are allowed', $same_client );
lead_check( 'sixth request from that client is limited', false === uls337_rate_limit_allows_request() );
$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.9';
lead_check( 'another client behind the same Cloudflare edge is not pooled into the limit', uls337_rate_limit_allows_request() && ! empty( $GLOBALS['lead_ip_lookups'] ) );
$storage_key = uls337_idempotency_storage_key( 'ttl-store-key-12345' );
lead_check( 'idempotency reservation uses a TTL transient, not a permanent option row', uls337_reserve_idempotency_key( 'ttl-store-key-12345' ) && isset( $GLOBALS['lead_transients'][ $storage_key ] ) && empty( $GLOBALS['lead_options'] ) );
$source = file_get_contents( __DIR__ . '/../../plugins/uplinksync-site-code/snippets/081-uplaa-337-quote-configurator-internal-target-quote-routing.php' );
lead_check( 'mail failure logs an operator signal without the lead email', false !== strpos( $source, "error_log( '[UPLAA-337] target-quote lead mail delivery failed.' );" ) && false === strpos( $source, 'error_log( $email' ) );

if ( $failures ) { echo "\n{$failures} failure(s)\n"; exit( 1 ); }
echo "\nAll lead endpoint hardening checks passed.\n";
