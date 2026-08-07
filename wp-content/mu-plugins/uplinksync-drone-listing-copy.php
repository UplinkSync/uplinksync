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
 *   UPLAA-474 (v1.1.0): the v1.0.0 guard latched the version option on prod after a
 *   single pass that did NOT apply (page absent at init / object-cached stale body),
 *   so the H1/body swaps never landed and never retried. This revision (a) latches
 *   ONLY once the change has settled or is confirmed already present, retrying until
 *   then, and (b) bumps the version so the already-latched prod option is superseded
 *   and the pass re-runs.
 *
 * Version: 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const UPLINKSYNC_DRONE_LISTING_COPY_VERSION = '1.1.0';
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

/**
 * Run the swap pass.
 *
 * Returns TRUE only when the migration has SETTLED — it either applied at least
 * one swap that persisted, or the page is confirmed already re-aimed (the lead
 * H1 needle is gone AND its replacement is present). Returns FALSE when the pass
 * could NOT settle: page not resolvable yet, source needles still present but no
 * write stuck, or the write was reverted. The caller latches the one-shot version
 * guard ONLY on TRUE, so a transient failure (page absent at init, object-cached
 * stale post_content, a reverted write) is retried on a later request instead of
 * being permanently latched after a single no-op pass — the root cause of
 * UPLAA-474, where the guard latched against a page whose H1 swap never took.
 */
function uplinksync_drone_listing_copy_run() {
	$page = uplinksync_drone_listing_copy_resolve();
	if ( ! ( $page instanceof WP_Post ) ) {
		update_option( UPLINKSYNC_DRONE_LISTING_COPY_APPLIED, 'page-absent' );
		return false;
	}

	$swaps   = uplinksync_drone_listing_copy_swaps();
	$h1_from = $swaps[0][0];
	$h1_to   = $swaps[0][1];

	$content = $page->post_content;
	$before  = $content;
	$applied = 0;

	foreach ( $swaps as $swap ) {
		list( $needle, $replacement ) = $swap;
		if ( '' !== $needle && false !== strpos( $content, $needle ) ) {
			$content = str_replace( $needle, $replacement, $content );
			$applied++;
		}
	}

	// Nothing changed this pass. Distinguish "already re-aimed" (settle + latch)
	// from "not yet" (do not latch; retry next request). The lead H1 swap is the
	// sentinel: source gone AND replacement present == the migration already landed.
	if ( $content === $before ) {
		$already = ( false === strpos( $before, $h1_from ) ) && ( false !== strpos( $before, $h1_to ) );
		update_option( UPLINKSYNC_DRONE_LISTING_COPY_APPLIED, $already ? 'already' : 'no-match' );
		return $already;
	}

	wp_update_post(
		array(
			'ID'           => $page->ID,
			'post_content' => $content,
		),
		true
	);

	// Confirm the write persisted — a filter or cache layer reverting it silently
	// must NOT latch the guard. Re-read fresh from the DB, not the object cache.
	clean_post_cache( $page->ID );
	$fresh = get_post( $page->ID );
	$stuck = ( $fresh instanceof WP_Post ) && ( false === strpos( $fresh->post_content, $h1_from ) );
	update_option( UPLINKSYNC_DRONE_LISTING_COPY_APPLIED, $stuck ? (string) $applied : 'reverted' );
	return $stuck;
}

/**
 * One-shot guard that latches ONLY once the migration has settled (see run()).
 * Until then it re-attempts on each request — cheap: resolve one page + a few
 * strpos checks. Once the version option is set the guard short-circuits.
 */
function uplinksync_drone_listing_copy_maybe_run() {
	if ( get_option( UPLINKSYNC_DRONE_LISTING_COPY_OPTION ) === UPLINKSYNC_DRONE_LISTING_COPY_VERSION ) {
		return;
	}
	if ( uplinksync_drone_listing_copy_run() ) {
		update_option( UPLINKSYNC_DRONE_LISTING_COPY_OPTION, UPLINKSYNC_DRONE_LISTING_COPY_VERSION );
	}
}
add_action( 'init', 'uplinksync_drone_listing_copy_maybe_run', 20 );
