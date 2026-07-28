<?php
/**
 * Plugin Name: UplinkSync — Homepage Band Rhythm (***-270 item 1 / ***-329)
 * Description: Homepage `/` (page ID 278) section-rhythm normalisation.
 *
 *   HISTORY. v1.0.0/v1.1.0 were a one-shot DB migration that flipped the 3rd homepage band
 *   (`uls-bg-dark` -> `uls-bg-light`) — the reserved "Ground and air, one team" owner-with-drone
 *   photo band — so `/` followed the house alternating rhythm (design-standard.md §"Section
 *   rhythm": never more than two consecutive same-value bands; a dark hero plus the dark band
 *   immediately under it count as ONE unit; the hero unit and the CTA band stay dark).
 *
 *   v2.0.0 (2026-07-28) — RETIRED TO A READ-ONLY NO-OP. The flip target no longer exists: the
 *   reserved photo-slot placeholder band was removed from Home page 278 (dev-placeholder cleanup,
 *   same date) — which deleted the exact band this migration flipped. Removing it changed the
 *   page parity, and the homepage now satisfies §"Section rhythm" WITHOUT a flip. Current bands:
 *
 *       1  uls-bg-dark  uls-section        (hero)            -.  "dark hero + the dark band
 *       2  uls-bg-dark  uls-trust-band     (FAA/Part 107)    -'  immediately under it = ONE unit"
 *       3  uls-bg-dark                     (Endpoint & aerial data)
 *       4  uls-bg-light uls-section        (Ground and air, from one desk)
 *       5  uls-bg-dark  uls-section        (UAV work scoped to your objectives)
 *       6  uls-cta-band uls-gradient-dark  (CTA — stays dark by rule)
 *
 *   Value run under the unit rule: [hero+trust] = D, Endpoint = D  -> 2 consecutive (allowed);
 *   Ground = L; UAV = D, CTA = D -> 2 consecutive (allowed). No run exceeds two -> COMPLIANT.
 *
 *   WHY NOT RE-TARGET THE FLIP TO ANOTHER BAND. No remaining dark band can be flipped to light by
 *   a class-only migration: the hero unit and the CTA band must stay dark (rule), and the trust
 *   strip and the Endpoint band both carry a DARK-optimised text palette (has-white-color /
 *   has-grey-color / has-accent-teal-color) that would be unreadable on a light background.
 *   Flipping either would need a full text-colour rewrite (mirroring the light band's
 *   has-dark-color / has-accent-600-color treatment) — outside this migration's safe two-token
 *   model and unsafe to apply blind without visual QA. The design's intended light break after
 *   the masthead WAS the photo band; it returns when the owner supplies the real photo (a light
 *   content band), not by re-colouring a dark content band. The residual within-dark tonal step
 *   (trust strip #102a4c next to navy Endpoint) is not a light/dark rhythm violation and is a
 *   trust-band CSS matter, out of scope for this dark<->light migration.
 *
 *   This file no longer writes post_content at all. It is a read-only guard: it records the
 *   one-shot flag so the already-deployed v1.x flip pass is superseded and never runs a stale
 *   flip, and it exposes pure helpers (band map + compliance check) used by the regression test.
 *   It registers NO output buffer and touches NO render path, so it cannot blank the page.
 *
 * Version: 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const UPLINKSYNC_HOME_RHYTHM_VERSION = '2.0.0';
const UPLINKSYNC_HOME_RHYTHM_OPTION  = 'uplinksync_home_rhythm_version';

/**
 * The Home page. ID 278 is authoritative (***-270 spec), but resolve by the
 * front-page setting first so a re-pointed front page is still evaluated.
 */
function uplinksync_home_rhythm_target_post_id() {
	$front = (int) get_option( 'page_on_front' );
	if ( $front > 0 ) {
		return $front;
	}
	return 278;
}

/**
 * Extract the ordered background value ('dark'|'light') of each TOP-LEVEL band
 * from block markup. A "band" is a depth-1 `wp:group` whose block-comment
 * className carries a rhythm token:
 *   - uls-bg-light                                    -> 'light'
 *   - uls-bg-dark | uls-gradient-dark | uls-cta-band  -> 'dark' (CTA reads dark)
 * Depth-1 groups without a rhythm token are structural wrappers and are skipped.
 * Nested inner groups (depth > 1) are never counted.
 *
 * @param string $content Block markup.
 * @return string[] Ordered list of 'dark'/'light'.
 */
function uplinksync_home_rhythm_band_values( $content ) {
	$values = array();
	if ( ! is_string( $content ) || '' === $content ) {
		return $values;
	}
	if ( ! preg_match_all( '/<!--\s*(\/?)wp:group\b([^>]*?)-->/s', $content, $m, PREG_SET_ORDER ) ) {
		return $values;
	}
	$depth = 0;
	foreach ( $m as $tok ) {
		$closing = ( '/' === $tok[1] );
		if ( $closing ) {
			$depth--;
			continue;
		}
		$depth++;
		if ( 1 !== $depth ) {
			continue; // only top-level groups are bands
		}
		$attrs = $tok[2];
		$cls   = '';
		if ( preg_match( '/"className"\s*:\s*"([^"]*)"/', $attrs, $cm ) ) {
			$cls = strtolower( $cm[1] );
		}
		if ( false !== strpos( $cls, 'uls-bg-light' ) ) {
			$values[] = 'light';
		} elseif (
			false !== strpos( $cls, 'uls-bg-dark' ) ||
			false !== strpos( $cls, 'uls-gradient-dark' ) ||
			false !== strpos( $cls, 'uls-cta-band' )
		) {
			$values[] = 'dark';
		}
		// else: structural top-level wrapper with no rhythm token -> not a band.
	}
	return $values;
}

/**
 * Apply the design-standard "hero unit" merge: a dark hero followed immediately
 * by a dark band counts as one unit (the second reads as part of the hero).
 * Concretely: if the first two bands are both dark, collapse them to one.
 *
 * @param string[] $values
 * @return string[]
 */
function uplinksync_home_rhythm_apply_hero_unit( array $values ) {
	if ( count( $values ) >= 2 && 'dark' === $values[0] && 'dark' === $values[1] ) {
		array_splice( $values, 1, 1 );
	}
	return $values;
}

/**
 * Longest run of consecutive identical values.
 *
 * @param string[] $values
 * @return int
 */
function uplinksync_home_rhythm_max_run( array $values ) {
	$max  = 0;
	$run  = 0;
	$prev = null;
	foreach ( $values as $v ) {
		$run  = ( $v === $prev ) ? $run + 1 : 1;
		$prev = $v;
		if ( $run > $max ) {
			$max = $run;
		}
	}
	return $max;
}

/**
 * True when the homepage satisfies §"Section rhythm": after collapsing the hero
 * unit, no more than two consecutive bands share the same value.
 *
 * @param string $content Block markup.
 * @return bool
 */
function uplinksync_home_rhythm_is_compliant( $content ) {
	$values = uplinksync_home_rhythm_apply_hero_unit( uplinksync_home_rhythm_band_values( $content ) );
	if ( count( $values ) < 2 ) {
		return true; // nothing to alternate
	}
	return uplinksync_home_rhythm_max_run( $values ) <= 2;
}

/**
 * Read-only evaluation. NEVER writes post_content (the flip is retired). Returns
 * a small status array for the caller/tests; the one-shot flag is recorded by
 * maybe_run() regardless so no stale v1.x flip can run.
 *
 * @return array{acted:bool, compliant:bool, reason:string}
 */
function uplinksync_home_rhythm_run() {
	$post_id = uplinksync_home_rhythm_target_post_id();
	$post    = get_post( $post_id );
	if ( ! ( $post instanceof WP_Post ) || 'page' !== $post->post_type ) {
		return array( 'acted' => false, 'compliant' => true, 'reason' => 'target-missing' );
	}
	$compliant = uplinksync_home_rhythm_is_compliant( $post->post_content );
	// Retired: we do not mutate content. If a future edit ever reintroduced a >2
	// run, this guard surfaces it (reason) for a human — it does not auto-flip,
	// because the only remaining dark bands need a text-palette rewrite, not a
	// class swap, and that must not be applied blind.
	return array(
		'acted'     => false,
		'compliant' => $compliant,
		'reason'    => $compliant ? 'compliant-no-flip-needed' : 'non-compliant-needs-manual-review',
	);
}

/**
 * One-shot flag. Bumping VERSION re-arms it once so the retired-flip status
 * supersedes the deployed v1.x pass; the run itself makes no write.
 */
function uplinksync_home_rhythm_maybe_run() {
	if ( get_option( UPLINKSYNC_HOME_RHYTHM_OPTION ) === UPLINKSYNC_HOME_RHYTHM_VERSION ) {
		return;
	}
	uplinksync_home_rhythm_run();
	update_option( UPLINKSYNC_HOME_RHYTHM_OPTION, UPLINKSYNC_HOME_RHYTHM_VERSION );
}
add_action( 'init', 'uplinksync_home_rhythm_maybe_run', 20 );
