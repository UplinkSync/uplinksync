<?php
/**
 * Plugin Name: UplinkSync — Utah location pack
 * Description: Utah titles/meta, service-area block, city list and ProfessionalService JSON-LD. Active only when the location switch is in utah mode AND uplinksync_location_utah_ready is set (see uplinksync-location-neutral.php and DB-STEPS.md). In neutral mode (default) the slots render nothing / the neutral wording and no SEO or schema filter is registered.
 * Version: 1.0.0
 *
 * Nothing here may claim a Utah base before it is true: "based in" appears only
 * when the owner sets the uplinksync_location_base_city option.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'uplinksync_location_mode' ) ) {
	// Loaded without the switch: behave as neutral.
	function uplinksync_location_mode() {
		return 'neutral';
	}
}
if ( ! function_exists( 'uplinksync_location_phone' ) ) {
	function uplinksync_location_phone() {
		return '';
	}
}

/**
 * Core Utah County cities (schema areaServed and the block's first group).
 *
 * @return string[]
 */
function uplinksync_location_utah_core_cities() {
	return array( 'Provo', 'Orem', 'Lehi', 'American Fork', 'Pleasant Grove', 'Spanish Fork', 'Springville' );
}

/**
 * Full city list shown in the service-area block. Filterable so the owner can
 * trim it to places he will actually take jobs in.
 *
 * @return string[]
 */
function uplinksync_location_utah_cities() {
	$cities = array_merge(
		uplinksync_location_utah_core_cities(),
		array( 'Saratoga Springs', 'Lindon', 'Vineyard', 'Highland', 'Alpine', 'Eagle Mountain' )
	);
	if ( function_exists( 'apply_filters' ) ) {
		$cities = apply_filters( 'uplinksync_location_utah_cities', $cities );
	}
	return array_values( array_filter( array_map( 'strval', (array) $cities ) ) );
}

/**
 * Per-page Utah title and meta description, keyed by page key.
 * Titles <= 60 chars, descriptions <= 155 (asserted in tests).
 *
 * @return array<string,array{title:string,description:string}>
 */
function uplinksync_location_utah_seo_map() {
	return array(
		'home'     => array(
			'title'       => 'Utah County IT Support & Managed Services | UplinkSync',
			'description' => 'Managed IT, websites and aerial photo & video for Utah County small businesses. Fixed plans, plain English, a reply in one business day. Get a free quote.',
		),
		'services' => array(
			'title'       => 'Managed IT, Web & Cybersecurity in Utah County | UplinkSync',
			'description' => 'Managed IT, Microsoft 365, networking, websites, hosting and security for Provo, Orem, Lehi and Utah County businesses. CompTIA A+ certified. Free quote.',
		),
		'drone'    => array(
			'title'       => 'Utah County Drone Photo & Video | FAA Part 107 | UplinkSync',
			'description' => 'FAA Part 107 drone photos, video and roof/site inspection for Utah County listings, builders and businesses. Delivered ready to use. Request a quote.',
		),
		'about'    => array(
			'title'       => 'About UplinkSync | Owner-Run IT & Drone Services, Utah',
			'description' => 'Meet Doug: FAA Part 107 pilot and CompTIA A+ technician. Owner-run IT, web and aerial services for Utah County businesses. Fully insured.',
		),
		'contact'  => array(
			'title'       => 'Contact UplinkSync | Free IT & Drone Quote in Utah County',
			'description' => 'Get a free quote for IT support, websites or drone work in Utah County. A human replies in one business day. Email or book a 15-minute call.',
		),
	);
}

/**
 * Location-line slot wording (hero eyebrow / H1 / intro add-on), neutral and Utah.
 *
 * @return array<string,array{neutral:string,utah:string}>
 */
function uplinksync_location_line_map() {
	return array(
		'eyebrow'     => array(
			'neutral' => 'Managed IT · Websites · Aerial photo & video',
			'utah'    => 'Managed IT · Websites · Aerial photo & video · Utah County',
		),
		'home_h1'     => array(
			'neutral' => 'IT that just works — and a person who answers.',
			'utah'    => 'IT that just works for Utah County businesses — and a person who answers.',
		),
		'services_h1' => array(
			'neutral' => 'Our services',
			'utah'    => 'IT services for Utah County small businesses',
		),
		'drone_h1'    => array(
			'neutral' => 'Property and listing photo and video — delivered ready to use',
			'utah'    => 'Utah County drone photo & video — delivered ready to use',
		),
		'about_h1'    => array(
			'neutral' => 'One team for your systems, your site, and the view from above.',
			'utah'    => 'Owner-run IT and aerial services, now in Utah County.',
		),
		'contact_h1'  => array(
			'neutral' => 'Get a free quote.',
			'utah'    => 'Get a free quote — Utah County and nearby.',
		),
		'intro'       => array(
			'neutral' => '',
			'utah'    => 'Serving Provo, Orem, Lehi, American Fork and the rest of Utah County, remotely and on-site.',
		),
	);
}

/**
 * Text for one location-line slot in the current mode ('' for unknown slots).
 *
 * @param string $slot Slot key.
 * @return string
 */
function uplinksync_location_line( $slot ) {
	$map = uplinksync_location_line_map();
	if ( ! isset( $map[ $slot ] ) ) {
		return '';
	}
	return $map[ $slot ][ 'utah' === uplinksync_location_mode() ? 'utah' : 'neutral' ];
}

/**
 * Shortcode [uplinksync_location_line slot="eyebrow"]. One slot instead of find-and-replace.
 */
function uplinksync_location_line_shortcode( $atts = array() ) {
	$atts = function_exists( 'shortcode_atts' ) ? shortcode_atts( array( 'slot' => 'eyebrow' ), (array) $atts ) : array_merge( array( 'slot' => 'eyebrow' ), (array) $atts );
	return htmlspecialchars( uplinksync_location_line( (string) $atts['slot'] ), ENT_QUOTES, 'UTF-8' );
}

/**
 * Service-area block HTML. Returns '' unless the effective mode is utah.
 * Templates/pages place it with [uplinksync_service_area] or the
 * uplinksync/service-area pattern; in neutral mode they render nothing.
 *
 * @return string
 */
function uplinksync_location_service_area_html() {
	if ( 'utah' !== uplinksync_location_mode() ) {
		return '';
	}
	$esc    = static function ( $v ) {
		return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' );
	};
	$city   = function_exists( 'get_option' ) ? trim( (string) get_option( 'uplinksync_location_base_city', '' ) ) : '';
	$travel = function_exists( 'get_option' ) ? trim( (string) get_option( 'uplinksync_location_travel_policy', '' ) ) : '';
	if ( '' === $travel ) {
		$travel = 'on-site when a job needs hands-on attention or a property needs flying';
	}
	$lead = ( '' !== $city ? "We're based in " . $esc( $city ) . ', Utah and work' : 'We work' )
		. ' with small businesses across Utah County &mdash; remotely for most IT work, and ' . $esc( $travel ) . '.';
	$quote = function_exists( 'home_url' ) ? home_url( '/request-quote/' ) : '/request-quote/';
	$items = '';
	foreach ( uplinksync_location_utah_cities() as $c ) {
		$items .= '<li>' . $esc( $c ) . '</li>';
	}
	return '<section class="uplinksync-service-area" aria-labelledby="uplinksync-service-area-h">'
		. '<h2 id="uplinksync-service-area-h">Where we work</h2>'
		. '<p>' . $lead . '</p>'
		. '<p class="uplinksync-service-area__label"><strong>Cities we serve:</strong></p>'
		. '<ul class="uplinksync-service-area__cities">' . $items . '</ul>'
		. '<p>Not on the list? Ask &mdash; travel is quoted case by case. <a href="' . $esc( $quote ) . '">Get a quote</a></p>'
		. '</section>';
}

function uplinksync_location_service_area_shortcode() {
	return uplinksync_location_service_area_html();
}

/**
 * ProfessionalService node (service-area business). No streetAddress, no
 * geo; telephone only when UPLINKSYNC_PHONE is set. hasCredential, priceRange
 * and image are omitted until the owner confirms them (utah-pack.md §6 notes).
 *
 * @return array
 */
function uplinksync_location_utah_org_node() {
	$home  = function_exists( 'home_url' ) ? home_url( '/' ) : 'https://uplinksync.com/';
	$home  = rtrim( $home, '/' ) . '/';
	$areas = array(
		array(
			'@type'           => 'AdministrativeArea',
			'name'            => 'Utah County',
			'containedInPlace' => array( '@type' => 'State', 'name' => 'Utah' ),
		),
	);
	foreach ( uplinksync_location_utah_core_cities() as $city ) {
		$areas[] = array( '@type' => 'City', 'name' => $city );
	}
	$offers = array();
	foreach ( array( 'Managed IT and security', 'Microsoft 365 and networking', 'Websites and hosting', 'Aerial photo and video' ) as $svc ) {
		$offers[] = array(
			'@type'       => 'Offer',
			'itemOffered' => array( '@type' => 'Service', 'name' => $svc, 'areaServed' => 'Utah County, UT' ),
		);
	}
	$contact = array(
		'@type'             => 'ContactPoint',
		'contactType'       => 'sales',
		'email'             => 'contact@uplinksync.com',
		'availableLanguage' => 'English',
	);
	$node = array(
		'@type'            => 'ProfessionalService',
		'@id'              => $home . '#organization',
		'name'             => 'UplinkSync LLC',
		'alternateName'    => 'UplinkSync',
		'url'              => $home,
		'logo'             => $home . 'wp-content/themes/uplinksync-child/assets/images/uplinksync-logo.svg',
		'description'      => 'Owner-run managed IT, web development, cybersecurity and FAA Part 107 aerial photo and video for small businesses in Utah County, Utah.',
		'email'            => 'contact@uplinksync.com',
		'knowsAbout'       => array( 'Managed IT services', 'Microsoft 365', 'Cybersecurity for small business', 'Web development and hosting', 'Drone photography', 'Roof and structure inspection' ),
		'founder'          => array( '@type' => 'Person', 'name' => 'Doug Irwin', 'jobTitle' => 'Owner' ),
		'areaServed'       => $areas,
		'sameAs'           => array(
			'https://www.linkedin.com/company/uplinksync/',
			'https://www.facebook.com/people/UplinkSync-LLC/61588243996549/',
			'https://www.instagram.com/uplinksyncllc/',
		),
		'contactPoint'     => $contact,
		'hasOfferCatalog'  => array( '@type' => 'OfferCatalog', 'name' => 'Services', 'itemListElement' => $offers ),
	);
	$phone = uplinksync_location_phone();
	if ( '' !== $phone ) {
		$node['telephone']                 = $phone;
		$node['contactPoint']['telephone'] = $phone;
	}
	return $node;
}

/**
 * Replace every full Organization-type node in a Rank Math graph with the
 * ProfessionalService node; add it when the page has none. Reference-only nodes
 * (just an @id) are left alone.
 *
 * @param array $data Rank Math graph.
 * @return array
 */
function uplinksync_location_utah_json_ld( $data ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}
	$node  = uplinksync_location_utah_org_node();
	$found = false;
	$walk  = static function ( $value ) use ( &$walk, &$found, $node ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$type = isset( $value['@type'] ) ? (array) $value['@type'] : array();
		if ( array_intersect( $type, array( 'Organization', 'LocalBusiness', 'ProfessionalService' ) ) && count( $value ) > 1 && isset( $value['@id'] ) && false !== strpos( (string) $value['@id'], '#organization' ) ) {
			$found = true;
			return $node;
		}
		foreach ( $value as $k => $v ) {
			if ( is_array( $v ) ) {
				$value[ $k ] = $walk( $v );
			}
		}
		return $value;
	};
	$data = $walk( $data );
	if ( ! $found ) {
		$data['uplinksync_organization'] = $node;
	}
	return $data;
}

/**
 * Page key for the current request: home|services|drone|about|contact or ''.
 */
function uplinksync_location_utah_page_key() {
	if ( function_exists( 'is_front_page' ) && is_front_page() ) {
		return 'home';
	}
	if ( ! function_exists( 'get_queried_object' ) ) {
		return '';
	}
	$obj  = get_queried_object();
	$slug = ( is_object( $obj ) && isset( $obj->post_name ) ) ? (string) $obj->post_name : '';
	$map  = array( 'services' => 'services', 'drone-services' => 'drone', 'about' => 'about', 'contact' => 'contact' );
	return $map[ $slug ] ?? '';
}

/**
 * Filter callback factory: returns the Utah value for the current page, else the input.
 */
function uplinksync_location_utah_seo( $field ) {
	return static function ( $value ) use ( $field ) {
		$seo = uplinksync_location_utah_seo_map();
		$key = uplinksync_location_utah_page_key();
		return ( '' !== $key && isset( $seo[ $key ][ $field ] ) ) ? $seo[ $key ][ $field ] : $value;
	};
}

// Slots exist in BOTH modes (they render nothing / neutral wording in neutral mode),
// so a page or template can carry them before the switch is flipped.
if ( function_exists( 'add_shortcode' ) ) {
	add_shortcode( 'uplinksync_service_area', 'uplinksync_location_service_area_shortcode' );
	add_shortcode( 'uplinksync_location_line', 'uplinksync_location_line_shortcode' );
}
if ( function_exists( 'add_action' ) ) {
	add_action(
		'init',
		static function () {
			if ( function_exists( 'register_block_pattern' ) ) {
				register_block_pattern(
					'uplinksync/service-area',
					array(
						'title'       => 'UplinkSync service area (Utah mode only)',
						'description' => 'Renders the Utah County service-area block; empty while the location switch is neutral.',
						'content'     => "<!-- wp:shortcode -->\n[uplinksync_service_area]\n<!-- /wp:shortcode -->",
					)
				);
			}
		}
	);
}

// SEO and schema: registered ONLY in utah mode (requested and ready). Priority 20
// is irrelevant to the neutral filters because those are never registered here.
if ( 'utah' === uplinksync_location_mode() && function_exists( 'add_filter' ) ) {
	add_filter( 'pre_get_document_title', uplinksync_location_utah_seo( 'title' ), 20 );
	add_filter( 'rank_math/frontend/title', uplinksync_location_utah_seo( 'title' ), 20 );
	add_filter( 'rank_math/frontend/description', uplinksync_location_utah_seo( 'description' ), 20 );
	add_filter( 'rank_math/opengraph/facebook/og_title', uplinksync_location_utah_seo( 'title' ), 20 );
	add_filter( 'rank_math/opengraph/facebook/og_description', uplinksync_location_utah_seo( 'description' ), 20 );
	add_filter( 'rank_math/opengraph/twitter/twitter_title', uplinksync_location_utah_seo( 'title' ), 20 );
	add_filter( 'rank_math/opengraph/twitter/twitter_description', uplinksync_location_utah_seo( 'description' ), 20 );
	add_filter( 'rank_math/json_ld', 'uplinksync_location_utah_json_ld', 20 );
}
