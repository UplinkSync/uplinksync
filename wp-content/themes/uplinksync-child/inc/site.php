<?php
/**
 * Richer Quiet Light — shared building blocks for the five file-driven pages
 * (templates/front-page.html, page-services.html, page-drone-services.html, page-about.html,
 * page-contact.html) and the site-wide header/footer.
 *
 * The page templates render theme patterns (patterns/site-*.php) instead of DB page content,
 * so production, staging and an empty-database preview all render the same markup. The
 * templates mark their <main> with the `uls-site` class; uplinksync_site_is_page() keys the
 * asset trimming off that marker in the RESOLVED template, so a DB-saved Site Editor override
 * (which lacks the marker) keeps its legacy stylesheets instead of rendering unstyled.
 *
 * @package uplinksync-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * True when the template being rendered is one of the Richer Quiet Light templates.
 *
 * Block themes resolve and render the template before wp_head, so the resolved content is
 * available when styles are enqueued.
 *
 * @return bool
 */
function uplinksync_site_is_page() {
	global $_wp_current_template_content;
	return is_string( $_wp_current_template_content ) && false !== strpos( $_wp_current_template_content, 'uls-site' );
}

/**
 * 24px stroke icons (decorative, aria-hidden).
 *
 * @param string $name Icon key.
 * @param int    $size Pixel size.
 * @return string SVG markup.
 */
function uplinksync_site_icon( $name, $size = 22 ) {
	static $paths = array(
		'shield'  => '<path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6l8-3z"/><path d="M9 12l2 2 4-4"/>',
		'drone'   => '<circle cx="5" cy="5" r="2.5"/><circle cx="19" cy="5" r="2.5"/><circle cx="5" cy="19" r="2.5"/><circle cx="19" cy="19" r="2.5"/><path d="M7 7l3 3M17 7l-3 3M7 17l3-3M17 17l-3-3"/><rect x="9.5" y="9.5" width="5" height="5" rx="1"/>',
		'badge'   => '<circle cx="12" cy="9" r="6"/><path d="M8.5 14L7 22l5-3 5 3-1.5-8"/>',
		'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'monitor' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/><path d="M8 10l2 2 4-4"/>',
		'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
		'globe'   => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z"/>',
		'flow'    => '<rect x="3" y="3" width="6" height="6" rx="1.5"/><rect x="15" y="15" width="6" height="6" rx="1.5"/><path d="M9 6h4a3 3 0 013 3v6"/>',
		'server'  => '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5h.01M7 16.5h.01"/>',
		'lock'    => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/>',
		'check'   => '<path d="M5 12l4 4 10-10"/>',
		'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'card'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/>',
		'image'   => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 9"/>',
		'cal'     => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>',
		'print'   => '<path d="M7 9V3h10v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M7 14h10v7H7z"/>',
		'phone'   => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z"/>',
		'map'     => '<path d="M9 4L3 6v14l6-2 6 2 6-2V4l-6 2-6-2z"/><path d="M9 4v14M15 6v14"/>',
		'home'    => '<path d="M3 11l9-7 9 7v9a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1z"/>',
		'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
		'spark'   => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5L18 18M6 18l2.5-2.5M15.5 8.5L18 6"/>',
		'orbit'   => '<circle cx="12" cy="12" r="3"/><ellipse cx="12" cy="12" rx="10" ry="4.5"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return sprintf(
		'<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
		(int) $size,
		$paths[ $name ]
	);
}

/**
 * Arrow used after text links.
 *
 * @return string
 */
function uplinksync_site_arrow() {
	return '<span class="arr">' . uplinksync_site_icon( 'arrow', 18 ) . '</span>';
}

/**
 * Responsive <img> for an image built by tools/imagery/build_site_images.py
 * (assets/images/site/{stem}-{width}.webp). Width/height come from the largest file, so the
 * browser reserves the box before load (no layout shift).
 *
 * @param string $stem   File stem, e.g. 'uplinksync-services-cybersecurity'.
 * @param array  $widths Available widths, ascending.
 * @param string $alt    Alt text ('' = decorative).
 * @param string $sizes  sizes attribute.
 * @param bool   $lcp    True for the above-the-fold hero: eager + fetchpriority=high.
 * @return string
 */
function uplinksync_site_img( $stem, array $widths, $alt, $sizes = '100vw', $lcp = false ) {
	$dir    = get_stylesheet_directory() . '/assets/images/site/';
	$uri    = get_stylesheet_directory_uri() . '/assets/images/site/';
	$srcset = array();
	foreach ( $widths as $w ) {
		$srcset[] = esc_url( $uri . $stem . '-' . (int) $w . '.webp' ) . ' ' . (int) $w . 'w';
	}
	$largest = end( $widths );
	$dims    = @getimagesize( $dir . $stem . '-' . (int) $largest . '.webp' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- missing file = no dims.
	$mid     = $widths[ (int) floor( ( count( $widths ) - 1 ) / 2 ) ];
	return sprintf(
		'<img src="%1$s" srcset="%2$s" sizes="%3$s"%4$s alt="%5$s" decoding="async"%6$s>',
		esc_url( $uri . $stem . '-' . (int) $mid . '.webp' ),
		implode( ', ', $srcset ),
		esc_attr( $sizes ),
		$dims ? sprintf( ' width="%d" height="%d"', (int) $dims[0], (int) $dims[1] ) : '',
		esc_attr( $alt ),
		$lcp ? ' fetchpriority="high"' : ' loading="lazy"'
	);
}

/** Width sets matching tools/imagery/build_site_images.py. */
const UPLINKSYNC_SITE_WIDE  = array( 768, 1280, 1916 );
const UPLINKSYNC_SITE_BAND  = array( 768, 1280, 1920 );
const UPLINKSYNC_SITE_CARD  = array( 480, 768, 1024, 1600 );
const UPLINKSYNC_SITE_CARD9 = array( 480, 768, 900 );
const UPLINKSYNC_SITE_AIR   = array( 480, 800, 1024 );

/**
 * Hero/LCP image preload, printed in <head> for the five templates (one per page).
 *
 * @param string $stem   Image stem.
 * @param array  $widths Widths.
 */
function uplinksync_site_preload_hero( $stem, array $widths ) {
	$uri    = get_stylesheet_directory_uri() . '/assets/images/site/';
	$srcset = array();
	foreach ( $widths as $w ) {
		$srcset[] = $uri . $stem . '-' . (int) $w . '.webp ' . (int) $w . 'w';
	}
	printf(
		'<link rel="preload" as="image" type="image/webp" imagesrcset="%s" imagesizes="100vw" fetchpriority="high">' . "\n",
		esc_attr( implode( ', ', $srcset ) )
	);
}

/**
 * Which hero image each Richer Quiet Light page uses (for the <head> preload).
 *
 * @return array|null [stem, widths] or null.
 */
function uplinksync_site_hero_for_request() {
	if ( is_front_page() || ( function_exists( 'uplinksync_child_is_singular_slug' ) && uplinksync_child_is_singular_slug( 'about' ) ) ) {
		return array( 'uplinksync-hero-town-dusk', UPLINKSYNC_SITE_WIDE );
	}
	if ( function_exists( 'uplinksync_child_is_singular_slug' ) && uplinksync_child_is_singular_slug( array( 'services', 'drone-services', 'contact' ) ) ) {
		return array( 'uplinksync-hero-aerial-sunset', UPLINKSYNC_SITE_WIDE );
	}
	return null;
}

/**
 * The "proof band" that overlaps the hero.
 *
 * @param array $items [icon, title, line] triples.
 * @return string
 */
function uplinksync_site_proof( array $items ) {
	$cells = '';
	foreach ( $items as $item ) {
		$cells .= sprintf(
			'<li class="proof-item"><span class="ico lt">%s</span><span><b>%s</b><span>%s</span></span></li>',
			uplinksync_site_icon( $item[0] ),
			esc_html( $item[1] ),
			esc_html( $item[2] )
		);
	}
	return '<div class="proof"><div class="wrap"><ul class="proof-card" aria-label="Why UplinkSync">' . $cells . '</ul></div></div>';
}

/**
 * Standard proof items (FAA / A+ / insured / reply promise).
 *
 * @return array
 */
function uplinksync_site_proof_std() {
	return array(
		array( 'drone', 'FAA Part 107', 'Certified commercial drone pilot' ),
		array( 'badge', 'CompTIA A+', 'Industry-certified IT support' ),
		array( 'shield', 'Fully insured', 'Certificate of insurance on request' ),
		array( 'clock', 'One business day', 'Reply promise, by a real person' ),
	);
}

/**
 * Glass panel in the hero.
 *
 * @param string $title Panel heading.
 * @param array  $items [icon, bold, line].
 * @return string
 */
function uplinksync_site_glass( $title, array $items ) {
	$li = '';
	foreach ( $items as $item ) {
		$li .= sprintf(
			'<li><span class="ico">%s</span><span><b>%s</b><span>%s</span></span></li>',
			uplinksync_site_icon( $item[0], 20 ),
			esc_html( $item[1] ),
			esc_html( $item[2] )
		);
	}
	return sprintf( '<aside class="glass" aria-label="%1$s"><h2>%1$s</h2><ul>%2$s</ul></aside>', esc_attr( $title ), $li );
}

/**
 * Image-led service card (whole card is the link).
 *
 * @param array $c Keys: img (stem), widths, icon, title, text, href, alt, tags (array).
 * @return string
 */
function uplinksync_site_card( array $c ) {
	$tags = '';
	if ( ! empty( $c['tags'] ) ) {
		$tags = '<ul class="tags">';
		foreach ( $c['tags'] as $t ) {
			$tags .= '<li>' . esc_html( $t ) . '</li>';
		}
		$tags .= '</ul>';
	}
	return sprintf(
		'<a class="card" href="%1$s"><span class="media">%2$s</span><span class="body"><span class="badge">%3$s</span><h3>%4$s</h3><p>%5$s</p>%6$s<span class="more">%7$s %8$s</span></span></a>',
		esc_url( $c['href'] ),
		uplinksync_site_img( $c['img'], $c['widths'], $c['alt'], $c['sizes'] ?? '(min-width: 900px) 33vw, (min-width: 640px) 50vw, 100vw' ),
		uplinksync_site_icon( $c['icon'] ),
		esc_html( $c['title'] ),
		esc_html( $c['text'] ),
		$tags,
		esc_html( $c['more'] ?? 'Learn more' ),
		uplinksync_site_arrow()
	);
}

/**
 * Numbered steps.
 *
 * @param string $eyebrow Eyebrow.
 * @param string $title   Section heading.
 * @param string $sub     Intro line.
 * @param array  $steps   [title, text, when].
 * @param string $id      Heading id.
 * @return string
 */
function uplinksync_site_steps( $eyebrow, $title, $sub, array $steps, $id ) {
	$li = '';
	foreach ( $steps as $n => $s ) {
		$li .= sprintf(
			'<li class="reveal"><span class="num" aria-hidden="true">%d</span><h3>%s</h3><p>%s</p>%s</li>',
			$n + 1,
			esc_html( $s[0] ),
			esc_html( $s[1] ),
			! empty( $s[2] ) ? '<span class="when">' . esc_html( $s[2] ) . '</span>' : ''
		);
	}
	return sprintf(
		'<section class="sec" aria-labelledby="%1$s"><div class="wrap"><div class="sec-head"><p class="eyebrow">%2$s</p><h2 id="%1$s">%3$s</h2><p>%4$s</p></div><ol class="steps">%5$s</ol></div></section>',
		esc_attr( $id ),
		esc_html( $eyebrow ),
		esc_html( $title ),
		esc_html( $sub ),
		$li
	);
}

/** Booking link (the booking-CTAs mu-plugin adopts it into the on-page dialog). */
const UPLINKSYNC_SITE_BOOK_IT  = 'https://book.uplinksync.com/team/uplinksync/it-consult';
const UPLINKSYNC_SITE_BOOK_UAV = 'https://book.uplinksync.com/team/uplinksync/uav-consult';
/** "Get a quote" opens the estimator modal (Snippet 84 intercepts a[href*="request-quote"]). */
const UPLINKSYNC_SITE_QUOTE = '/request-quote/';

/**
 * Photo CTA band.
 *
 * @param string $title   Heading (plain text).
 * @param string $lede    Lede (may contain the email link; trusted markup from this file).
 * @param string $book    Booking URL.
 * @return string
 */
function uplinksync_site_cta( $title, $lede, $book = UPLINKSYNC_SITE_BOOK_IT ) {
	return sprintf(
		'<section class="cta dark" aria-labelledby="cta-h">%1$s<div class="wrap"><div><h2 id="cta-h">%2$s</h2><p>%3$s</p><p class="promise"><span class="dot" aria-hidden="true"></span>Reply within one business day · Fixed plans · No obligation</p></div><div class="acts"><a class="btn btn-dk btn-lg" href="%4$s">Get a quote</a><a class="tlink" href="%5$s">Prefer to talk? Book a call %6$s</a></div></div></section>',
		uplinksync_site_img( 'uplinksync-cta-band', UPLINKSYNC_SITE_BAND, '' ),
		esc_html( $title ),
		wp_kses_post( $lede ),
		esc_url( UPLINKSYNC_SITE_QUOTE ),
		esc_url( $book ),
		uplinksync_site_arrow()
	);
}

/** Email link used in ledes. */
function uplinksync_site_email_link() {
	return '<a href="mailto:contact@uplinksync.com">contact@uplinksync.com</a>';
}

/**
 * Client hub band (tint).
 *
 * @return string
 */
function uplinksync_site_hub() {
	$tiles = array(
		array( 'card', 'Pay an invoice', 'https://billing.uplinksync.com/client/login' ),
		array( 'image', 'Your photos & video', 'https://media.uplinksync.com' ),
		array( 'cal', 'Book time with us', UPLINKSYNC_SITE_BOOK_IT ),
		array( 'print', 'Print shop', '/prints/' ),
	);
	$html = '';
	foreach ( $tiles as $t ) {
		$html .= sprintf( '<a class="hub-tile" href="%s"><span class="ico lt">%s</span>%s</a>', esc_url( $t[2] ), uplinksync_site_icon( $t[0], 20 ), esc_html( $t[1] ) );
	}
	return '<section class="hub-band" aria-labelledby="hub-h"><div class="wrap"><div><p class="eyebrow">Client hub</p><h2 id="hub-h">Already a client?</h2><p class="muted">Pay, download your files or book time in one place.</p></div><div class="hub-grid">' . $html . '</div></div></section>';
}

/**
 * Real, permitted client quotes. Nothing renders until the owner adds at least one:
 *   wp option update uplinksync_reviews '[{"quote":"...","name":"Sam","role":"Owner, Example Co"}]' --format=json
 * Never ship placeholder copy (brand_guardian finding 3).
 *
 * @param string $title Section heading.
 * @return string '' when there are no reviews.
 */
function uplinksync_site_reviews( $title = 'What clients say' ) {
	$reviews = function_exists( 'get_option' ) ? get_option( 'uplinksync_reviews', array() ) : array();
	$reviews = is_array( $reviews ) ? $reviews : array();
	$cards   = '';
	foreach ( array_slice( $reviews, 0, 3 ) as $r ) {
		if ( ! is_array( $r ) || empty( $r['quote'] ) || empty( $r['name'] ) ) {
			continue;
		}
		$cards .= sprintf(
			'<figure class="review"><blockquote>&ldquo;%s&rdquo;</blockquote><figcaption><b>%s</b><span>%s</span></figcaption></figure>',
			esc_html( $r['quote'] ),
			esc_html( $r['name'] ),
			esc_html( $r['role'] ?? '' )
		);
	}
	if ( '' === $cards ) {
		return '';
	}
	return '<section class="sec" aria-labelledby="rv-h"><div class="wrap"><div class="sec-head"><p class="eyebrow">In their words</p><h2 id="rv-h">' . esc_html( $title ) . '</h2></div><div class="reviews">' . $cards . '</div></div></section>';
}

/**
 * Published phone as a tel: link ('' when UPLINKSYNC_PHONE is empty).
 *
 * @param string $class Link class.
 * @return string
 */
function uplinksync_site_phone_link( $class = '' ) {
	$display = function_exists( 'uplinksync_phone' ) ? uplinksync_phone() : '';
	$e164    = function_exists( 'uplinksync_phone_e164' ) ? uplinksync_phone_e164() : '';
	if ( '' === $display || '' === $e164 ) {
		return '';
	}
	return sprintf( '<a%s href="tel:%s">%s</a>', $class ? ' class="' . esc_attr( $class ) . '"' : '', esc_attr( $e164 ), esc_html( $display ) );
}

/* -------------------------------------------------------------------------------------------
 * Location slots (MR !180, feat/location-mode-switch). When the location switch is loaded,
 * the hero eyebrow/H1, the intro add-on and the "Where we work" block come from it, so a flip
 * to Utah mode changes the visible copy too. Without it (or in neutral mode) the output is
 * byte-identical to the hard-coded neutral markup.
 * ----------------------------------------------------------------------------------------- */

/**
 * Hero text for a location slot, as HTML.
 *
 * @param string $slot          Slot key (eyebrow, home_h1, services_h1, drone_h1, about_h1, contact_h1).
 * @param string $fallback_html Neutral markup used today (may contain one <em> accent).
 * @return string
 */
function uplinksync_site_slot( $slot, $fallback_html ) {
	$text = function_exists( 'uplinksync_location_line' ) ? (string) uplinksync_location_line( $slot ) : '';
	$neutral = html_entity_decode( uplinksync_site_strip_tags( $fallback_html ), ENT_QUOTES, 'UTF-8' );
	if ( '' === $text || $text === $neutral ) {
		return $fallback_html;
	}
	// Keep the display-size accent: the clause after " — " goes in <em> when the neutral copy had one.
	$pos = strpos( $text, ' — ' );
	if ( false !== strpos( $fallback_html, '<em>' ) && false !== $pos ) {
		return esc_html( substr( $text, 0, $pos + 5 ) ) . '<em>' . esc_html( substr( $text, $pos + 5 ) ) . '</em>';
	}
	return esc_html( $text );
}

/**
 * The lede plus the location intro add-on ('' in neutral mode, so neutral output is unchanged).
 *
 * @param string $lede_html Lede markup.
 * @return string
 */
function uplinksync_site_lede( $lede_html ) {
	$intro = function_exists( 'uplinksync_location_line' ) ? trim( (string) uplinksync_location_line( 'intro' ) ) : '';
	return '' === $intro ? $lede_html : $lede_html . ' ' . esc_html( $intro );
}

/**
 * "Where we work" block from the location switch, wrapped for the page grid. Renders
 * nothing at all in neutral mode or when the switch is not installed.
 *
 * @return string
 */
function uplinksync_site_service_area() {
	$html = function_exists( 'uplinksync_location_service_area_html' ) ? (string) uplinksync_location_service_area_html() : '';
	return '' === trim( $html ) ? '' : '<div class="sec flush"><div class="wrap">' . $html . '</div></div>';
}

/**
 * strip_tags that works without WordPress loaded (tests).
 *
 * @param string $s Markup.
 * @return string
 */
function uplinksync_site_strip_tags( $s ) {
	return function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $s ) : strip_tags( $s );
}

/* -------------------------------------------------------------------------------------------
 * Assets
 * ----------------------------------------------------------------------------------------- */

/**
 * On the Richer Quiet Light templates, drop the legacy presentation layers their markup
 * replaces (brand/brand-blocks with !important DM Sans headings, imagery-system, the GSAP +
 * ScrollTrigger motion layer, split-horizon/hero, the old estimate range modal and quote-form
 * CSS, the drone gallery CSS) and load the tiny site.js instead. Every other page keeps them.
 */
function uplinksync_site_assets() {
	if ( ! uplinksync_site_is_page() ) {
		return;
	}
	$styles  = array( 'uplinksync-brand', 'uplinksync-brand-blocks', 'uplinksync-imagery-system', 'uplinksync-split-horizon', 'uplinksync-motion', 'uplinksync-hero', 'uplinksync-estimate-book', 'uplinksync-quote-form', 'uplinksync-drone-gallery', 'uplinksync-immich-embed' );
	$scripts = array( 'uplinksync-hero', 'uplinksync-split-horizon', 'uplinksync-motion', 'gsap-scrolltrigger', 'gsap', 'uplinksync-estimate-book', 'uplinksync-air-hero' );
	foreach ( $styles as $handle ) {
		wp_dequeue_style( $handle );
	}
	foreach ( $scripts as $handle ) {
		wp_dequeue_script( $handle );
	}
	wp_enqueue_script(
		'uplinksync-site',
		get_stylesheet_directory_uri() . '/assets/js/site.js',
		array(),
		uplinksync_child_asset_ver( 'assets/js/site.js' ),
		array( 'strategy' => 'defer', 'in_footer' => true )
	);
}
add_action( 'wp_enqueue_scripts', 'uplinksync_site_assets', 99 );

/** Preload the hero image on the five pages (the LCP element). */
function uplinksync_site_head() {
	if ( ! uplinksync_site_is_page() ) {
		return;
	}
	$hero = uplinksync_site_hero_for_request();
	if ( $hero ) {
		uplinksync_site_preload_hero( $hero[0], $hero[1] );
	}
}
add_action( 'wp_head', 'uplinksync_site_head', 2 );

/* -------------------------------------------------------------------------------------------
 * Header: aria-current on the active nav item + the published phone (if any)
 * ----------------------------------------------------------------------------------------- */

/**
 * Normalised path of a URL or of the current request.
 *
 * @param string $url URL or path.
 * @return string e.g. '/services/'.
 */
function uplinksync_site_path( $url ) {
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	return '/' . trim( $path, '/' ) . ( '' === trim( $path, '/' ) ? '' : '/' );
}

/**
 * Mark the static header nav link for the current section with aria-current="page"
 * (core only does this for links to post objects).
 *
 * @param string $content Rendered block.
 * @param array  $block   Parsed block.
 * @return string
 */
function uplinksync_site_nav_current( $content, $block ) {
	if ( is_admin() || empty( $block['attrs']['url'] ) || false !== strpos( $content, 'aria-current' ) ) {
		return $content;
	}
	$link = uplinksync_site_path( $block['attrs']['url'] );
	$here = uplinksync_site_path( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- compared only.
	if ( '/' === $link || ( $here !== $link && 0 !== strpos( $here, $link ) ) ) {
		return $content;
	}
	return preg_replace( '#<a\b#', '<a aria-current="page"', $content, 1 );
}
add_filter( 'render_block_core/navigation-link', 'uplinksync_site_nav_current', 10, 2 );

/**
 * Put the published phone (UPLINKSYNC_PHONE) beside the header "Get a quote" button.
 *
 * @param string $content Rendered block.
 * @param array  $block   Parsed block.
 * @return string
 */
function uplinksync_site_header_phone( $content, $block ) {
	$class = isset( $block['attrs']['className'] ) ? (string) $block['attrs']['className'] : '';
	if ( false === strpos( $class, 'uls-ql-header-cta' ) ) {
		return $content;
	}
	$phone = uplinksync_site_phone_link( 'uls-ql-phone' );
	return '' === $phone ? $content : $phone . $content;
}
add_filter( 'render_block_core/buttons', 'uplinksync_site_header_phone', 10, 2 );

/* -------------------------------------------------------------------------------------------
 * WooCommerce mini-cart: WooCommerce auto-inserts it after core/navigation in every header
 * (Block Hooks API). Keep it only where it is useful — shop, product, cart, checkout,
 * account and the /prints/ store landing — so brochure pages ship no cart icon, no empty
 * drawer (axe aria-hidden-focus) and no mini-cart CSS/JS.
 * ----------------------------------------------------------------------------------------- */

/**
 * True on store contexts.
 *
 * @return bool
 */
function uplinksync_site_is_store() {
	if ( function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) ) {
		return true;
	}
	return function_exists( 'is_page' ) && is_page( 'prints' );
}

/**
 * Drop the hooked mini-cart from the header outside the store.
 *
 * @param array $hooked Hooked block types.
 * @return array
 */
function uplinksync_site_hooked_blocks( $hooked ) {
	if ( is_admin() || uplinksync_site_is_store() || ! is_array( $hooked ) ) {
		return $hooked;
	}
	return array_values( array_diff( $hooked, array( 'woocommerce/mini-cart' ) ) );
}
add_filter( 'hooked_block_types', 'uplinksync_site_hooked_blocks', PHP_INT_MAX );

/**
 * A mini-cart block saved directly into a (DB) header is removed the same way.
 *
 * @param string $content Rendered block.
 * @return string
 */
function uplinksync_site_mini_cart( $content ) {
	return ( is_admin() || uplinksync_site_is_store() ) ? $content : '';
}
add_filter( 'render_block_woocommerce/mini-cart', 'uplinksync_site_mini_cart', 10 );

/**
 * Mobile menu extras: "Get a quote" and the published phone as the last menu items. They are
 * shown only inside the opened overlay menu (CSS), because ≥1024 px the header shows both.
 *
 * @param string $content Rendered navigation block.
 * @param array  $block   Parsed block.
 * @return string
 */
function uplinksync_site_menu_extras( $content, $block ) {
	$class = isset( $block['attrs']['className'] ) ? (string) $block['attrs']['className'] : '';
	if ( false === strpos( $class, 'uls-ql-nav' ) || false !== strpos( $content, 'uls-ql-menu-extra' ) ) {
		return $content;
	}
	$items = '<li class="wp-block-navigation-item uls-ql-menu-extra"><a class="wp-block-navigation-item__content" href="' . esc_url( UPLINKSYNC_SITE_QUOTE ) . '">Get a quote</a></li>';
	$phone = uplinksync_site_phone_link( 'wp-block-navigation-item__content' );
	if ( '' !== $phone ) {
		$items .= '<li class="wp-block-navigation-item uls-ql-menu-extra">' . $phone . '</li>';
	}
	$pos = strrpos( $content, '</ul>' );
	return false === $pos ? $content : substr_replace( $content, $items . '</ul>', $pos, 5 );
}
add_filter( 'render_block_core/navigation', 'uplinksync_site_menu_extras', 10, 2 );

/**
 * /request-quote/ (estimator inline fallback page): no GSAP/motion layer there (nothing
 * animates; it only logged "target not found"). Its markup and CSS belong to the estimator.
 */
function uplinksync_site_quote_page_assets() {
	if ( ! function_exists( 'is_page' ) || ! is_page( 'request-quote' ) ) {
		return;
	}
	foreach ( array( 'uplinksync-motion', 'gsap-scrolltrigger', 'gsap' ) as $h ) {
		wp_dequeue_script( $h );
	}
	wp_dequeue_style( 'uplinksync-motion' );
}
add_action( 'wp_enqueue_scripts', 'uplinksync_site_quote_page_assets', 99 );

/* -------------------------------------------------------------------------------------------
 * Place-free share/schema images (QA minor 1). Rank Math's stored home image points at a
 * place-named still (…/aerial-stills/idaho-falls-full.jpg). On the five Richer Quiet Light
 * pages, any image URL whose file name carries a place slug is replaced by the page hero.
 * ----------------------------------------------------------------------------------------- */

/**
 * Place-free replacement for a place-named image URL ('' = leave as is).
 *
 * @param string $url Image URL.
 * @return string
 */
function uplinksync_site_neutral_image( $url ) {
	if ( ! is_string( $url ) || ! preg_match( '#/[^/?]*(idaho-falls|kaysville|palisades|pocatello|saratoga-springs|soda-springs)[^/?]*\.(jpe?g|png|webp)#i', $url ) ) {
		return '';
	}
	$hero = uplinksync_site_hero_for_request();
	$stem = $hero ? $hero[0] : 'uplinksync-hero-town-dusk';
	return get_stylesheet_directory_uri() . '/assets/images/site/' . $stem . '-1280.webp';
}

/**
 * JSON-LD walker.
 *
 * @param mixed $value Graph or node.
 * @return mixed
 */
function uplinksync_site_schema_images( $value ) {
	if ( ! uplinksync_site_is_page() ) {
		return $value;
	}
	if ( is_string( $value ) ) {
		$new = uplinksync_site_neutral_image( $value );
		return '' === $new ? $value : $new;
	}
	if ( is_array( $value ) ) {
		foreach ( $value as $k => $v ) {
			$value[ $k ] = uplinksync_site_schema_images( $v );
		}
	}
	return $value;
}
add_filter( 'rank_math/json_ld', 'uplinksync_site_schema_images', 30 );

/**
 * og:image / twitter:image.
 *
 * @param string $url Image URL.
 * @return string
 */
function uplinksync_site_og_image( $url ) {
	if ( ! uplinksync_site_is_page() ) {
		return $url;
	}
	$new = uplinksync_site_neutral_image( $url );
	return '' === $new ? $url : $new;
}
add_filter( 'rank_math/opengraph/facebook/og_image', 'uplinksync_site_og_image', 30 );
add_filter( 'rank_math/opengraph/twitter/twitter_image', 'uplinksync_site_og_image', 30 );
