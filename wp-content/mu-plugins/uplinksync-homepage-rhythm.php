<?php
/**
 * Plugin Name: UplinkSync — Homepage Band Rhythm Normalisation (***-270 item 1 / ***-329)
 * Description: One-shot, idempotent DATABASE migration that flips the 3rd homepage band
 *   (the "Ground and air, one team" reserved-photo band, immediately under the hero + trust
 *   strip) from `uls-bg-dark` -> `uls-bg-light` on Home page ID 278, so `/` follows the house
 *   section rhythm (design-standard.md §"Section rhythm": never >2 consecutive same-value bands;
 *   hero unit stays dark, CTA band stays dark). Owner-accepted decision "normalise homepage to
 *   the alternating light/dark rhythm" (***-270 checkbox, accepted 2026-07-24).
 *
 *   WHY A DB MIGRATION AND NOT A RUNTIME REWRITE: the band markup lives in the WP DB (Home page
 *   ID 278 post_content), not in the repo. Runtime output-buffer (ob_start) rewrites are BANNED
 *   here — they blanked production twice (see uplinksync-unified-services.php [DISABLED AGAIN]).
 *   This plugin registers NO output buffer and touches NO render path. It runs once on `init`,
 *   edits post_content via wp_update_post, records a version flag, and never runs again. It is
 *   the same repo-captured, credential-free DB-write idiom already proven safe by
 *   uplinksync-page-seeder.php (wp_insert_post on init).
 *
 *   FAIL-SAFE BY CONSTRUCTION: the migration only acts when it can PRECISELY and UNAMBIGUOUSLY
 *   locate the target band (unique marker text + exactly one enclosing dark band wrapper whose
 *   opening region carries exactly two `uls-bg-dark` tokens). On any mismatch — page missing,
 *   marker absent, already-light, wrong token count — it makes NO change and records the flag
 *   anyway so it never thrashes. It cannot blank the page: it does not participate in rendering,
 *   and a bad match is a no-op, not a partial write.
 *
 *   v1.1.0 — NESTING-ROBUST BAND LOCATOR. v1.0.0 anchored on the *nearest* `<!-- wp:group `
 *   before the marker, which in the live markup is an INNER nested group (the band wraps nested
 *   groups around the photo/caption). That inner window did not carry the band's two
 *   `uls-bg-dark` tokens, so the fail-safe guard aborted and the flip silently no-op'd on prod
 *   (band 3 stayed dark; homepage kept 3 consecutive dark bands). Fix: anchor on the nearest
 *   preceding `<!-- wp:group ` whose OPENING COMMENT itself carries `uls-bg-dark` — i.e. the dark
 *   band wrapper. Inner groups carry no `uls-bg-*` token, so this skips them regardless of
 *   nesting depth. Bumping the version re-arms the one-shot guard so the corrected pass runs.
 *
 * Version: 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const UPLINKSYNC_HOME_RHYTHM_VERSION = '1.1.0';
const UPLINKSYNC_HOME_RHYTHM_OPTION  = 'uplinksync_home_rhythm_version';

/**
 * The Home page. ID 278 is authoritative (***-270 spec), but we resolve by the
 * front-page setting first and only fall back to 278, so a re-pointed front page
 * still migrates the right post.
 */
function uplinksync_home_rhythm_target_post_id() {
	$front = (int) get_option( 'page_on_front' );
	if ( $front > 0 ) {
		return $front;
	}
	return 278;
}

/**
 * Perform the single-band flip on a block-markup string.
 *
 * Strategy (deliberately conservative, nesting-robust):
 *   1. Locate the unique target-band marker. The 3rd band is the reserved
 *      owner-with-drone photo band; its "Ground and air, one team" heading and
 *      "owner-with-drone shot" caption appear nowhere else on the page.
 *   2. Walk backward from the marker over `<!-- wp:group ` opening comments and
 *      pick the nearest one whose OPENING COMMENT carries `uls-bg-dark`. Inner
 *      groups have no `uls-bg-*` class, so this is the dark band wrapper itself —
 *      robust to any number of nested inner groups (the flaw that no-op'd v1.0.0).
 *   3. In the bounded window [band-wrapper-comment .. marker], require EXACTLY two
 *      `uls-bg-dark` tokens (the className in the block-comment JSON + the class on
 *      the wrapper <div>). Anything else => ambiguous => abort (no-op).
 *   4. Replace those two tokens with `uls-bg-light`. Nothing else is touched.
 *
 * @return array{changed:bool, reason:string, content:string}
 */
function uplinksync_home_rhythm_flip( $content ) {
	if ( ! is_string( $content ) || '' === $content ) {
		return array( 'changed' => false, 'reason' => 'empty-content', 'content' => $content );
	}

	// Unique marker for the 3rd band. Prefer the caption text (most specific);
	// fall back to the heading. Both are unique to this band.
	$marker_pos = stripos( $content, 'owner-with-drone shot' );
	if ( false === $marker_pos ) {
		$marker_pos = stripos( $content, 'Ground and air, one team' );
	}
	if ( false === $marker_pos ) {
		return array( 'changed' => false, 'reason' => 'marker-not-found', 'content' => $content );
	}

	// Walk backward over group-opening comments; the band wrapper is the nearest
	// one whose opening comment (up to its `-->`) carries `uls-bg-dark`. This skips
	// inner nested groups (which carry no uls-bg-* token) at any depth.
	$group_open = false;
	$search_to  = $marker_pos;
	while ( true ) {
		$candidate = strripos( substr( $content, 0, $search_to ), '<!-- wp:group ' );
		if ( false === $candidate ) {
			break;
		}
		$comment_end = strpos( $content, '-->', $candidate );
		$comment     = false === $comment_end
			? substr( $content, $candidate, $marker_pos - $candidate )
			: substr( $content, $candidate, $comment_end - $candidate );
		if ( false !== stripos( $comment, 'uls-bg-dark' ) ) {
			$group_open = $candidate;
			break;
		}
		if ( false !== stripos( $comment, 'uls-bg-light' ) ) {
			// Nearest band wrapper is already light — idempotent no-op.
			return array( 'changed' => false, 'reason' => 'already-light', 'content' => $content );
		}
		$search_to = $candidate; // keep walking outward past inner groups
	}
	if ( false === $group_open ) {
		return array( 'changed' => false, 'reason' => 'dark-band-wrapper-not-found', 'content' => $content );
	}

	$before = substr( $content, 0, $group_open );
	$window = substr( $content, $group_open, $marker_pos - $group_open );
	$after  = substr( $content, $marker_pos );

	// Require exactly the two expected dark tokens (comment className + div class).
	$dark_count = substr_count( strtolower( $window ), 'uls-bg-dark' );
	if ( 2 !== $dark_count ) {
		return array( 'changed' => false, 'reason' => 'ambiguous-dark-count:' . $dark_count, 'content' => $content );
	}

	// Case-preserving replace of the two tokens in the bounded window only.
	$new_window = str_replace( 'uls-bg-dark', 'uls-bg-light', $window );
	if ( $new_window === $window ) {
		return array( 'changed' => false, 'reason' => 'no-substitution', 'content' => $content );
	}

	return array( 'changed' => true, 'reason' => 'flipped', 'content' => $before . $new_window . $after );
}

function uplinksync_home_rhythm_run() {
	$post_id = uplinksync_home_rhythm_target_post_id();
	$post    = get_post( $post_id );
	if ( ! ( $post instanceof WP_Post ) || 'page' !== $post->post_type ) {
		return; // Target missing — no-op (flag still recorded by caller so we don't thrash).
	}

	$result = uplinksync_home_rhythm_flip( $post->post_content );
	if ( empty( $result['changed'] ) ) {
		return; // No-op on any non-exact match.
	}

	// wp_update_post sanitises via KSES for the post author's caps; run as an edit
	// that preserves everything else. We only changed two class tokens.
	wp_update_post(
		array(
			'ID'           => $post_id,
			'post_content' => $result['content'],
		),
		true
	);
}

/**
 * One-shot guard. The flip is itself idempotent (re-running finds 'already-light'
 * and no-ops), so this guard is belt-and-suspenders: it stops the pass from
 * re-reading the post on every request once applied. Bumping VERSION re-arms it.
 */
function uplinksync_home_rhythm_maybe_run() {
	if ( get_option( UPLINKSYNC_HOME_RHYTHM_OPTION ) === UPLINKSYNC_HOME_RHYTHM_VERSION ) {
		return;
	}
	uplinksync_home_rhythm_run();
	update_option( UPLINKSYNC_HOME_RHYTHM_OPTION, UPLINKSYNC_HOME_RHYTHM_VERSION );
}
add_action( 'init', 'uplinksync_home_rhythm_maybe_run', 20 );
