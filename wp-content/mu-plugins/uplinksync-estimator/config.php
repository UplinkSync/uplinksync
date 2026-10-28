<?php
/**
 * UplinkSync estimator — configuration (rates, booking events, booking-field maps).
 *
 * Loaded by ../uplinksync-estimator.php. Pure data + pure functions only (no hooks), so the
 * standalone tests in ../tests/ can require it without WordPress.
 *
 * RATES are copied VERBATIM from the database-resident Code Snippet #82 as rendered live on
 * 2026-10-27 (UAV "EMBEDDED" model, UPLAA-330, and the IT_CFG monthly model, UPLAA-337).
 * Nothing here is a new or changed rate. The one wording difference from the 2026-08-14
 * repo mirror (snippets-export/082-*) is the disclaimer, which on the live site says
 * "can change" (the mirror says "may differ"); the LIVE wording is kept.
 * tests/test-estimator-pricing.php pins every number and the resulting price ranges.
 */

defined( 'ABSPATH' ) || exit;

/**
 * UAV rate model (snippet 82 `EMBEDDED`). Emitted to the browser as JSON; the estimator JS
 * still prefers window.ULS_ESTIMATOR_CONFIG when another script defines one (same as before).
 *
 * @return array
 */
function uls_estimator_uav_rates() {
	return array(
		'currency'   => '$',
		'travel'     => array( 'freeRadiusMi' => 25, 'perMileOver' => 0.75 ),
		'rush'       => array( 'standard' => 1.00, 'rush' => 1.30, 'same_day' => 1.50 ),
		/*
		 * Usage rights for commissioned creative work (owner decision 2026-10-28, PRICING-PROPOSAL §2):
		 * UplinkSync keeps the copyright; the client's licence for the use stated on the quote is
		 * included. Only lines with 'rights' => true show the choice (mapping/inspection deliverables
		 * are client data under a Project licence: no multiplier). Formula, applied to the creative
		 * subtotal S = base + options (travel is never marked up, rush applies last):
		 *   rights   = max( S × mult, S + minAdd )   (standard ×1 = S, so existing prices are unchanged)
		 *   raw      = S × raw.pct when ticked        (additive; combinable with any rights choice)
		 *   total    = ( rights + raw + travel ) × rush
		 */
		'rights'     => array(
			'label'   => 'Usage rights',
			'help'    => 'UplinkSync keeps the copyright. Standard covers the use stated on your quote (web, social, MLS, print and your own ads). Exclusive: we license it to no one else. Buy-out: the copyright is assigned to you.',
			'choices' => array(
				array( 'v' => 'standard', 'label' => 'Standard licence (included)', 'mult' => 1.0, 'minAdd' => 0 ),
				array( 'v' => 'exclusive', 'label' => 'Exclusive use (x1.5)', 'mult' => 1.5, 'minAdd' => 0 ),
				array( 'v' => 'buyout', 'label' => 'Full buy-out (x2.5, min +$400)', 'mult' => 2.5, 'minAdd' => 400 ),
			),
			'raw'     => array( 'label' => 'Raw / unedited footage', 'pct' => 0.5 ),
		),
		'disclaimer' => 'Estimate only — a starting figure based on typical projects, not a quote. Your firm price is confirmed by a UplinkSync team member and can change with site conditions, access, and deliverables.',
		'lines'      => array(
			'real_estate' => array(
				'posture'    => 'band',
				'label'      => 'Real-estate / property photography',
				'base'       => 175,
				'bandSpread' => 0.35,
				'rights'     => true,
				'options'    => array(
					array(
						'id'      => 'sqft',
						'label'   => 'Property size',
						'type'    => 'select',
						'choices' => array(
							array( 'v' => 'small', 'label' => 'Under 2,000 sq ft (home/lot)', 'add' => 0 ),
							array( 'v' => 'medium', 'label' => '2,000-5,000 sq ft or acreage', 'add' => 75 ),
							array( 'v' => 'large', 'label' => 'Estate / commercial parcel', 'add' => 200 ),
						),
					),
					array( 'id' => 'video', 'label' => 'Add cinematic video reel', 'type' => 'check', 'add' => 150 ),
					array( 'id' => 'twilight', 'label' => 'Twilight / golden-hour session', 'type' => 'check', 'add' => 90 ),
				),
			),
			'mapping'     => array(
				'posture'        => 'starting',
				'label'          => 'Mapping & surveying',
				'base'           => 500,
				'startingSpread' => 0.40,
				'options'        => array(
					array(
						'id'      => 'acres',
						'label'   => 'Approximate acreage',
						'type'    => 'select',
						'choices' => array(
							array( 'v' => 's', 'label' => 'Up to 20 acres', 'add' => 0 ),
							array( 'v' => 'm', 'label' => '20-100 acres', 'add' => 250 ),
							array( 'v' => 'l', 'label' => 'Over 100 acres', 'add' => 750 ),
						),
					),
					array(
						'id'      => 'deliverable',
						'label'   => 'Deliverable',
						'type'    => 'select',
						'choices' => array(
							array( 'v' => 'ortho', 'label' => '2D orthomosaic map', 'add' => 0 ),
							array( 'v' => 'model', 'label' => '3D model / point cloud', 'add' => 300 ),
							array( 'v' => 'both', 'label' => 'Both', 'add' => 450 ),
						),
					),
				),
			),
			'inspection'  => array(
				'posture'        => 'starting',
				'label'          => 'Roof / structure inspection',
				'base'           => 300,
				'startingSpread' => 0.40,
				'options'        => array(
					array(
						'id'      => 'structures',
						'label'   => 'Number of structures',
						'type'    => 'select',
						'choices' => array(
							array( 'v' => '1', 'label' => '1 structure', 'add' => 0 ),
							array( 'v' => '2', 'label' => '2-3 structures', 'add' => 150 ),
							array( 'v' => '4', 'label' => '4+ structures', 'add' => 350 ),
						),
					),
					array( 'id' => 'report', 'label' => 'Formal written report', 'type' => 'check', 'add' => 120 ),
				),
			),
			'tower'       => array( 'posture' => 'contact', 'label' => 'Tower / industrial / other' ),
		),
	);
}

/**
 * IT rate model (snippet 82 `IT_CFG`, monthly-subscription framing).
 *
 * @return array
 */
function uls_estimator_it_rates() {
	return array(
		'managed'    => array(
			'posture'    => 'perUser',
			'unit'       => '/month',
			'label'      => 'Managed IT / support',
			'spread'     => 0.25,
			'perServer'  => 150,
			'engagement' => true,
			'tiers'      => array(
				array( 'v' => 'basic', 'label' => 'Help desk / basic support', 'rate' => 60 ),
				array( 'v' => 'managed', 'label' => 'Fully managed', 'rate' => 150 ),
				array( 'v' => 'security', 'label' => 'Managed + security / compliance', 'rate' => 275 ),
			),
		),
		'automation' => array(
			'posture'    => 'monthly',
			'unit'       => '/month',
			'label'      => 'Automation & AI',
			'spread'     => 0.30,
			'engagement' => true,
			'options'    => array(
				array(
					'id'      => 'atype',
					'label'   => 'What kind of automation plan?',
					'type'    => 'select',
					'choices' => array(
						array( 'v' => 'workflow', 'label' => 'Workflow automation partner (Zapier / Make / n8n)', 'add' => 500 ),
						array( 'v' => 'chatbot', 'label' => 'AI assistant / chatbot — managed', 'add' => 750 ),
						array( 'v' => 'data', 'label' => 'Data / systems integration — managed', 'add' => 1000 ),
						array( 'v' => 'custom', 'label' => 'Custom AI partner retainer', 'add' => 3000 ),
					),
				),
				array(
					'id'      => 'complexity',
					'label'   => 'Complexity',
					'type'    => 'select',
					'choices' => array(
						array( 'v' => 'simple', 'label' => 'Straightforward', 'add' => 0 ),
						array( 'v' => 'moderate', 'label' => 'Moderate (a few moving parts)', 'add' => 500 ),
						array( 'v' => 'advanced', 'label' => 'Advanced (AI reasoning / many systems)', 'add' => 1500 ),
					),
				),
				array(
					'id'      => 'processes',
					'label'   => 'How many processes / integrations maintained?',
					'type'    => 'select',
					'choices' => array(
						array( 'v' => '1', 'label' => '1', 'add' => 0 ),
						array( 'v' => '2', 'label' => '2-3', 'add' => 400 ),
						array( 'v' => '4', 'label' => '4+', 'add' => 1000 ),
					),
				),
			),
		),
		'web'        => array(
			'posture'    => 'monthly',
			'unit'       => '/month',
			'label'      => 'Web development',
			'spread'     => 0.30,
			'engagement' => true,
			'options'    => array(
				array(
					'id'      => 'stype',
					'label'   => 'What kind of site plan?',
					'type'    => 'select',
					'choices' => array(
						array( 'v' => 'brochure', 'label' => 'Marketing / brochure site (host + maintain)', 'add' => 149 ),
						array( 'v' => 'ecom', 'label' => 'E-commerce store — managed', 'add' => 349 ),
						array( 'v' => 'webapp', 'label' => 'Web app / portal — managed', 'add' => 900 ),
					),
				),
				array(
					'id'      => 'pages',
					'label'   => 'Approximate pages',
					'type'    => 'select',
					'choices' => array(
						array( 'v' => 's', 'label' => 'Up to 5', 'add' => 0 ),
						array( 'v' => 'm', 'label' => '6-15', 'add' => 50 ),
						array( 'v' => 'l', 'label' => '16+', 'add' => 150 ),
					),
				),
				array( 'id' => 'feat_booking', 'label' => 'Booking / scheduling', 'type' => 'check', 'add' => 50 ),
				array( 'id' => 'feat_crm', 'label' => 'CRM / customer portal integration', 'type' => 'check', 'add' => 250 ),
				array( 'id' => 'feat_seo', 'label' => 'Ongoing SEO & content plan', 'type' => 'check', 'add' => 100 ),
			),
		),
		'mixed'      => array( 'posture' => 'contact', 'label' => 'A mix / not sure yet' ),
	);
}

/*
 * ---------------------------------------------------------------------------------------
 * Booking (Cal.com Cloud, team "uplinksync" 467448). Owner decision 2026-10-27: keep Cloud,
 * use the PUBLIC v2 slots/bookings endpoints through a WordPress proxy, no API key.
 * ---------------------------------------------------------------------------------------
 */

/**
 * Server-side event whitelist. The browser only ever sends the KEY ("it" / "uav").
 *
 * `length` is only a fallback: the UI shows the length Cal.com reports for the slots.
 *
 * @return array key => [id, slug, length]
 */
function uls_estimator_events() {
	return array(
		'it'  => array( 'id' => 7469726, 'slug' => 'team/uplinksync/it-consult', 'length' => 30 ),
		'uav' => array( 'id' => 7469727, 'slug' => 'team/uplinksync/uav-consult', 'length' => 30 ),
	);
}

/**
 * Required booking-field option VALUES on Cal.com Cloud, verified 2026-10-27 via the public
 * GET https://api.cal.com/v2/teams/467448/event-types?eventSlug=… (no key). On Cloud the
 * option values are the human labels, NOT the old self-host slugs ("new-website" etc.).
 *
 * @return array
 */
function uls_estimator_booking_options() {
	return array(
		'goal'         => array( 'New website', 'Systems / help-desk support', 'Security', 'Cloud / M365', 'Networking', 'Other' ),
		'site-type'    => array( 'Residential / roof', 'Commercial building', 'Land / acreage (mapping)', 'Construction / progress', 'Event', 'Other' ),
		'deliverables' => array( 'Photos', 'Video', 'Orthomosaic / mapping', 'Inspection report' ),
	);
}

/**
 * Default "Main goal" for the IT consult, derived from the estimator's IT area + tier.
 * The visitor can change it in "Your details"; this only pre-selects.
 *
 * @param string $area managed|automation|web|mixed.
 * @param string $tier basic|managed|security (managed area only).
 * @return string A value from uls_estimator_booking_options()['goal'].
 */
function uls_estimator_default_goal( $area, $tier = '' ) {
	if ( 'managed' === $area ) {
		return ( 'security' === $tier ) ? 'Security' : 'Systems / help-desk support';
	}
	if ( 'web' === $area ) {
		return 'New website';
	}
	return 'Other';
}

/**
 * Default "Site type & size" for the UAV consult, from the estimator's UAV line + choice.
 *
 * @param string $line   real_estate|mapping|inspection|tower.
 * @param string $choice sqft (small|medium|large) or structures (1|2|4).
 * @return string A value from uls_estimator_booking_options()['site-type'].
 */
function uls_estimator_default_site_type( $line, $choice = '' ) {
	switch ( $line ) {
		case 'real_estate':
			return ( 'large' === $choice ) ? 'Commercial building' : 'Residential / roof';
		case 'mapping':
			return 'Land / acreage (mapping)';
		case 'inspection':
			return ( '1' === (string) $choice || '' === (string) $choice ) ? 'Residential / roof' : 'Commercial building';
		default:
			return 'Other';
	}
}

/**
 * Deliverables (required multi-select on the UAV consult), derived from the UAV answers.
 *
 * @param string $line   UAV line key.
 * @param bool   $video  "Add cinematic video reel" ticked (real-estate).
 * @param bool   $report "Formal written report" ticked (inspection).
 * @return string[] Values from uls_estimator_booking_options()['deliverables'].
 */
function uls_estimator_default_deliverables( $line, $video = false, $report = false ) {
	switch ( $line ) {
		case 'real_estate':
			return $video ? array( 'Photos', 'Video' ) : array( 'Photos' );
		case 'mapping':
			return array( 'Orthomosaic / mapping' );
		case 'inspection':
			return $report ? array( 'Photos', 'Inspection report' ) : array( 'Photos' );
		default:
			return array( 'Photos' );
	}
}
