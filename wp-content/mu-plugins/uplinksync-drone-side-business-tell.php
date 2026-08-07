<?php
/**
 * Plugin Name: UplinkSync — Drone page: strip the side-business tell (UPLAA-459)
 * Description: One-shot, idempotent DATABASE migration that REMOVES the side-business
 *   "tell" from the /drone-services/ page body — the confession clause
 *   "— an additional service for UplinkSync managed-IT clients and referrals" — so a
 *   first-time buyer is not told the drone work is a side line off the managed-IT desk.
 *
 *   WHY THIS EXISTS AS A DB MIGRATION AND NOT A TEMPLATE EDIT:
 *   The MR that removed this clause from the file template
 *   (page-drone-services.html, UPLAA-459 MR !125) merged, but the live
 *   /drone-services/ body renders from the WP DB (post_content), NOT the file
 *   template — proven on UPLAA-474. A file-template edit therefore never touches
 *   what renders. Exactly like uplinksync-drone-listing-copy.php (UPLAA-452/474),
 *   the change must be captured in-repo as a credential-free init-time DB edit that
 *   deploys with wp-content. Live REST is 401-locked to agents, so no wp-cli/SSH.
 *
 *   RELATIONSHIP TO uplinksync-drone-listing-copy.php (UPLAA-452/474):
 *   That migration RE-AIMS the copy; its "Work with us" swap replaces the LEADING
 *   fragment ("Aerial capture for real estate, infrastructure inspection, and
 *   events") and LEAVES the trailing confession clause intact. This migration is
 *   INDEPENDENT and ORDER-SAFE with respect to it: it targets the confession tail
 *   by its stable core phrase regardless of whether the re-aim swap has run yet, so
 *   the two can fire in any init order across any request without colliding.
 *
 *   SAFETY — CANNOT CORRUPT THE PAGE:
 *   The strip is a single preg_replace of one specific, long, high-confidence phrase.
 *   If the phrase is absent the pattern does not match and the pass is a NO-OP — it
 *   registers NO output buffer and touches NO render path (unlike the banned
 *   output-buffer rewrite in uplinksync-unified-services.php [DISABLED]).
 *
 *   DASH-AGNOSTIC NEEDLE:
 *   The DB stored the em-dash separator in an unknown encoding (em/en/hyphen), and
 *   WP may texturize dashes differently than the source template. The core phrase
 *   "an additional service for UplinkSync managed-IT clients and referrals" is
 *   dashless and quoteless (texturize-safe), so it is used both as the strip anchor
 *   and as the settle sentinel. A `[—–-]` character class swallows whatever dash and
 *   surrounding whitespace precede it, collapsing "...events — an additional...
 *   referrals. Full" back to "...events. Full" — the exact result of MR !125.
 *
 *   SETTLE-GATE (adopted from uplinksync-drone-listing-copy.php v1.1.0, from v1.0.0
 *   here so the UPLAA-474 relatch bug is never introduced):
 *   run() returns TRUE only once the change has SETTLED — a persisted write, or the
 *   confession core is confirmed already absent. It returns FALSE when it could not
 *   settle (page not resolvable, or a write did not stick), and the one-shot version
 *   guard latches ONLY on TRUE, so a transient failure retries on a later request
 *   instead of latching permanently against a no-op pass.
 *
 *   OWNER EDITS ALWAYS WIN: if the owner rewrites this sentence, the phrase no longer
 *   matches and the strip is skipped.
 *
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const UPLINKSYNC_DRONE_SIDE_BUSINESS_TELL_VERSION = '1.0.0';
const UPLINKSYNC_DRONE_SIDE_BUSINESS_TELL_OPTION  = 'uplinksync_drone_side_business_tell_version';
const UPLINKSYNC_DRONE_SIDE_BUSINESS_TELL_APPLIED = 'uplinksync_drone_side_business_tell_applied';

/**
 * The dashless, texturize-safe core of the confession clause. Used both as the
 * strip anchor (inside the dash-agnostic pattern) and as the settle sentinel: the
 * migration has landed iff this substring is absent from the post_content.
 */
function uplinksync_drone_side_business_tell_core() {
	return 'an additional service for UplinkSync managed-IT clients and referrals';
}

/**
 * Dash-agnostic pattern: any leading whitespace + any dash variant (em/en/hyphen)
 * + any whitespace + the core phrase. The `u` flag handles UTF-8 dashes. Removing
 * the whole match collapses the sentence back to a clean "...events. Full-res...".
 */
function uplinksync_drone_side_business_tell_pattern() {
	return '/\s*[\x{2014}\x{2013}\-]\s*' . preg_quote( uplinksync_drone_side_business_tell_core(), '/' ) . '/u';
}

/**
 * Resolve the drone page by slug, trying the linked variants in order.
 * Mirrors uplinksync-drone-listing-copy.php so both migrations agree on the target.
 */
function uplinksync_drone_side_business_tell_resolve() {
	$paths = array( 'drone-services', 'drone-services-2', 'drone' );
	foreach ( $paths as $path ) {
		$page = get_page_by_path( $path, OBJECT, 'page' );
		if ( $page instanceof WP_Post ) {
			return $page;
		}
	}
	foreach ( $paths as $slug ) {
		$found = get_posts(
			array(
				'post_type'      => 'page',
				'name'           => $slug,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
			)
		);
		if ( ! empty( $found ) && $found[0] instanceof WP_Post ) {
			return $found[0];
		}
	}
	return null;
}

/**
 * Run the strip pass. Returns TRUE only when the change has SETTLED (see header).
 */
function uplinksync_drone_side_business_tell_run() {
	$page = uplinksync_drone_side_business_tell_resolve();
	if ( ! ( $page instanceof WP_Post ) ) {
		update_option( UPLINKSYNC_DRONE_SIDE_BUSINESS_TELL_APPLIED, 'page-absent' );
		return false;
	}

	$core    = uplinksync_drone_side_business_tell_core();
	$pattern = uplinksync_drone_side_business_tell_pattern();

	$before  = $page->post_content;
	$content = preg_replace( $pattern, '', $before );

	// preg_replace returns null on error — treat as an unsettled pass, do not latch.
	if ( null === $content ) {
		update_option( UPLINKSYNC_DRONE_SIDE_BUSINESS_TELL_APPLIED, 'preg-error' );
		return false;
	}

	// Nothing changed. Distinguish "already stripped" (core absent -> settle + latch)
	// from "page not yet loaded with the tell" (core present -> retry next request).
	if ( $content === $before ) {
		$already = ( false === strpos( $before, $core ) );
		update_option( UPLINKSYNC_DRONE_SIDE_BUSINESS_TELL_APPLIED, $already ? 'already' : 'no-match' );
		return $already;
	}

	wp_update_post(
		array(
			'ID'           => $page->ID,
			'post_content' => $content,
		),
		true
	);

	// Confirm the write persisted — a filter/cache reverting it silently must NOT
	// latch the guard. Re-read fresh from the DB, not the object cache.
	clean_post_cache( $page->ID );
	$fresh = get_post( $page->ID );
	$stuck = ( $fresh instanceof WP_Post ) && ( false === strpos( $fresh->post_content, $core ) );
	update_option( UPLINKSYNC_DRONE_SIDE_BUSINESS_TELL_APPLIED, $stuck ? 'stripped' : 'reverted' );
	return $stuck;
}

/**
 * One-shot guard that latches ONLY once the strip has settled (see run()). Until
 * then it re-attempts each request — cheap: resolve one page + one preg_replace.
 */
function uplinksync_drone_side_business_tell_maybe_run() {
	if ( get_option( UPLINKSYNC_DRONE_SIDE_BUSINESS_TELL_OPTION ) === UPLINKSYNC_DRONE_SIDE_BUSINESS_TELL_VERSION ) {
		return;
	}
	if ( uplinksync_drone_side_business_tell_run() ) {
		update_option( UPLINKSYNC_DRONE_SIDE_BUSINESS_TELL_OPTION, UPLINKSYNC_DRONE_SIDE_BUSINESS_TELL_VERSION );
	}
}
// Priority 21: after the re-aim migration (priority 20) when both run in one request,
// but correctness does not depend on order — the two needles are disjoint.
add_action( 'init', 'uplinksync_drone_side_business_tell_maybe_run', 21 );
