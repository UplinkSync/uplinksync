<?php
/**
 * Plugin Name: UplinkSync — Real-estate bundle price/turnaround figures (UPLAA-475)
 * NOTE ON THE NEEDLE: the live post_content stores the placeholder with a `***-20` token (a
 * history-scrub artifact from an early commit), NOT `UPLAA-20`. The needle below matches the
 * stored string VERBATIM — matching the wrong token is precisely the no-op trap that stranded
 * UPLAA-474.
 *
 * Description: One-shot, idempotent DATABASE migration that fills the "What it costs and how
 *   fast" block on the live /for/real-estate/ bundle page with the owner-APPROVED Option A
 *   figures (owner decision recorded on UPLAA-475, 2026-08-08):
 *       • $1,500 to build (one-time)   • $175/month (hosting & care)
 *       • IDX / MLS listing feed billed pass-through (at cost)   • 10-day delivery SLA
 *   These four figures are the owner's, verbatim from his decision — NOTHING is invented.
 *
 *   WHAT IS DELIBERATELY LEFT BLANK (barred from fabrication, per the owner comment):
 *     - Listing photography/video and its per-listing turnaround: owner-supplied media that
 *       does not exist yet (the /drone-services/ library is 122 scenic prints, zero property
 *       work — MEASURED, UPLAA-444). The photography-pending marker in the page body is NOT
 *       touched by this migration.
 *     - Coverage area: the owner did NOT provide a coverage-area list, so it stays a visible
 *       "quoted per brokerage" ask rather than an invented service radius. Fabricating client-
 *       facing coverage claims is barred regardless of how good the placeholder would look.
 *
 *   WHY A DB MIGRATION AND NOT A SEED EDIT: /for/real-estate/ is already live in the WP DB
 *   (seeded by uplinksync-page-seeder.php, which is INSERT-ONLY — it never edits an existing
 *   page). Editing the seed body alone therefore cannot update the live page. This migration
 *   surgically str_replaces the single placeholder paragraph in post_content, the same
 *   credential-free, repo-captured idiom proven safe by uplinksync-service-copy.php and
 *   uplinksync-drone-listing-copy.php. It registers NO output buffer and touches NO render
 *   path (the ob_start rewrites that blanked prod twice are banned here).
 *
 *   OWNER EDITS ALWAYS WIN: the swap fires only while the untouched `UPLAA-20 / owner-gated`
 *   placeholder signature is still present in post_content. If the owner (or anyone) has
 *   already edited that block, the needle no longer matches and the migration is a no-op — it
 *   cannot blank or corrupt the page.
 *
 *   SELF-HEALING SETTLE-GATE: the one-shot version guard latches ONLY after the write is
 *   confirmed to have persisted (re-read fresh from the DB, not the object cache) OR the page
 *   is confirmed already carrying the figures (fresh install seeded them directly). A transient
 *   failure — page absent at init, stale cache, a reverted write — is retried on a later
 *   request instead of being permanently latched after a single no-op pass (the UPLAA-474 bug).
 *
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const UPLINKSYNC_RE_FIGURES_VERSION = '1.0.0';
const UPLINKSYNC_RE_FIGURES_OPTION  = 'uplinksync_real_estate_figures_version';
const UPLINKSYNC_RE_FIGURES_APPLIED = 'uplinksync_real_estate_figures_applied'; // audit: number | already | no-match | page-absent | reverted

/**
 * The placeholder paragraph seeded on the live page (uplinksync-page-seeder.php, UPLAA-456).
 * Matched verbatim, including the real newlines the seeder wrote (PHP "\n"), so this is an
 * exact substring of the stored post_content.
 */
function uplinksync_re_figures_needle() {
	return "<!-- wp:paragraph -->\n<p><!-- ***-20 / owner-gated: a stated price and a stated listing-media turnaround time are design goals (UPLAA-444) but are the owner's to set. Real-estate buyers shopping repeat volume specifically want per-listing pricing + turnaround SLA + coverage area (MEASURED gap in the booking flow). Leave these as a visible request rather than inventing numbers. --></p>\n<!-- /wp:paragraph -->";
}

/**
 * The owner-approved Option A figures (2026-08-08). Prices are escaped (\$) so PHP does not
 * treat them as variable interpolation. Media pricing and coverage area stay a visible ask —
 * not invented — with an audit marker retained.
 */
function uplinksync_re_figures_replacement() {
	return "<!-- wp:list -->\n<ul>\n<li><strong>\$1,500 to build</strong> — one-time, for a website designed and built for your brokerage.</li>\n<li><strong>\$175/month</strong> — hosting, care, and day-to-day support once you're live.</li>\n<li><strong>IDX / MLS listing feed</strong> — integrated and billed pass-through (at cost).</li>\n<li><strong>10-day delivery</strong> — from kickoff to launch.</li>\n</ul>\n<!-- /wp:list -->\n\n<!-- wp:paragraph -->\n<p>Listing photography &amp; video and your coverage area are quoted per brokerage — <a href=\"/contact/#quote-form\">tell us about your listings</a> and we'll scope it with you. <!-- UPLAA-475 owner-gated: per-listing media pricing + coverage-area list are the owner's to set; left as a visible ask rather than invented (owner decision 2026-08-08: Option A pricing applied, media/coverage remain owner-supplied). --></p>\n<!-- /wp:paragraph -->";
}

/**
 * Resolve /for/real-estate/ (nested under the `for` parent). Full-hierarchy match first,
 * then a leaf post_name fallback in case it sits under an unexpected parent.
 */
function uplinksync_re_figures_resolve() {
	$page = get_page_by_path( 'for/real-estate', OBJECT, 'page' );
	if ( $page instanceof WP_Post ) {
		return $page;
	}
	$found = get_posts(
		array(
			'post_type'      => 'page',
			'name'           => 'real-estate',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
		)
	);
	if ( ! empty( $found ) && $found[0] instanceof WP_Post ) {
		return $found[0];
	}
	return null;
}

/**
 * Run the swap. Returns TRUE only when settled: the placeholder was replaced and the write
 * persisted, OR the page already carries the figures (needle gone AND the $1,500 sentinel
 * present — e.g. a fresh install seeded the final body). Returns FALSE when it could not
 * settle (page absent, needle present but write did not stick, or the write was reverted),
 * so the caller retries on a later request rather than latching a no-op.
 */
function uplinksync_re_figures_run() {
	$page = uplinksync_re_figures_resolve();
	if ( ! ( $page instanceof WP_Post ) ) {
		update_option( UPLINKSYNC_RE_FIGURES_APPLIED, 'page-absent' );
		return false;
	}

	$needle   = uplinksync_re_figures_needle();
	$sentinel = '$1,500 to build'; // present iff the figures are already on the page
	$before   = $page->post_content;

	// Nothing to swap. Distinguish "already applied" (settle + latch) from "not yet".
	if ( false === strpos( $before, $needle ) ) {
		$already = ( false !== strpos( $before, $sentinel ) );
		update_option( UPLINKSYNC_RE_FIGURES_APPLIED, $already ? 'already' : 'no-match' );
		return $already;
	}

	$content = str_replace( $needle, uplinksync_re_figures_replacement(), $before );

	wp_update_post(
		array(
			'ID'           => $page->ID,
			'post_content' => $content,
		),
		true
	);

	// Confirm the write persisted — a filter or cache layer silently reverting it must NOT
	// latch the guard. Re-read fresh from the DB, not the object cache.
	clean_post_cache( $page->ID );
	$fresh = get_post( $page->ID );
	$stuck = ( $fresh instanceof WP_Post )
		&& ( false === strpos( $fresh->post_content, $needle ) )
		&& ( false !== strpos( $fresh->post_content, $sentinel ) );
	update_option( UPLINKSYNC_RE_FIGURES_APPLIED, $stuck ? 'applied' : 'reverted' );
	return $stuck;
}

/**
 * One-shot guard that latches ONLY once the migration has settled (see run()). Until then it
 * re-attempts on each request — cheap: resolve one page + a couple of strpos checks. Once the
 * version option is set the guard short-circuits.
 */
function uplinksync_re_figures_maybe_run() {
	if ( get_option( UPLINKSYNC_RE_FIGURES_OPTION ) === UPLINKSYNC_RE_FIGURES_VERSION ) {
		return;
	}
	if ( uplinksync_re_figures_run() ) {
		update_option( UPLINKSYNC_RE_FIGURES_OPTION, UPLINKSYNC_RE_FIGURES_VERSION );
	}
}
add_action( 'init', 'uplinksync_re_figures_maybe_run', 20 );
