<?php
/**
 * Plugin Name: UplinkSync — "Get a quote" estimator + native booking (Quiet Light)
 * Description: Versioned home of the 3-step quote estimator (Project type → Your estimate → Book a time) that until now lived only in the database as Code Snippets #82 ([uls_quote_flow]) and #84 (site-wide modal). Same questions, same rate model, same lead hand-off (snippet #81's uls337_target_quote admin-ajax handler, unchanged), re-skinned to Quiet Light. Step 3 is a NATIVE calendar fed by this site's own REST proxy (uplinksync/v1/slots, /book) to Cal.com Cloud's public v2 API — the Cal.com embed SDK/iframe is gone from this flow.
 * Version: 1.0.0
 *
 * TAKEOVER / COEXISTENCE
 * mu-plugins load before Code Snippets executes its rows, so while snippet #82/#84 are still
 * active they would re-register [uls_quote_flow] and print a second #uls-qmodal. Until the
 * DB step (MR DB-STEPS.md: back up rows 81/82/84, deactivate 82 + 84) has run, this plugin
 * stays DORMANT (front end unchanged) — it checks wp_snippets read-only. Once 82/84 are
 * inactive it takes over automatically. Kill switch: define( 'ULS_ESTIMATOR_DISABLE', true )
 * (then reactivate 82 + 84 for the old flow). Snippet #81 (the internal target-quote email)
 * stays active: this flow still posts to it.
 *
 * FILES: uplinksync-estimator/{config,booking-api,markup}.php, estimator.css, estimator.js.
 * TESTS: tests/test-estimator-pricing.php, tests/test-estimator-booking-api.php.
 */

defined( 'ABSPATH' ) || exit;

const ULS_ESTIMATOR_VERSION = '1.0.0';

require_once __DIR__ . '/uplinksync-estimator/config.php';
require_once __DIR__ . '/uplinksync-estimator/booking-api.php';
require_once __DIR__ . '/uplinksync-estimator/markup.php';

/**
 * Are the legacy DB snippets (#82 flow, #84 modal) still active? Read-only, cached 5 min.
 * Missing table (CI preview, Code Snippets removed) = not active.
 *
 * @return bool
 */
function uls_estimator_legacy_active() {
	static $memo = null;
	if ( null !== $memo ) {
		return $memo;
	}
	$cached = get_transient( 'uls_est_legacy_snippets' );
	if ( 'yes' === $cached || 'no' === $cached ) {
		$memo = ( 'yes' === $cached );
		return $memo;
	}
	global $wpdb;
	$table = $wpdb->prefix . 'snippets';
	$prev  = $wpdb->suppress_errors( true );
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	$n     = 0;
	if ( $found === $table ) {
		$n = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE id IN (82, 84) AND active = 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
	}
	$wpdb->suppress_errors( $prev );
	$memo = $n > 0;
	set_transient( 'uls_est_legacy_snippets', $memo ? 'yes' : 'no', 300 );
	return $memo;
}

/**
 * Should the front-end estimator run on this request?
 *
 * @return bool
 */
function uls_estimator_enabled() {
	if ( defined( 'ULS_ESTIMATOR_DISABLE' ) && ULS_ESTIMATOR_DISABLE ) {
		return false;
	}
	return ! uls_estimator_legacy_active();
}

/**
 * Is this the inline /request-quote/ page (page 997)?
 *
 * @return bool
 */
function uls_estimator_is_quote_page() {
	return function_exists( 'is_page' ) && ( is_page( 997 ) || is_page( 'request-quote' ) );
}

// REST routes are independent of the takeover: they are inert until the new JS calls them.
add_action( 'rest_api_init', 'uls_estimator_register_routes' );
add_filter( 'rest_authentication_errors', 'uls_estimator_rest_auth_allow', PHP_INT_MAX );

/**
 * Register [uls_quote_flow] late on init so it wins over any earlier registration.
 */
add_action(
	'init',
	function () {
		if ( uls_estimator_enabled() ) {
			add_shortcode( 'uls_quote_flow', 'uls_estimator_flow_markup' );
		}
	},
	99
);

/**
 * Assets. The JS is site-wide (the modal can open anywhere). The CSS is enqueued up front
 * only on the inline quote page; elsewhere estimator.js injects it on first intent (hover /
 * focus / touch of a trigger) so it never counts against a page's CSS budget until used.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( is_admin() || ! uls_estimator_enabled() ) {
			return;
		}
		$base = plugins_url( 'uplinksync-estimator/', __FILE__ );
		$dir  = __DIR__ . '/uplinksync-estimator/';
		$cver = ULS_ESTIMATOR_VERSION . '.' . (int) @filemtime( $dir . 'estimator.css' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$jver = ULS_ESTIMATOR_VERSION . '.' . (int) @filemtime( $dir . 'estimator.js' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$css  = $base . 'estimator.css?ver=' . rawurlencode( $cver );

		if ( uls_estimator_is_quote_page() ) {
			wp_enqueue_style( 'uplinksync-qf', $base . 'estimator.css', array(), $cver );
		}
		wp_enqueue_script( 'uplinksync-qf', $base . 'estimator.js', array(), $jver, array( 'in_footer' => true, 'strategy' => 'defer' ) );

		$boot = array(
			'uav'      => uls_estimator_uav_rates(),
			'it'       => uls_estimator_it_rates(),
			'rest'     => esc_url_raw( rest_url( 'uplinksync/v1/' ) ),
			'css'      => esc_url_raw( $css ),
			'fallback' => array(
				'it'  => uls_estimator_fallback_url( 'it' ),
				'uav' => uls_estimator_fallback_url( 'uav' ),
			),
		);
		// No e-mail addresses in here: the contact/social output buffer linkifies bare
		// addresses anywhere in the document, which would break this script (cf. the
		// 2026-08-13 JSON-LD incident). JSON_HEX_* keeps the payload inert in a <script>.
		wp_add_inline_script( 'uplinksync-qf', 'window.ULS_QF_BOOT=' . wp_json_encode( $boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';', 'before' );
	}
);

/**
 * The site-wide modal (snippet #84), skipped on the inline quote page (no duplicate #uls-qf).
 */
add_action(
	'wp_footer',
	function () {
		if ( is_admin() || ! uls_estimator_enabled() || uls_estimator_is_quote_page() ) {
			return;
		}
		echo uls_estimator_modal_markup(); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
	},
	30
);
