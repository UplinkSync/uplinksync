<?php
/**
 * Standalone regression test for uplinksync_home_rhythm_flip() (***-329).
 *
 * The flip function is pure (string in / array out), so no WordPress bootstrap
 * is needed. We define ABSPATH and stub the two WP functions the plugin file
 * references at load time, then include the plugin and exercise the matcher.
 *
 * Run:  php wp-content/mu-plugins/tests/test-homepage-rhythm.php
 *
 * REGRESSION FOCUS: v1.0.0 anchored on the NEAREST `<!-- wp:group ` before the
 * marker — an inner nested group whose window lacked the band's two uls-bg-dark
 * tokens — so it silently no-op'd on prod and band 3 stayed dark. The
 * `nested_band_flips` case reproduces that exact structure and asserts v1.1.0
 * flips the OUTER dark band wrapper, not an inner group.
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

/*
 * Realistic band 3 markup: an OUTER dark band wrapper (uls-bg-dark appears twice —
 * once in the block-comment JSON, once on the wrapper <div>), containing TWO nested
 * inner groups (no uls-bg-* token) before the caption marker. This is the shape that
 * defeated v1.0.0.
 */
$band3_nested = <<<'HTML'
<!-- wp:group {"className":"uls-bg-dark uls-section"} -->
<div class="wp-block-group uls-bg-dark uls-section">
  <!-- wp:group {"className":"uls-inner"} -->
  <div class="wp-block-group uls-inner">
    <!-- wp:group {"className":"uls-media"} -->
    <div class="wp-block-group uls-media">
      <!-- wp:heading --><h2>Ground and air, one team</h2><!-- /wp:heading -->
      <figure><img alt="owner-with-drone shot"/></figure>
    </div>
    <!-- /wp:group -->
  </div>
  <!-- /wp:group -->
</div>
<!-- /wp:group -->
HTML;

// A dark band BEFORE band 3 (must not be touched) and a light band AFTER.
$prefix = '<!-- wp:group {"className":"uls-bg-dark uls-section"} -->' .
	'<div class="wp-block-group uls-bg-dark uls-section"><h2>FAA Part 107</h2></div><!-- /wp:group -->';
$suffix = '<!-- wp:group {"className":"uls-bg-light uls-section"} -->' .
	'<div class="wp-block-group uls-bg-light uls-section"><h2>Ground and air, from one desk</h2></div><!-- /wp:group -->';

$page = $prefix . $band3_nested . $suffix;

// 1. Nested band 3 flips (the v1.0.0 regression).
$r = uplinksync_home_rhythm_flip( $page );
check( 'nested_band_flips: changed', ! empty( $r['changed'] ), 'reason=' . $r['reason'] );
check(
	'nested_band_flips: band3 now light',
	strpos( $r['content'], '<div class="wp-block-group uls-bg-light uls-section"><h2>Ground and air, one team' ) === false
		? ( substr_count( $r['content'], 'uls-bg-light' ) === 4 ) // 2 tokens flipped in band3 + 2 in suffix
		: true,
	'light token count=' . substr_count( $r['content'], 'uls-bg-light' )
);
// The prefix dark band and its heading must be untouched.
check( 'nested_band_flips: prefix dark band untouched', strpos( $r['content'], '<div class="wp-block-group uls-bg-dark uls-section"><h2>FAA Part 107' ) !== false );
// Exactly the two band-3 dark tokens were converted: page had 4 dark tokens, now 2 remain (prefix band).
check( 'nested_band_flips: only band3 tokens flipped', substr_count( $r['content'], 'uls-bg-dark' ) === 2, 'remaining dark=' . substr_count( $r['content'], 'uls-bg-dark' ) );

// 2. Idempotency: re-running on the flipped output is a no-op.
$r2 = uplinksync_home_rhythm_flip( $r['content'] );
check( 'idempotent: second run no-ops', empty( $r2['changed'] ) && $r2['reason'] === 'already-light', 'reason=' . $r2['reason'] );

// 3. Marker absent => no-op.
$r3 = uplinksync_home_rhythm_flip( '<div class="wp-block-group uls-bg-dark">nothing here</div>' );
check( 'no marker: no-op', empty( $r3['changed'] ) && $r3['reason'] === 'marker-not-found' );

// 4. Empty content => no-op.
$r4 = uplinksync_home_rhythm_flip( '' );
check( 'empty content: no-op', empty( $r4['changed'] ) && $r4['reason'] === 'empty-content' );

// 5. Ambiguous window (band wrapper carries an extra stray dark token) => abort.
$ambiguous = '<!-- wp:group {"className":"uls-bg-dark uls-section"} -->' .
	'<div class="wp-block-group uls-bg-dark uls-section"><span class="uls-bg-dark"></span>' .
	'<h2>Ground and air, one team</h2><figure><img alt="owner-with-drone shot"/></figure></div><!-- /wp:group -->';
$r5 = uplinksync_home_rhythm_flip( $ambiguous );
check( 'ambiguous dark count: abort', empty( $r5['changed'] ) && strpos( $r5['reason'], 'ambiguous-dark-count' ) === 0, 'reason=' . $r5['reason'] );

echo "\n" . ( $failures === 0 ? "ALL PASS\n" : "$failures FAILURE(S)\n" );
exit( $failures === 0 ? 0 : 1 );
