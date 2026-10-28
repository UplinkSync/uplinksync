<?php
/**
 * Plugin Name: UplinkSync — Location-neutral presentation
 * Description: Keeps the gated site location-neutral until the business location is established (UPL-944). Rewrites database-backed public copy and metadata without changing quote, booking, service, or estimator behaviour.
 * Version: 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Location mode requested by configuration: 'neutral' (default) or 'utah'.
 *
 * The UPLINKSYNC_LOCATION_MODE constant (wp-config.php) wins over the
 * `uplinksync_location_mode` option so a rollback never needs the database.
 * Anything other than the exact string 'utah' means 'neutral' (fail closed).
 *
 * @return string
 */
function uplinksync_location_mode_requested() {
	if ( defined( 'UPLINKSYNC_LOCATION_MODE' ) ) {
		$mode = UPLINKSYNC_LOCATION_MODE;
	} elseif ( function_exists( 'get_option' ) ) {
		$mode = get_option( 'uplinksync_location_mode', 'neutral' );
	} else {
		$mode = 'neutral';
	}
	return 'utah' === $mode ? 'utah' : 'neutral';
}

/**
 * True once the stored copy has been rewritten and the owner has set
 * `uplinksync_location_utah_ready` (see DB-STEPS.md). Until then utah mode
 * refuses to activate, because the database still holds Idaho-era copy that
 * the neutral filters are currently hiding.
 *
 * @return bool
 */
function uplinksync_location_utah_ready() {
	if ( defined( 'UPLINKSYNC_LOCATION_UTAH_READY' ) ) {
		return (bool) UPLINKSYNC_LOCATION_UTAH_READY;
	}
	if ( ! function_exists( 'get_option' ) ) {
		return false;
	}
	$ready = get_option( 'uplinksync_location_utah_ready', '' );
	return ! in_array( $ready, array( '', '0', 0, false, null, 'no', 'false' ), true );
}

/**
 * Effective location mode. 'utah' only when requested AND ready.
 *
 * @return string
 */
function uplinksync_location_mode() {
	if ( 'utah' === uplinksync_location_mode_requested() && uplinksync_location_utah_ready() ) {
		return 'utah';
	}
	return 'neutral';
}

/**
 * Optional public phone number. Empty means hidden everywhere, including schema.
 * Read-only: defined elsewhere (constant) or via the `uplinksync_phone` option.
 *
 * @return string
 */
function uplinksync_location_phone() {
	// The validated number from uplinksync-phone.php wins when that plugin is present.
	if ( function_exists( 'uplinksync_phone_e164' ) ) {
		return (string) uplinksync_phone_e164();
	}
	if ( defined( 'UPLINKSYNC_PHONE' ) ) {
		$phone = UPLINKSYNC_PHONE;
	} elseif ( function_exists( 'get_option' ) ) {
		$phone = get_option( 'uplinksync_phone', '' );
	} else {
		$phone = '';
	}
	return is_string( $phone ) ? trim( $phone ) : '';
}

if ( function_exists( 'add_action' ) ) {
	// Requested utah mode while not ready: say so, and stay neutral.
	add_action(
		'admin_notices',
		static function () {
			if ( 'utah' !== uplinksync_location_mode_requested() || uplinksync_location_utah_ready() || ! current_user_can( 'manage_options' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p><strong>UplinkSync location mode:</strong> Utah mode was requested but is NOT active. The site is still location-neutral because <code>uplinksync_location_utah_ready</code> is not set. Rewrite the stored page copy first (see DB-STEPS.md), then run <code>wp option update uplinksync_location_utah_ready 1</code>.</p></div>';
		}
	);
}

if ( function_exists( 'add_filter' ) ) {
	// Refuse to store 'utah' while not ready, so the option never says one thing and the site does another.
	add_filter(
		'pre_update_option_uplinksync_location_mode',
		static function ( $value, $old_value = null ) {
			if ( 'utah' === $value && ! uplinksync_location_utah_ready() ) {
				return $old_value;
			}
			return $value;
		},
		10,
		2
	);
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
		// Post-go-live sentence entries (2026-10-25). They run before every
		// shorter entry and before the word-level fallbacks below, so these
		// strings never reach the 'the service area' / 'remote-first service'
		// safety net. Stored copy is untouched; retiring this plugin restores it.
		// Rank Math titles, descriptions and focus keywords (pages 278, 279, 1256, 1262, 1291).
		'Local IT Support & Managed Services | UplinkSync Idaho Falls' => 'Local IT Support & Managed Services | UplinkSync',
		'Local IT Support &amp; Managed Services | UplinkSync Idaho Falls' => 'Local IT Support &amp; Managed Services | UplinkSync',
		'IT Services & Managed IT Support | UplinkSync Idaho Falls' => 'IT Services & Managed IT Support | UplinkSync',
		'IT Services &amp; Managed IT Support | UplinkSync Idaho Falls' => 'IT Services &amp; Managed IT Support | UplinkSync',
		'for small and midsize businesses in Idaho Falls, Ammon & Rexburg.' => 'for small and midsize businesses, remotely and on-site by arrangement.',
		'for small and midsize businesses in Idaho Falls, Ammon &amp; Rexburg.' => 'for small and midsize businesses, remotely and on-site by arrangement.',
		'business automation for Idaho Falls-area businesses.' => 'business automation for small and growing businesses.',
		'managed IT services Idaho Falls' => 'managed IT services',
		'IT support Idaho Falls' => 'IT support',
		'One local Eastern Idaho team runs your front desk' => 'One dedicated team runs your front desk',
		'for small businesses and trades in Eastern Idaho — one local team, one number to call.' => 'for small businesses and trades — one dedicated team, one number to call.',
		'for small businesses and trades in Eastern Idaho &mdash; one local team, one number to call.' => 'for small businesses and trades &mdash; one dedicated team, one number to call.',
		'for small businesses and trades in Eastern Idaho &#8212; one local team, one number to call.' => 'for small businesses and trades &#8212; one dedicated team, one number to call.',
		'and small businesses and trades across Eastern Idaho' => 'and small businesses and trades',
		// Front-page template (wp_template 1266).
		'Managed IT · Web · Aerial Photo & Video · Idaho Falls' => 'Managed IT · Web · Aerial Photo & Video',
		'Managed IT · Web · Aerial Photo &amp; Video · Idaho Falls' => 'Managed IT · Web · Aerial Photo &amp; Video',
		'Managed IT &#183; Web &#183; Aerial Photo &amp; Video &#183; Idaho Falls' => 'Managed IT &#183; Web &#183; Aerial Photo &amp; Video',
		'one certified local team, right here in eastern Idaho' => 'one certified team',
		'looked after by one team here in eastern Idaho,' => 'looked after by one team,',
		'a real person, here in eastern Idaho.' => 'a real person.',
		'an estate-sales business here in eastern Idaho' => 'an estate-sales business',
		'for local businesses of every kind' => 'for businesses of every kind',
		'certified local team' => 'certified team',
		', right here in eastern Idaho' => '',
		// Martial-arts page (1256) body copy.
		', handled by the same people, in Eastern Idaho.' => ', handled by the same people.',
		// Drone services FAQ (wp_template 410), visible text and FAQPage JSON-LD.
		'We operate across the Eastern Idaho corridor and evaluate each site' => 'We evaluate each site',
		// Vertical-reel captions (wp_template 410) and their attachment alt text.
		'Ammon, Idaho · Aerial by UplinkSync' => 'Aerial by UplinkSync',
		'Ammon, Idaho &#183; Aerial by UplinkSync' => 'Aerial by UplinkSync',
		'Ammon, Idaho &middot; Aerial by UplinkSync' => 'Aerial by UplinkSync',
		'Aerial vertical still over Ammon, Idaho by UplinkSync' => 'Aerial vertical still by UplinkSync',
		// About page (383) and its attachment alt/caption text.
		'UplinkSync · Idaho Falls.' => 'UplinkSync.',
		'UplinkSync &#183; Idaho Falls.' => 'UplinkSync.',
		'at his workspace in Idaho Falls.' => 'at his workspace.',
		'Main Street and the county courthouse, the Eastern Idaho community UplinkSync is based in and serves.' => 'Main Street and the county courthouse.',
		'Aerial view of downtown Rexburg, Idaho' => 'Aerial view of a downtown',
		'Downtown Rexburg, Idaho' => 'Downtown main street',
		// /drone-services/ collection tiles (snippet 108 alt: "Aerial view — {term}, by UplinkSync.").
		'Aerial view — Idaho Falls, by UplinkSync.' => 'Aerial view by UplinkSync.',
		'Aerial view — Pocatello, by UplinkSync.' => 'Aerial view by UplinkSync.',
		'Aerial view — Kaysville, by UplinkSync.' => 'Aerial view by UplinkSync.',
		'Aerial view — Saratoga Springs, by UplinkSync.' => 'Aerial view by UplinkSync.',
		// Print-collection term descriptions (product_cat 26, 29).
		"Idaho's Gate City from the air" => 'The Gate City from the air',
		'Idaho’s Gate City from the air' => 'The Gate City from the air',
		'Idaho&#8217;s Gate City from the air' => 'The Gate City from the air',
		'Aerial cityscape photography over Saratoga Springs, Utah — a lakeside city on the northwest shore of Utah Lake in Utah County.' => 'Aerial cityscape photography over a lakeside city on the shore of a large freshwater lake.',
		'Aerial cityscape photography over Saratoga Springs, Utah &#8212; a lakeside city on the northwest shore of Utah Lake in Utah County.' => 'Aerial cityscape photography over a lakeside city on the shore of a large freshwater lake.',
		// Original UPL-944 entries.
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
		'Distance from Idaho Falls area (miles)' => 'Estimated round-trip travel (miles)',
		'Distance from Idaho&nbsp;Falls area (miles)' => 'Estimated round-trip travel (miles)',
		"Distance from Idaho\u{00A0}Falls area (miles)" => 'Estimated round-trip travel (miles)',
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

	// Photo subjects are factual product/collection names, not service-area
	// claims: a text node that is exactly a collection name (term links, shop
	// filter chips, gallery labels) passes through, and a subject name that
	// leads a product title ("Pocatello Aerial View No. 20"), the collection
	// position bar ("Pocatello · 4 of 4") or a collection archive's document
	// title ("Pocatello - UplinkSync") is masked from the catch-alls below.
	$subjects = 'Idaho Falls|Pocatello|Kaysville|Saratoga Springs|Palisades Reservoir';
	if ( preg_match( '/^\s*(?:' . $subjects . ')\s*$/u', $text ) ) {
		return $text;
	}
	$masked = array();
	$mask   = preg_replace_callback(
		'/\b(?:' . $subjects . ')\b(?=\s+(?:Aerial View|Mountain Vista)\b|\s*(?:·|&middot;|&#183;)\s*\d+\s+of\s+\d+|\s+(?:-|–|—|&#8211;|&#8212;|\|)\s+UplinkSync\b)/u',
		static function ( $match ) use ( &$masked ) {
			$masked[] = $match[0];
			return "\u{E000}" . ( count( $masked ) - 1 ) . "\u{E001}";
		},
		$text
	);
	if ( is_string( $mask ) ) {
		$text = $mask;
	}

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

	foreach ( $masked as $i => $subject ) {
		$text = str_replace( "\u{E000}" . $i . "\u{E001}", $subject, $text );
	}
	return $text;
}

/**
 * True when $value is the phone number the owner publishes via UPLINKSYNC_PHONE.
 * Safe when the phone mu-plugin is absent (then nothing is public).
 *
 * @param mixed $value tel: target, display text or schema value.
 * @return bool
 */
function uplinksync_location_neutral_is_public_phone( $value ) {
	return is_string( $value ) && function_exists( 'uplinksync_phone_is_public' ) && uplinksync_phone_is_public( $value );
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
		'#(?:&\#183;|&middot;|·)\s*UplinkSync LLC,\s*3100 N Lakeharbor Lane, Suite 176 \#508,\s*Boise, ID 83703,\s*United States\.?#iu',
		'',
		$html
	);

	// Remove every telephone anchor (including one injected by the contact fixer)
	// EXCEPT the number published through UPLINKSYNC_PHONE (mu-plugins/uplinksync-phone.php).
	$html = preg_replace_callback(
		'#<a\b[^>]*href=(["\'])tel:([^"\']*)\1[^>]*>.*?</a>#is',
		static function ( $m ) {
			return uplinksync_location_neutral_is_public_phone( $m[2] ) ? $m[0] : '';
		},
		$html
	);
	// Remove the retired owner phone in plain-text variants and an immediately preceding
	// line break/icon, unless the owner re-publishes that same number on purpose.
	if ( ! uplinksync_location_neutral_is_public_phone( '+12089952704' ) ) {
		$html = preg_replace( '#(?:<br\s*/?>\s*)?(?:📞\s*)?(?:\+?1[\s.\-]*)?\(?208\)?[\s.\-]*995[\s.\-]*2704#u', '', $html );
	}

	// Remove the former mailbox/address wherever a stale legal paragraph uses a
	// slightly different entity encoding than the exact block above.
	$html = str_ireplace( '3100 N Lakeharbor Lane, Suite 176 #508', '', $html );
	$html = preg_replace( '/\bBoise,?\s+(?:ID|Idaho)\s+83703\b/iu', '', $html );
	$html = preg_replace( '#Business address:\s*(?:UplinkSync LLC,?\s*)?(?:,|&\#183;|&middot;|\s|<br\s*/?>)*#iu', '', $html );

	return $html;
}

/**
 * Strip geographic and telephone properties from a schema value recursively.
 *
 * Text values are neutralised; URL-bearing properties and any string that is
 * itself a URL or path are returned byte-for-byte, so image/product/page URLs
 * containing a place slug (e.g. idaho-falls-full.jpg) are never rewritten.
 *
 * @param mixed       $value Schema value.
 * @param string|null $key   Property name that holds $value (inherited by list items).
 * @return mixed|null
 */
function uplinksync_location_neutral_schema_value( $value, $key = null ) {
	if ( ! is_array( $value ) ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}
		$url_keys = array( '@id', '@context', 'url', 'contentUrl', 'thumbnailUrl', 'embedUrl', 'image', 'logo', 'sameAs', 'target', 'item', 'mainEntityOfPage', 'urlTemplate' );
		if ( ( is_string( $key ) && in_array( $key, $url_keys, true ) ) || preg_match( '#^(?:[a-z][a-z0-9+.-]*://|//|/|mailto:|tel:|data:|urn:)#i', $value ) ) {
			return $value;
		}
		return uplinksync_location_neutral_text( $value );
	}

	$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
	if ( $is_list ) {
		$out = array();
		foreach ( $value as $item ) {
			$clean = uplinksync_location_neutral_schema_value( $item, $key );
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
		// The published business number (UPLINKSYNC_PHONE) is allowed through.
		if ( 'telephone' === $key && isset( $value['telephone'] ) && uplinksync_location_neutral_is_public_phone( $value['telephone'] ) ) {
			continue;
		}
		unset( $value[ $key ] );
	}

	if ( isset( $value['@type'] ) ) {
		$types = array_values( array_diff( (array) $value['@type'], array( 'LocalBusiness', 'ProfessionalService' ) ) );
		if ( ! $types ) {
			$types = array( 'Organization' );
		}
		$value['@type'] = is_array( $value['@type'] ) ? $types : $types[0];
	}

	foreach ( $value as $prop => $item ) {
		$clean = uplinksync_location_neutral_schema_value( $item, $prop );
		if ( null === $clean ) {
			unset( $value[ $prop ] );
		} else {
			$value[ $prop ] = $clean;
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

	$original = $html;
	// preg_* returns NULL on a pattern/PCRE error; never let one failed rewrite blank a rendered block.
	foreach ( array( 'uplinksync_location_neutral_structural_rewrites', 'uplinksync_location_neutral_json_ld', 'uplinksync_location_neutral_visible_html' ) as $step ) {
		$next = $step( $html );
		if ( ! is_string( $next ) || ( '' === $next && '' !== $html ) ) {
			return $original;
		}
		$html = $next;
	}
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

/**
 * Location-free organization facts added to the head JSON-LD in neutral mode
 * (after the neutraliser, priority 1000): founder, and telephone only when
 * UPLINKSYNC_PHONE is set. No place data is ever added here.
 *
 * @param mixed $value Schema value (Rank Math graph or nested node).
 * @return mixed
 */
function uplinksync_location_neutral_org_extras( $value ) {
	if ( ! is_array( $value ) ) {
		return $value;
	}
	$type = isset( $value['@type'] ) ? (array) $value['@type'] : array();
	if ( in_array( 'Organization', $type, true ) && ! empty( $value['name'] ) ) {
		if ( empty( $value['founder'] ) ) {
			$value['founder'] = array(
				'@type' => 'Person',
				'name'  => 'Doug Irwin',
			);
		}
		$phone = uplinksync_location_phone();
		if ( '' !== $phone ) {
			$value['telephone'] = $phone;
		}
	}
	foreach ( $value as $prop => $item ) {
		if ( is_array( $item ) ) {
			$value[ $prop ] = uplinksync_location_neutral_org_extras( $item );
		}
	}
	return $value;
}

/**
 * Last-priority SEO filters keep core, Rank Math and social metadata aligned.
 */
function uplinksync_location_neutral_seo_text( $value ) {
	return uplinksync_location_neutral_text( $value );
}

// Registered only in neutral mode (the default). In utah mode none of these run.
if ( 'neutral' === uplinksync_location_mode() ) {
	add_filter( 'rank_math/json_ld', 'uplinksync_location_neutral_rank_math_schema', 999 );
	add_filter( 'render_block', 'uplinksync_location_neutral_fragment', 999 );
	add_filter( 'the_content', 'uplinksync_location_neutral_fragment', 999 );
	add_filter( 'pre_get_document_title', 'uplinksync_location_neutral_seo_text', 999 );
	add_filter( 'rank_math/frontend/title', 'uplinksync_location_neutral_seo_text', 999 );
	add_filter( 'rank_math/frontend/description', 'uplinksync_location_neutral_seo_text', 999 );
	add_filter( 'rank_math/opengraph/facebook/og_title', 'uplinksync_location_neutral_seo_text', 999 );
	add_filter( 'rank_math/opengraph/facebook/og_description', 'uplinksync_location_neutral_seo_text', 999 );
	add_filter( 'rank_math/opengraph/twitter/twitter_title', 'uplinksync_location_neutral_seo_text', 999 );
	add_filter( 'rank_math/opengraph/twitter/twitter_description', 'uplinksync_location_neutral_seo_text', 999 );
	// og:image:alt / twitter:image:alt carry attachment alt text or the focus keyword.
	add_filter( 'rank_math/opengraph/facebook/og_image_alt', 'uplinksync_location_neutral_seo_text', 999 );
	add_filter( 'rank_math/opengraph/twitter/twitter_image_alt', 'uplinksync_location_neutral_seo_text', 999 );

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
	add_filter( 'rank_math/json_ld', 'uplinksync_location_neutral_org_extras', 1000 );
}
