<?php
/**
 * Plugin Name: UplinkSync SEO meta repairs
 * Description: (1) Repairs the author URL that Admin Site Enhancements' "Obfuscate Author Slugs" corrupts for pages whose post_author is 0, and drops the resulting nameless Person from Rank Math JSON-LD (Article.author -> the Organization). (2) Brand favicon set from the child theme when no real Site Icon is set (the stored one is a "generated-test" image). (3) Branded 1200x630 og:image / twitter:image on key pages and a default card everywhere else that has none.
 *
 * Root cause of (1): ASE's author_link filter does
 *   str_replace( '/' . $author_slug, '/' . $encrypted, $link )
 * and core passes an EMPTY nicename when the author ID resolves to no user (post_author = 0 on
 * About, Contact and other seeded pages). str_replace( '/', '/<hash>', ... ) then splices the
 * hash after every slash: https:/<h>/<h>uplinksync.com/<h>author/<h>. Rank Math uses that URL as
 * the Person @id/url and Article author @id. Fixed here in code; DB-STEPS.md (optional) gives
 * the pages a real author.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------------------------------
 * 1. Author URL + schema repair
 * ---------------------------------------------------------------------------------------- */

/**
 * True for an http(s) URL whose authority is missing or broken (e.g. "https:/af35…/…").
 *
 * @param mixed $value Candidate.
 * @return bool
 */
function uplinksync_seo_is_broken_url( $value ) {
	if ( ! is_string( $value ) || ! preg_match( '#^https?:#i', $value ) ) {
		return false;
	}
	if ( ! preg_match( '#^https?://([a-z0-9.-]+)(?::\d+)?(?:[/?\#]|$)#i', $value, $m ) ) {
		return true;
	}
	// A slug hash spliced in front of the host leaves "<hex>uplinksync.com".
	return (bool) preg_match( '#^[0-9a-f]{16,}#i', $m[1] );
}

/**
 * author_link, after ASE (priority 10): an author with no slug has no archive, so link home
 * instead of letting a mangled URL reach templates, feeds or schema.
 *
 * @param string $link      Author URL.
 * @param int    $author_id Author ID.
 * @param string $nicename  Author slug ('' when the user does not exist).
 * @return string
 */
function uplinksync_seo_author_link( $link, $author_id = 0, $nicename = '' ) {
	if ( '' === (string) $nicename || uplinksync_seo_is_broken_url( $link ) ) {
		return home_url( '/' );
	}
	return $link;
}

/**
 * Is this schema node a Person that does not describe anyone (no name, or broken @id/url)?
 *
 * @param mixed $node Node.
 * @return bool
 */
function uplinksync_seo_is_orphan_person( $node ) {
	if ( ! is_array( $node ) || ! in_array( 'Person', isset( $node['@type'] ) ? (array) $node['@type'] : array(), true ) ) {
		return false;
	}
	if ( empty( $node['name'] ) ) {
		return true;
	}
	foreach ( array( '@id', 'url' ) as $k ) {
		if ( isset( $node[ $k ] ) && uplinksync_seo_is_broken_url( $node[ $k ] ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Find the Organization @id in a graph (fallback: home/#organization).
 *
 * @param array $nodes Graph nodes.
 * @return string
 */
function uplinksync_seo_org_id( array $nodes ) {
	foreach ( $nodes as $node ) {
		if ( is_array( $node ) && ! empty( $node['@id'] ) && is_string( $node['@id'] )
			&& array_intersect( array( 'Organization', 'ProfessionalService', 'LocalBusiness' ), isset( $node['@type'] ) ? (array) $node['@type'] : array() )
			&& ! uplinksync_seo_is_broken_url( $node['@id'] ) ) {
			return $node['@id'];
		}
	}
	return home_url( '/#organization' );
}

/**
 * Re-point references to removed nodes and drop any remaining broken @id/url strings.
 *
 * @param mixed  $value  Node / value.
 * @param array  $remap  Old @id => new @id.
 * @param string $org_id Organization @id (for inline orphan Persons).
 * @return mixed
 */
function uplinksync_seo_schema_walk( $value, array $remap, $org_id ) {
	if ( ! is_array( $value ) ) {
		return $value;
	}
	if ( uplinksync_seo_is_orphan_person( $value ) ) {
		return array( '@id' => $org_id );
	}
	if ( isset( $value['@id'] ) && is_string( $value['@id'] ) && isset( $remap[ $value['@id'] ] ) ) {
		$value['@id'] = $remap[ $value['@id'] ];
	}
	foreach ( $value as $k => $v ) {
		if ( ( '@id' === $k || 'url' === $k ) && uplinksync_seo_is_broken_url( $v ) ) {
			unset( $value[ $k ] );
			continue;
		}
		$value[ $k ] = uplinksync_seo_schema_walk( $v, $remap, $org_id );
	}
	return $value;
}

/**
 * rank_math/json_ld: drop top-level orphan Person nodes, credit their articles to the
 * Organization, and strip any broken URL left anywhere. Handles Rank Math's keyed array and a
 * plain {"@graph": [...]} document.
 *
 * @param mixed $data Graph.
 * @return mixed
 */
function uplinksync_seo_schema_repair( $data ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}
	$has_graph = isset( $data['@graph'] ) && is_array( $data['@graph'] );
	$nodes     = $has_graph ? $data['@graph'] : $data;
	$org_id    = uplinksync_seo_org_id( $nodes );
	$remap     = array();
	foreach ( $nodes as $k => $node ) {
		if ( uplinksync_seo_is_orphan_person( $node ) ) {
			if ( isset( $node['@id'] ) && is_string( $node['@id'] ) ) {
				$remap[ $node['@id'] ] = $org_id;
			}
			unset( $nodes[ $k ] );
		}
	}
	if ( $has_graph || array_keys( $nodes ) === range( 0, count( $nodes ) - 1 ) || array() === $nodes ) {
		$nodes = array_values( $nodes );
	}
	$nodes = uplinksync_seo_schema_walk( $nodes, $remap, $org_id );
	if ( $has_graph ) {
		$data['@graph'] = $nodes;
		return $data;
	}
	return $nodes;
}

/* ------------------------------------------------------------------------------------------
 * 2. Favicon (file-driven; a real Site Icon chosen in wp-admin still wins)
 * ---------------------------------------------------------------------------------------- */

/**
 * Should the theme's brand icon replace the stored Site Icon?
 *
 * @return bool
 */
function uplinksync_seo_use_theme_icon() {
	static $use = null;
	if ( null !== $use ) {
		return $use;
	}
	if ( ! is_readable( get_stylesheet_directory() . '/assets/images/brand/favicon/icon-512.png' ) ) {
		return $use = false;
	}
	$id = (int) get_option( 'site_icon' );
	if ( $id <= 0 ) {
		return $use = true;
	}
	$file = (string) get_post_meta( $id, '_wp_attached_file', true );
	return $use = ( '' === $file || (bool) preg_match( '#generated-test#i', $file ) );
}

/**
 * Theme favicon URL for a requested size.
 *
 * @param int $size Pixel size.
 * @return string
 */
function uplinksync_seo_icon_file_url( $size ) {
	$size = (int) $size;
	if ( 180 === $size ) {
		$file = 'apple-touch-icon.png';
	} elseif ( $size <= 16 ) {
		$file = 'favicon-16.png';
	} elseif ( $size <= 32 ) {
		$file = 'favicon-32.png';
	} elseif ( $size <= 192 ) {
		$file = 'icon-192.png';
	} elseif ( $size <= 270 ) {
		$file = 'icon-270.png';
	} else {
		$file = 'icon-512.png';
	}
	return get_stylesheet_directory_uri() . '/assets/images/brand/favicon/' . $file;
}

/**
 * get_site_icon_url: wp_site_icon(), has_site_icon(), the login page and /favicon.ico
 * (do_favicon) all read through this.
 *
 * @param string $url  Stored icon URL.
 * @param int    $size Size.
 * @return string
 */
function uplinksync_seo_site_icon_url( $url, $size = 512 ) {
	return uplinksync_seo_use_theme_icon() ? uplinksync_seo_icon_file_url( $size ) : $url;
}

/**
 * Add a 16px PNG and the multi-size .ico next to core's 32/192/180/270 tags.
 *
 * @param array $tags Meta tags.
 * @return array
 */
function uplinksync_seo_site_icon_tags( $tags ) {
	if ( ! uplinksync_seo_use_theme_icon() ) {
		return $tags;
	}
	$base   = get_stylesheet_directory_uri() . '/assets/images/brand/favicon/';
	$tags[] = sprintf( '<link rel="icon" href="%s" sizes="16x16" />', esc_url( $base . 'favicon-16.png' ) );
	$tags[] = sprintf( '<link rel="icon" href="%s" sizes="any" />', esc_url( $base . 'favicon.ico' ) );
	return $tags;
}

/* ------------------------------------------------------------------------------------------
 * 3. Share images
 * ---------------------------------------------------------------------------------------- */

/** Page slug => card key. Front page is 'home'. */
const UPLINKSYNC_SEO_OG_PAGES = array(
	'services'       => 'services',
	'drone-services' => 'drone',
	'about'          => 'about',
	'contact'        => 'contact',
);

/** Alt text per card (no place names). */
const UPLINKSYNC_SEO_OG_ALT = array(
	'home'     => 'UplinkSync: managed IT, web and aerial services',
	'services' => 'UplinkSync IT support, websites and automation',
	'drone'    => 'UplinkSync aerial photo, video and inspections',
	'about'    => 'Doug, owner of UplinkSync',
	'contact'  => 'Get a quote or book a call with UplinkSync',
	'default'  => 'UplinkSync: managed IT, web and aerial services',
);

/**
 * Card key for a request context.
 *
 * @param bool   $is_front Front page?
 * @param string $slug     Singular page slug ('' if not a page).
 * @return string           '' = not a key page.
 */
function uplinksync_seo_og_key_for( $is_front, $slug ) {
	if ( $is_front ) {
		return 'home';
	}
	return isset( UPLINKSYNC_SEO_OG_PAGES[ $slug ] ) ? UPLINKSYNC_SEO_OG_PAGES[ $slug ] : '';
}

/**
 * Card key for the current request.
 *
 * @return string
 */
function uplinksync_seo_og_key() {
	$slug = '';
	if ( is_page() ) {
		$post = get_queried_object();
		$slug = ( $post && isset( $post->post_name ) ) ? (string) $post->post_name : '';
	}
	return uplinksync_seo_og_key_for( is_front_page(), $slug );
}

/**
 * Card image data.
 *
 * @param string $key Card key.
 * @return array
 */
function uplinksync_seo_og_card( $key ) {
	return array(
		'url'    => get_stylesheet_directory_uri() . '/assets/images/seo/og-' . $key . '.jpg',
		'width'  => 1200,
		'height' => 630,
		'type'   => 'image/jpeg',
		'alt'    => UPLINKSYNC_SEO_OG_ALT[ $key ],
	);
}

/** URL of the card added on this request (key pages), so other candidates can be dropped. */
$GLOBALS['uplinksync_seo_og_forced'] = '';

/**
 * rank_math/opengraph/{network}/add_images (runs before Rank Math looks for images): on a key
 * page, add its branded card first.
 *
 * @param object $image Rank Math Image collector.
 */
function uplinksync_seo_og_add_images( $image ) {
	$key = uplinksync_seo_og_key();
	if ( '' === $key || ! is_object( $image ) || ! method_exists( $image, 'add_image' ) ) {
		return;
	}
	$card                                = uplinksync_seo_og_card( $key );
	$GLOBALS['uplinksync_seo_og_forced'] = $card['url'];
	$image->add_image( $card );
}

/**
 * rank_math/opengraph/{network}/image_array: on a key page, the card is the only image
 * (stored Rank Math/featured images, incl. a place-named home still, are skipped).
 *
 * @param mixed $attachment Candidate.
 * @return mixed
 */
function uplinksync_seo_og_only_card( $attachment ) {
	$forced = $GLOBALS['uplinksync_seo_og_forced'];
	if ( '' === $forced || ! is_array( $attachment ) || ! isset( $attachment['url'] ) ) {
		return $attachment;
	}
	return $attachment['url'] === $forced ? $attachment : array();
}

/**
 * rank_math/opengraph/{network}/add_additional_images: any other page with no image gets
 * the default card.
 *
 * @param object $image Rank Math Image collector.
 */
function uplinksync_seo_og_default( $image ) {
	if ( is_object( $image ) && method_exists( $image, 'has_images' ) && ! $image->has_images() ) {
		$image->add_image( uplinksync_seo_og_card( 'default' ) );
	}
}

/**
 * Without Rank Math: minimal og:image / twitter tags.
 */
function uplinksync_seo_og_fallback_head() {
	if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
		return;
	}
	$key  = uplinksync_seo_og_key();
	$card = uplinksync_seo_og_card( '' === $key ? 'default' : $key );
	printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $card['url'] ) );
	printf( '<meta property="og:image:width" content="1200" />' . "\n" . '<meta property="og:image:height" content="630" />' . "\n" );
	printf( '<meta property="og:image:alt" content="%s" />' . "\n", esc_attr( $card['alt'] ) );
	echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
	printf( '<meta name="twitter:image" content="%s" />' . "\n", esc_url( $card['url'] ) );
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'author_link', 'uplinksync_seo_author_link', 11, 3 );
	// Before the theme's image swap (30) and the location layer (999); phone stays last.
	add_filter( 'rank_math/json_ld', 'uplinksync_seo_schema_repair', 25 );

	add_filter( 'get_site_icon_url', 'uplinksync_seo_site_icon_url', 20, 2 );
	add_filter( 'site_icon_meta_tags', 'uplinksync_seo_site_icon_tags', 20 );

	foreach ( array( 'facebook', 'twitter' ) as $uplinksync_seo_network ) {
		add_action( "rank_math/opengraph/{$uplinksync_seo_network}/add_images", 'uplinksync_seo_og_add_images', 5 );
		add_filter( "rank_math/opengraph/{$uplinksync_seo_network}/image_array", 'uplinksync_seo_og_only_card', 20 );
		add_action( "rank_math/opengraph/{$uplinksync_seo_network}/add_additional_images", 'uplinksync_seo_og_default', 20 );
	}
	unset( $uplinksync_seo_network );
	add_filter(
		'rank_math/opengraph/twitter/card_type',
		static function () {
			return 'summary_large_image';
		},
		20
	);
	add_action( 'wp_head', 'uplinksync_seo_og_fallback_head', 5 );
}
