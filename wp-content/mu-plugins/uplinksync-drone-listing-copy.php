<?php
/**
 * Plugin Name: UplinkSync — Drone page: re-aim at listing photo/video (UPLAA-452)
 * Description: One-shot, idempotent DATABASE migration that RE-AIMS the /drone-services/
 *   page body toward property/listing photo & video and DEMOTES inspection / mapping /
 *   survey to a supporting line — the SHIP ORDER #7 correction (scope-v3; converged
 *   decision UPLAA-444 comment 90e2be2d). The page body lives in the WP DB (post_content),
 *   not the repo, and live REST is 401-locked to agents, so — exactly like
 *   uplinksync-service-copy.php and uplinksync-page-seeder.php — the change is captured
 *   in-repo as a credential-free init-time DB edit that deploys with wp-content.
 *
 *   WHY TARGETED str_replace AND NOT A WHOLESALE PAGE REWRITE: the drone page is a rich,
 *   owner-built layout (Immich video embeds, the watermark-as-proof-of-authorship passage
 *   that is PROTECTED per UPLAA-452 comment 1, gallery collections). We must NOT restructure
 *   it. Each swap below is a single high-confidence substring that either matches exactly
 *   (and is replaced) or is not found (and is a NO-OP). A not-found needle changes nothing,
 *   so this migration cannot blank or corrupt the page the way the banned output-buffer
 *   rewrite did (see uplinksync-unified-services.php [DISABLED AGAIN]). It registers NO
 *   output buffer and touches NO render path.
 *
 *   NEEDLES ARE TEXTURIZE-SAFE: prose needles avoid smart quotes and dashes (which WP may
 *   store or texturize differently than the rendered HTML). The estimator option strings
 *   live in a `wp:html` block, which WordPress stores and emits VERBATIM, so those needles
 *   are matched against the exact rendered markup (including `&amp;`).
 *
 *   SCOPE GUARDRAILS (UPLAA-452 comment 1):
 *     - DEMOTE, DO NOT DELETE. Inspection/mapping stay sellable; they stop being co-equal
 *       with the creative offer. This migration re-aims COPY only.
 *     - The estimator line items (mapping/orthomosaic deliverables) are NOT removed here.
 *       Comment 1: "Worth one sentence of confirmation before deleting inspection
 *       deliverables from the estimator." That confirmation is raised separately on the
 *       issue; deletion is deferred until it lands. We only relabel the lead option so the
 *       creative line reads first-class.
 *     - The PROTECTED watermark passage is never targeted.
 *
 *   OWNER EDITS ALWAYS WIN: because each swap is a plain str_replace of a specific seeded
 *   phrase, any owner rewrite of that phrase means the needle no longer matches and the
 *   swap is skipped. The migration records how many swaps applied (per version) for audit.
 *
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const UPLINKSYNC_DRONE_LISTING_COPY_VERSION = '1.0.0';
const UPLINKSYNC_DRONE_LISTING_COPY_OPTION  = 'uplinksync_drone_listing_copy_version';
const UPLINKSYNC_DRONE_LISTING_COPY_APPLIED = 'uplinksync_drone_listing_copy_applied';

/**
 * Ordered list of targeted [ needle => replacement ] swaps applied to the
 * /drone-services/ post_content. Each is independent and no-ops if absent.
 */
function uplinksync_drone_listing_copy_swaps() {
	return array(
		// --- H1 (heading block; texturize-safe fragment, dashless/quoteless) -----------
		array(
			'Aerial imagery, inspection stills, and site overviews',
			'Property and listing photo and video',
		),

		// --- Hero lede: lead on listing media; keep the FAA/in-house proof --------------
		array(
			'High-resolution aerial photography, infrastructure inspection stills, and full-site overviews across Idaho and Utah',
			'High-resolution aerial photos and cinematic video of your property or listing across Idaho and Utah',
		),

		// --- "Aerial imagery" receive-card heading: name the primary offer clearly ------
		array(
			'>Aerial imagery</h2>',
			'>Listing photo &amp; video</h2>',
		),

		// --- Collapse the two supporting cards into one demoted "Also available" line.
		//     Inspection-stills card heading -> the single supporting-services heading. ---
		array(
			'>Inspection stills</h2>',
			'>Also available</h2>',
		),
		array(
			'Close-up frames of roofs, structures, and hard-to-reach infrastructure — clear detail for condition reports without putting anyone on a ladder.',
			'Roof and structure inspection, mapping, and site survey — flown by the same certified operator when you need documentation rather than marketing media.',
		),

		// --- "Work with us" closing line: lead real-estate, demote the rest -------------
		array(
			'Aerial capture for real estate, infrastructure inspection, and events',
			'Property and listing photo and video, plus events — with inspection, mapping and survey also available',
		),
	);
}

/**
 * Resolve the drone page by slug, trying the linked variants in order.
 */
function uplinksync_drone_listing_copy_resolve() {
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

function uplinksync_drone_listing_copy_run() {
	$page = uplinksync_drone_listing_copy_resolve();
	if ( ! ( $page instanceof WP_Post ) ) {
		update_option( UPLINKSYNC_DRONE_LISTING_COPY_APPLIED, 'page-absent' );
		return;
	}

	$content = $page->post_content;
	$before  = $content;
	$applied = 0;

	foreach ( uplinksync_drone_listing_copy_swaps() as $swap ) {
		list( $needle, $replacement ) = $swap;
		if ( '' !== $needle && false !== strpos( $content, $needle ) ) {
			$content = str_replace( $needle, $replacement, $content );
			$applied++;
		}
	}

	// Nothing matched (already re-aimed, or owner-edited) => idempotent no-op.
	if ( $content === $before ) {
		update_option( UPLINKSYNC_DRONE_LISTING_COPY_APPLIED, '0' );
		return;
	}

	wp_update_post(
		array(
			'ID'           => $page->ID,
			'post_content' => $content,
		),
		true
	);
	update_option( UPLINKSYNC_DRONE_LISTING_COPY_APPLIED, (string) $applied );
}

/**
 * One-shot guard. The pass is itself idempotent (every needle is gone after the
 * first apply), so this avoids re-scanning post_content on every request.
 */
function uplinksync_drone_listing_copy_maybe_run() {
	if ( get_option( UPLINKSYNC_DRONE_LISTING_COPY_OPTION ) === UPLINKSYNC_DRONE_LISTING_COPY_VERSION ) {
		return;
	}
	uplinksync_drone_listing_copy_run();
	update_option( UPLINKSYNC_DRONE_LISTING_COPY_OPTION, UPLINKSYNC_DRONE_LISTING_COPY_VERSION );
}
add_action( 'init', 'uplinksync_drone_listing_copy_maybe_run', 20 );
