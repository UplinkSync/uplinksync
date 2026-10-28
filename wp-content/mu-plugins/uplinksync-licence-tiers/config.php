<?php
/**
 * UplinkSync licence tiers — configuration (pure data + pure functions, no hooks).
 *
 * Owner decisions 2026-10-28 (portal/pricing/PRICING-PROPOSAL.md §1, §5):
 * Personal $35 (flat, all photos) · Commercial with credit $79 · Commercial $119 · Extended $399 ·
 * Exclusive = quote (a link, never a variation). Licence text v2.0 is DRAFT pending counsel review.
 * Loaded by ../uplinksync-licence-tiers.php; required directly by tests/test-licence-tiers.php.
 */

defined( 'ABSPATH' ) || exit;

const ULS_LIC_VERSION     = 'ULS-LIC v2.0';
const ULS_LIC_ATTRIBUTE   = 'pa_licence';
const ULS_LIC_CREDIT_LINE = 'Aerial by UplinkSync — uplinksync.com';
const ULS_LIC_DL_LIMIT    = 5;   // DR-006 G5: downloads per licence.
const ULS_LIC_DL_EXPIRY   = 30;  // DR-006 G5: days; re-issue on request.

/**
 * The four purchasable photo tiers, in display order. Slugs are the pa_licence term slugs.
 *
 * @return array<string,array>
 */
function uls_lic_tiers() {
	return array(
		'personal'          => array(
			'label'   => 'Personal',
			'price'   => 35,
			'for'     => 'Private individuals',
			'summary' => 'Your home, gifts you don\'t sell, and personal posts that earn no money.',
			'allowed' => 'Private, non-commercial use only: prints for your own home (any size), gifts that are not sold, wallpaper, and non-monetised personal social posts, blogs and family videos.',
			'not'     => 'Any business, client or monetised use; resale; print-on-demand.',
			'credit'  => 'requested',
		),
		'commercial-credit' => array(
			'label'   => 'Commercial with credit',
			'price'   => 79,
			'for'     => 'Small businesses, agents, publishers',
			'summary' => 'Everything Commercial allows, as long as the credit appears with every use.',
			'allowed' => 'Everything Commercial allows, on one condition: every use shows "Aerial by UplinkSync" with a link to uplinksync.com (the plain URL in print; on screen or in the credits for video). Editorial and news use fit here.',
			'not'     => 'Paid advertising, broadcast, merchandise for sale, or print over 5,000 copies (see Extended). If a credit is missing you have 14 days after notice to add it; after that the licence becomes Commercial and the upgrade fee in our price list applies.',
			'credit'  => 'required',
		),
		'commercial'        => array(
			'label'   => 'Commercial',
			'price'   => 119,
			'for'     => 'Businesses',
			'summary' => 'Your business\'s own marketing, web, social, email and up to 5,000 prints. Credit optional.',
			'allowed' => 'The named business\'s own marketing, communications, editorial and décor: websites, organic and monetised social, email, presentations, listing marketing, and up to 5,000 printed copies in total. An agency or designer may use it in work for one end client.',
			'not'     => 'Paid advertising, broadcast, merchandise for sale, or print over 5,000 copies (see Extended); templates and stock resale.',
			'credit'  => 'optional',
		),
		'extended'          => array(
			'label'   => 'Extended',
			'price'   => 399,
			'for'     => 'Ad campaigns, publishers, products',
			'summary' => 'Commercial plus paid ads, unlimited print, broadcast and up to 10,000 items for sale.',
			'allowed' => 'Everything Commercial allows, plus paid advertising in any medium, unlimited print runs, broadcast, streaming and cinema, and merchandise for resale up to 10,000 units.',
			'not'     => 'More than 10,000 merchandise units, or exclusivity (ask for a quote).',
			'credit'  => 'optional',
		),
	);
}

/**
 * Restrictions that apply to every tier (licence v2.0 base terms).
 *
 * @return string[]
 */
function uls_lic_base_restrictions() {
	return array(
		'No resale, sharing or sub-licensing of the file itself, and no offering it for others to print or download (stock sites, templates, print-on-demand).',
		'No use in a logo or trademark.',
		'No AI or machine-learning training, and no dataset inclusion. No NFTs.',
		'No unlawful, defamatory or misleading use, and no implied endorsement by UplinkSync or anyone shown.',
	);
}

/**
 * Plain-English credit requirement for a tier.
 *
 * @param string $slug Tier slug.
 * @return string
 */
function uls_lic_credit_text( $slug ) {
	$t = uls_lic_tiers();
	$c = isset( $t[ $slug ] ) ? $t[ $slug ]['credit'] : 'optional';
	if ( 'required' === $c ) {
		return 'Required with every use: "' . ULS_LIC_CREDIT_LINE . '"';
	}
	if ( 'requested' === $c ) {
		return 'Not required. We\'d love a credit: "' . ULS_LIC_CREDIT_LINE . '"';
	}
	return 'Optional: "' . ULS_LIC_CREDIT_LINE . '"';
}

/**
 * Licence ID for an order line: ULS-<order number>-<item id>.
 *
 * @param string|int $order_number Order number.
 * @param int        $item_id      Order item id.
 * @return string
 */
function uls_lic_id( $order_number, $item_id ) {
	return 'ULS-' . preg_replace( '/[^0-9A-Za-z]/', '', (string) $order_number ) . '-' . (int) $item_id;
}

/**
 * Parse a licence ID back to [order number, item id], or null.
 *
 * @param string $id Licence id.
 * @return array|null
 */
function uls_lic_parse_id( $id ) {
	if ( ! is_string( $id ) || ! preg_match( '/^ULS-([0-9A-Za-z]+)-([0-9]+)$/', trim( $id ), $m ) ) {
		return null;
	}
	return array( $m[1], (int) $m[2] );
}

/**
 * Upgrade price between two tiers = current price difference (0 when not an upgrade).
 *
 * @param string $from Owned tier slug.
 * @param string $to   Target tier slug.
 * @return int
 */
function uls_lic_upgrade_price( $from, $to ) {
	$t = uls_lic_tiers();
	if ( ! isset( $t[ $from ], $t[ $to ] ) ) {
		return 0;
	}
	return max( 0, $t[ $to ]['price'] - $t[ $from ]['price'] );
}

/**
 * Rank of a tier (0 = Personal ... 3 = Extended), -1 if unknown.
 *
 * @param string $slug Tier slug.
 * @return int
 */
function uls_lic_rank( $slug ) {
	$k = array_search( $slug, array_keys( uls_lic_tiers() ), true );
	return false === $k ? -1 : (int) $k;
}
