<?php
/**
 * Organization structured-data enrichment.
 *
 * The site's #1 stated business outcome is local search visibility, but the
 * Organization node Rank Math emits is effectively empty:
 *
 *     {"@type":"Organization","@id":"https://uplinksync.com/#organization","name":"UplinkSync"}
 *
 * No url, logo, telephone, email, sameAs or areaServed. This file fills that in
 * by filtering Rank Math's own JSON-LD graph rather than printing a second,
 * competing block — two Organization nodes would be worse than one thin one.
 *
 * WHY A THEME INCLUDE AND NOT A NEW mu-plugin: the mu-plugin layer is already
 * 30 files deep and is the site's largest regression risk (see ecosystem-docs
 * `114-detail-uplinksync-web-redesign-phase0.md` §4.6). This is presentation
 * metadata that belongs with the theme, is version-controlled, and is reversible
 * by removing the require in functions.php.
 *
 * ── FACT PROVENANCE (H1: never invent business facts) ────────────────────────
 * Every literal below is owner-authoritative or already published by the site:
 *   - phone / email  : mu-plugins/uplinksync-contact-and-social-fixes.php,
 *                      marked "Values are owner-authoritative (Doug Irwin,
 *                      2026-07-21) — do not re-derive". Reused via the constants
 *                      it defines, so there stays ONE source of truth.
 *   - social profiles: same file (UPLINKSYNC_SOCIAL_*).
 *   - legal name     : "UplinkSync LLC" — the Facebook profile above and the
 *                      Authentik tenant both use it.
 *   - areaServed     : Idaho Falls, Ammon and Rexburg ONLY. These are the three
 *                      the site already states publicly ("Idaho Falls, Ammon &
 *                      Rexburg" on /contact/).
 *
 * ── DELIBERATELY NOT ASSERTED ───────────────────────────────────────────────
 *   - postalAddress / geo / openingHours: no street address is published
 *     anywhere on the site and none was supplied. Not invented.
 *   - LocalBusiness / ProfessionalService: Google requires `address` for
 *     LocalBusiness rich results. Emitting one without an address produces an
 *     invalid entity, which is worse than the honest Organization below. This
 *     is the intended upgrade once the owner supplies an address (or confirms
 *     service-area-business status). [VERIFY WITH OWNER]
 *   - Blackfoot and Pocatello: named as target market in the redesign brief,
 *     but the site does not currently claim to serve them. Adding them here
 *     would be a geographic claim, which H1 forbids. [VERIFY WITH OWNER]
 *   - foundingDate, numberOfEmployees, awards, certifications: unverified.
 *
 * @package uplinksync-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Service areas the site already publicly claims.
 *
 * @return array<int,string>
 */
function uplinksync_child_area_served(): array {
	return array( 'Idaho Falls', 'Ammon', 'Rexburg' );
}

/**
 * Enrich Rank Math's Organization node in place.
 *
 * Merges rather than replaces: anything Rank Math (or the owner, via Rank Math's
 * own settings UI) already set wins, so this never silently overwrites a value
 * configured in wp-admin.
 *
 * @param array $data  The JSON-LD entity graph, keyed by entity name.
 * @return array
 */
function uplinksync_child_enrich_organization_schema( $data ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}

	$home = untrailingslashit( home_url() );

	$additions = array(
		'@type'         => 'Organization',
		'name'          => 'UplinkSync',
		'legalName'     => 'UplinkSync LLC',
		'url'           => $home . '/',
		'areaServed'    => array_map(
			static function ( $city ) {
				return array(
					'@type' => 'City',
					'name'  => $city,
				);
			},
			uplinksync_child_area_served()
		),
	);

	$logo = get_stylesheet_directory() . '/assets/images/uplinksync-logo.svg';
	if ( file_exists( $logo ) ) {
		$additions['logo'] = array(
			'@type' => 'ImageObject',
			'url'   => get_stylesheet_directory_uri() . '/assets/images/uplinksync-logo.svg',
		);
	}

	// Contact facts — reuse the owner-authoritative constants when the mu-plugin
	// that owns them is present, so the value is never duplicated by hand.
	if ( defined( 'UPLINKSYNC_CONTACT_PHONE_E164' ) ) {
		$additions['telephone'] = UPLINKSYNC_CONTACT_PHONE_E164;
	}
	if ( defined( 'UPLINKSYNC_CONTACT_EMAIL' ) ) {
		$additions['email'] = UPLINKSYNC_CONTACT_EMAIL;
	}

	$same_as = array();
	foreach ( array( 'UPLINKSYNC_SOCIAL_LINKEDIN', 'UPLINKSYNC_SOCIAL_FACEBOOK', 'UPLINKSYNC_SOCIAL_INSTAGRAM' ) as $const ) {
		if ( defined( $const ) && constant( $const ) ) {
			$same_as[] = constant( $const );
		}
	}
	if ( $same_as ) {
		$additions['sameAs'] = $same_as;
	}

	if ( isset( $additions['telephone'] ) ) {
		$additions['contactPoint'] = array(
			'@type'       => 'ContactPoint',
			'contactType' => 'customer service',
			'telephone'   => $additions['telephone'],
			'areaServed'  => 'US',
			'availableLanguage' => 'English',
		);
	}

	foreach ( $data as $key => $entity ) {
		if ( ! is_array( $entity ) ) {
			continue;
		}
		$type = $entity['@type'] ?? '';
		$is_org = ( 'Organization' === $type )
			|| ( is_array( $type ) && in_array( 'Organization', $type, true ) );

		if ( $is_org ) {
			// Existing values win — never clobber anything already configured.
			$data[ $key ] = array_merge( $additions, $entity );
			break;
		}
	}

	return $data;
}
add_filter( 'rank_math/json_ld', 'uplinksync_child_enrich_organization_schema', 20 );
