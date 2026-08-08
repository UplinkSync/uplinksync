<?php
/**
 * Plugin Name: UplinkSync — Drone page: strip side-business tells from the FSE template (UPLAA-459)
 * Description: Removes the two remaining "aerial is a bolt-on to our managed-IT desk" tells
 *              from the /drone-services/ page. Unlike the earlier UPLAA-459 migrations, this one
 *              edits the ACTUAL render source.
 * Version: 1.0.0
 *
 * WHY THIS EXISTS (read before touching):
 *   The /drone-services/ body is NOT rendered from the page's post_content — page 397's
 *   post_content is empty. It renders from a Full-Site-Editing block template stored as a
 *   `wp_template` post: theme slug `uplinksync-child//page-drone-services` (UPLAA-478 diagnosis).
 *   Every prior UPLAA-459 str_replace ran against post_content and was therefore a guaranteed
 *   no-op. This migration targets the template post instead, matching how UPLAA-478 landed its
 *   six re-aim swaps.
 *
 *   TWO tells are removed (both were live on prod after UPLAA-478/452 landed):
 *     1. "Work with us" closing line. UPLAA-452's S6 re-aim replacement text itself smuggled the
 *        tell back in: "...with inspection, mapping and survey also available — an additional
 *        service for UplinkSync managed-IT clients and referrals." The trailing clause discloses
 *        the one-desk reality and gates the offer to existing IT clients. Strip the clause; keep
 *        the plain customer-facing sentence.
 *     2. Hero lede tail: "Aerial is an added service for the UplinkSync team that already manages
 *        your IT." Removed entirely — it exists only to frame aerial as a bolt-on off the IT desk.
 *
 *   NO REASON, NO DISCLOSURE (UPLAA-459 scope): replacements state the customer-facing offer
 *   plainly and give no internal reason.
 *
 *   OWNER EDITS ALWAYS WIN: each change is a plain str_replace of a specific seeded string. If an
 *   editor has since rewritten the sentence, the needle won't match and that swap is skipped —
 *   never a destructive rewrite.
 *
 *   IDEMPOTENT + SELF-HEALING: the guard latches on a version option ONLY once the change has
 *   SETTLED (needles absent when re-read fresh from the DB). Until then it re-attempts each
 *   request — cheap: resolve one template + a couple of str_replace calls.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const UPLINKSYNC_DRONE_TELL_TEMPLATE_VERSION = '1.0.0';
const UPLINKSYNC_DRONE_TELL_TEMPLATE_OPTION  = 'uplinksync_drone_tell_template_version';
const UPLINKSYNC_DRONE_TELL_TEMPLATE_APPLIED = 'uplinksync_drone_tell_template_applied';

/**
 * Candidate template ids for the drone page, tried in order. The canonical id is the
 * child-theme FSE template UPLAA-478 identified; the bare-slug fallbacks cover a theme rename.
 */
function uplinksync_drone_tell_template_ids() {
	return array(
		'uplinksync-child//page-drone-services',
		'uplinksync//page-drone-services',
	);
}

/**
 * Ordered list of targeted [ needle => replacement ] swaps applied to the template
 * post_content. Each is independent and no-ops if its needle is absent.
 *
 * Needles are the texturize-safe, dashless cores of the served strings so they match the
 * stored block markup regardless of wptexturize on the em-dashes elsewhere in the sentence.
 */
function uplinksync_drone_tell_template_swaps() {
	return array(
		// 1. "Work with us" closing line — strip ONLY the gating clause, keeping the terminal
		//    period so the sentence ends "...survey also available." Matching just the clause
		//    (not the whole re-aimed sentence) keeps the needle small and independent of how
		//    UPLAA-478's S6 replacement text is stored. Both dash forms are covered: the served
		//    HTML shows an em-dash (—), but the stored block markup may hold a double-hyphen (--)
		//    that wptexturize converts on render. Whichever is present, the leading separator is
		//    consumed so no dangling dash remains before the period.
		array(
			' — an additional service for UplinkSync managed-IT clients and referrals',
			'',
		),
		array(
			' -- an additional service for UplinkSync managed-IT clients and referrals',
			'',
		),
		// Belt-and-braces: if only the core clause (no separator) is stored, drop it too. The
		// preceding "available" then abuts the period directly, still grammatical.
		array(
			'an additional service for UplinkSync managed-IT clients and referrals',
			'',
		),

		// 2. Hero lede tail — remove the bolt-on-off-the-IT-desk sentence entirely. Leading
		//    space consumed so the preceding sentence's period stays clean.
		array(
			' Aerial is an added service for the UplinkSync team that already manages your IT.',
			'',
		),
	);
}

/**
 * The two settle sentinels: the migration has landed iff NEITHER of these disclosure
 * substrings remains in the template content.
 */
function uplinksync_drone_tell_template_sentinels() {
	return array(
		'an additional service for UplinkSync managed-IT clients and referrals',
		'an added service for the UplinkSync team that already manages your IT',
	);
}

/**
 * Resolve the drone-services block template post. Uses the WP block-template API to find the
 * theme template, then loads the backing wp_template post so we can persist an edit.
 */
function uplinksync_drone_tell_template_resolve() {
	if ( ! function_exists( 'get_block_template' ) ) {
		return null;
	}
	foreach ( uplinksync_drone_tell_template_ids() as $id ) {
		$tpl = get_block_template( $id, 'wp_template' );
		if ( $tpl && ! empty( $tpl->wp_id ) ) {
			$post = get_post( $tpl->wp_id );
			if ( $post instanceof WP_Post ) {
				return $post;
			}
		}
	}
	return null;
}

/**
 * Run the strip pass. Returns TRUE only when the change has SETTLED.
 */
function uplinksync_drone_tell_template_run() {
	$post = uplinksync_drone_tell_template_resolve();
	if ( ! ( $post instanceof WP_Post ) ) {
		update_option( UPLINKSYNC_DRONE_TELL_TEMPLATE_APPLIED, 'template-absent' );
		return false;
	}

	$before = $post->post_content;
	$after  = $before;
	foreach ( uplinksync_drone_tell_template_swaps() as $swap ) {
		$after = str_replace( $swap[0], $swap[1], $after );
	}

	$sentinels_gone = function ( $content ) {
		foreach ( uplinksync_drone_tell_template_sentinels() as $s ) {
			if ( false !== strpos( $content, $s ) ) {
				return false;
			}
		}
		return true;
	};

	// Nothing changed. Distinguish "already stripped" (sentinels absent -> settle + latch)
	// from "template not carrying the tell yet" (sentinel present -> retry next request).
	if ( $after === $before ) {
		$already = $sentinels_gone( $before );
		update_option( UPLINKSYNC_DRONE_TELL_TEMPLATE_APPLIED, $already ? 'already' : 'no-match' );
		return $already;
	}

	wp_update_post(
		array(
			'ID'           => $post->ID,
			'post_content' => $after,
		),
		true
	);

	// Confirm the write persisted — a filter/cache reverting it silently must NOT latch the
	// guard. Re-read fresh from the DB, not the object cache.
	clean_post_cache( $post->ID );
	$fresh = get_post( $post->ID );
	$stuck = ( $fresh instanceof WP_Post ) && $sentinels_gone( $fresh->post_content );
	update_option( UPLINKSYNC_DRONE_TELL_TEMPLATE_APPLIED, $stuck ? 'stripped' : 'reverted' );
	return $stuck;
}

/**
 * One-shot guard that latches ONLY once the strip has settled. Until then it re-attempts each
 * request — cheap: resolve one template + a couple of str_replace calls.
 */
function uplinksync_drone_tell_template_maybe_run() {
	if ( get_option( UPLINKSYNC_DRONE_TELL_TEMPLATE_OPTION ) === UPLINKSYNC_DRONE_TELL_TEMPLATE_VERSION ) {
		return;
	}
	if ( uplinksync_drone_tell_template_run() ) {
		update_option( UPLINKSYNC_DRONE_TELL_TEMPLATE_OPTION, UPLINKSYNC_DRONE_TELL_TEMPLATE_VERSION );
	}
}
// Priority 25: after the re-aim (20) and the post_content confession strips (21/22). Order does
// not matter for correctness — this migration targets a different object (the wp_template post).
add_action( 'init', 'uplinksync_drone_tell_template_maybe_run', 25 );
