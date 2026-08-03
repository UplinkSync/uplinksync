<?php
/**
 * Plugin Name: UplinkSync — /contact/ SEO metadata
 * Description: Applies the CMO-approved (Peitho) SEO title, meta description, and og:description to the /contact/ page (UPLAA-427, parent UPLAA-425, from weekly review UPLAA-424). The old strings were a 20-char title ("Contact - UplinkSync") and a 32-char description ("Local · human · one business day") — well below the geo-optimised ~150-170 char pattern the Home/Services/Drone siblings use, on the site's highest-intent conversion page. This is metadata only: the on-page "Local · human · one business day" line and hero copy are NOT touched. Captured in-repo as an mu-plugin (deploys with wp-content, no wp-admin/REST creds needed) — the same idiom as the drone document_title_parts filter in the child theme and uplinksync-page-seeder.php.
 * Author: Cadmus
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * True only on the singular /contact/ page (slug "contact"), never in admin or feeds.
 */
function uplinksync_contact_seo_is_target() {
	if ( is_admin() || ! is_singular() ) {
		return false;
	}
	$queried = get_queried_object();
	return ( $queried && ! empty( $queried->post_name ) && 'contact' === $queried->post_name );
}

/** CMO-approved copy (Peitho, 2026-08-03, UPLAA-425). */
function uplinksync_contact_seo_title() {
	return 'Contact UplinkSync | Free IT & Drone Quote — Idaho Falls';
}
function uplinksync_contact_seo_description() {
	return 'Contact UplinkSync for local IT support, managed services & drone work in Idaho Falls, Ammon & Rexburg. Free quote — a human replies in one business day.';
}

/*
 * Title. Matches the drone precedent: filter document_title_parts so the
 * <title> tag reflects the approved string regardless of theme defaults.
 * Rank Math (the active SEO plugin) is also given the title via its own
 * frontend filter so its <title> and og:title stay in sync with core.
 */
add_filter(
	'document_title_parts',
	function ( $parts ) {
		if ( uplinksync_contact_seo_is_target() ) {
			$parts = array( 'title' => uplinksync_contact_seo_title() );
		}
		return $parts;
	}
);

add_filter(
	'rank_math/frontend/title',
	function ( $title ) {
		return uplinksync_contact_seo_is_target() ? uplinksync_contact_seo_title() : $title;
	}
);

/*
 * Meta description + og:description. Rank Math derives og:description from the
 * frontend description when a page-specific OG value is not set, so filtering
 * rank_math/frontend/description keeps <meta name="description"> and
 * og:description identical (the issue's requirement). The explicit
 * opengraph/facebook filter is a belt-and-braces guard in case a stale
 * page-level OG description is stored in the DB.
 */
add_filter(
	'rank_math/frontend/description',
	function ( $desc ) {
		return uplinksync_contact_seo_is_target() ? uplinksync_contact_seo_description() : $desc;
	}
);

add_filter(
	'rank_math/opengraph/facebook/og_description',
	function ( $desc ) {
		return uplinksync_contact_seo_is_target() ? uplinksync_contact_seo_description() : $desc;
	}
);
