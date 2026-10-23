<?php
/**
 * Plugin Name: UplinkSync — Location-neutral presentation
 * Description: Keeps the gated site location-neutral until the business location is established (UPL-944). Rewrites database-backed public copy and metadata without changing quote, booking, service, or estimator behaviour.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replace approved, public-facing location phrases with neutral wording.
 *
 * This function receives visible text or a safe text attribute only. URLs,
 * filenames, CSS and script source are never passed to it.
 *
 * @param string $text Visible text or metadata text.
 * @return string
 */
function uplinksync_location_neutral_text( $text ) {
	if ( ! is_string( $text ) || '' === $text ) {
		return $text;
	}

	$exact = array(
		'Contact UplinkSync | Free IT & Drone Quote — Idaho Falls' => 'Contact UplinkSync | Free IT & Drone Quote',
		'Contact UplinkSync | Free IT &amp; Drone Quote — Idaho Falls' => 'Contact UplinkSync | Free IT &amp; Drone Quote',
		'Contact UplinkSync for local IT support, managed services & drone work in Idaho Falls, Ammon & Rexburg. Free quote — a human replies in one business day.' => 'Contact UplinkSync for managed IT, web, automation and drone work. Serving clients remotely and on-site by arrangement. A human replies in one business day.',
		'Contact UplinkSync for local IT support, managed services &amp; drone work in Idaho Falls, Ammon &amp; Rexburg. Free quote — a human replies in one business day.' => 'Contact UplinkSync for managed IT, web, automation and drone work. Serving clients remotely and on-site by arrangement. A human replies in one business day.',
		'High-resolution aerial photography, infrastructure inspection stills, and full-site overviews across Idaho and Utah' => 'High-resolution aerial photography, infrastructure inspection stills, and full-site overviews for remote and on-site projects by arrangement',
		'High-resolution aerial photos and cinematic video of your property or listing across Idaho and Utah' => 'High-resolution aerial photos and cinematic video of your property or listing, available on-site by arrangement',
		'Cinematic reels, high-resolution stills, and infrastructure inspection across Idaho and Utah.' => 'Cinematic reels, high-resolution stills, and infrastructure inspection, available on-site by arrangement.',
		'Our managed services are remote-first, so we support clients across the Rexburg–Idaho Falls–Blackfoot–Pocatello corridor and the surrounding area without needing to be on-site for most work. Onsite visits are available when a job genuinely needs hands-on attention; travel outside the corridor is quoted case-by-case.' => 'Our managed services are remote-first. On-site visits are available by arrangement when a job needs hands-on attention, with travel quoted case by case.',
		'Our managed services are remote-first, so we support clients across the Rexburg&#8211;Idaho Falls&#8211;Blackfoot&#8211;Pocatello corridor and the surrounding area without needing to be on-site for most work. Onsite visits are available when a job genuinely needs hands-on attention; travel outside the corridor is quoted case-by-case.' => 'Our managed services are remote-first. On-site visits are available by arrangement when a job needs hands-on attention, with travel quoted case by case.',
		'UplinkSync serves the Rexburg–Idaho Falls–Blackfoot–Pocatello corridor and immediate surrounding areas.' => 'UplinkSync serves clients remotely, with on-site work available by arrangement.',
		'UplinkSync provides structured, security-focused managed IT services for small and growing businesses throughout the Rexburg–Idaho Falls–Blackfoot–Pocatello corridor and immediate surrounding areas.' => 'UplinkSync provides structured, security-focused managed IT services for small and growing businesses remotely, with on-site work available by arrangement.',
		'Do you support businesses outside Rexburg / Idaho Falls?' => 'Do you support businesses remotely?',
		'Based in Eastern Idaho — on-site when you need us.' => 'Remote-first — on-site when arranged.',
		'We live and work here in the Rexburg area. When a job needs boots on the ground — a rack rebuilt, a site walked, a property flown — we drive to you, not route you through a call center three states away.' => 'We work remotely by default. When a job needs boots on the ground — a rack rebuilt, a site walked, or a property flown — on-site work is available by arrangement.',
		'Proactive monitoring, security, and helpdesk from a specialist team in Eastern Idaho — so problems get caught early and fixed fast, by people who already know your setup.' => 'Proactive monitoring, security, and helpdesk from a responsive specialist team — so problems get caught early and fixed fast, by people who already know your setup.',
		'Cybersecurity for Eastern Idaho businesses' => 'Cybersecurity for small and growing businesses',
		'IT & Drone · one team · Idaho Falls' => 'IT & Drone · one team · remote and on-site',
		'IT &amp; Drone · one team · Idaho Falls' => 'IT &amp; Drone · one team · remote and on-site',
		'Local businesses across eastern Idaho.' => 'Businesses supported remotely and on-site.',
		'We look after the systems, sites and cameras for a martial-arts academy, a retail shop and a family-run estate-sales business here in eastern Idaho.' => 'We look after the systems, sites and cameras for a martial-arts academy, a retail shop and a family-run estate-sales business.',
		'Distance from Idaho Falls area (miles)' => 'Estimated round-trip travel (miles)',
		'Distance from Idaho Falls area (miles)' => 'Estimated round-trip travel (miles)',
		'Distance from Idaho Falls:' => 'Estimated travel:',
		'Licensed & certified · trusted by local businesses' => 'Licensed & certified · trusted by businesses',
		'Licensed &amp; certified · trusted by local businesses' => 'Licensed &amp; certified · trusted by businesses',
		'Local IT & drone clients' => 'IT & drone clients',
		'Local IT &amp; drone clients' => 'IT &amp; drone clients',
		'Trusted by local IT & drone clients across our community.' => 'Trusted by IT and drone clients.',
		'Trusted by local IT &amp; drone clients across our community.' => 'Trusted by IT and drone clients.',
		'Local · human · one business day' => 'Direct · human · one business day',
		'Local · Trustworthy · Tech-forward' => 'Responsive · Trustworthy · Tech-forward',
		'Local, human IT & automation partners' => 'Direct, human IT & automation partners',
		'Local, human IT &amp; automation partners' => 'Direct, human IT &amp; automation partners',
		'UplinkSync is a local IT and automation partner' => 'UplinkSync is a responsive IT and automation partner',
		'one local team' => 'one dedicated team',
		'One local team' => 'One dedicated team',
		'one local number to call' => 'one team to call',
		'handled locally' => 'handled directly',
		'Handled locally' => 'Handled directly',
		'with local IT that answers when you call' => 'with responsive IT that answers when you call',
		'Managed IT, handled locally.' => 'Managed IT, handled directly.',
		'a local outfit in Eastern Idaho' => 'a responsive specialist team',
		'Local — Eastern Idaho roots' => 'Remote-first — on-site by arrangement',
		'Based in — Rexburg & Idaho Falls' => 'Remote-first — on-site by arrangement',
		'Based in — Rexburg &amp; Idaho Falls' => 'Remote-first — on-site by arrangement',
		'Eastern Idaho' => 'remote-first service',
		'eastern Idaho' => 'remote-first service',
		'Aerial print gallery · Idaho & Utah' => 'Aerial print gallery',
		'Aerial print gallery · Idaho &amp; Utah' => 'Aerial print gallery',
		'Cinematic reel, Idaho and Utah' => 'Cinematic aerial reel',
		'Palisades Reservoir, Idaho' => 'Mountain reservoir landscape',
		'Palisades, Idaho' => 'Mountain landscape',
		'Palisades Reservoir, ID' => 'Mountain reservoir landscape',
		'Pocatello, Idaho — Brooklyn\'s Playground' => 'Aerial cityscape',
		'Pocatello, Idaho' => 'Aerial cityscape',
		'Pocatello, ID' => 'Aerial cityscape',
		'Idaho Falls, Idaho' => 'Aerial cityscape',
		'Idaho Falls, ID' => 'Aerial cityscape',
		'Kaysville, Utah' => 'Aerial cityscape',
		'Kaysville, UT' => 'Aerial cityscape',
		'Saratoga Springs, Utah' => 'Aerial cityscape',
		'Saratoga Springs, UT' => 'Aerial cityscape',
		'Idaho and Utah' => 'remote and on-site projects',
		'Idaho & Utah' => 'remote and on-site projects',
		'Idaho &amp; Utah' => 'remote and on-site projects',
	);

	$text = str_ireplace( array_keys( $exact ), array_values( $exact ), $text );

	// Catch residual standalone place names in visible copy without touching
	// URLs or filenames. More specific combinations above run first.
	$text = preg_replace(
		'/\b(?:Saratoga Springs|Idaho Falls|Bonneville County|Utah County|Wasatch Front|Salt Lake City|Rexburg|Blackfoot|Pocatello|Kaysville|Ammon|Boise|Provo|Ogden)\b(?:,?\s+(?:Idaho|Utah|ID|UT))?/iu',
		'the service area',
		$text
	);
	$text = preg_replace( '/\b(?:Idaho|Utah)\b/iu', 'the service area', $text );

	// Normalise artefacts produced when a location was adjacent to punctuation.
	$text = preg_replace( '/(?:the service area\s*[·,—-]\s*){2,}/iu', 'the service area · ', $text );
	$text = preg_replace( '/\s{2,}/u', ' ', $text );
	return $text;
}

/**
 * Remove address/phone blocks and neutralise legal/service-area clauses whose
 * markup spans multiple text nodes.
 *
 * @param string $html Complete HTML document.
 * @return string
 */
function uplinksync_location_neutral_structural_rewrites( $html ) {
	$replacements = array(
		'<p>Idaho law governs these Service Terms. Disputes will be brought in courts located in&nbsp;<strong>Bonneville County, Idaho</strong>.</p>' => '<p>These Service Terms are governed by applicable law. Disputes will be brought in a court of competent jurisdiction.</p>',
		'<p>These Terms are governed by the laws of the State of Idaho, United States, without regard to its conflict-of-laws rules, and any disputes relating to the Website will be handled in the state or federal courts located in Idaho.</p>' => '<p>These Terms are governed by applicable law, without regard to conflict-of-laws rules. Disputes relating to the Website will be handled in a court of competent jurisdiction.</p>',
		'<p>Business Information:<br>UplinkSync LLC<br>3100 N Lakeharbor Lane, Suite 176 #508<br>Boise, ID 83703<br>United States</p>' => '<p>Business Information:<br>UplinkSync LLC<br>Serving clients remotely and on-site by arrangement.</p>',
	);
	$html = str_replace( array_keys( $replacements ), array_values( $replacements ), $html );
	$html = preg_replace(
		'#<p>UplinkSync serves the(?:&nbsp;|\x{00a0}|\s)*<strong>.*?</strong>\.</p>#isu',
		'<p>UplinkSync serves clients remotely, with on-site work available by arrangement.</p>',
		$html
	);
	$html = preg_replace(
		'#<p>UplinkSync provides structured, security-focused managed IT services for small and growing businesses throughout the(?:&nbsp;|\x{00a0}|\s)*<strong>.*?</strong>\.</p>#isu',
		'<p>UplinkSync provides structured, security-focused managed IT services remotely, with on-site work available by arrangement.</p>',
		$html
	);
	$html = preg_replace(
		'#Business address:\s*UplinkSync LLC,\s*3100 N Lakeharbor Lane, Suite 176 \#508,\s*Boise, ID 83703,\s*United States\.?\s*#iu',
		'Serving clients remotely and on-site by arrangement. ',
		$html
	);
	$html = preg_replace(
		'#(?:&#183;|&middot;|·)\s*UplinkSync LLC,\s*3100 N Lakeharbor Lane, Suite 176 \#508,\s*Boise, ID 83703,\s*United States\.?#iu',
		'',
		$html
	);

	// Remove any telephone anchor, including one injected by the contact fixer.
	$html = preg_replace( '#<a\b[^>]*href=(["\'])tel:[^"\']*\1[^>]*>.*?</a>#is', '', $html );
	// Remove the owner phone in plain-text variants and an immediately preceding
	// line break/icon. Email remains the public contact route.
	$html = preg_replace( '#(?:<br\s*/?>\s*)?(?:📞\s*)?(?:\+?1[\s.\-]*)?\(?208\)?[\s.\-]*995[\s.\-]*2704#u', '', $html );

	// Remove the former mailbox/address wherever a stale legal paragraph uses a
	// slightly different entity encoding than the exact block above.
	$html = str_ireplace( '3100 N Lakeharbor Lane, Suite 176 #508', '', $html );
	$html = preg_replace( '/\bBoise,?\s+(?:ID|Idaho)\s+83703\b/iu', '', $html );
	$html = preg_replace( '#Business address:\s*(?:UplinkSync LLC,?\s*)?(?:,|&#183;|&middot;|\s|<br\s*/?>)*#iu', '', $html );

	return $html;
}

/**
 * Strip geographic and telephone properties from a schema value recursively.
 *
 * @param mixed $value Schema value.
 * @return mixed|null
 */
function uplinksync_location_neutral_schema_value( $value ) {
	if ( ! is_array( $value ) ) {
		return is_string( $value ) ? uplinksync_location_neutral_text( $value ) : $value;
	}

	$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
	if ( $is_list ) {
		$out = array();
		foreach ( $value as $item ) {
			$clean = uplinksync_location_neutral_schema_value( $item );
			if ( null !== $clean ) {
				$out[] = $clean;
			}
		}
		return $out;
	}

	$type = isset( $value['@type'] ) ? (array) $value['@type'] : array();
	$place_types = array( 'Place', 'City', 'State', 'AdministrativeArea', 'PostalAddress', 'GeoCoordinates', 'GeoShape' );
	if ( array_intersect( $type, $place_types ) ) {
		return null;
	}

	$drop = array(
		'address', 'addressLocality', 'addressRegion', 'postalCode', 'streetAddress',
		'geo', 'areaServed', 'serviceArea', 'location', 'contentLocation',
		'locationCreated', 'foundingLocation', 'hasMap', 'map', 'telephone', 'faxNumber',
	);
	foreach ( $drop as $key ) {
		unset( $value[ $key ] );
	}

	if ( isset( $value['@type'] ) ) {
		$types = array_values( array_diff( (array) $value['@type'], array( 'LocalBusiness', 'ProfessionalService' ) ) );
		if ( ! $types ) {
			$types = array( 'Organization' );
		}
		$value['@type'] = is_array( $value['@type'] ) ? $types : $types[0];
	}

	foreach ( $value as $key => $item ) {
		$clean = uplinksync_location_neutral_schema_value( $item );
		if ( null === $clean ) {
			unset( $value[ $key ] );
		} else {
			$value[ $key ] = $clean;
		}
	}
	return $value;
}

/**
 * Rank Math schema filter: remove location and telephone fields before output.
 *
 * @param array $data Rank Math graph.
 * @return array
 */
function uplinksync_location_neutral_rank_math_schema( $data ) {
	$clean = uplinksync_location_neutral_schema_value( $data );
	return is_array( $clean ) ? $clean : array();
}
add_filter( 'rank_math/json_ld', 'uplinksync_location_neutral_rank_math_schema', 999 );

/**
 * Sanitize every JSON-LD block, including schema printed outside Rank Math.
 *
 * @param string $html Complete document.
 * @return string
 */
function uplinksync_location_neutral_json_ld( $html ) {
	return preg_replace_callback(
		'#(<script\b[^>]*type=(["\'])application/ld\+json\2[^>]*>)(.*?)(</script>)#is',
		static function ( $match ) {
			$json = trim( $match[3] );
			// Repair the known email-obfuscation injection before decoding.
			$json = preg_replace( '#<!--email_off--><a\b[^>]*href="mailto:[^"]+"[^>]*>(.*?)</a><!--email_on-->#is', '$1', $json );
			$data = json_decode( $json, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				return $match[0];
			}
			$data = uplinksync_location_neutral_schema_value( $data );
			$encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			return $match[1] . $encoded . $match[4];
		},
		$html
	);
}

/**
 * Rewrite visible text nodes and safe text-bearing attributes only.
 *
 * @param string $html Complete document after structural/schema rewrites.
 * @return string
 */
function uplinksync_location_neutral_visible_html( $html ) {
	$parts = preg_split( '#(<[^>]+>)#s', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	if ( ! is_array( $parts ) ) {
		return $html;
	}

	$protected = '';
	foreach ( $parts as $index => $part ) {
		if ( '' === $part ) {
			continue;
		}
		if ( '<' !== $part[0] ) {
			if ( 'script' === $protected ) {
				// Keep executable code and URLs byte-for-byte. The estimator's
				// display label is the sole location-bearing JavaScript string.
				$parts[ $index ] = str_replace( 'Distance from Idaho Falls:', 'Estimated travel:', $part );
			} elseif ( '' === $protected ) {
				$parts[ $index ] = uplinksync_location_neutral_text( $part );
			}
			continue;
		}

		if ( preg_match( '#^</(script|style)\b#i', $part, $closing ) ) {
			$protected = '';
			continue;
		}
		if ( preg_match( '#^<(script|style)\b#i', $part, $opening ) ) {
			$protected = strtolower( $opening[1] );
			continue;
		}
		if ( '' !== $protected ) {
			continue;
		}

		// Only text-bearing attributes are safe to rewrite. Never touch href,
		// src, srcset, class, id, data attributes, styles, or filenames.
		$parts[ $index ] = preg_replace_callback(
			'/\s(alt|title|aria-label)=("|\')(.*?)\2/isu',
			static function ( $match ) {
				return ' ' . $match[1] . '=' . $match[2] . uplinksync_location_neutral_text( $match[3] ) . $match[2];
			},
			$part
		);
		if ( preg_match( '/^<meta\b/i', $part ) ) {
			$parts[ $index ] = preg_replace_callback(
				'/\scontent=("|\')(.*?)\1/isu',
				static function ( $match ) {
					return ' content=' . $match[1] . uplinksync_location_neutral_text( $match[2] ) . $match[1];
				},
				$parts[ $index ]
			);
		}
	}
	return implode( '', $parts );
}

/**
 * Neutralise one rendered block/content fragment.
 *
 * @param string $html Rendered fragment.
 * @return string
 */
function uplinksync_location_neutral_fragment( $html ) {
	if ( ! is_string( $html ) || '' === $html ) {
		return $html;
	}

	$html = uplinksync_location_neutral_structural_rewrites( $html );
	$html = uplinksync_location_neutral_json_ld( $html );
	$html = uplinksync_location_neutral_visible_html( $html );
	return $html;
}

/**
 * Full-document compatibility wrapper used by standalone regression fixtures.
 * Runtime rendering uses bounded WordPress block/content filters below; no new
 * output buffer is registered.
 *
 * @param string $html Rendered HTML document.
 * @return string
 */
function uplinksync_location_neutral_rewrite( $html ) {
	if ( ! is_string( $html ) || false === stripos( $html, '</body>' ) ) {
		return $html;
	}
	return uplinksync_location_neutral_fragment( $html );
}

add_filter( 'render_block', 'uplinksync_location_neutral_fragment', 999 );
add_filter( 'the_content', 'uplinksync_location_neutral_fragment', 999 );

/**
 * Last-priority SEO filters keep core, Rank Math and social metadata aligned.
 */
function uplinksync_location_neutral_seo_text( $value ) {
	return uplinksync_location_neutral_text( $value );
}
add_filter( 'pre_get_document_title', 'uplinksync_location_neutral_seo_text', 999 );
add_filter( 'rank_math/frontend/title', 'uplinksync_location_neutral_seo_text', 999 );
add_filter( 'rank_math/frontend/description', 'uplinksync_location_neutral_seo_text', 999 );
add_filter( 'rank_math/opengraph/facebook/og_title', 'uplinksync_location_neutral_seo_text', 999 );
add_filter( 'rank_math/opengraph/facebook/og_description', 'uplinksync_location_neutral_seo_text', 999 );
add_filter( 'rank_math/opengraph/twitter/twitter_title', 'uplinksync_location_neutral_seo_text', 999 );
add_filter( 'rank_math/opengraph/twitter/twitter_description', 'uplinksync_location_neutral_seo_text', 999 );

add_filter(
	'document_title_parts',
	static function ( $parts ) {
		foreach ( $parts as $key => $value ) {
			if ( is_string( $value ) ) {
				$parts[ $key ] = uplinksync_location_neutral_text( $value );
			}
		}
		return $parts;
	},
	999
);
