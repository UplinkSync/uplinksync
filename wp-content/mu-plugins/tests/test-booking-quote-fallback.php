<?php
/**
 * Standalone regression test for the guided quote fallback repair (UPL-884).
 *
 * Run: php wp-content/mu-plugins/tests/test-booking-quote-fallback.php
 */

error_reporting( E_ALL );

define( 'ABSPATH', __DIR__ );
if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$args ) {}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) { return $url; }
}

require __DIR__ . '/../uplinksync-booking-ctas.php';

$failures = 0;
function check( $name, $condition, $detail = '' ) {
	global $failures;
	if ( $condition ) {
		echo "  PASS  {$name}\n";
		return;
	}

	$failures++;
	echo "  FAIL  {$name}" . ( $detail ? "  ({$detail})" : '' ) . "\n";
}

$placeholder = '<a id="qf-cal-fallback-link" class="qf-btn primary" href="#" target="_blank" rel="noopener">Book your consultation &rarr;</a>';
$expected_url = 'https://book.uplinksync.com/team/uplinksync/it-consult';
$repaired     = uplinksync_book_repair_quote_fallback( $placeholder );

check( 'fallback gets the canonical team consultation URL', false !== strpos( $repaired, 'href="' . $expected_url . '"' ), $repaired );
check( 'fallback no longer contains the placeholder href', false === strpos( $repaired, 'href="#"' ), $repaired );
check( 'fallback keeps its target and rel attributes', false !== strpos( $repaired, 'target="_blank" rel="noopener"' ), $repaired );
check( 'repair is idempotent', $repaired === uplinksync_book_repair_quote_fallback( $repaired ) );

$other_link = '<a id="unrelated-link" href="#">Unrelated fragment link</a>';
check( 'unrelated placeholder links are untouched', $other_link === uplinksync_book_repair_quote_fallback( $other_link ) );

$dynamic_url = 'https://book.uplinksync.com/team/uplinksync/uav-consult?name=Ada';
$dynamic     = '<a href="' . $dynamic_url . '" id="qf-cal-fallback-link">Book</a>';
check( 'JavaScript-selected booking URLs are preserved', $dynamic === uplinksync_book_repair_quote_fallback( $dynamic ) );

$single_quoted = "<a class='qf-btn' href='#' id='qf-cal-fallback-link'>Book</a>";
check( 'single-quoted placeholder is repaired', false !== strpos( uplinksync_book_repair_quote_fallback( $single_quoted ), 'href="' . $expected_url . '"' ) );

echo "\n" . ( 0 === $failures ? "ALL PASS\n" : "{$failures} FAILURE(S)\n" );
exit( 0 === $failures ? 0 : 1 );
