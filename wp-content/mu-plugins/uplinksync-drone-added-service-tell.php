<?php
/**
 * Plugin Name: UplinkSync — Drone page: strip the "added service" tell (UPLAA-459)
 * Description: One-shot, idempotent DATABASE migration that REMOVES a SECOND
 *   side-business "tell" from the /drone-services/ page body — the sentence
 *   "Aerial is an added service for the UplinkSync team that already manages your IT."
 *   in the intro/hero block, so a first-time buyer is not told the drone work is a
 *   bolt-on off the managed-IT desk.
 *
 *   WHY A SEPARATE MIGRATION FROM uplinksync-drone-side-business-tell.php (MR !128):
 *   The sitewide copy sweep (UPLAA-459) found TWO distinct tells rendered live on
 *   /drone-services/, in two different blocks:
 *     (A) the "Work with us" trailing clause
 *         "— an additional service for UplinkSync managed-IT clients and referrals"
 *         — handled by uplinksync-drone-side-business-tell.php (MR !128).
 *     (B) the intro/hero sentence THIS migration strips:
 *         "Aerial is an added service for the UplinkSync team that already manages
 *          your IT."
 *   MR !128's needle is the phrase "an additional service for UplinkSync managed-IT
 *   clients and referrals" and does NOT match tell (B). The two needles are disjoint,
 *   so this migration is INDEPENDENT and ORDER-SAFE with respect to !128 and to the
 *   re-aim migration (uplinksync-drone-listing-copy.php, UPLAA-452/474) — all three
 *   can fire in any init order across any request without colliding.
 *
 *   WHY THIS EXISTS AS A DB MIGRATION AND NOT A TEMPLATE EDIT:
 *   The live /drone-services/ body renders from the WP DB (post_content), NOT the
 *   file template — proven on UPLAA-474. A file-template edit therefore never touches
 *   what renders. Like uplinksync-drone-listing-copy.php and
 *   uplinksync-drone-side-business-tell.php, the change is captured in-repo as a
 *   credential-free init-time DB edit that deploys with wp-content. Live REST is
 *   401-locked to agents, so no wp-cli/SSH is available.
 *
 *   SAFETY — CANNOT CORRUPT THE PAGE:
 *   The strip is a single preg_replace of one specific, long, high-confidence
 *   sentence. If the sentence is absent the pattern does not match and the pass is a
 *   NO-OP — it registers NO output buffer and touches NO render path.
 *
 *   TEXTURIZE-SAFE NEEDLE:
 *   The core phrase "an added service for the UplinkSync team that already manages
 *   your IT" is dashless and quoteless, so WP's wptexturize cannot rewrite it. The
 *   pattern swallows the leading "Aerial is " and the trailing period, collapsing
 *   "...your delivery looks like. Aerial is an added service ... your IT." back to
 *   "...your delivery looks like." — a clean paragraph end.
 *
 *   SETTLE-GATE (adopted from uplinksync-drone-side-business-tell.php v1.0.0 so the
 *   UPLAA-474 relatch bug is never introduced):
 *   run() returns TRUE only once the change has SETTLED — a persisted write, or the
 *   core is confirmed already absent. It returns FALSE when it could not settle (page
 *   not resolvable, or a write did not stick), and the one-shot version guard latches
 *   ONLY on TRUE, so a transient failure retries on a later request instead of
 *   latching permanently against a no-op pass.
 *
 *   OWNER EDITS ALWAYS WIN: if the owner rewrites this sentence, the phrase no longer
 *   matches and the strip is skipped.
 *
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const UPLINKSYNC_DRONE_ADDED_SERVICE_TELL_VERSION = '1.0.0';
const UPLINKSYNC_DRONE_ADDED_SERVICE_TELL_OPTION  = 'uplinksync_drone_added_service_tell_version';
const UPLINKSYNC_DRONE_ADDED_SERVICE_TELL_APPLIED = 'uplinksync_drone_added_service_tell_applied';

/**
 * The dashless, texturize-safe core of the "added service" sentence. Used both as
 * the strip anchor (inside the pattern) and as the settle sentinel: the migration
 * has landed iff this substring is absent from the post_content.
 */
function uplinksync_drone_added_service_tell_core() {
	return 'an added service for the UplinkSync team that already manages your IT';
}

/**
 * Pattern: any leading whitespace + "Aerial is " + the core phrase + optional
 * whitespace + a trailing period. The `u` flag keeps it UTF-8 safe. Removing the
 * whole match collapses the sentence away and leaves the preceding sentence's period
 * intact ("...your delivery looks like.").
 */
function uplinksync_drone_added_service_tell_pattern() {
	return '/\s*Aerial is ' . preg_quote( uplinksync_drone_added_service_tell_core(), '/' ) . '\s*\./u';
}

/**
 * Resolve the drone page by slug, trying the linked variants in order.
 * Mirrors the sibling drone migrations so all agree on the target.
 */
function uplinksync_drone_added_service_tell_resolve() {
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
function uplinksync_drone_added_service_tell_run() {
	$page = uplinksync_drone_added_service_tell_resolve();
	if ( ! ( $page instanceof WP_Post ) ) {
		update_option( UPLINKSYNC_DRONE_ADDED_SERVICE_TELL_APPLIED, 'page-absent' );
		return false;
	}

	$core    = uplinksync_drone_added_service_tell_core();
	$pattern = uplinksync_drone_added_service_tell_pattern();

	$before  = $page->post_content;
	$content = preg_replace( $pattern, '', $before );

	// preg_replace returns null on error — treat as an unsettled pass, do not latch.
	if ( null === $content ) {
		update_option( UPLINKSYNC_DRONE_ADDED_SERVICE_TELL_APPLIED, 'preg-error' );
		return false;
	}

	// Nothing changed. Distinguish "already stripped" (core absent -> settle + latch)
	// from "page not yet loaded with the tell" (core present -> retry next request).
	if ( $content === $before ) {
		$already = ( false === strpos( $before, $core ) );
		update_option( UPLINKSYNC_DRONE_ADDED_SERVICE_TELL_APPLIED, $already ? 'already' : 'no-match' );
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
	update_option( UPLINKSYNC_DRONE_ADDED_SERVICE_TELL_APPLIED, $stuck ? 'stripped' : 'reverted' );
	return $stuck;
}

/**
 * One-shot guard that latches ONLY once the strip has settled (see run()). Until
 * then it re-attempts each request — cheap: resolve one page + one preg_replace.
 */
function uplinksync_drone_added_service_tell_maybe_run() {
	if ( get_option( UPLINKSYNC_DRONE_ADDED_SERVICE_TELL_OPTION ) === UPLINKSYNC_DRONE_ADDED_SERVICE_TELL_VERSION ) {
		return;
	}
	if ( uplinksync_drone_added_service_tell_run() ) {
		update_option( UPLINKSYNC_DRONE_ADDED_SERVICE_TELL_OPTION, UPLINKSYNC_DRONE_ADDED_SERVICE_TELL_VERSION );
	}
}
// Priority 22: after the re-aim (20) and the !128 confession-tail strip (21) when all
// run in one request, but correctness does not depend on order — the needles are disjoint.
add_action( 'init', 'uplinksync_drone_added_service_tell_maybe_run', 22 );
