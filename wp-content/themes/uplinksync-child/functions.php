<?php
/**
 * UplinkSync child theme bootstrap.
 * Loads parent theme styles, then the brand token/override layer from visual-system.md (***-21/***-46).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ***-42: provision the CF7 quote forms + self-resolving shortcodes in code
 * (see inc/quote-form-seed.php). Keeps the form definitions under version
 * control instead of as hand-built wp-admin rows, and removes the dependency
 * on a human dashboard step to create them.
 */
require_once get_stylesheet_directory() . '/inc/quote-form-seed.php';

/**
 * ***-186: register the [immich_share] shortcode that embeds curated Immich
 * public share albums from media.uplinksync.com. Enforces owner constraints in
 * code (share links only, host-locked, no NAS reach) so publishing a graded
 * Landscape loop is a one-line content edit once ***-160 curation is done.
 */
require_once get_stylesheet_directory() . '/inc/immich-embed.php';

/**
 * ***-391 / ***-183 (plan v2.0): register the [hero_loop] shortcode + its
 * priority-21 conditional asset enqueue. Reuses the anonymous Immich playback
 * plumbing from immich-embed.php (loaded just above) to stream the curated,
 * graded Landscape hero loops from media.uplinksync.com. Renders nothing until
 * [hero_loop] is placed in content, so loading this file cannot change what the
 * homepage renders on its own.
 */
require_once get_stylesheet_directory() . '/inc/hero-loop.php';

/**
 * ***-99: restore correct asset resolution under a child theme.
 *
 * The parent theme (hostinger-ai-theme) was never written for child themes.
 * It builds its own asset paths with the *stylesheet* helpers, which resolve
 * to the ACTIVE theme — i.e. this child — as soon as a child theme is active.
 * That points the parent at uplinksync-child/assets/, where its files do not
 * exist, so the compiled stylesheet and JS bundle 301/404 and the live site
 * renders unstyled.
 *
 * There are two independent code paths in the parent, and each needs its own
 * fix. We cannot edit the parent — it is vendored and Hostinger updates it —
 * so both fixes live here in the child.
 *
 * 1. CONSTANTS. functions.php and includes/Admin/Assets.php build admin/editor
 *    asset URLs from HOSTINGER_AI_WEBSITES_ASSETS_URL / _THEME_PATH. The parent
 *    guards each define with `if ( ! defined( ... ) )`, and WordPress loads the
 *    child's functions.php in full BEFORE the parent's, so the child can claim
 *    those constants first and pin them to the parent (template) directory.
 */
if ( ! defined( 'HOSTINGER_AI_WEBSITES_THEME_PATH' ) ) {
	define( 'HOSTINGER_AI_WEBSITES_THEME_PATH', get_template_directory() );
}
if ( ! defined( 'HOSTINGER_AI_WEBSITES_ASSETS_URL' ) ) {
	define( 'HOSTINGER_AI_WEBSITES_ASSETS_URL', get_template_directory_uri() . '/assets' );
}

/**
 * 2. RAW HELPER CALLS. The parent's *frontend* assets are the ones that broke
 *    the public site, and they do NOT go through the constants above. They are
 *    enqueued with get_stylesheet_directory_uri() directly:
 *      - includes/Assets.php        -> assets/css/style.min.css (the 16 KB sheet)
 *      - includes/Assets.php        -> assets/js/front-scripts.min.js
 *      - includes/Elementor/WidgetManager.php uses get_template_directory_uri()
 *        (already correct — left untouched by this filter).
 *    Defining the constants alone therefore does NOT fix the homepage; that is
 *    why this filter exists in addition to the constants.
 *
 *    This filter is file-existence driven and self-correcting: for any style or
 *    script whose URL points inside THIS theme's directory, if the file is not
 *    present here but IS present in the parent theme, it is a parent-owned asset
 *    mis-resolved to the child dir, so we reroute the URL to the parent. The
 *    child's own assets (tokens.css, brand.css, quote-form.*, drone-gallery.css)
 *    exist here, so they are never touched. Any future parent asset added to a
 *    raw stylesheet-helper path is covered automatically.
 */
function uplinksync_child_reroute_parent_assets( $src ) {
	if ( empty( $src ) || ! is_string( $src ) ) {
		return $src;
	}

	$child_uri   = get_stylesheet_directory_uri();
	$parent_uri  = get_template_directory_uri();

	// Nothing to do when no child theme is active (parent === stylesheet).
	if ( $child_uri === $parent_uri ) {
		return $src;
	}

	// Only act on URLs that resolve inside this (child) theme directory.
	$src_path = strtok( $src, '?' );
	if ( 0 !== strpos( $src_path, $child_uri . '/' ) ) {
		return $src;
	}

	$relative    = substr( $src_path, strlen( $child_uri ) );
	$child_file  = get_stylesheet_directory() . $relative;
	$parent_file = get_template_directory() . $relative;

	// Ours (exists in child) -> leave alone. Parent-owned (only in parent) -> reroute.
	if ( file_exists( $child_file ) || ! file_exists( $parent_file ) ) {
		return $src;
	}

	$query = ( strlen( $src ) > strlen( $src_path ) ) ? substr( $src, strlen( $src_path ) ) : '';
	return $parent_uri . $relative . $query;
}
add_filter( 'style_loader_src', 'uplinksync_child_reroute_parent_assets', 5 );
add_filter( 'script_loader_src', 'uplinksync_child_reroute_parent_assets', 5 );

/**
 * ***-102 (cache-bust): version a child-owned asset by its file mtime.
 *
 * WHY: every child enqueue previously passed wp_get_theme()->get( 'Version' ),
 * a STATIC '1.0.0' that never changes when a CSS/JS file's *content* changes.
 * The site sits behind Cloudflare, whose edge cache key includes the ?ver=
 * query string. With a frozen ?ver=1.0.0 the key never rotated, so after a
 * content change (e.g. the MR !22 brand-layer redesign) the edge kept serving
 * the STALE copy under Cache-Control: max-age=604800 — measured live as a
 * cf-cache-status: HIT of the pre-redesign file hours after deploy. Visitors
 * saw none of the fix. Keying the version on filemtime() rotates ?ver= the
 * moment a file's bytes change, rotating the edge cache key with it, so a
 * merge is visible immediately and future edits self-bust. Falls back to the
 * theme version if the file is somehow unreadable.
 *
 * @param string $relative Path under the child theme root, e.g. 'assets/css/brand.css'.
 * @return string Version string safe for wp_enqueue_style()/_script().
 */
function uplinksync_child_asset_ver( $relative ) {
	$path = get_stylesheet_directory() . '/' . ltrim( $relative, '/' );
	$mtime = @filemtime( $path );
	return $mtime ? (string) $mtime : (string) wp_get_theme()->get( 'Version' );
}

function uplinksync_child_enqueue_assets() {
	/**
	 * ***-192: parent is now Twenty Twenty-Five (a block theme), whose
	 * style.css is a metadata stub — WordPress auto-loads TT5's presentation
	 * from its theme.json, so there is no compiled parent stylesheet to enqueue
	 * as a dependency. The former `hostinger-ai-theme-style` handle (and its
	 * 16KB style.min.css) is retired with the flip. The brand layer below now
	 * anchors on the WordPress-core block styles + this theme's theme.json.
	 */
	wp_enqueue_style(
		'uplinksync-tokens',
		get_stylesheet_directory_uri() . '/assets/css/tokens.css',
		array(),
		uplinksync_child_asset_ver( 'assets/css/tokens.css' )
	);

	wp_enqueue_style(
		'uplinksync-brand',
		get_stylesheet_directory_uri() . '/assets/css/brand.css',
		array( 'uplinksync-tokens' ),
		uplinksync_child_asset_ver( 'assets/css/brand.css' )
	);

	/**
	 * ***-102: bind the brand layer to the Gutenberg block markup the parent
	 * theme actually emits. brand.css targets `uls-*` utility classes intended
	 * for an Elementor build; the live homepage is a block-theme page, so those
	 * classes never appear and the section/card/button/hero/footer styling is
	 * orphaned. brand-blocks.css re-expresses those visual-system.md rules against
	 * the real block classes (wp-block-button__link, wp-block-cover,
	 * hostinger-ai-service-*, the footer template part). Loaded last so it wins.
	 */
	wp_enqueue_style(
		'uplinksync-brand-blocks',
		get_stylesheet_directory_uri() . '/assets/css/brand-blocks.css',
		array( 'uplinksync-brand' ),
		uplinksync_child_asset_ver( 'assets/css/brand-blocks.css' )
	);

	/**
	 * ***-133: cohesive imagery system. Styles the classes the
	 * uplinksync-imagery-system mu-plugin tags onto front-end <img> elements —
	 * the navy grade for retained IT/server stock and the crop framing for the
	 * unified "ground + air" brand key art that replaced the off-brief stock.
	 * Loaded last so image rules win over the brand-blocks layer.
	 */
	wp_enqueue_style(
		'uplinksync-imagery-system',
		get_stylesheet_directory_uri() . '/assets/css/imagery-system.css',
		array( 'uplinksync-brand-blocks' ),
		uplinksync_child_asset_ver( 'assets/css/imagery-system.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'uplinksync_child_enqueue_assets', 20 );

/**
 * ***-178: WooCommerce store house-brand layer.
 *
 * The site-wide brand layer (brand-blocks.css) deliberately excludes WooCommerce
 * — its button rule is scoped `:not(.wc-block-components-button):not([type="submit"])`
 * and its header states the scope is kept narrow to avoid disturbing shop chrome.
 * The result was that the shop grid, the product single (which renders the classic
 * `woocommerce/legacy-template` markup), and cart/checkout all fell through to the
 * WooCommerce / core-block DEFAULTS — off-brand typography, buttons, price and
 * spacing against an otherwise cinematic-dark, DM Sans, navy-tokened site.
 *
 * woocommerce.css binds the house tokens/components onto both the classic and
 * block Woo markup. It is gated to store contexts and enqueued after
 * brand-blocks.css so it wins the cascade there and loads on no other page.
 * Guarded with function_exists() so the theme is safe if WooCommerce is inactive.
 */
function uplinksync_child_woocommerce_assets() {
	if ( ! function_exists( 'is_woocommerce' ) ) {
		return;
	}
	if ( ! ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) ) {
		return;
	}
	wp_enqueue_style(
		'uplinksync-woocommerce',
		get_stylesheet_directory_uri() . '/assets/css/woocommerce.css',
		array( 'uplinksync-brand-blocks' ),
		uplinksync_child_asset_ver( 'assets/css/woocommerce.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'uplinksync_child_woocommerce_assets', 21 );

/**
 * *** — trim WooCommerce/WooCommerce-Blocks stylesheets off brochure pages.
 *
 * WooCommerce ships several stylesheets that load site-wide by default
 * (`woocommerce-layout`, `woocommerce-smallscreen`, `woocommerce-general`, and
 * the WooCommerce-Blocks `wc-blocks-style` / `woocommerce-blocktheme`). On this
 * site the store is a small part of the surface — the homepage, /services/,
 * /about/, /contact/ and /drone-services/ are brochure pages that render no
 * WooCommerce markup, yet they were still shipping the shop CSS (confirmed live
 * 2026-07-29: wc-blocks-style, woocommerce-layout, -smallscreen, -blocktheme all
 * enqueued on the homepage). This is dead weight and a latent shop-brand-leak
 * surface on pages that never render a product.
 *
 * This runs only on NON-store contexts (the exact inverse of the store gate in
 * uplinksync_child_woocommerce_assets) so cart/checkout/account/shop/product and
 * product-taxonomy archives are untouched. Dequeue is presentation-only and
 * cannot fatal or blank a page — worst case a stray Woo-styled element on a
 * brochure page loses styling, and there are none. Fully reversible: delete this
 * function + hook. Guarded with function_exists() so it no-ops when WooCommerce
 * is inactive. Priority 100 so it runs after WooCommerce's own default-priority
 * enqueues.
 */
function uplinksync_child_trim_brochure_commerce_assets() {
	if ( ! function_exists( 'is_woocommerce' ) ) {
		return;
	}
	// Leave every genuine store context alone.
	if ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) {
		return;
	}
	$brochure_dequeue = array(
		'woocommerce-general',
		'woocommerce-layout',
		'woocommerce-smallscreen',
		'woocommerce-blocktheme',
		'wc-blocks-style',
		'wc-blocks-packages-style',
	);
	foreach ( $brochure_dequeue as $handle ) {
		wp_dequeue_style( $handle );
	}
}
add_action( 'wp_enqueue_scripts', 'uplinksync_child_trim_brochure_commerce_assets', 100 );

/**
 * ***-99: match a singular view by slug across post types.
 *
 * The conditional assets below were originally gated on is_page( 'slug' ), but
 * the live URLs they target are WooCommerce products, not Pages:
 *   /product/drone-services/        -> is_product(), slug "drone-services"
 *   /product/managed-it-services/   -> is_product(), slug "managed-it-services"
 * is_page() is false for products, so those enqueues never fired on the live
 * site. This helper matches the queried object's slug on any singular view
 * (page OR product OR post), so the assets load wherever that content lives.
 *
 * @param string|string[] $slugs One or more post slugs to match.
 * @return bool
 */
function uplinksync_child_is_singular_slug( $slugs ) {
	if ( ! is_singular() ) {
		return false;
	}
	$queried = get_queried_object();
	if ( ! $queried || empty( $queried->post_name ) ) {
		return false;
	}
	return in_array( $queried->post_name, (array) $slugs, true );
}

/**
 * ***-69 / ***-99: drone gallery watermark CSS.
 * Live target is the WooCommerce product /product/drone-services/ (slug
 * "drone-services"). Title tag is fixed per the ***-25 strategy.
 */
function uplinksync_child_drone_gallery_assets() {
	if ( ! uplinksync_child_is_singular_slug( 'drone-services' ) ) {
		return;
	}
	wp_enqueue_style(
		'uplinksync-drone-gallery',
		get_stylesheet_directory_uri() . '/assets/css/drone-gallery.css',
		array( 'uplinksync-brand' ),
		uplinksync_child_asset_ver( 'assets/css/drone-gallery.css' )
	);

	// "See it in motion" poster-over-video overlay. Vanilla, no dependency,
	// deferred in the footer; enhancement-only, so a load failure leaves the
	// native <video poster> + the separate still exactly as they render today.
	wp_enqueue_script(
		'uplinksync-air-hero',
		get_stylesheet_directory_uri() . '/assets/js/air-hero.js',
		array(),
		uplinksync_child_asset_ver( 'assets/js/air-hero.js' ),
		array( 'strategy' => 'defer', 'in_footer' => true )
	);
}
add_action( 'wp_enqueue_scripts', 'uplinksync_child_drone_gallery_assets', 21 );

/**
 * *** #4: Estimate-as-modal + cal.com pop-up booking. Loads where the estimator
 * lives — the /services/ and /contact/ pages. The JS is DOM-only (moves the
 * estimator into an accessible modal and adds a "Book a time" button that carries
 * data-cal-link/data-cal-prefill); the cal.com pop-up itself is opened by the
 * booking-CTAs mu-plugin runtime, which also loads on estimator pages. Enhancement
 * only — without JS the estimator renders inline exactly as before.
 */
function uplinksync_child_estimate_book_assets() {
	if ( ! uplinksync_child_is_singular_slug( array( 'services', 'contact' ) ) ) {
		return;
	}
	wp_enqueue_style(
		'uplinksync-estimate-book',
		get_stylesheet_directory_uri() . '/assets/css/estimate-book.css',
		array( 'uplinksync-brand' ),
		uplinksync_child_asset_ver( 'assets/css/estimate-book.css' )
	);
	wp_enqueue_script(
		'uplinksync-estimate-book',
		get_stylesheet_directory_uri() . '/assets/js/estimate-book.js',
		array(),
		uplinksync_child_asset_ver( 'assets/js/estimate-book.js' ),
		array( 'strategy' => 'defer', 'in_footer' => true )
	);
}
add_action( 'wp_enqueue_scripts', 'uplinksync_child_estimate_book_assets', 21 );

function uplinksync_child_drone_gallery_title( $title_parts ) {
	if ( uplinksync_child_is_singular_slug( 'drone-services' ) ) {
		return array( 'title' => 'Real Estate Drone Photo & Video | UplinkSync' );
	}
	return $title_parts;
}
add_filter( 'document_title_parts', 'uplinksync_child_drone_gallery_title' );

/*
 * UPLAA-452 fix: Rank Math (the active SEO plugin) renders the <title> and
 * og:title itself and ignores document_title_parts, so the re-aimed drone
 * title never reached production. Mirror the uplinksync-contact-seo.php
 * precedent and feed Rank Math the same approved string on its own frontend
 * filter so <title>, og:title and twitter:title stay in sync with core.
 */
add_filter(
	'rank_math/frontend/title',
	function ( $title ) {
		return uplinksync_child_is_singular_slug( 'drone-services' )
			? 'Real Estate Drone Photo & Video | UplinkSync'
			: $title;
	}
);

/**
 * ***-42 / ***-99: quote form styling/behaviour, only where the form lives.
 * Markup is supplied by Contact Form 7 (installed on the host, not vendored).
 *
 * Loads on the master-form page /contact (slug "contact"), the Managed IT
 * services page /services/managed-it (slug "managed-it", host of the mini-form
 * per spec §3), and the legacy WooCommerce product /product/managed-it-services/
 * (slug "managed-it-services") which is kept for continuity.
 */
function uplinksync_child_quote_form_assets() {
	if ( ! uplinksync_child_is_singular_slug( array( 'contact', 'managed-it', 'managed-it-services' ) ) ) {
		return;
	}
	wp_enqueue_style(
		'uplinksync-quote-form',
		get_stylesheet_directory_uri() . '/assets/css/quote-form.css',
		array( 'uplinksync-brand' ),
		uplinksync_child_asset_ver( 'assets/css/quote-form.css' )
	);

	wp_enqueue_script(
		'uplinksync-quote-form',
		get_stylesheet_directory_uri() . '/assets/js/quote-form.js',
		array(),
		uplinksync_child_asset_ver( 'assets/js/quote-form.js' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'uplinksync_child_quote_form_assets', 21 );

/**
 * Motion layer — GSAP + ScrollTrigger, self-hosted.
 *
 * Deliberately NOT a plugin. GSAP became free for all uses in April 2025, so the
 * cinematic toolkit costs nothing and belongs in the theme rather than adding
 * weight and an update surface to a site we are actively slimming down (see the
 * Elementor removal). Self-hosted rather than CDN: no third-party runtime
 * dependency for a company that sells web design, and browser cache
 * partitioning means a public CDN no longer buys a shared-cache benefit anyway.
 *
 * Loaded in the footer and deferred. ScrollTrigger depends on gsap, and
 * motion.js depends on both, so the dependency array enforces order.
 *
 * ~46KB gzipped combined. That is a real cost, so it is only enqueued on the
 * front end, and every effect is opt-in per element via data-uls-* attributes.
 */
function uplinksync_child_motion_assets() {
	if ( is_admin() ) {
		return;
	}

	wp_enqueue_style(
		'uplinksync-motion',
		get_stylesheet_directory_uri() . '/assets/css/motion.css',
		array(),
		uplinksync_child_asset_ver( '/assets/css/motion.css' )
	);

	wp_enqueue_script(
		'gsap',
		get_stylesheet_directory_uri() . '/assets/js/vendor/gsap.min.js',
		array(),
		uplinksync_child_asset_ver( '/assets/js/vendor/gsap.min.js' ),
		array( 'strategy' => 'defer', 'in_footer' => true )
	);

	wp_enqueue_script(
		'gsap-scrolltrigger',
		get_stylesheet_directory_uri() . '/assets/js/vendor/ScrollTrigger.min.js',
		array( 'gsap' ),
		uplinksync_child_asset_ver( '/assets/js/vendor/ScrollTrigger.min.js' ),
		array( 'strategy' => 'defer', 'in_footer' => true )
	);

	wp_enqueue_script(
		'uplinksync-motion',
		get_stylesheet_directory_uri() . '/assets/js/motion.js',
		array( 'gsap', 'gsap-scrolltrigger' ),
		uplinksync_child_asset_ver( '/assets/js/motion.js' ),
		array( 'strategy' => 'defer', 'in_footer' => true )
	);
}
add_action( 'wp_enqueue_scripts', 'uplinksync_child_motion_assets', 22 );

/**
 * "The Split Horizon" homepage (concept: docs/website-redesign/homepage-wow-concept-2026-07-28.md).
 * Front page only. CSS is scoped under .uls-splith (no leakage); JS is DOM-only,
 * deferred, and a no-op without .uls-splith. Type stays DM Sans (the live font) —
 * the concept's Manrope/Fira are deliberately not introduced. The hero "Get a fast
 * estimate" buttons carry uls-consult-trigger + data-uls-book-open, so the booking
 * mu-plugin injects and opens the existing cal.com chooser modal (no duplicate
 * modal system).
 */
function uplinksync_child_split_horizon_assets() {
	if ( ! is_front_page() ) {
		return;
	}
	wp_enqueue_style(
		'uplinksync-split-horizon',
		get_stylesheet_directory_uri() . '/assets/css/split-horizon.css',
		array( 'uplinksync-brand' ),
		uplinksync_child_asset_ver( 'assets/css/split-horizon.css' )
	);
	wp_enqueue_script(
		'uplinksync-split-horizon',
		get_stylesheet_directory_uri() . '/assets/js/split-horizon.js',
		array(),
		uplinksync_child_asset_ver( 'assets/js/split-horizon.js' ),
		array( 'strategy' => 'defer', 'in_footer' => true )
	);
}
add_action( 'wp_enqueue_scripts', 'uplinksync_child_split_horizon_assets', 22 );

/**
 * *** (post-MR!93 fix): product/shop pages showed the breadcrumb TWICE. The
 * house store templates (single-product.html / archive-product.html) render the
 * styled block breadcrumb (`wp:woocommerce/breadcrumbs`), and WooCommerce's classic
 * hook (`woocommerce_breadcrumb` on `woocommerce_before_main_content`) renders a
 * second, unstyled one. Drop the classic hook so exactly ONE — the styled block —
 * remains. Reversible (delete this block); a no-op if the classic hook isn't bound.
 */
function uplinksync_child_dedupe_wc_breadcrumb() {
	remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
}
add_action( 'init', 'uplinksync_child_dedupe_wc_breadcrumb', 20 );
