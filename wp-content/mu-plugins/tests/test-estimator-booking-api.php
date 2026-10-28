<?php
/**
 * Standalone tests for the native-booking proxy's pure functions (no network, no WordPress).
 * Never calls api.cal.com: upstream bodies below are fixtures shaped like the real v2
 * responses (recorded read-only from GET /v2/slots on 2026-10-27).
 *
 * Run: php wp-content/mu-plugins/tests/test-estimator-booking-api.php
 */

error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error { // phpcs:ignore
		private $c;
		public function __construct( $c = '' ) { $this->c = $c; }
		public function get_error_code() { return $this->c; }
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $x ) { return $x instanceof WP_Error; }
}
$GLOBALS['uls_actions'] = array();
$GLOBALS['uls_filters'] = array();
function do_action( $tag, ...$a ) { $GLOBALS['uls_actions'][] = $tag; }
function apply_filters( $tag, $v ) { return isset( $GLOBALS['uls_filters'][ $tag ] ) ? $GLOBALS['uls_filters'][ $tag ] : $v; }
class WP_REST_Response { // phpcs:ignore
	public $data; public $status; public $headers = array();
	public function __construct( $d = null, $s = 200 ) { $this->data = $d; $this->status = $s; }
	public function header( $k, $v ) { $this->headers[ $k ] = $v; }
}
class Fake_Req { // phpcs:ignore
	private $j; public function __construct( $j ) { $this->j = $j; }
	public function get_json_params() { return $this->j; }
	public function get_param( $k ) { return null; }
}

require __DIR__ . '/../uplinksync-estimator/config.php';
require __DIR__ . '/../uplinksync-estimator/booking-api.php';

$failures = 0;
function check( $name, $cond, $detail = '' ) {
	global $failures;
	echo ( $cond ? '  PASS  ' : '  FAIL  ' ) . $name . ( ! $cond && '' !== $detail ? "  ({$detail})" : '' ) . "\n";
	if ( ! $cond ) {
		$failures++;
	}
}

$today = '2026-10-27';
$ok    = array( 'event' => 'it', 'start' => '2026-11-01', 'end' => '2026-11-30' );

echo "GET /slots validation:\n";
$v = uls_estimator_validate_slots_params( $ok, $today );
check( 'valid params accepted; it => 7469726', is_array( $v ) && 7469726 === $v['eventTypeId'] );
$v2 = uls_estimator_validate_slots_params( array_merge( $ok, array( 'event' => 'uav' ) ), $today );
check( 'uav => 7469727', is_array( $v2 ) && 7469727 === $v2['eventTypeId'] );
check( 'unknown event key rejected', 'bad_event' === uls_estimator_validate_slots_params( array_merge( $ok, array( 'event' => 'uav-weekend' ) ), $today ) );
check( 'raw numeric event id rejected (whitelist keys only)', 'bad_event' === uls_estimator_validate_slots_params( array_merge( $ok, array( 'event' => '7469731' ) ), $today ) );
check( 'array event rejected', 'bad_event' === uls_estimator_validate_slots_params( array_merge( $ok, array( 'event' => array( 'it' ) ) ), $today ) );
check( '31-day span accepted', is_array( uls_estimator_validate_slots_params( array_merge( $ok, array( 'start' => '2026-11-01', 'end' => '2026-12-02' ) ), $today ) ) );
check( '32-day span rejected', 'range_too_long' === uls_estimator_validate_slots_params( array_merge( $ok, array( 'start' => '2026-11-01', 'end' => '2026-12-03' ) ), $today ) );
check( 'end before start rejected', 'bad_range' === uls_estimator_validate_slots_params( array_merge( $ok, array( 'start' => '2026-11-10', 'end' => '2026-11-01' ) ), $today ) );
check( 'impossible date rejected', 'bad_date' === uls_estimator_validate_slots_params( array_merge( $ok, array( 'start' => '2026-02-30' ) ), $today ) );
check( 'non-date rejected', 'bad_date' === uls_estimator_validate_slots_params( array_merge( $ok, array( 'start' => '2026-11-01T00:00:00Z' ) ), $today ) );
check( 'trailing-newline date rejected', 'bad_date' === uls_estimator_validate_slots_params( array_merge( $ok, array( 'start' => "2026-11-01\n" ) ), $today ) );
check( 'past range rejected', 'out_of_window' === uls_estimator_validate_slots_params( array_merge( $ok, array( 'start' => '2026-09-01', 'end' => '2026-09-10' ) ), $today ) );
check( 'start beyond 120 days rejected', 'out_of_window' === uls_estimator_validate_slots_params( array_merge( $ok, array( 'start' => '2027-03-01', 'end' => '2027-03-05' ) ), $today ) );
check( 'time zone is no longer part of the slots request (bounded cache keys)', ! isset( $v['timeZone'] ) );

echo "\nL2 month-window off-by-one (reproduced, now clamped):\n";
$jan = uls_estimator_validate_slots_params( array( 'event' => 'it', 'start' => '2027-01-01', 'end' => '2027-01-31' ), '2026-10-01' );
check( 'visitor on 2026-10-01 opening January: accepted, end clamped to 2027-01-29', is_array( $jan ) && '2027-01-29' === $jan['end'], json_encode( $jan ) );
$aug = uls_estimator_validate_slots_params( array( 'event' => 'it', 'start' => '2027-08-01', 'end' => '2027-08-31' ), '2027-05-01' );
check( 'visitor on 2027-05-01 opening August: accepted, end clamped to 2027-08-29', is_array( $aug ) && '2027-08-29' === $aug['end'], json_encode( $aug ) );
$d120 = uls_estimator_validate_slots_params( array( 'event' => 'it', 'start' => '2027-02-24', 'end' => '2027-02-24' ), '2026-10-27' );
check( '/book check for a slot on day 120 works (was 502)', is_array( $d120 ) );

echo "\nUpstream URL (SSRF + M4 bounded keys):\n";
$url = uls_estimator_slots_upstream_url( $v );
check( 'fixed host + path', 0 === strpos( $url, 'https://api.cal.com/v2/slots?' ), $url );
check( 'only the expected params, always UTC, ranged, window widened 1 day each side', 'eventTypeId=7469726&start=2026-10-31&end=2026-12-01&timeZone=UTC&format=range' === substr( $url, strlen( 'https://api.cal.com/v2/slots?' ) ), $url );
check( 'bookings URL is a constant', 'https://api.cal.com/v2/bookings' === ULS_ESTIMATOR_CAL_BOOKINGS_URL );
check( 'cal-api-version headers per recon', '2024-09-04' === ULS_ESTIMATOR_CAL_SLOTS_VER && '2024-08-13' === ULS_ESTIMATOR_CAL_BOOKINGS_VER );

echo "\nSlots output = times only:\n";
$fixture = json_encode(
	array(
		'status' => 'success',
		'data'   => array(
			'2026-11-02' => array( array( 'start' => '2026-11-02T17:30:00.000-07:00', 'attendees' => 1, 'bookingUid' => 'x' ), array( 'start' => '2026-11-02T18:00:00.000-07:00' ) ),
			'2026-11-03' => array( array( 'start' => '<script>alert(1)</script>' ), array( 'start' => '2026-11-03T18:00:00Z' ) ),
			'__proto__'  => array( array( 'start' => '2026-11-04T18:00:00Z' ) ),
		),
		'internal' => array( 'userId' => 42, 'email' => 'owner@example.com' ),
	)
);
$days = uls_estimator_extract_slots( $fixture );
check( 'flat, sorted UTC list of valid instants only', array( '2026-11-03T00:30:00Z', '2026-11-03T01:00:00Z', '2026-11-03T18:00:00Z' ) === $days, json_encode( $days ) );
check( 'drops non-ISO values and odd keys; no internal data', false === strpos( json_encode( $days ), 'script' ) && false === strpos( json_encode( $days ), 'owner@' ) && false === strpos( json_encode( $days ), 'bookingUid' ) && ! in_array( '2026-11-04T18:00:00Z', $days, true ) );
check( 'upstream error body => null', null === uls_estimator_extract_slots( '{"status":"error","error":{"message":"secret detail"}}' ) );
check( 'non-JSON => null', null === uls_estimator_extract_slots( '<html>502</html>' ) );
check( 'offered: same instant, different offset notation', uls_estimator_slot_offered( '2026-11-03T11:00:00-07:00', $days ) );
check( 'not offered: other time', ! uls_estimator_slot_offered( '2026-11-03T19:00:00Z', $days ) );
$ranged = json_encode( array( 'data' => array( '2026-11-02' => array( array( 'start' => '2026-11-02T17:30:00.000-07:00', 'end' => '2026-11-02T17:45:00.000-07:00' ) ) ) ) );
check( 'call length comes from Cal.com (15-min slot => 15)', 15 === uls_estimator_extract_length( $ranged ) );
check( 'length: 30-min ranged slot => 30', 30 === uls_estimator_extract_length( json_encode( array( 'data' => array( 'd' => array( array( 'start' => '2026-11-02T17:30:00Z', 'end' => '2026-11-02T18:00:00Z' ) ) ) ) ) ) );
check( 'length: no end / implausible => null (UI omits it)', null === uls_estimator_extract_length( $fixture ) && null === uls_estimator_extract_length( json_encode( array( 'data' => array( 'd' => array( array( 'start' => '2026-11-02T17:30:00Z', 'end' => '2026-11-03T17:30:00Z' ) ) ) ) ) ) );
check( 'ISO check rejects a trailing newline (L5)', ! uls_estimator_is_iso_instant( "2026-11-03T18:00:00Z\n" ) );

echo "\nPOST /book validation + payload:\n";
$it_body = array(
	'event'    => 'it',
	'start'    => '2026-11-02T17:30:00.000-07:00',
	'timeZone' => 'America/Denver',
	'name'     => ' Jane <b>Smith</b> ',
	'email'    => 'Jane@Example.com',
	'notes'    => "IT: Managed IT / support — \$300 – \$375 /month\n  • IT — Users: 5",
	'it'       => array( 'goal' => 'Systems / help-desk support', 'setup' => '' ),
	'uav'      => array( 'site' => 'ignored' ),
);
$r = uls_estimator_validate_booking( $it_body );
check( 'valid IT booking accepted', isset( $r['payload'] ), json_encode( $r ) );
$p = $r['payload'];
check( 'payload keys are exactly start/eventTypeId/attendee/bookingFieldsResponses', array( 'start', 'eventTypeId', 'attendee', 'bookingFieldsResponses' ) === array_keys( $p ) );
check( 'start normalised to UTC Z', '2026-11-03T00:30:00Z' === $p['start'], $p['start'] );
foreach ( array( 'Mars/Base', '../../etc/passwd', 'America/Denver&x=1', '', str_repeat( 'A', 80 ) ) as $bad ) {
	check( 'attendee bad timeZone rejected: ' . substr( $bad, 0, 20 ), 'bad_tz' === uls_estimator_validate_booking( array_merge( $it_body, array( 'timeZone' => $bad ) ) )['error'] );
}
check( 'eventTypeId from whitelist', 7469726 === $p['eventTypeId'] );
check( 'name tags stripped + trimmed', 'Jane Smith' === $p['attendee']['name'], $p['attendee']['name'] );
check( 'email lower-cased', 'jane@example.com' === $p['attendee']['email'] );
check( 'IT required fields present (goal + current-setup)', 'Systems / help-desk support' === $p['bookingFieldsResponses']['goal'] && '' !== $p['bookingFieldsResponses']['current-setup'] );
check( 'UAV fields not sent for IT', ! isset( $p['bookingFieldsResponses']['site-location'] ) );
check( 'notes carried (newlines kept)', false !== strpos( $p['bookingFieldsResponses']['notes'], "\n" ) );
check( 'stale self-host slug value rejected (new-website)', 'bad_goal' === uls_estimator_validate_booking( array_merge( $it_body, array( 'it' => array( 'goal' => 'new-website' ) ) ) )['error'] );
check( 'unknown event rejected', 'bad_event' === uls_estimator_validate_booking( array_merge( $it_body, array( 'event' => 'uav-service' ) ) )['error'] );
check( 'bad email rejected', 'bad_email' === uls_estimator_validate_booking( array_merge( $it_body, array( 'email' => 'a@b' ) ) )['error'] );
check( 'header-injection email rejected', 'bad_email' === uls_estimator_validate_booking( array_merge( $it_body, array( 'email' => "a@b.com\r\nBcc: x@y.com" ) ) )['error'] );
check( 'empty name rejected', 'bad_name' === uls_estimator_validate_booking( array_merge( $it_body, array( 'name' => '<i></i>' ) ) )['error'] );
check( 'non-ISO start rejected', 'bad_start' === uls_estimator_validate_booking( array_merge( $it_body, array( 'start' => 'tomorrow 6pm' ) ) )['error'] );
check( 'bad tz rejected', 'bad_tz' === uls_estimator_validate_booking( array_merge( $it_body, array( 'timeZone' => 'Nowhere/Land' ) ) )['error'] );
check( 'non-array body rejected', 'bad_body' === uls_estimator_validate_booking( 'x' )['error'] );
$long = uls_estimator_validate_booking( array_merge( $it_body, array( 'notes' => str_repeat( 'n', 5000 ), 'name' => str_repeat( 'N', 300 ) ) ) );
check( 'length caps (notes 2000, name 100)', 2000 === preg_match_all( '/./us', $long['payload']['bookingFieldsResponses']['notes'] ) && 100 === preg_match_all( '/./us', $long['payload']['attendee']['name'] ) );

$uav_body = array(
	'event'    => 'uav',
	'start'    => '2026-11-03T18:00:00Z',
	'timeZone' => 'America/Denver',
	'name'     => 'Sam',
	'email'    => 'sam@example.com',
	'uav'      => array( 'site' => '12 Main St', 'siteType' => 'Residential / roof', 'line' => 'real_estate', 'video' => true ),
);
$u = uls_estimator_validate_booking( $uav_body );
check( 'valid UAV booking accepted; eventTypeId 7469727', isset( $u['payload'] ) && 7469727 === $u['payload']['eventTypeId'], json_encode( $u ) );
$ur = $u['payload']['bookingFieldsResponses'];
check( 'UAV required fields present', '12 Main St' === $ur['site-location'] && 'Residential / roof' === $ur['site-type'] && array( 'Photos', 'Video' ) === $ur['deliverables'] );
$bk = uls_estimator_validate_booking( array_merge( $uav_body, array( 'uav' => array( 'site' => 'Lot 4', 'siteType' => 'Event', 'deliverables' => array( 'Video', 'Photos', 'Video' ) ) ) ) );
check( '"Book a call" path: explicit deliverables accepted (deduped), no line needed', isset( $bk['payload'] ) && array( 'Video', 'Photos' ) === $bk['payload']['bookingFieldsResponses']['deliverables'], json_encode( $bk ) );
foreach ( array( array(), array( 'photos' ), array( 'Photos', array( 'x' ) ), 'Photos' ) as $bad ) {
	check( 'bad explicit deliverables rejected: ' . json_encode( $bad ), 'bad_deliverables' === uls_estimator_validate_booking( array_merge( $uav_body, array( 'uav' => array( 'site' => 'x', 'siteType' => 'Event', 'deliverables' => $bad ) ) ) )['error'] );
}
check( 'missing site rejected', 'bad_site' === uls_estimator_validate_booking( array_merge( $uav_body, array( 'uav' => array( 'siteType' => 'Event', 'line' => 'tower' ) ) ) )['error'] );
check( 'site-type outside whitelist rejected', 'bad_site_type' === uls_estimator_validate_booking( array_merge( $uav_body, array( 'uav' => array( 'site' => 'x', 'siteType' => 'residential-roof', 'line' => 'tower' ) ) ) )['error'] );
check( 'unknown UAV line rejected', 'bad_line' === uls_estimator_validate_booking( array_merge( $uav_body, array( 'uav' => array( 'site' => 'x', 'siteType' => 'Event', 'line' => 'rm -rf' ) ) ) )['error'] );

echo "\nBooking response = start/end only:\n";
$resp = json_encode( array( 'status' => 'success', 'data' => array( 'id' => 1, 'uid' => 'abc', 'start' => '2026-11-03T00:30:00.000Z', 'end' => '2026-11-03T01:00:00.000Z', 'hosts' => array( array( 'email' => 'owner@example.com' ) ), 'meetingUrl' => 'https://app.cal.com/video/abc' ) ) );
$out  = uls_estimator_extract_booking( 201, $resp );
check( 'success => start + end only', array( 'start' => '2026-11-03T00:30:00.000Z', 'end' => '2026-11-03T01:00:00.000Z' ) === $out, json_encode( $out ) );
check( '400 => null (never echoed)', null === uls_estimator_extract_booking( 400, '{"status":"error","error":{"message":"responses - {goal}error_required_field"}}' ) );
check( 'status error in 200 => null', null === uls_estimator_extract_booking( 200, '{"status":"error"}' ) );
check( 'garbage => null', null === uls_estimator_extract_booking( 201, 'nope' ) );

echo "\nRate-limit keys (M1):\n";
check( 'IPv6 bucketed by /64', '2001:db8:1:2::/64' === uls_estimator_ip_bucket( '2001:db8:1:2:aaaa:bbbb:cccc:dddd' ) && uls_estimator_ip_bucket( '2001:db8:1:2::1' ) === uls_estimator_ip_bucket( '2001:db8:1:2:ffff::9' ) );
check( 'different /64s differ', uls_estimator_ip_bucket( '2001:db8:1:2::1' ) !== uls_estimator_ip_bucket( '2001:db8:1:3::1' ) );
check( 'IPv4 kept whole', '198.51.100.7' === uls_estimator_ip_bucket( '198.51.100.7' ) && 'invalid' === uls_estimator_ip_bucket( 'nope' ) );
check( 'email: lower-case + strip +tag', 'victim@example.com' === uls_estimator_normalise_email( 'Victim+1@Example.com' ) );
check( 'gmail: dots + tag stripped, googlemail folded', 'johnsmith@gmail.com' === uls_estimator_normalise_email( 'John.Smith+x@googlemail.com' ) && 'johnsmith@gmail.com' === uls_estimator_normalise_email( 'j.o.h.n.smith@gmail.com' ) );
check( 'non-gmail dots kept', 'john.smith@example.com' === uls_estimator_normalise_email( 'john.smith@example.com' ) );
check( 'daily cap default 20', 20 === ULS_ESTIMATOR_DAILY_CAP_DEFAULT && 300 === ULS_ESTIMATOR_UPSTREAM_BUDGET );
$src = file_get_contents( __DIR__ . '/../uplinksync-estimator/booking-api.php' );
check( 'counters are atomic SQL increments on expiring transient rows', false !== strpos( $src, 'SET option_value = option_value + 1' ) && false !== strpos( $src, '_transient_timeout_' ) && false !== strpos( $src, 'wp_cache_incr' ) );
check( 'per-email limit uses the normalised address; daily cap + upstream budget enforced', false !== strpos( $src, "'book-email|' . uls_estimator_normalise_email(" ) && false !== strpos( $src, "'book-day', 86400 ) > uls_estimator_daily_cap()" ) && false !== strpos( $src, "'upstream-budget', 600 ) > ULS_ESTIMATOR_UPSTREAM_BUDGET" ) );
check( 'counter/cache names are keyed hashes (no md5 of email)', false === strpos( $src, 'md5(' ) && false !== strpos( $src, 'hash_hmac' ) );

echo "\nClient IP (M2) — production behind Cloudflare, staging direct:\n";
check( 'staging (direct peer): CF header ignored', '203.0.113.9' === uls_estimator_client_ip( array( 'REMOTE_ADDR' => '203.0.113.9', 'HTTP_CF_CONNECTING_IP' => '1.2.3.4' ) ) );
check( 'staging: X-Forwarded-For never trusted', '203.0.113.9' === uls_estimator_client_ip( array( 'REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4' ) ) );
check( 'production (Cloudflare peer v4): CF-Connecting-IP used', '198.51.100.7' === uls_estimator_client_ip( array( 'REMOTE_ADDR' => '104.16.1.1', 'HTTP_CF_CONNECTING_IP' => '198.51.100.7' ) ) );
check( 'production (Cloudflare peer v6): CF-Connecting-IP used', '2001:db8::1' === uls_estimator_client_ip( array( 'REMOTE_ADDR' => '2606:4700::1', 'HTTP_CF_CONNECTING_IP' => '2001:db8::1' ) ) );
check( 'production: XFF ignored even from Cloudflare', '104.16.1.1' === uls_estimator_client_ip( array( 'REMOTE_ADDR' => '104.16.1.1', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4' ) ) );
check( 'garbage CF header ignored', '104.16.1.1' === uls_estimator_client_ip( array( 'REMOTE_ADDR' => '104.16.1.1', 'HTTP_CF_CONNECTING_IP' => 'evil, 1.2.3.4' ) ) );
check( 'ranges are a dated constant', false !== strpos( $src, 'checked 2026-10-27' ) && in_array( '104.16.0.0/13', ULS_ESTIMATOR_CLOUDFLARE_RANGES, true ) );
$GLOBALS['uls_filters']['uls_estimator_cloudflare_ranges'] = array( '203.0.113.0/24' );
check( 'ranges filterable', '9.9.9.9' === uls_estimator_client_ip( array( 'REMOTE_ADDR' => '203.0.113.9', 'HTTP_CF_CONNECTING_IP' => '9.9.9.9' ) ) );
unset( $GLOBALS['uls_filters']['uls_estimator_cloudflare_ranges'] );

echo "\nPage-cache bypass (H1):\n";
uls_estimator_no_page_cache();
check( 'litespeed_control_set_nocache fired', in_array( 'litespeed_control_set_nocache', $GLOBALS['uls_actions'], true ) );
check( 'DONOTCACHEPAGE defined', defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE );
$r = uls_estimator_nocache( new WP_REST_Response( array() ) );
check( 'Cache-Control: no-store, private', false !== strpos( $r->headers['Cache-Control'], 'no-store' ) && false !== strpos( $r->headers['Cache-Control'], 'private' ) );
foreach ( array( 'uls_estimator_rest_slots', 'uls_estimator_rest_book', 'uls_estimator_book_permission' ) as $fn ) {
	$rf = new ReflectionFunction( $fn );
	$body = implode( '', array_slice( file( $rf->getFileName() ), $rf->getStartLine(), 3 ) );
	check( "{$fn} disables page cache first", false !== strpos( $body, 'uls_estimator_no_page_cache();' ) );
}

echo "\nHoneypot (L3):\n";
$GLOBALS['uls_actions'] = array();
$hp = uls_estimator_rest_book( new Fake_Req( array_merge( $it_body, array( 'hp' => 'http://spam.example' ) ) ) );
check( 'filled trap => 400 generic error with the plain booking link, never a fake "confirmed"', 400 === $hp->status && false === $hp->data['ok'] && 'rejected' === $hp->data['error'] && 0 === strpos( $hp->data['fallback'], 'https://book.uplinksync.com/' ) );
$mk = file_get_contents( __DIR__ . '/../uplinksync-estimator/markup.php' );
check( 'trap field: random name, no label text, aria-hidden, tabindex -1, autocomplete off', false === strpos( $mk, 'Company website' ) && false !== strpos( $mk, 'data-qf-trap="1" tabindex="-1" autocomplete="off"' ) && false !== strpos( $mk, '<div class="qf-hp" aria-hidden="true">' ) );

echo "\nRequired Cal.com booking fields (public GET /v2/teams/467448/event-types, 2026-10-27):\n";
$required = array( 'it' => array( 'goal', 'current-setup' ), 'uav' => array( 'site-location', 'site-type', 'deliverables' ) );
check( 'IT payload carries every required field', ! array_diff( $required['it'], array_keys( $p['bookingFieldsResponses'] ) ) && '' !== $p['attendee']['name'] && '' !== $p['attendee']['email'] );
check( 'UAV payload carries every required field', ! array_diff( $required['uav'], array_keys( $ur ) ) );

echo "\nREST lock exception (exact routes only):\n";
$ase = new WP_Error( 'rest_api_authentication_required' );
$GLOBALS['wp'] = (object) array( 'query_vars' => array( 'rest_route' => '/uplinksync/v1/slots' ) );
check( '/uplinksync/v1/slots opened', true === uls_estimator_rest_auth_allow( $ase ) );
$GLOBALS['wp']->query_vars['rest_route'] = '/uplinksync/v1/book';
check( '/uplinksync/v1/book opened', true === uls_estimator_rest_auth_allow( $ase ) );
foreach ( array( '/wp/v2/users', '/uplinksync/v1/slots/../../wp/v2/users', '/uplinksync/v1', '/uplinksync/v1/slotsx' ) as $rt ) {
	$GLOBALS['wp']->query_vars['rest_route'] = $rt;
	check( 'still locked: ' . $rt, $ase === uls_estimator_rest_auth_allow( $ase ) );
}
$GLOBALS['wp']->query_vars['rest_route'] = '/uplinksync/v1/slots';
$other = new WP_Error( 'rest_cookie_invalid_nonce' );
check( 'other auth errors pass through untouched', $other === uls_estimator_rest_auth_allow( $other ) );
check( 'non-error results untouched', null === uls_estimator_rest_auth_allow( null ) );

echo "\nFallback link:\n";
check( 'it => book.uplinksync.com/team/uplinksync/it-consult', 'https://book.uplinksync.com/team/uplinksync/it-consult' === uls_estimator_fallback_url( 'it' ) );
check( 'uav => …/uav-consult; unknown => it', 'https://book.uplinksync.com/team/uplinksync/uav-consult' === uls_estimator_fallback_url( 'uav' ) && uls_estimator_fallback_url( 'x' ) === uls_estimator_fallback_url( 'it' ) );

echo "\n" . ( 0 === $failures ? "ALL PASS\n" : "{$failures} FAILURE(S)\n" );
exit( 0 === $failures ? 0 : 1 );
