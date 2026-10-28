<?php
/**
 * Booking CTAs v4: one booking UI. The old dark dialog and the Cal.com embed SDK are gone;
 * CTAs are real links (no-JS fallback) marked as triggers for the estimator's native
 * "Book a call" mode.
 *
 * Run: php wp-content/mu-plugins/tests/test-booking-ctas-native.php
 */

error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
foreach ( array( 'add_action', 'add_filter' ) as $uls_fn ) {
	if ( ! function_exists( $uls_fn ) ) {
		eval( "function {$uls_fn}( ...\$a ) {}" ); // phpcs:ignore Squiz.PHP.Eval -- test stub.
	}
}
function esc_url( $u ) { return $u; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }

require __DIR__ . '/../uplinksync-booking-ctas.php';

$failures = 0;
function check( $name, $cond, $detail = '' ) {
	global $failures;
	echo ( $cond ? '  PASS  ' : '  FAIL  ' ) . $name . ( ! $cond && '' !== $detail ? "  ({$detail})" : '' ) . "\n";
	if ( ! $cond ) {
		$failures++;
	}
}

$doc = '<html><body>'
	. '<div class="wp-block-button"><a href="/contact/">Talk to a specialist</a></div>'
	. '<p><a href="https://book.uplinksync.com/team/uplinksync/it-consult">Prefer to talk? Book a call →</a></p>'
	. '<li><a href="https://book.uplinksync.com/team/uplinksync/uav-consult">Book a drone call</a></li>'
	. '<a id="qf-cal-fallback-link" href="https://book.uplinksync.com/team/uplinksync/it-consult" data-uls-book-direct="1">Book on book.uplinksync.com</a>'
	. '<p>Progress records</p></div>'
	. '</body></html>';
$out = uplinksync_book_rewrite( $doc );

check( 'no old dialog injected', false === strpos( $out, 'id="uls-book-modal"' ) && false === strpos( $out, 'uplinksync-book-cta-css' ) && false === strpos( $out, 'uplinksync-book-cta-js' ) );
check( 'no Cal.com embed SDK anywhere', false === stripos( $out, 'embed.js' ) && false === strpos( $out, 'Cal(' ) );
check( 'old dialog functions removed', ! function_exists( 'uplinksync_book_modal_markup' ) && ! function_exists( 'uplinksync_book_runtime_js' ) && ! function_exists( 'uplinksync_book_inject_runtime' ) );
check( 'injected consult CTA is a real link (no-JS fallback) + native trigger', (bool) preg_match( '#<a class="[^"]*uls-book-link" href="https://book\.uplinksync\.com/team/uplinksync/it-consult" data-uls-book-open="uls-qmodal" data-uls-book-intent="it"#', $out ), $out );
check( 'injected UAV CTA is a real link to uav-consult, intent uav', (bool) preg_match( '#<a href="https://book\.uplinksync\.com/team/uplinksync/uav-consult" class="[^"]*uls-consult-trigger" data-uls-book-open="uls-qmodal" data-uls-book-intent="uav"#', $out ), $out );
check( 'book.uplinksync.com links keep their href and become native triggers', 2 <= substr_count( $out, 'data-uls-book-open="uls-qmodal" data-uls-book-intent="' ) && false !== strpos( $out, 'href="https://book.uplinksync.com/team/uplinksync/uav-consult" data-uls-book-open="uls-qmodal" data-uls-book-intent="uav"' ) );
check( 'controls point at the estimator modal', false === strpos( $out, 'aria-controls="uls-book-modal"' ) && false !== strpos( $out, 'aria-controls="uls-qmodal"' ) );
check( 'estimator fallback link stays a plain link', (bool) preg_match( '#<a id="qf-cal-fallback-link"[^>]*data-uls-book-direct="1">#', $out ) && ! preg_match( '#<a id="qf-cal-fallback-link"[^>]*data-uls-book-open#', $out ) );

echo "\n" . ( 0 === $failures ? "ALL PASS\n" : "{$failures} FAILURE(S)\n" );
exit( 0 === $failures ? 0 : 1 );
