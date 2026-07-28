<?php
/**
 * Standalone regression test for the homepage rhythm helpers (***-329, v2.0.0).
 *
 * The helpers are pure (string in / array or bool out), so no WordPress bootstrap
 * is needed. We define ABSPATH and stub the WP functions the plugin references at
 * load time, then include the plugin and exercise the band-map + compliance logic.
 *
 * Run:  php wp-content/mu-plugins/tests/test-homepage-rhythm.php
 *
 * CONTEXT: v1.x flipped the reserved "Ground and air, one team" photo band
 * (uls-bg-dark -> uls-bg-light). That band was removed from Home 278 (2026-07-28
 * dev-placeholder cleanup), which changed the page parity. v2.0.0 retires the
 * mutation and instead VERIFIES the page satisfies design-standard §"Section
 * rhythm" (never >2 consecutive same-value bands; the dark hero + the dark band
 * immediately under it collapse to one unit). These tests lock that in:
 *   - the current post-removal band sequence is compliant (no flip needed);
 *   - the hero-unit collapse is applied;
 *   - the CTA gradient band counts as dark;
 *   - nested inner groups are never miscounted as bands;
 *   - a genuine >2 run is reported non-compliant.
 */

error_reporting( E_ALL );

define( 'ABSPATH', __DIR__ );
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $k, $d = false ) { return $d; }
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$a ) {}
}

require __DIR__ . '/../uplinksync-homepage-rhythm.php';

$failures = 0;
function check( $name, $cond, $detail = '' ) {
	global $failures;
	if ( $cond ) {
		echo "  PASS  $name\n";
	} else {
		$failures++;
		echo "  FAIL  $name" . ( $detail ? "  ($detail)" : '' ) . "\n";
	}
}

/**
 * Helper: build a top-level band wrapper with the given className, containing a
 * couple of nested inner groups (no rhythm token) — the shape that defeated the
 * old nearest-group locator. Inner groups must NOT be counted as bands.
 */
function band( $className, $heading ) {
	return '<!-- wp:group {"align":"full","className":"' . $className . '"} -->' . "\n"
		. '<div class="wp-block-group alignfull ' . $className . '">' . "\n"
		. '  <!-- wp:group {"className":"uls-inner"} --><div class="wp-block-group uls-inner">' . "\n"
		. '    <!-- wp:heading --><h2>' . $heading . '</h2><!-- /wp:heading -->' . "\n"
		. '  </div><!-- /wp:group -->' . "\n"
		. '</div>' . "\n"
		. '<!-- /wp:group -->' . "\n";
}

/*
 * Fixture 1 — the CURRENT live homepage band sequence (photo-slot band removed):
 *   1 dark (hero) | 2 dark (trust) | 3 dark (Endpoint) | 4 light (Ground) |
 *   5 dark (UAV)  | 6 dark (CTA gradient)
 */
$home_now =
	band( 'uls-bg-dark uls-section', 'Technology specialists' ) .
	band( 'uls-bg-dark uls-trust-band', 'FAA Part 107' ) .
	band( 'uls-bg-dark', 'Endpoint &amp; aerial data' ) .
	band( 'uls-bg-light uls-section', 'Ground and air, from one desk' ) .
	band( 'uls-bg-dark uls-section', 'UAV work scoped to your objectives' ) .
	band( 'uls-cta-band uls-gradient-dark', 'One team for the ground and the air' );

$values = uplinksync_home_rhythm_band_values( $home_now );
check( 'band_values: 6 bands detected', count( $values ) === 6, 'got=' . count( $values ) );
check( 'band_values: exact sequence D,D,D,L,D,D', $values === array( 'dark', 'dark', 'dark', 'light', 'dark', 'dark' ), implode( ',', $values ) );
check( 'band_values: CTA gradient counts as dark', end( $values ) === 'dark' );

$merged = uplinksync_home_rhythm_apply_hero_unit( $values );
check( 'hero_unit: collapses first two darks -> 5 values', count( $merged ) === 5, 'got=' . count( $merged ) );
check( 'hero_unit: merged sequence is D,D,L,D,D', $merged === array( 'dark', 'dark', 'light', 'dark', 'dark' ), implode( ',', $merged ) );
check( 'max_run of merged is 2', uplinksync_home_rhythm_max_run( $merged ) === 2, 'run=' . uplinksync_home_rhythm_max_run( $merged ) );
check( 'CURRENT homepage is COMPLIANT (no flip needed)', uplinksync_home_rhythm_is_compliant( $home_now ) === true );

/* Fixture 2 — the removed marker is genuinely absent from the current page. */
check( 'retired: photo-band marker gone', strpos( $home_now, 'Ground and air, one team' ) === false && strpos( $home_now, 'owner-with-drone shot' ) === false );

/* Fixture 3 — nested inner groups are not miscounted (single band -> one value). */
$one = band( 'uls-bg-dark uls-section', 'solo' );
check( 'nesting: one band -> one value', uplinksync_home_rhythm_band_values( $one ) === array( 'dark' ) );

/* Fixture 4 — the hero-unit rule permits exactly the opening 3-dark shape
 * (hero + hugging dark = one unit, then one more dark = 2 consecutive). */
$three_dark_open = band( 'uls-bg-dark', 'hero' ) . band( 'uls-bg-dark', 'trust' ) . band( 'uls-bg-dark', 'first content' );
check( 'hero_unit: 3 opening darks are compliant (unit + 1)', uplinksync_home_rhythm_is_compliant( $three_dark_open ) === true );

/* Fixture 5 — a genuine >2 run (four darks: unit + 2) is NON-compliant. */
$four_dark = band( 'uls-bg-dark', 'a' ) . band( 'uls-bg-dark', 'b' ) . band( 'uls-bg-dark', 'c' ) . band( 'uls-bg-dark', 'd' );
check( 'non-compliant: 4 consecutive darks flagged', uplinksync_home_rhythm_is_compliant( $four_dark ) === false );

/* Fixture 6 — a >2 LIGHT run is also non-compliant (rule is symmetric). */
$three_light = band( 'uls-bg-dark', 'hero' ) . band( 'uls-bg-light', 'a' ) . band( 'uls-bg-light', 'b' ) . band( 'uls-bg-light', 'c' );
check( 'non-compliant: 3 consecutive lights flagged', uplinksync_home_rhythm_is_compliant( $three_light ) === false );

/* Fixture 7 — empty content is trivially compliant and yields no bands. */
check( 'empty: no bands', uplinksync_home_rhythm_band_values( '' ) === array() );
check( 'empty: compliant', uplinksync_home_rhythm_is_compliant( '' ) === true );

echo "\n" . ( $failures === 0 ? "ALL PASS\n" : "$failures FAILURE(S)\n" );
exit( $failures === 0 ? 0 : 1 );
