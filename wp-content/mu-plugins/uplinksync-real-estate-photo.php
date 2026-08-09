<?php
/**
 * Plugin Name: UplinkSync — Real-estate bundle listing photo (UPLAA-475)
 * NOTE ON THE NEEDLE: the live post_content stores the marker with a `***-456` token (a
 * history-scrub artifact), NOT `UPLAA-456`. The needle below matches the stored string
 * VERBATIM — matching the wrong token is the no-op trap that stranded UPLAA-474.
 *
 * Description: One-shot, idempotent DATABASE migration that swaps the "photography pending"
 *   marker on the live /for/real-estate/ bundle page for the OWNER-APPROVED listing photo.
 *
 *   OWNER AUTHORIZATION: on UPLAA-475 the owner accepted request_confirmation e1eef027
 *   (2026-08-08) to publish `OpenHouse_t20-web.jpg` — a client open-house still — into the
 *   media slot. The personal/family Dave-Joni property footage was explicitly held OUT and is
 *   NOT shipped. The published image is the EXIF-stripped 1600x900 web derivative captured in
 *   this plugin's uplinksync-assets/ directory (GPS/metadata removed before commit).
 *
 *   WHAT STAYS BLANK (still barred from fabrication): coverage area and per-listing media
 *   pricing remain a visible "quoted per brokerage" ask (owner did not provide them). This
 *   migration only fills the single image slot the owner authorized.
 *
 *   WHY A DB MIGRATION, NOT A SEED EDIT: /for/real-estate/ is already live; the seeder is
 *   insert-only and never edits an existing page. This migration sideloads the committed image
 *   into the media library (once), then str_replaces the single marker paragraph in
 *   post_content with an image block. It registers NO output buffer and touches NO render path.
 *
 *   OWNER EDITS ALWAYS WIN: the swap fires only while the untouched `***-456 photography
 *   pending` marker is still present. If anyone has edited that block, the needle no longer
 *   matches and the migration is a no-op — it cannot blank or corrupt the page.
 *
 *   SELF-HEALING SETTLE-GATE: the one-shot version guard latches ONLY after the write is
 *   confirmed persisted (re-read fresh from the DB) OR the page already carries the image.
 *   A transient failure retries on a later request instead of latching a no-op (UPLAA-474 bug).
 *
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const UPLINKSYNC_RE_PHOTO_VERSION = '1.0.0';
const UPLINKSYNC_RE_PHOTO_OPTION  = 'uplinksync_real_estate_photo_version';
const UPLINKSYNC_RE_PHOTO_APPLIED = 'uplinksync_real_estate_photo_applied'; // audit: applied | already | no-match | page-absent | reverted | sideload-failed
const UPLINKSYNC_RE_PHOTO_ATTACH  = 'uplinksync_real_estate_photo_attachment_id';

const UPLINKSYNC_RE_PHOTO_FILE = 'uplinksync-assets/real-estate-listing-openhouse.jpg';
const UPLINKSYNC_RE_PHOTO_ALT  = 'Aerial view of a client open-house listing captured by UplinkSync FAA Part 107 drone media';

/**
 * The photography-pending marker paragraph seeded on the live page (uplinksync-page-seeder.php,
 * UPLAA-456). Matched verbatim, including the seeder's real newlines, so this is an exact
 * substring of the stored post_content.
 */
function uplinksync_re_photo_needle() {
	return "<!-- wp:paragraph -->\n<p><!-- ***-456 photography pending: NO property/listing imagery exists yet (/drone-services/ is 122 scenic prints, zero property work — MEASURED, UPLAA-444). Do NOT substitute stock or generated listing photos. Insert a real listing photo/video gallery here once the owner supplies property media. Component intentionally omitted until then (design-standard §7 / visual-system §5). --></p>\n<!-- /wp:paragraph -->";
}

/**
 * The published image block. Built at run time so the attachment URL/ID reflect the sideloaded
 * media. A retained audit comment records the owner authorization.
 */
function uplinksync_re_photo_replacement( $attachment_id, $url ) {
	$alt = esc_attr( UPLINKSYNC_RE_PHOTO_ALT );
	$src = esc_url( $url );
	return "<!-- wp:image {\"id\":{$attachment_id},\"sizeSlug\":\"large\",\"linkDestination\":\"none\"} -->\n"
		. "<figure class=\"wp-block-image size-large\"><img src=\"{$src}\" alt=\"{$alt}\" class=\"wp-image-{$attachment_id}\"/>"
		. "<figcaption class=\"wp-element-caption\">A client open-house listing, captured with FAA Part 107 aerial media. <!-- UPLAA-475 owner-approved (accepted confirmation e1eef027, 2026-08-08): client open-house still; personal-property footage deliberately excluded. --></figcaption></figure>\n"
		. "<!-- /wp:image -->";
}

/** Resolve /for/real-estate/ (nested under the `for` parent), with a leaf fallback. */
function uplinksync_re_photo_resolve() {
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
 * Sideload the committed image into the media library exactly once. Returns the attachment ID,
 * or 0 on failure. Idempotent: the ID is cached in an option, and a prior sideload with the
 * same source basename is reused rather than duplicated.
 */
function uplinksync_re_photo_ensure_attachment() {
	$cached = (int) get_option( UPLINKSYNC_RE_PHOTO_ATTACH, 0 );
	if ( $cached > 0 && get_post( $cached ) instanceof WP_Post ) {
		return $cached;
	}

	// Reuse an existing attachment created by a prior run (guards against option loss).
	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'name'           => 'real-estate-listing-openhouse',
			'posts_per_page' => 1,
			'post_status'    => 'inherit',
		)
	);
	if ( ! empty( $existing ) && $existing[0] instanceof WP_Post ) {
		update_option( UPLINKSYNC_RE_PHOTO_ATTACH, $existing[0]->ID );
		return $existing[0]->ID;
	}

	$src = plugin_dir_path( __FILE__ ) . UPLINKSYNC_RE_PHOTO_FILE;
	if ( ! file_exists( $src ) ) {
		return 0;
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	// Copy to a temp path so media_handle_sideload can move it without touching the repo file.
	$tmp = wp_tempnam( 'real-estate-listing-openhouse.jpg' );
	if ( ! $tmp || ! @copy( $src, $tmp ) ) {
		return 0;
	}

	$file_array = array(
		'name'     => 'real-estate-listing-openhouse.jpg',
		'tmp_name' => $tmp,
	);
	$attachment_id = media_handle_sideload( $file_array, 0, 'UplinkSync real-estate listing (open house)' );
	if ( is_wp_error( $attachment_id ) ) {
		@unlink( $tmp );
		return 0;
	}

	update_post_meta( $attachment_id, '_wp_attachment_image_alt', UPLINKSYNC_RE_PHOTO_ALT );
	update_option( UPLINKSYNC_RE_PHOTO_ATTACH, $attachment_id );
	return (int) $attachment_id;
}

/**
 * Run the swap. Returns TRUE only when settled: the marker was replaced and the write
 * persisted, OR the page already carries the image. Returns FALSE when it could not settle
 * (page absent, sideload failed, or the write reverted), so the caller retries later.
 */
function uplinksync_re_photo_run() {
	$page = uplinksync_re_photo_resolve();
	if ( ! ( $page instanceof WP_Post ) ) {
		update_option( UPLINKSYNC_RE_PHOTO_APPLIED, 'page-absent' );
		return false;
	}

	$needle   = uplinksync_re_photo_needle();
	$sentinel = 'real-estate-listing-openhouse'; // present in the img src/class once applied
	$before   = $page->post_content;

	// Nothing to swap. Distinguish "already applied" (settle + latch) from "not yet".
	if ( false === strpos( $before, $needle ) ) {
		$already = ( false !== strpos( $before, $sentinel ) );
		update_option( UPLINKSYNC_RE_PHOTO_APPLIED, $already ? 'already' : 'no-match' );
		return $already;
	}

	$attachment_id = uplinksync_re_photo_ensure_attachment();
	if ( $attachment_id <= 0 ) {
		update_option( UPLINKSYNC_RE_PHOTO_APPLIED, 'sideload-failed' );
		return false;
	}

	$url     = wp_get_attachment_url( $attachment_id );
	$content = str_replace( $needle, uplinksync_re_photo_replacement( $attachment_id, $url ), $before );

	wp_update_post(
		array(
			'ID'           => $page->ID,
			'post_content' => $content,
		),
		true
	);

	// Confirm the write persisted — re-read fresh from the DB, not the object cache.
	clean_post_cache( $page->ID );
	$fresh = get_post( $page->ID );
	$stuck = ( $fresh instanceof WP_Post )
		&& ( false === strpos( $fresh->post_content, $needle ) )
		&& ( false !== strpos( $fresh->post_content, $sentinel ) );
	update_option( UPLINKSYNC_RE_PHOTO_APPLIED, $stuck ? 'applied' : 'reverted' );
	return $stuck;
}

/**
 * One-shot guard that latches ONLY once the migration has settled. Until then it re-attempts on
 * each request. Once the version option is set the guard short-circuits.
 */
function uplinksync_re_photo_maybe_run() {
	if ( get_option( UPLINKSYNC_RE_PHOTO_OPTION ) === UPLINKSYNC_RE_PHOTO_VERSION ) {
		return;
	}
	if ( uplinksync_re_photo_run() ) {
		update_option( UPLINKSYNC_RE_PHOTO_OPTION, UPLINKSYNC_RE_PHOTO_VERSION );
	}
}
add_action( 'init', 'uplinksync_re_photo_maybe_run', 21 );
