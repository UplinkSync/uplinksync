<?php
/**
 * ***-391 / ***-183 (plan v2.0): cinematic hero loops, Immich origin.
 *
 * Owner-approved plan (video-cdn-hero-plan.md v2.0): the homepage hero is a
 * static poster with an OPTIONAL short, silent, seamless background loop served
 * from our own Immich (media.uplinksync.com) — NOT a paid video CDN and NOT the
 * 27GB source on shared hosting. The loops are 8-15s graded cuts from the
 * cleared Landscape/ source (Palisades + Idaho Falls aerials), boomerang-looped
 * for seamlessness, ~1-3MB each, 1920x1080 H.264. They live in the Immich album
 * "Site — Hero Loops (***-391)" behind a single public share key and stream
 * through the SAME anonymous /video/playback plumbing the [immich_video]
 * shortcode already uses (***-186/247/255) — reused here, not reinvented.
 *
 * DESIGN CONTRACT (plan §4), enforced in code:
 *   - The POSTER <img> is the LCP element (fetchpriority="high", intrinsic
 *     dimensions set) so Largest Contentful Paint never waits on video bytes.
 *   - The <video> is preload="none" and purely DECORATIVE (aria-hidden,
 *     tabindex="-1", muted, loop, playsinline) so it costs nothing until the
 *     browser chooses to fetch it, and is invisible to assistive tech.
 *   - prefers-reduced-motion renders POSTER-ONLY: CSS hides the video and
 *     hero.js never calls play(). No motion is forced on anyone.
 *   - FAIL-SAFE: if the Immich playback URL cannot be built the hero still
 *     renders a complete, correct STATIC hero (poster + copy). A media problem
 *     degrades to a still image, never to a broken or blank hero.
 *
 * HOMEPAGE SAFETY: this file only REGISTERS the [hero_loop] shortcode and a
 * conditional asset enqueue. It renders nothing on its own. The hero appears
 * only where an editor places [hero_loop] in content (or on the front page via
 * a template that calls it). Activating the theme therefore cannot change what
 * the homepage renders until that placement is made — a deliberate, reversible
 * content edit.
 *
 * @package uplinksync-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The public Immich share key that authorises anonymous playback of the hero
 * loop album. This is a PUBLIC share key by design (the same class of token the
 * [immich_share]/[immich_video] shortcodes already expose) — it grants read-only
 * streaming of these six curated, cleared Landscape loops and nothing else.
 */
if ( ! defined( 'UPLINKSYNC_HERO_SHARE_KEY' ) ) {
	define( 'UPLINKSYNC_HERO_SHARE_KEY', 'StpoFe5xPTxbizpBKskaPY_mFHVDqmdn4TLfbNDuEOIzoPcuCHZqahLhHh7r9UTSJzI' );
}

/**
 * Ordered hero-loop set. The FIRST entry is the default single loop; the full
 * list is handed to hero.js as candidates it may rotate through (motion only).
 * Each asset is a curated, graded, cleared Landscape cut uploaded to the
 * "Site — Hero Loops (***-391)" Immich album.
 *
 * @return array[] List of [ 'asset' => uuid, 'label' => string ].
 */
function uplinksync_hero_loops() {
	return array(
		array( 'asset' => '3db7289f-3ee9-4f14-8a85-ab453968c276', 'label' => 'Palisades Reservoir at dawn' ),
		array( 'asset' => 'f0ef38d3-25c5-4072-8c64-7b88160b0869', 'label' => 'Palisades ridgeline' ),
		array( 'asset' => 'a8297921-5a86-4086-b7c0-473655c7f649', 'label' => 'Palisades shoreline' ),
		array( 'asset' => '6fb8df44-0cd7-458a-8b6c-7bfab73d2183', 'label' => 'Canyon in autumn' ),
		array( 'asset' => 'd84e7991-b915-4eef-ba76-3f506c95ded6', 'label' => 'Idaho Falls & the Snake River' ),
		array( 'asset' => 'd34ecbe1-e229-42fe-8c26-06dfeb56285e', 'label' => 'Valley at dusk' ),
	);
}

/**
 * Absolute URL of the committed poster still (the LCP image). Kept as a helper
 * so a future poster swap is one edit.
 *
 * @return string
 */
function uplinksync_hero_poster_url() {
	return get_stylesheet_directory_uri() . '/assets/media/hero/palisades-hero.jpg';
}

/**
 * Register hero CSS/JS. Follows the child-theme priority-21 conditional-enqueue
 * pattern (drone-gallery/estimate-book/quote-form): style depends on
 * `uplinksync-brand` (registered at priority 20) so the brand layer resolves
 * first; JS is deferred in the footer and is enhancement-only.
 *
 * Loads where a hero can render: the front page, or any singular view whose
 * content contains the [hero_loop] shortcode. Enqueuing the CSS where no hero
 * markup exists is inert (the rules match nothing), so this is homepage-safe.
 */
function uplinksync_hero_register_assets() {
	if ( ! uplinksync_hero_should_enqueue() ) {
		return;
	}
	uplinksync_hero_enqueue_assets();
}
add_action( 'wp_enqueue_scripts', 'uplinksync_hero_register_assets', 21 );

/**
 * Whether the current request is one where a hero can appear.
 *
 * @return bool
 */
function uplinksync_hero_should_enqueue() {
	if ( is_front_page() ) {
		return true;
	}
	if ( is_singular() ) {
		$post = get_post();
		if ( $post && has_shortcode( (string) $post->post_content, 'hero_loop' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Idempotently enqueue the hero assets. Called by the priority-21 registrar and
 * again by the shortcode at render time (so the assets are present even if the
 * hero is composed in a context the registrar could not detect). wp_enqueue_*
 * de-duplicates by handle, so calling twice is safe.
 */
function uplinksync_hero_enqueue_assets() {
	wp_enqueue_style(
		'uplinksync-hero',
		get_stylesheet_directory_uri() . '/assets/css/hero.css',
		array( 'uplinksync-brand' ),
		uplinksync_child_asset_ver( 'assets/css/hero.css' )
	);
	wp_enqueue_script(
		'uplinksync-hero',
		get_stylesheet_directory_uri() . '/assets/js/hero.js',
		array(),
		uplinksync_child_asset_ver( 'assets/js/hero.js' ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);

	// Hand the media host + public share key to hero.js at render time so a
	// `.uls-hero__video[data-hero-asset="<uuid>"]` in a template can carry only
	// the asset UUID (never the key). The key stays in ONE place — the
	// UPLINKSYNC_HERO_SHARE_KEY define above — so it never appears as a `key=…`
	// URL literal in a version-controlled template. Localize once.
	static $localized = false;
	if ( ! $localized ) {
		wp_localize_script(
			'uplinksync-hero',
			'ulsHeroCfg',
			array(
				'mediaBase' => 'https://' . ( defined( 'UPLINKSYNC_MEDIA_HOST' ) ? UPLINKSYNC_MEDIA_HOST : 'media.uplinksync.com' ),
				'shareKey'  => UPLINKSYNC_HERO_SHARE_KEY,
			)
		);
		$localized = true;
	}
}

/**
 * [hero_loop] — the cinematic homepage hero.
 *
 * Usage (content edit, no code change):
 *   [hero_loop heading="Managed IT that keeps Idaho Falls moving."
 *              subheading="Local support, enterprise reliability."
 *              cta_text="Get an estimate" cta_url="/contact/"]
 *
 * Attributes (all optional — every one has a safe default):
 *   heading      Hero H1 text.
 *   subheading   Supporting line under the H1.
 *   cta_text     Primary button label ('' hides the button).
 *   cta_url      Primary button destination.
 *   cta2_text    Secondary (ghost) button label ('' hides it).
 *   cta2_url     Secondary button destination.
 *   motion       'on' (default) enables the background loop where motion is
 *                allowed; 'off' forces a poster-only static hero.
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML.
 */
function uplinksync_hero_loop_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'heading'    => 'Managed IT & drone services for the Mountain West.',
			'subheading' => 'Local support, enterprise reliability — from the ground up and the sky down.',
			'cta_text'   => 'Get an estimate',
			'cta_url'    => '/contact/',
			'cta2_text'  => 'Explore services',
			'cta2_url'   => '/services/',
			'motion'     => 'on',
			'heading_tag' => 'h1',
		),
		$atts,
		'hero_loop'
	);

	// Heading element: bounded to h1/h2 so a placement above an existing page
	// H1 can demote this heading and keep a single document H1 (SEO/a11y).
	$heading_tag = strtolower( trim( (string) $atts['heading_tag'] ) );
	if ( ! in_array( $heading_tag, array( 'h1', 'h2' ), true ) ) {
		$heading_tag = 'h1';
	}

	uplinksync_hero_enqueue_assets();

	$poster = esc_url( uplinksync_hero_poster_url() );
	$motion = ( 'off' !== strtolower( trim( (string) $atts['motion'] ) ) );

	// Build the ordered list of anonymous playback URLs. Any asset that fails
	// validation is simply skipped; if none survive, the hero is poster-only.
	$sources = array();
	if ( $motion && function_exists( 'uplinksync_immich_playback_src' ) ) {
		foreach ( uplinksync_hero_loops() as $loop ) {
			$src = uplinksync_immich_playback_src( $loop['asset'], UPLINKSYNC_HERO_SHARE_KEY );
			if ( '' !== $src ) {
				$sources[] = $src;
			}
		}
	}

	$default_src = ! empty( $sources ) ? $sources[0] : '';
	$sources_json = wp_json_encode( $sources );

	// Media layer: poster is the LCP image; the decorative video (if we have a
	// source) sits behind it and is revealed by hero.js once it can play.
	$media  = '<div class="uls-hero__media">';
	$media .= sprintf(
		'<img class="uls-hero__poster" src="%1$s" alt="" width="1920" height="1080" fetchpriority="high" decoding="async" />',
		$poster
	);
	if ( '' !== $default_src ) {
		$media .= sprintf(
			'<video class="uls-hero__video" preload="none" muted loop playsinline autoplay aria-hidden="true" tabindex="-1" poster="%1$s" data-hero-sources="%2$s">' .
			'<source src="%3$s" type="video/mp4" /></video>',
			esc_attr( $poster ),
			esc_attr( $sources_json ),
			esc_url( $default_src )
		);
	}
	$media .= '<span class="uls-hero__scrim" aria-hidden="true"></span>';
	$media .= '</div>';

	// Content layer.
	$inner  = '<div class="uls-hero__inner">';
	$heading = sanitize_text_field( $atts['heading'] );
	if ( '' !== $heading ) {
		$inner .= '<' . $heading_tag . ' class="uls-hero__heading">' . esc_html( $heading ) . '</' . $heading_tag . '>';
	}
	$sub = sanitize_text_field( $atts['subheading'] );
	if ( '' !== $sub ) {
		$inner .= '<p class="uls-hero__sub">' . esc_html( $sub ) . '</p>';
	}

	$buttons = '';
	$buttons .= uplinksync_hero_button( $atts['cta_text'], $atts['cta_url'], 'is-primary' );
	$buttons .= uplinksync_hero_button( $atts['cta2_text'], $atts['cta2_url'], 'is-ghost' );
	if ( '' !== $buttons ) {
		$inner .= '<div class="uls-hero__cta">' . $buttons . '</div>';
	}
	$inner .= '</div>';

	return '<section class="uls-hero' . ( $motion ? '' : ' is-static' ) . '" role="region" aria-label="Introduction">' . $media . $inner . '</section>';
}
add_shortcode( 'hero_loop', 'uplinksync_hero_loop_shortcode' );

/**
 * Render one hero button, or '' when the label is empty.
 *
 * @param string $text  Button label.
 * @param string $url   Destination.
 * @param string $class Variant class.
 * @return string
 */
function uplinksync_hero_button( $text, $url, $class ) {
	$text = sanitize_text_field( (string) $text );
	if ( '' === $text ) {
		return '';
	}
	$url = esc_url( '' !== trim( (string) $url ) ? $url : '#' );
	return sprintf(
		'<a class="uls-hero__btn %1$s" href="%2$s">%3$s</a>',
		esc_attr( $class ),
		$url,
		esc_html( $text )
	);
}
