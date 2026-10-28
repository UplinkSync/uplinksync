<?php
/**
 * UplinkSync estimator — native booking proxy (WP REST, namespace uplinksync/v1).
 *
 *   GET  /wp-json/uplinksync/v1/slots?event=it|uav&start=YYYY-MM-DD&end=YYYY-MM-DD&timeZone=IANA
 *   POST /wp-json/uplinksync/v1/book   (JSON body, see uls_estimator_validate_booking())
 *
 * The browser only ever talks to this same-origin endpoint. The server calls exactly two
 * FIXED upstream URLs (ULS_ESTIMATOR_CAL_SLOTS_URL / ULS_ESTIMATOR_CAL_BOOKINGS_URL); no part
 * of a request path or host is ever derived from visitor input (SSRF). Cal.com Cloud's public
 * v2 endpoints need no API key (recon 2026-10-27), so there is no secret here at all.
 *
 * Security controls (see the MR description for the full review):
 * - event id from a server-side whitelist (it => 7469726, uav => 7469727);
 * - date range ≤ 31 days, end clamped to 120 days ahead; availability always fetched in UTC
 *   (bounded cache keys) and grouped into the visitor's days client-side;
 * - every page cache kept out (LiteSpeed API + DONOTCACHEPAGE + no-store, private);
 * - POST: a nonce issued by GET /slots, honeypot (generic error), atomic per-IP (IPv6 /64) and
 *   per-normalised-email limits, a site-wide daily cap, a global upstream budget, the chosen
 *   start must be in the live slot list, atomic duplicate-submit dedupe, length caps,
 *   whitelist-only select values;
 * - client IP: CF-Connecting-IP only from a Cloudflare peer; X-Forwarded-For never;
 * - responses carry slot times / booking start-end only — never upstream bodies or errors;
 * - logs carry status codes and the event key only; counter names are keyed hashes.
 *
 * The pure functions (no WordPress calls) are unit-tested in
 * tests/test-estimator-booking-api.php.
 */

defined( 'ABSPATH' ) || exit;

const ULS_ESTIMATOR_CAL_SLOTS_URL    = 'https://api.cal.com/v2/slots';
const ULS_ESTIMATOR_CAL_BOOKINGS_URL = 'https://api.cal.com/v2/bookings';
const ULS_ESTIMATOR_CAL_SLOTS_VER    = '2024-09-04';
const ULS_ESTIMATOR_CAL_BOOKINGS_VER = '2024-08-13';
const ULS_ESTIMATOR_FALLBACK_ORIGIN  = 'https://book.uplinksync.com';
const ULS_ESTIMATOR_NONCE_ACTION     = 'uls_estimator_book';
const ULS_ESTIMATOR_MAX_RANGE_DAYS   = 31;
const ULS_ESTIMATOR_MAX_AHEAD_DAYS   = 120;
const ULS_ESTIMATOR_SLOT_CACHE_TTL   = 60;
const ULS_ESTIMATOR_ROUTES           = array( '/uplinksync/v1/slots', '/uplinksync/v1/book' );
const ULS_ESTIMATOR_UPSTREAM_BUDGET  = 300; // Upstream Cal.com calls per 10 min, site-wide.
const ULS_ESTIMATOR_DAILY_CAP_DEFAULT = 20; // Booking attempts per day (option uls_estimator_daily_cap).

/*
 * ------------------------------------------------------------------------------------------
 * Pure helpers (unit-tested; no WordPress dependencies).
 * ------------------------------------------------------------------------------------------
 */

/**
 * Is $tz a real IANA zone (including backward-compatible links browsers may report)?
 *
 * @param mixed $tz Candidate.
 * @return bool
 */
function uls_estimator_valid_tz( $tz ) {
	if ( ! is_string( $tz ) || '' === $tz || strlen( $tz ) > 64 ) {
		return false;
	}
	static $zones = null;
	if ( null === $zones ) {
		$zones = array_flip( DateTimeZone::listIdentifiers( DateTimeZone::ALL_WITH_BC ) );
	}
	return isset( $zones[ $tz ] );
}

/**
 * Strict Y-m-d parser.
 *
 * @param mixed $s Candidate.
 * @return DateTimeImmutable|null UTC midnight of that date.
 */
function uls_estimator_parse_date( $s ) {
	if ( ! is_string( $s ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s ) ) {
		return null;
	}
	$d = DateTimeImmutable::createFromFormat( '!Y-m-d', $s, new DateTimeZone( 'UTC' ) );
	if ( ! $d || $d->format( 'Y-m-d' ) !== $s ) {
		return null;
	}
	return $d;
}

/**
 * Validate GET /slots parameters. The visitor's time zone is NOT part of this: availability is
 * always fetched in UTC (bounded cache keys) and grouped into days client-side (review M4).
 * The end date is CLAMPED to the booking horizon instead of rejected (review L2).
 *
 * @param array  $p     Raw params (event, start, end).
 * @param string $today Y-m-d "today" in UTC (injectable for tests).
 * @return array|string [event, eventTypeId, start, end, upStart, upEnd] or an error code.
 */
function uls_estimator_validate_slots_params( $p, $today ) {
	$events = uls_estimator_events();
	$event  = isset( $p['event'] ) ? $p['event'] : '';
	if ( ! is_string( $event ) || ! isset( $events[ $event ] ) ) {
		return 'bad_event';
	}
	$start = uls_estimator_parse_date( isset( $p['start'] ) ? $p['start'] : null );
	$end   = uls_estimator_parse_date( isset( $p['end'] ) ? $p['end'] : null );
	$t0    = uls_estimator_parse_date( $today );
	if ( ! $start || ! $end || ! $t0 ) {
		return 'bad_date';
	}
	if ( $end < $start ) {
		return 'bad_range';
	}
	if ( (int) $start->diff( $end )->days > ULS_ESTIMATOR_MAX_RANGE_DAYS ) {
		return 'range_too_long';
	}
	$horizon = $t0->modify( '+' . ULS_ESTIMATOR_MAX_AHEAD_DAYS . ' days' );
	// Allow "yesterday" (UTC) so visitors ahead of UTC still see today.
	if ( $start < $t0->modify( '-1 day' ) || $start > $horizon ) {
		return 'out_of_window';
	}
	if ( $end > $horizon ) {
		$end = $horizon;
	}
	// Upstream window in UTC, one day wider each side so every visitor zone's local days are covered.
	return array(
		'event'       => $event,
		'eventTypeId' => (int) $events[ $event ]['id'],
		'start'       => $start->format( 'Y-m-d' ),
		'end'         => $end->format( 'Y-m-d' ),
		'upStart'     => $start->modify( '-1 day' )->format( 'Y-m-d' ),
		'upEnd'       => $end->modify( '+1 day' )->format( 'Y-m-d' ),
	);
}

/**
 * The ONLY upstream slots URL this code can produce: fixed base + encoded validated params,
 * always in UTC.
 *
 * @param array $v Output of uls_estimator_validate_slots_params().
 * @return string
 */
function uls_estimator_slots_upstream_url( $v ) {
	return ULS_ESTIMATOR_CAL_SLOTS_URL . '?' . http_build_query(
		array(
			'eventTypeId' => (int) $v['eventTypeId'],
			'start'       => $v['upStart'],
			'end'         => $v['upEnd'],
			'timeZone'    => 'UTC',
			'format'      => 'range', // start + end per slot, so the call length comes from Cal.com.
		),
		'',
		'&',
		PHP_QUERY_RFC3986
	);
}

/**
 * Strict ISO-8601 instant with offset or Z (what Cal.com returns and what we accept back).
 * `D` modifier: `$` must not match before a trailing newline (review L5).
 *
 * @param mixed $s Candidate.
 * @return bool
 */
function uls_estimator_is_iso_instant( $s ) {
	return is_string( $s )
		&& (bool) preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d{1,3})?)?(Z|[+-]\d{2}:\d{2})$/D', $s );
}

/**
 * Reduce an upstream slots body to a sorted list of UTC instants — times only, nothing else.
 *
 * @param string $body Raw upstream JSON.
 * @return string[]|null Null when the body is not the expected shape.
 */
function uls_estimator_extract_slots( $body ) {
	$j = json_decode( (string) $body, true );
	if ( ! is_array( $j ) || ! isset( $j['data'] ) || ! is_array( $j['data'] ) ) {
		return null;
	}
	$out = array();
	foreach ( $j['data'] as $day => $list ) {
		if ( ! is_string( $day ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $day ) || ! is_array( $list ) ) {
			continue;
		}
		foreach ( $list as $slot ) {
			$s = is_array( $slot ) && isset( $slot['start'] ) ? $slot['start'] : ( is_string( $slot ) ? $slot : null );
			if ( count( $out ) < 1000 && uls_estimator_is_iso_instant( $s ) && false !== ( $t = strtotime( $s ) ) ) {
				$out[ $t ] = gmdate( 'Y-m-d\TH:i:s\Z', $t );
			}
		}
	}
	ksort( $out );
	return array_values( $out );
}

/**
 * Call length in minutes as Cal.com reports it (end - start of the first ranged slot), so the
 * UI never hard-codes it (owner is moving both events to 15 min). Null when absent/implausible.
 *
 * @param string $body Raw upstream JSON.
 * @return int|null
 */
function uls_estimator_extract_length( $body ) {
	$j = json_decode( (string) $body, true );
	if ( ! is_array( $j ) || ! isset( $j['data'] ) || ! is_array( $j['data'] ) ) {
		return null;
	}
	foreach ( $j['data'] as $list ) {
		foreach ( (array) $list as $slot ) {
			if ( is_array( $slot ) && uls_estimator_is_iso_instant( isset( $slot['start'] ) ? $slot['start'] : null ) && uls_estimator_is_iso_instant( isset( $slot['end'] ) ? $slot['end'] : null ) ) {
				$m = (int) round( ( strtotime( $slot['end'] ) - strtotime( $slot['start'] ) ) / 60 );
				return ( $m >= 5 && $m <= 240 ) ? $m : null;
			}
		}
	}
	return null;
}

/**
 * Is $start (ISO instant) one of the offered slots? Compared as UNIX timestamps so offset
 * formatting differences ("Z" vs "-06:00") cannot cause a false negative.
 *
 * @param string   $start ISO instant.
 * @param string[] $slots Output of uls_estimator_extract_slots().
 * @return bool
 */
function uls_estimator_slot_offered( $start, $slots ) {
	$t = strtotime( $start );
	if ( false === $t ) {
		return false;
	}
	foreach ( (array) $slots as $s ) {
		if ( strtotime( $s ) === $t ) {
			return true;
		}
	}
	return false;
}

/**
 * Trim, strip tags/control chars and cap a single-line or multi-line text value.
 *
 * @param mixed $s         Value.
 * @param int   $max       Max characters.
 * @param bool  $multiline Keep newlines.
 * @return string
 */
function uls_estimator_clean_text( $s, $max, $multiline = false ) {
	if ( ! is_string( $s ) ) {
		return '';
	}
	$s = strip_tags( $s );
	$s = $multiline
		? preg_replace( '/[^\P{C}\n]+/u', '', str_replace( "\r", '', $s ) )
		: preg_replace( '/\p{C}+/u', ' ', $s );
	$s = trim( preg_replace( $multiline ? '/[ \t]+/' : '/\s+/', ' ', (string) $s ) );
	if ( function_exists( 'mb_substr' ) ) {
		return mb_substr( $s, 0, $max );
	}
	return substr( $s, 0, $max );
}

/**
 * Validate a POST /book body and build the exact upstream payload.
 *
 * Body: { event, start, timeZone, name, email, notes, hp,
 *         it:  { goal, setup }                         (event "it")
 *         uav: { site, siteType, line, video, report } (event "uav") }
 *
 * @param array $b Decoded JSON body.
 * @return array ['payload' => array, 'email' => string, 'event' => string] or ['error' => code].
 */
function uls_estimator_validate_booking( $b ) {
	if ( ! is_array( $b ) ) {
		return array( 'error' => 'bad_body' );
	}
	$events = uls_estimator_events();
	$opts   = uls_estimator_booking_options();
	$event  = isset( $b['event'] ) && is_string( $b['event'] ) ? $b['event'] : '';
	if ( ! isset( $events[ $event ] ) ) {
		return array( 'error' => 'bad_event' );
	}
	$start = isset( $b['start'] ) ? $b['start'] : '';
	if ( ! uls_estimator_is_iso_instant( $start ) || false === strtotime( $start ) ) {
		return array( 'error' => 'bad_start' );
	}
	$tz = isset( $b['timeZone'] ) ? $b['timeZone'] : '';
	if ( ! uls_estimator_valid_tz( $tz ) ) {
		return array( 'error' => 'bad_tz' );
	}
	$name = uls_estimator_clean_text( isset( $b['name'] ) ? $b['name'] : '', 100 );
	if ( '' === $name ) {
		return array( 'error' => 'bad_name' );
	}
	$email = isset( $b['email'] ) && is_string( $b['email'] ) ? strtolower( trim( $b['email'] ) ) : '';
	if ( strlen( $email ) > 254 || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
		return array( 'error' => 'bad_email' );
	}
	$notes = uls_estimator_clean_text( isset( $b['notes'] ) ? $b['notes'] : '', 2000, true );

	$responses = array();
	if ( 'it' === $event ) {
		$it   = isset( $b['it'] ) && is_array( $b['it'] ) ? $b['it'] : array();
		$goal = isset( $it['goal'] ) && is_string( $it['goal'] ) ? $it['goal'] : '';
		if ( ! in_array( $goal, $opts['goal'], true ) ) {
			return array( 'error' => 'bad_goal' );
		}
		$setup = uls_estimator_clean_text( isset( $it['setup'] ) ? $it['setup'] : '', 1000, true );
		if ( '' === $setup ) {
			$setup = '' !== $notes ? uls_estimator_clean_text( $notes, 1000, true ) : 'Submitted via the website estimator.';
		}
		$responses['goal']          = $goal;
		$responses['current-setup'] = $setup;
	} else {
		$uav  = isset( $b['uav'] ) && is_array( $b['uav'] ) ? $b['uav'] : array();
		$site = uls_estimator_clean_text( isset( $uav['site'] ) ? $uav['site'] : '', 200 );
		if ( '' === $site ) {
			return array( 'error' => 'bad_site' );
		}
		$stype = isset( $uav['siteType'] ) && is_string( $uav['siteType'] ) ? $uav['siteType'] : '';
		if ( ! in_array( $stype, $opts['site-type'], true ) ) {
			return array( 'error' => 'bad_site_type' );
		}
		if ( isset( $uav['deliverables'] ) ) {
			// "Book a call" path: the visitor picked deliverables explicitly (whitelist only).
			$d = is_array( $uav['deliverables'] ) ? array_values( array_unique( $uav['deliverables'], SORT_REGULAR ) ) : array();
			if ( ! $d || count( $d ) > count( $opts['deliverables'] ) || array_diff( $d, $opts['deliverables'] ) || array_filter( $d, function ( $x ) { return ! is_string( $x ); } ) ) {
				return array( 'error' => 'bad_deliverables' );
			}
			$deliverables = $d;
		} else {
			// Estimator path: derived from the UAV answers.
			$line = isset( $uav['line'] ) && is_string( $uav['line'] ) ? $uav['line'] : '';
			if ( ! in_array( $line, array( 'real_estate', 'mapping', 'inspection', 'tower' ), true ) ) {
				return array( 'error' => 'bad_line' );
			}
			$deliverables = uls_estimator_default_deliverables( $line, ! empty( $uav['video'] ), ! empty( $uav['report'] ) );
		}
		$responses['site-location'] = $site;
		$responses['site-type']     = $stype;
		$responses['deliverables']  = $deliverables;
	}
	if ( '' !== $notes ) {
		$responses['notes'] = $notes;
	}

	$payload = array(
		'start'                  => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $start ) ),
		'eventTypeId'            => (int) $events[ $event ]['id'],
		'attendee'               => array(
			'name'     => $name,
			'email'    => $email,
			'timeZone' => $tz,
			'language' => 'en',
		),
		'bookingFieldsResponses' => $responses,
	);

	return array( 'payload' => $payload, 'email' => $email, 'event' => $event );
}

/**
 * Reduce an upstream booking response to { start, end } — nothing else leaves the server.
 *
 * @param int    $code HTTP status.
 * @param string $body Raw upstream JSON.
 * @return array|null
 */
function uls_estimator_extract_booking( $code, $body ) {
	if ( $code < 200 || $code >= 300 ) {
		return null;
	}
	$j = json_decode( (string) $body, true );
	if ( ! is_array( $j ) || ( isset( $j['status'] ) && 'success' !== $j['status'] ) || empty( $j['data'] ) || ! is_array( $j['data'] ) ) {
		return null;
	}
	$d = $j['data'];
	if ( isset( $d[0] ) && is_array( $d[0] ) ) { // Recurring/array shape: take the first.
		$d = $d[0];
	}
	$start = isset( $d['start'] ) ? $d['start'] : null;
	$end   = isset( $d['end'] ) ? $d['end'] : null;
	if ( ! uls_estimator_is_iso_instant( $start ) ) {
		return null;
	}
	return array(
		'start' => $start,
		'end'   => uls_estimator_is_iso_instant( $end ) ? $end : null,
	);
}

/**
 * Rate-limit bucket for an IP: IPv4 as-is, IPv6 by its /64 (a single client controls a whole
 * /64, so per-address buckets are free to rotate — review M1).
 *
 * @param string $ip Address.
 * @return string
 */
function uls_estimator_ip_bucket( $ip ) {
	$bin = @inet_pton( (string) $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( false === $bin ) {
		return 'invalid';
	}
	if ( 16 === strlen( $bin ) ) {
		return inet_ntop( substr( $bin, 0, 8 ) . str_repeat( "\0", 8 ) ) . '/64';
	}
	return inet_ntop( $bin );
}

/**
 * Canonical mailbox for the per-e-mail limit: lower-case, "+tag" removed, and for Gmail the dots
 * removed and googlemail.com folded into gmail.com. Only used as a rate-limit KEY — the booking
 * itself keeps the address exactly as typed.
 *
 * @param string $email Validated address.
 * @return string
 */
function uls_estimator_normalise_email( $email ) {
	$email = strtolower( trim( (string) $email ) );
	$at    = strrpos( $email, '@' );
	if ( false === $at ) {
		return $email;
	}
	$local  = substr( $email, 0, $at );
	$domain = substr( $email, $at + 1 );
	$plus   = strpos( $local, '+' );
	if ( false !== $plus ) {
		$local = substr( $local, 0, $plus );
	}
	if ( 'googlemail.com' === $domain ) {
		$domain = 'gmail.com';
	}
	if ( 'gmail.com' === $domain ) {
		$local = str_replace( '.', '', $local );
	}
	return $local . '@' . $domain;
}

/**
 * Is $ip inside one of the CIDRs? (IPv4 + IPv6.)
 *
 * @param string   $ip    Address.
 * @param string[] $cidrs Ranges.
 * @return bool
 */
function uls_estimator_ip_in( $ip, $cidrs ) {
	$bin = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( false === $bin ) {
		return false;
	}
	foreach ( $cidrs as $cidr ) {
		list( $net, $bits ) = explode( '/', $cidr );
		$nbin = @inet_pton( $net ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( false === $nbin || strlen( $nbin ) !== strlen( $bin ) ) {
			continue;
		}
		$bits  = (int) $bits;
		$bytes = intdiv( $bits, 8 );
		$rem   = $bits % 8;
		if ( substr( $bin, 0, $bytes ) !== substr( $nbin, 0, $bytes ) ) {
			continue;
		}
		if ( 0 === $rem ) {
			return true;
		}
		$mask = ( 0xff << ( 8 - $rem ) ) & 0xff;
		if ( ( ord( $bin[ $bytes ] ) & $mask ) === ( ord( $nbin[ $bytes ] ) & $mask ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Cloudflare's published edge ranges, https://www.cloudflare.com/ips/ — checked 2026-10-27.
 * Re-check yearly; override without a deploy via the `uls_estimator_cloudflare_ranges` filter.
 */
const ULS_ESTIMATOR_CLOUDFLARE_RANGES = array(
	'173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
	'108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
	'162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
	'2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
	'2a06:98c0::/29', '2c0f:f248::/32',
);

/**
 * Cloudflare ranges (filterable).
 *
 * @return string[]
 */
function uls_estimator_cloudflare_ranges() {
	$r = ULS_ESTIMATOR_CLOUDFLARE_RANGES;
	if ( function_exists( 'apply_filters' ) ) {
		$f = apply_filters( 'uls_estimator_cloudflare_ranges', $r );
		$r = is_array( $f ) ? $f : $r;
	}
	return $r;
}

/**
 * Client IP for rate limiting (review M2). Production is behind Cloudflare; staging is not.
 * CF-Connecting-IP is trusted ONLY when the TCP peer (REMOTE_ADDR) is a published Cloudflare
 * edge address. X-Forwarded-For is never trusted.
 *
 * @param array $server $_SERVER-like array.
 * @return string
 */
function uls_estimator_client_ip( $server ) {
	$peer = isset( $server['REMOTE_ADDR'] ) ? trim( (string) $server['REMOTE_ADDR'] ) : '';
	$cf   = isset( $server['HTTP_CF_CONNECTING_IP'] ) ? trim( (string) $server['HTTP_CF_CONNECTING_IP'] ) : '';
	if ( '' !== $cf && false !== @inet_pton( $cf ) && uls_estimator_ip_in( $peer, uls_estimator_cloudflare_ranges() ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return $cf;
	}
	return $peer;
}

/**
 * Plain fallback booking URL (book.uplinksync.com redirects to the Cal.com Cloud page).
 *
 * @param string $event it|uav.
 * @return string
 */
function uls_estimator_fallback_url( $event ) {
	$events = uls_estimator_events();
	$e      = isset( $events[ $event ] ) ? $events[ $event ] : $events['it'];
	return ULS_ESTIMATOR_FALLBACK_ORIGIN . '/' . $e['slug'];
}

/*
 * ------------------------------------------------------------------------------------------
 * WordPress wiring.
 * ------------------------------------------------------------------------------------------
 */

/**
 * Register the two routes.
 */
function uls_estimator_register_routes() {
	register_rest_route(
		'uplinksync/v1',
		'/slots',
		array(
			'methods'             => 'GET',
			'callback'            => 'uls_estimator_rest_slots',
			'permission_callback' => '__return_true', // Public by design; validated + rate-limited in the callback.
		)
	);
	register_rest_route(
		'uplinksync/v1',
		'/book',
		array(
			'methods'             => 'POST',
			'callback'            => 'uls_estimator_rest_book',
			'permission_callback' => 'uls_estimator_book_permission',
		)
	);
}

/**
 * The site-wide REST lock (Admin Site Enhancements "Disable REST API") answers anonymous
 * requests with rest_api_authentication_required. Re-open EXACTLY these two routes — matched
 * on the parsed REST route, never on the raw URI/query string (see snippet 091's 2026-08-14
 * SEC fix for why). Every other route keeps the lock.
 *
 * @param mixed $result Prior filter result.
 * @return mixed
 */
function uls_estimator_rest_auth_allow( $result ) {
	if ( ! is_wp_error( $result ) || 'rest_api_authentication_required' !== $result->get_error_code() ) {
		return $result;
	}
	$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';
	$route = '/' . trim( $route, '/' );
	return in_array( $route, ULS_ESTIMATOR_ROUTES, true ) ? true : $result;
}

/**
 * Keep every page cache out of these responses (review H1). LiteSpeed Cache's "Cache REST API"
 * (on by default, TTL 7 days) would otherwise cache GET /slots publicly — including the nonce —
 * and it overwrites a plain X-LiteSpeed-Cache-Control header, so its own API is used.
 */
function uls_estimator_no_page_cache() {
	if ( function_exists( 'do_action' ) ) {
		do_action( 'litespeed_control_set_nocache', 'uls booking: live availability + nonce' );
	}
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
}

/**
 * Response headers: never stored by browsers, proxies or the LiteSpeed/Cloudflare edge.
 *
 * @param WP_REST_Response $r Response.
 * @return WP_REST_Response
 */
function uls_estimator_nocache( $r ) {
	$r->header( 'Cache-Control', 'no-store, private, max-age=0' );
	$r->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
	return $r;
}

/**
 * A string request param or a default (array params never reach string casts — review L4).
 *
 * @param mixed  $v       Value.
 * @param string $default Default.
 * @return string
 */
function uls_estimator_str( $v, $default = '' ) {
	return is_string( $v ) ? $v : $default;
}

/**
 * Uniform failure body: an error code + the plain booking link. Never upstream detail.
 *
 * @param string $code   Short machine code.
 * @param int    $status HTTP status.
 * @param string $event  it|uav for the fallback link.
 * @return WP_REST_Response
 */
function uls_estimator_fail( $code, $status, $event = 'it' ) {
	return uls_estimator_nocache(
		new WP_REST_Response(
			array(
				'ok'       => false,
				'error'    => $code,
				'fallback' => uls_estimator_fallback_url( $event ),
			),
			$status
		)
	);
}

/**
 * Keyed hash for counter / cache names, so no raw IP or e-mail (or a reversible md5 of one) is
 * stored (review L8).
 *
 * @param string $material Key material.
 * @return string
 */
function uls_estimator_key( $material ) {
	$salt = function_exists( 'wp_salt' ) ? wp_salt( 'nonce' ) : 'uls';
	return substr( hash_hmac( 'sha256', $material, $salt ), 0, 32 );
}

/**
 * Atomic fixed-window counter (review M1/M4). Returns the count INCLUDING this hit.
 * Storage is a transient (option row + timeout row) whose name rotates per window, so rows
 * expire and WordPress' daily expired-transient sweep removes them. The increment is a single
 * SQL UPDATE, so parallel requests cannot all read 0. With a persistent object cache,
 * wp_cache_incr() is used instead.
 *
 * @param string $bucket Key material (hashed).
 * @param int    $window Seconds.
 * @return int
 */
function uls_estimator_counter_hit( $bucket, $window ) {
	$name = 'uls_est_c_' . uls_estimator_key( $bucket . '|' . (int) floor( time() / $window ) );
	if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
		wp_cache_add( $name, 0, 'uls_est', 2 * $window );
		$n = wp_cache_incr( $name, 1, 'uls_est' );
		return false === $n ? 1 : (int) $n;
	}
	global $wpdb;
	$opt = '_transient_' . $name;
	$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off') ON DUPLICATE KEY UPDATE option_id = option_id", '_transient_timeout_' . $name, (string) ( time() + 2 * $window ) ) );
	$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '0', 'off') ON DUPLICATE KEY UPDATE option_id = option_id", $opt ) );
	$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = %s", $opt ) );
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $opt ) );
}

/**
 * Site-wide daily cap on booking attempts that reach Cal.com (option, default 20).
 *
 * @return int
 */
function uls_estimator_daily_cap() {
	$cap = function_exists( 'get_option' ) ? (int) get_option( 'uls_estimator_daily_cap', ULS_ESTIMATOR_DAILY_CAP_DEFAULT ) : ULS_ESTIMATOR_DAILY_CAP_DEFAULT;
	return $cap > 0 ? $cap : ULS_ESTIMATOR_DAILY_CAP_DEFAULT;
}

/**
 * Upstream HTTP defaults: short timeout, no redirects, TLS verified, a real User-Agent
 * (Cloudflare 1010 blocks library UAs in front of api.cal.com).
 *
 * @param string $version cal-api-version header value.
 * @return array
 */
function uls_estimator_http_args( $version ) {
	return array(
		'timeout'     => 8,
		'redirection' => 0,
		'sslverify'   => true,
		'user-agent'  => 'Mozilla/5.0 (compatible; UplinkSyncSite/1.0; +https://uplinksync.com)',
		'headers'     => array(
			'cal-api-version' => $version,
			'Accept'          => 'application/json',
		),
	);
}

/**
 * Fetch (and cache for 60 s) the UTC slot list for validated params. Every real upstream call
 * spends from a global budget (300 per 10 min) so the site can't be used to hammer Cal.com.
 *
 * @param array $v Validated params.
 * @return array|string ['slots' => string[], 'len' => int|null], or 'budget' / 'upstream'.
 */
function uls_estimator_fetch_slots( $v ) {
	$ck     = 'uls_est_s_' . uls_estimator_key( $v['eventTypeId'] . '|' . $v['upStart'] . '|' . $v['upEnd'] );
	$cached = get_transient( $ck );
	if ( is_array( $cached ) && isset( $cached['slots'] ) ) {
		return $cached;
	}
	if ( uls_estimator_counter_hit( 'upstream-budget', 600 ) > ULS_ESTIMATOR_UPSTREAM_BUDGET ) {
		return 'budget';
	}
	$res = wp_remote_get( uls_estimator_slots_upstream_url( $v ), uls_estimator_http_args( ULS_ESTIMATOR_CAL_SLOTS_VER ) );
	if ( is_wp_error( $res ) ) {
		error_log( '[uls-estimator] slots upstream transport error event=' . $v['event'] ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		return 'upstream';
	}
	$code  = (int) wp_remote_retrieve_response_code( $res );
	$body  = wp_remote_retrieve_body( $res );
	$slots = ( 200 === $code ) ? uls_estimator_extract_slots( $body ) : null;
	if ( null === $slots ) {
		error_log( '[uls-estimator] slots upstream status=' . $code . ' event=' . $v['event'] ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		return 'upstream';
	}
	$out = array( 'slots' => $slots, 'len' => uls_estimator_extract_length( $body ) );
	set_transient( $ck, $out, ULS_ESTIMATOR_SLOT_CACHE_TTL );
	return $out;
}

/**
 * GET /slots → { ok, event, lengthMinutes, slots: [UTC ISO…], nonce }.
 *
 * @param WP_REST_Request $req Request.
 * @return WP_REST_Response
 */
function uls_estimator_rest_slots( $req ) {
	uls_estimator_no_page_cache();
	$event = uls_estimator_str( $req->get_param( 'event' ), 'it' );
	$ip    = uls_estimator_ip_bucket( uls_estimator_client_ip( $_SERVER ) );
	if ( uls_estimator_counter_hit( 'slots|' . $ip, 600 ) > 40 ) {
		return uls_estimator_fail( 'rate_limited', 429, $event );
	}
	$v = uls_estimator_validate_slots_params(
		array(
			'event' => $req->get_param( 'event' ),
			'start' => $req->get_param( 'start' ),
			'end'   => $req->get_param( 'end' ),
		),
		gmdate( 'Y-m-d' )
	);
	if ( is_string( $v ) ) {
		return uls_estimator_fail( $v, 400, $event );
	}
	$got = uls_estimator_fetch_slots( $v );
	if ( 'budget' === $got ) {
		return uls_estimator_fail( 'busy', 503, $v['event'] );
	}
	if ( ! is_array( $got ) ) {
		return uls_estimator_fail( 'upstream', 502, $v['event'] );
	}
	$events = uls_estimator_events();
	return uls_estimator_nocache(
		new WP_REST_Response(
			array(
				'ok'            => true,
				'event'         => $v['event'],
				// From Cal.com (slot end - start); config only as a fallback.
				'lengthMinutes' => $got['len'] ? (int) $got['len'] : (int) $events[ $v['event'] ]['length'],
				'slots'         => $got['slots'],
				// Fresh per response; never cached (see uls_estimator_no_page_cache()).
				'nonce'         => wp_create_nonce( ULS_ESTIMATOR_NONCE_ACTION ),
			),
			200
		)
	);
}

/**
 * POST /book permission: the nonce from GET /slots must be present and valid. (For anonymous
 * visitors this nonce is shared and obtainable by any script — it is CSRF hygiene, not an
 * anti-automation control; the limits below and the Cal.com event settings are.)
 *
 * @param WP_REST_Request $req Request.
 * @return true|WP_Error
 */
function uls_estimator_book_permission( $req ) {
	uls_estimator_no_page_cache();
	$nonce = (string) $req->get_header( 'x_uls_nonce' );
	if ( '' === $nonce || ! wp_verify_nonce( $nonce, ULS_ESTIMATOR_NONCE_ACTION ) ) {
		return new WP_Error( 'uls_bad_nonce', 'Please reload and try again.', array( 'status' => 403 ) );
	}
	return true;
}

/**
 * POST /book.
 *
 * @param WP_REST_Request $req Request.
 * @return WP_REST_Response
 */
function uls_estimator_rest_book( $req ) {
	uls_estimator_no_page_cache();
	$body  = $req->get_json_params();
	$event = is_array( $body ) && isset( $body['event'] ) ? uls_estimator_str( $body['event'], 'it' ) : 'it';

	// Honeypot: a generic error (the visitor sees the plain booking link), never a fake
	// "confirmed" — an autofilled trap must not silently swallow a real request (review L3).
	if ( is_array( $body ) && isset( $body['hp'] ) && '' !== trim( (string) uls_estimator_str( $body['hp'] ) ) ) {
		return uls_estimator_fail( 'rejected', 400, $event );
	}

	$ip = uls_estimator_ip_bucket( uls_estimator_client_ip( $_SERVER ) );
	if ( uls_estimator_counter_hit( 'book|' . $ip, 600 ) > 5 ) {
		return uls_estimator_fail( 'rate_limited', 429, $event );
	}

	$v = uls_estimator_validate_booking( $body );
	if ( isset( $v['error'] ) ) {
		return uls_estimator_fail( $v['error'], 400, $event );
	}
	$event = $v['event'];

	if ( uls_estimator_counter_hit( 'book-email|' . uls_estimator_normalise_email( $v['email'] ), 3600 ) > 3 ) {
		return uls_estimator_fail( 'rate_limited', 429, $event );
	}

	// Same person + slot + event within 10 min (Back -> Continue, double click): the first
	// request wins atomically; later ones get its result (or "in progress").
	$dkey = uls_estimator_normalise_email( $v['email'] ) . '|' . $v['payload']['start'] . '|' . $event;
	$dk   = 'uls_est_d_' . uls_estimator_key( $dkey );
	if ( uls_estimator_counter_hit( 'dupe|' . $dkey, 600 ) > 1 ) {
		$prev = get_transient( $dk );
		return is_array( $prev )
			? uls_estimator_nocache( new WP_REST_Response( array_merge( array( 'ok' => true ), $prev ), 200 ) )
			: uls_estimator_fail( 'in_progress', 409, $event );
	}

	// The chosen start must be a slot Cal.com is offering right now.
	$day   = gmdate( 'Y-m-d', strtotime( $v['payload']['start'] ) );
	$check = uls_estimator_validate_slots_params( array( 'event' => $event, 'start' => $day, 'end' => $day ), gmdate( 'Y-m-d' ) );
	$got = is_array( $check ) ? uls_estimator_fetch_slots( $check ) : 'upstream';
	if ( ! is_array( $got ) ) {
		return uls_estimator_fail( 'budget' === $got ? 'busy' : 'upstream', 'budget' === $got ? 503 : 502, $event );
	}
	if ( ! uls_estimator_slot_offered( $v['payload']['start'], $got['slots'] ) ) {
		return uls_estimator_fail( 'slot_taken', 409, $event );
	}

	// Site-wide daily cap on attempts that reach Cal.com.
	if ( uls_estimator_counter_hit( 'book-day', 86400 ) > uls_estimator_daily_cap() ) {
		return uls_estimator_fail( 'daily_cap', 429, $event );
	}

	$args         = uls_estimator_http_args( ULS_ESTIMATOR_CAL_BOOKINGS_VER );
	$args['body'] = wp_json_encode( $v['payload'] );
	$args['headers']['Content-Type'] = 'application/json';
	$res = wp_remote_post( ULS_ESTIMATOR_CAL_BOOKINGS_URL, $args );
	if ( is_wp_error( $res ) ) {
		error_log( '[uls-estimator] book upstream transport error event=' . $event ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		return uls_estimator_fail( 'upstream', 502, $event );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$out  = uls_estimator_extract_booking( $code, wp_remote_retrieve_body( $res ) );
	if ( null === $out ) {
		error_log( '[uls-estimator] book upstream status=' . $code . ' event=' . $event ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		return uls_estimator_fail( 'upstream', 502, $event );
	}

	set_transient( $dk, $out, 600 );
	return uls_estimator_nocache( new WP_REST_Response( array_merge( array( 'ok' => true ), $out ), 200 ) );
}
