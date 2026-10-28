<?php
/**
 * Client Hub (interim, Richer Quiet Light) — one registry of client destinations and the
 * small building blocks that use it:
 *   - /hub/ tiles            (patterns/site-hub.php, templates/page-hub.html)
 *   - home "Client hub" band (uplinksync_site_hub() in inc/site.php)
 *   - footer "Client hub" column (patterns/site-footer.php)
 *   - the "Back to Client Hub" bar on store/account pages (patterns/hub-backbar.php)
 *   - /prints/ collection covers (patterns/site-prints.php)
 *
 * Every client link lives in uplinksync_hub_links(), so a destination changes in one place.
 * When UplinkPortal later owns /hub/, its shortcode replaces the tile grid; the links stay.
 *
 * @package uplinksync-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client destinations. external = leaves uplinksync.com (opens in a new tab, says so).
 *
 * Notes (portal/RECON.md):
 * - pay:   billing.* 301s to invoice.* (billing.* is Headscale-only except /client/), so link
 *          the public host directly.
 * - media: unchanged (owner default Q1: keep the existing Immich link working).
 * - sign:  signers never need a DocuSeal account (per-document emailed link, /s/<slug>); DocuSeal's
 *          /sign_in is the STAFF login, so the tile explains that and offers a resend via /contact/.
 * - book:  a book.uplinksync.com link is adopted by mu-plugins/uplinksync-booking-ctas.php
 *          into the native booking modal, so it is NOT marked external (no new tab).
 * - help:  /contact/ (owner question Q8 open; support.uplinksync.com/login is the agent login).
 *
 * @return array<string,array{icon:string,label:string,desc:string,href:string,external:bool}>
 */
function uplinksync_hub_links() {
	$book = defined( 'UPLINKSYNC_SITE_BOOK_IT' ) ? UPLINKSYNC_SITE_BOOK_IT : 'https://book.uplinksync.com/team/uplinksync/it-consult';
	return array(
		'pay'    => array(
			'icon'     => 'card',
			'label'    => 'Pay an invoice',
			'desc'     => 'See your invoices and statements, and pay online. Sign in with the email we invoice.',
			'href'     => 'https://invoice.uplinksync.com/client/login',
			'external' => true,
		),
		'media'  => array(
			'icon'     => 'image',
			'label'    => 'Your photos & video',
			'desc'     => 'Aerial stills, video and finished deliverables from your shoot.',
			'href'     => 'https://media.uplinksync.com',
			'external' => true,
		),
		'sign'   => array(
			'icon'     => 'sign',
			'label'    => 'Sign a document',
			'desc'     => 'Signing links arrive by email, so there is no account to create. Can’t find yours? We’ll resend it.',
			'href'     => '/contact/?topic=signing',
			'external' => false,
		),
		'store'  => array(
			'icon'     => 'user',
			'label'    => 'Your store account',
			'desc'     => 'Orders, downloads and licences from the print shop.',
			'href'     => '/my-account/',
			'external' => false,
		),
		'book'   => array(
			'icon'     => 'cal',
			'label'    => 'Book a call',
			'desc'     => 'Pick a time for a consult, a site visit or a shoot.',
			'href'     => $book,
			'external' => false,
		),
		'help'   => array(
			'icon'     => 'help',
			'label'    => 'Get support',
			'desc'     => 'Send us a message and a real person replies within one business day.',
			'href'     => '/contact/',
			'external' => false,
		),
		'prints' => array(
			'icon'     => 'print',
			'label'    => 'Print shop',
			'desc'     => 'Aerial prints, browsed by collection.',
			'href'     => '/prints/',
			'external' => false,
		),
	);
}

/** The six destinations on /hub/ and in the footer column, in order. */
const UPLINKSYNC_HUB_TILES = array( 'pay', 'media', 'sign', 'store', 'book', 'help' );

/**
 * Anchor attributes for a destination: href, plus target/rel when it leaves the site.
 *
 * @param array $l Link from uplinksync_hub_links().
 * @return string
 */
function uplinksync_hub_attrs( array $l ) {
	$attrs = ' href="' . esc_url( $l['href'] ) . '"';
	if ( ! empty( $l['external'] ) ) {
		$attrs .= ' target="_blank" rel="noopener"';
	}
	return $attrs;
}

/**
 * Visible "opens in a new tab" note for external links ('' for on-site links). Visible text
 * (not only an icon), so it reaches screen readers and sighted users alike.
 *
 * @param array  $l     Link.
 * @param string $class Wrapper class.
 * @return string
 */
function uplinksync_hub_newtab( array $l, $class = 'newtab' ) {
	if ( empty( $l['external'] ) ) {
		return '';
	}
	return '<span class="' . esc_attr( $class ) . '">' . uplinksync_hub_icon( 'external', 14 ) . ' opens in a new tab</span>';
}

/**
 * Icons used only by the hub (the rest come from uplinksync_site_icon()).
 *
 * @param string $name Icon key.
 * @param int    $size Size.
 * @return string
 */
function uplinksync_hub_icon( $name, $size = 22 ) {
	static $extra = array(
		'sign'     => '<path d="M4 20h16"/><path d="M14.5 4.5l3 3L8 17H5v-3z"/>',
		'help'     => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 114 2c-.9.6-1.5 1.1-1.5 2.5"/><path d="M12 17h.01"/>',
		'external' => '<path d="M14 4h6v6"/><path d="M20 4l-9 9"/><path d="M18 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1h5"/>',
		'back'     => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
	);
	if ( isset( $extra[ $name ] ) ) {
		return sprintf(
			'<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
			(int) $size,
			$extra[ $name ]
		);
	}
	return function_exists( 'uplinksync_site_icon' ) ? uplinksync_site_icon( $name, $size ) : '';
}

/**
 * The /hub/ tile grid: icon, label, plain-language description, new-tab note.
 *
 * @return string
 */
function uplinksync_hub_tiles() {
	$links = uplinksync_hub_links();
	$html  = '';
	foreach ( UPLINKSYNC_HUB_TILES as $key ) {
		$l     = $links[ $key ];
		$html .= sprintf(
			'<li><a class="htile" data-hub="%1$s"%2$s><span class="ico lt lg">%3$s</span><span class="htxt"><span class="hlabel">%4$s %5$s</span><span class="hdesc">%6$s</span>%7$s</span></a></li>',
			esc_attr( $key ),
			uplinksync_hub_attrs( $l ),
			uplinksync_hub_icon( $l['icon'], 24 ),
			esc_html( $l['label'] ),
			function_exists( 'uplinksync_site_arrow' ) ? uplinksync_site_arrow() : '',
			esc_html( $l['desc'] ),
			uplinksync_hub_newtab( $l )
		);
	}
	return '<ul class="htiles" aria-label="Client destinations">' . $html . '</ul>';
}

/**
 * Footer "Client hub" list items (the six destinations plus the print shop).
 *
 * @return string <li> items.
 */
function uplinksync_hub_footer_items() {
	$links = uplinksync_hub_links();
	$out   = '';
	foreach ( array_merge( UPLINKSYNC_HUB_TILES, array( 'prints' ) ) as $key ) {
		$l    = $links[ $key ];
		$out .= '<li><a' . uplinksync_hub_attrs( $l ) . '>' . esc_html( $l['label'] )
			. ( empty( $l['external'] ) ? '' : '<span class="screen-reader-text"> (opens in a new tab)</span>' ) . '</a></li>';
	}
	return $out;
}

/**
 * Slim "Back to Client Hub" bar for store and account pages.
 *
 * @param string $context Optional second link: 'store' adds "Print shop".
 * @return string
 */
function uplinksync_hub_backbar( $context = '' ) {
	$extra = '';
	if ( 'store' === $context ) {
		$extra = '<a class="hb-link" href="/prints/">' . uplinksync_hub_icon( 'print', 18 ) . 'Print shop</a>';
	}
	return '<nav class="hubbar" aria-label="Client Hub"><div class="hb-wrap"><a class="hb-link hb-back" href="/hub/">'
		. uplinksync_hub_icon( 'back', 18 ) . 'Back to Client Hub</a>' . $extra . '</div></nav>';
}

/* -------------------------------------------------------------------------------------------
 * /prints/ collection covers. The DB page linked "-master-<hex>-1024x576" renditions, which
 * wp-content/.htaccess (UPLAA-412) denies, so every cover was a 403. Covers here are always the
 * product's WATERMARKED preview attachment at a display size — never a master rendition.
 * ----------------------------------------------------------------------------------------- */

/**
 * True when a URL points at an un-watermarked store master: any rendition, duplicate suffix
 * (-1), edited copy (-e<timestamp>) or format. Matches the "-master-" token anywhere, the same
 * test uplinksync-collection-archive.php uses (review L9).
 *
 * @param string $url URL.
 * @return bool
 */
function uplinksync_hub_is_master_url( $url ) {
	return is_string( $url ) && false !== stripos( $url, '-master-' );
}

/**
 * Public cover URL for a preview attachment ('' when only a master would be served).
 * Returns the preview file itself (Code Snippet 045's small-size master swap was retired
 * 2026-10-28; this guard still refuses any '-master-' URL).
 *
 * @param int    $attachment_id Preview attachment.
 * @param string $size          Image size.
 * @return string
 */
function uplinksync_hub_cover_url( $attachment_id, $size = 'large' ) {
	if ( ! $attachment_id || ! function_exists( 'wp_get_attachment_image_url' ) ) {
		return '';
	}
	$url = (string) wp_get_attachment_image_url( (int) $attachment_id, $size );
	return ( '' === $url || uplinksync_hub_is_master_url( $url ) ) ? '' : $url;
}

/**
 * Print collections under the aerial-photography parent, largest first.
 *
 * @return array<int,array{name:string,href:string,count:int,from:string,cover:string,kind:string}>
 */
function uplinksync_hub_collections() {
	if ( ! function_exists( 'get_term_by' ) || ! taxonomy_exists( 'product_cat' ) ) {
		return array();
	}
	$parent = get_term_by( 'slug', 'aerial-photography', 'product_cat' );
	$terms  = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'parent'     => $parent ? (int) $parent->term_id : 0,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	$out = array();
	foreach ( $terms as $t ) {
		if ( $parent && (int) $t->term_id === (int) $parent->term_id ) {
			continue;
		}
		$link = get_term_link( $t );
		if ( is_wp_error( $link ) ) {
			continue;
		}
		$ids   = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
				'tax_query'      => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => (int) $t->term_id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery -- 5 terms, cached page.
			)
		);
		$cover = '';
		$from  = null;
		foreach ( $ids as $id ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
			if ( ! $product || ! $product->is_visible() ) {
				continue; // hidden from the catalog: neither its price nor its image counts.
			}
			if ( '' === $cover ) {
				$cover = uplinksync_hub_cover_url( (int) $product->get_image_id() );
			}
			$price = uplinksync_hub_product_min_price( $product );
			if ( null !== $price && ( null === $from || $price < $from ) ) {
				$from = $price;
			}
		}
		$out[] = array(
			'name'  => $t->name,
			'href'  => $link,
			'count' => (int) $t->count,
			'from'  => null === $from ? '' : uplinksync_hub_money( $from ),
			'cover' => $cover,
			'kind'  => 'palisades-reservoir' === $t->slug ? 'Mountainscapes' : 'Cityscapes',
		);
	}
	return $out;
}

/**
 * Lowest current price of a product (min variation price for variable products), or null.
 * Uses the WooCommerce API, not the raw _price meta, which holds several rows for variable
 * products (review L10).
 *
 * @param object $product WC_Product.
 * @return float|null
 */
function uplinksync_hub_product_min_price( $product ) {
	$price = ( method_exists( $product, 'is_type' ) && $product->is_type( 'variable' ) && method_exists( $product, 'get_variation_price' ) )
		? $product->get_variation_price( 'min' )
		: $product->get_price();
	return ( '' !== $price && null !== $price && is_numeric( $price ) ) ? (float) $price : null;
}

/**
 * "From" price as plain text in the store currency, whole amounts without decimals ("$29").
 *
 * @param float $amount Amount.
 * @return string
 */
function uplinksync_hub_money( $amount ) {
	$amount = (float) $amount;
	if ( function_exists( 'wc_price' ) ) {
		$html = wc_price( $amount, array( 'decimals' => floor( $amount ) === $amount ? 0 : 2 ) );
		return trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
	}
	return '$' . rtrim( rtrim( number_format( $amount, 2, '.', '' ), '0' ), '.' );
}

/**
 * Collection card grid for /prints/. Cards without a public cover fall back to a branded
 * gradient (never a broken image).
 *
 * @param array $cols From uplinksync_hub_collections().
 * @return string
 */
function uplinksync_hub_collection_cards( array $cols ) {
	if ( ! $cols ) {
		return '<p class="muted">Collections are being updated. <a href="/shop/">Shop all prints</a>.</p>';
	}
	$html = '';
	foreach ( array_values( $cols ) as $i => $c ) {
		$img   = '' === $c['cover'] ? '<span class="pc-fallback" aria-hidden="true"></span>'
			: '<img src="' . esc_url( $c['cover'] ) . '" width="1024" height="576" alt="" loading="' . ( $i < 2 ? 'eager' : 'lazy' ) . '" decoding="async">';
		$meta  = sprintf( '%d %s', $c['count'], 1 === $c['count'] ? 'print' : 'prints' ) . ( '' !== $c['from'] ? ' · from ' . $c['from'] : '' );
		$html .= sprintf(
			'<li%1$s><a class="pcard" href="%2$s"><span class="pc-media">%3$s</span><span class="pc-body"><span class="pc-kind">%4$s · Collection %5$02d</span><span class="pc-name">%6$s</span><span class="pc-meta">%7$s</span><span class="pc-go">View collection %8$s</span></span></a></li>',
			0 === $i ? ' class="feature"' : '',
			esc_url( $c['href'] ),
			$img,
			esc_html( $c['kind'] ),
			$i + 1,
			esc_html( $c['name'] ),
			esc_html( $meta ),
			function_exists( 'uplinksync_site_arrow' ) ? uplinksync_site_arrow() : ''
		);
	}
	return '<ul class="pcards" aria-label="Print collections">' . $html . '</ul>';
}

/* -------------------------------------------------------------------------------------------
 * Store/account polish
 * ----------------------------------------------------------------------------------------- */

/**
 * WooCommerce My Account menu: plain-language labels.
 *
 * @param array $items Endpoint => label.
 * @return array
 */
function uplinksync_hub_account_menu( $items ) {
	$labels = array(
		'dashboard'       => 'Overview',
		'orders'          => 'Orders',
		'downloads'       => 'Downloads',
		'edit-address'    => 'Addresses',
		'edit-account'    => 'Account details',
		'customer-logout' => 'Sign out',
	);
	foreach ( $labels as $k => $v ) {
		if ( isset( $items[ $k ] ) ) {
			$items[ $k ] = $v;
		}
	}
	return $items;
}
add_filter( 'woocommerce_account_menu_items', 'uplinksync_hub_account_menu', 20 );

/**
 * Store/account page header ([uls_store_head], used by patterns/store-head.php): back bar plus a
 * slim navy header whose text follows the page (account, order received, checkout, cart).
 *
 * @return string
 */
function uplinksync_hub_store_head() {
	$head = array( 'Print shop', 'Your order', 'Questions? Email contact@uplinksync.com.' );
	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		$head = array( 'Your store account', is_user_logged_in() ? 'Your store account' : 'Sign in to your store account', 'Orders, downloads and licences from the print shop.' );
	} elseif ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
		$head = array( 'Print shop', 'Thank you for your order', 'Your receipt and download links are below and in your email.' );
	} elseif ( function_exists( 'is_checkout' ) && is_checkout() ) {
		$head = array( 'Print shop', 'Checkout', 'Secure payment. Full-resolution files are delivered on purchase.' );
	} elseif ( function_exists( 'is_cart' ) && is_cart() ) {
		$head = array( 'Print shop', 'Your cart', 'Review your prints, then check out securely.' );
	}
	return '<div class="rq store-top alignfull">' . uplinksync_hub_backbar( 'store' )
		. '<section class="hero dark phero slim storehero" aria-labelledby="h1"><div class="wrap"><div><p class="eyebrow">' . esc_html( $head[0] )
		. '</p><h1 id="h1">' . esc_html( $head[1] ) . '</h1><p class="lede">' . esc_html( $head[2] ) . '</p></div></div></section></div>';
}
add_shortcode( 'uls_store_head', 'uplinksync_hub_store_head' );

/**
 * Expand the render-time tokens ([uls_store_head], [uls_hub_page]) in a pattern's HTML block.
 * The block template canvas runs do_shortcode() BEFORE do_blocks(), so a token inside a
 * pattern is never reached by it; render_block runs at render time, after the main query.
 *
 * @param string $content Rendered core/html block.
 * @return string
 */
function uplinksync_hub_store_head_block( $content ) {
	$content = (string) $content;
	if ( false !== strpos( $content, '[uls_store_head]' ) ) {
		$content = str_replace( '[uls_store_head]', uplinksync_hub_store_head(), $content );
	}
	if ( false !== strpos( $content, '[uls_hub_page]' ) ) {
		$content = str_replace( '[uls_hub_page]', uplinksync_hub_page(), $content );
	}
	return $content;
}
add_filter( 'render_block_core/html', 'uplinksync_hub_store_head_block', 10 );

/* -------------------------------------------------------------------------------------------
 * /hub/ — one page, two states (owner decision 2026-10-28: the portal lives at /hub/).
 * ----------------------------------------------------------------------------------------- */

/** Sign-in entry for the hub: WooCommerce My Account, back to /hub/ afterwards. */
const UPLINKSYNC_HUB_SIGNIN = '/my-account/?redirect_to=%2Fhub%2F';

/**
 * The UplinkPortal shell for the signed-in client, or '' when the plugin (or its renderer) is
 * not active. Accepts a renderer that returns its markup or one that echoes it.
 *
 * @return string
 */
function uplinksync_hub_portal_html() {
	if ( ! function_exists( 'uplinkportal_render_portal' ) ) {
		return '';
	}
	ob_start();
	$ret  = uplinkportal_render_portal();
	$echo = (string) ob_get_clean();
	return trim( is_string( $ret ) && '' !== trim( $ret ) ? $ret : $echo );
}

/**
 * First name (or display name) of the signed-in user, '' when unknown.
 *
 * @return string
 */
function uplinksync_hub_user_name() {
	if ( ! function_exists( 'wp_get_current_user' ) ) {
		return '';
	}
	$u = wp_get_current_user();
	if ( ! $u || empty( $u->ID ) ) {
		return '';
	}
	$first = function_exists( 'get_user_meta' ) ? (string) get_user_meta( $u->ID, 'first_name', true ) : '';
	return '' !== trim( $first ) ? trim( $first ) : (string) $u->display_name;
}

/**
 * "How signing in works" + "Don't have an account yet?" panels (signed-out state).
 *
 * @return string
 */
function uplinksync_hub_help() {
	$check = uplinksync_site_icon( 'check', 18 );
	$book  = uplinksync_hub_links()['book']['href'];
	return '<section class="sec hubhelp" aria-labelledby="signin-h"><div class="wrap hubhelp-grid">'
		. '<div class="hubnote"><h2 id="signin-h">How signing in works</h2><ul class="ticks">'
		. '<li>' . $check . '<span>Sign in to your client hub to see your orders and downloads in one place. Invoices, photos and documents open on their own secure pages and come straight back here.</span></li>'
		. '<li>' . $check . '<span>Documents to sign usually arrive as a link in your email. Open that link; you do not need an account.</span></li>'
		. '<li>' . $check . '<span>Not sure which one you need? <a href="mailto:contact@uplinksync.com">Email us</a> and we’ll point you at it.</span></li>'
		. '</ul></div>'
		. '<div class="hubnote alt"><h2>Don’t have an account yet?</h2><p>Your Client Hub access is set up when we start working together. If you’re not a client yet, the best first step is a conversation.</p>'
		. '<p class="acts"><a class="btn btn-primary" href="' . esc_url( UPLINKSYNC_SITE_QUOTE ) . '">Get a quote</a><a class="tlink" href="' . esc_url( $book ) . '">Book a call ' . uplinksync_site_arrow() . '</a></p></div>'
		. '</div></section>';
}

/**
 * The whole /hub/ body for the current visitor.
 *
 * @return string
 */
function uplinksync_hub_page() {
	$in     = function_exists( 'is_user_logged_in' ) && is_user_logged_in();
	$portal = $in ? uplinksync_hub_portal_html() : '';
	$tiles  = uplinksync_hub_tiles();

	if ( ! $in ) {
		return '<section class="hero dark phero slim hubhero" aria-labelledby="h1"><div class="wrap"><div>'
			. '<p class="eyebrow">For clients</p><h1 id="h1">Client Hub</h1>'
			. '<p class="lede">Everything you need in one place: billing, bookings, your photos and your paperwork. Bookmark this page; you won’t need to remember anything else.</p>'
			. '<p class="hub-signin"><a class="btn btn-dk btn-lg" href="' . esc_url( UPLINKSYNC_HUB_SIGNIN ) . '">Sign in to your client hub</a><span>Already bought from the print shop? Use that sign-in.</span></p>'
			. '</div></div></section>'
			. '<section class="hubmain" aria-label="Client destinations"><div class="wrap">' . $tiles . '</div></section>'
			. uplinksync_hub_help();
	}

	$name  = uplinksync_hub_user_name();
	$hello = '' === $name ? 'Welcome back' : 'Welcome back, ' . $name;
	$html  = '<section class="hero dark phero slim hubhero in" aria-labelledby="h1"><div class="wrap"><div>'
		. '<p class="eyebrow">Client Hub</p><h1 id="h1">' . esc_html( $hello ) . '</h1>'
		. '<p class="lede">Your account at a glance, and every client service one click away.</p>'
		. '<p class="hub-acct"><a href="/my-account/">Your store account</a><a href="' . esc_url( function_exists( 'wp_logout_url' ) ? wp_logout_url( '/hub/' ) : '/my-account/customer-logout/' ) . '">Sign out</a></p>'
		. '</div></div></section>';
	if ( '' !== $portal ) {
		// The portal shell is the plugin's own escaped markup.
		$html .= '<section class="hubportal" aria-label="Your client portal"><div class="wrap">' . $portal . '</div></section>'
			. '<section class="sec hubrest" aria-labelledby="rest-h"><div class="wrap"><div class="sec-head"><p class="eyebrow">More services</p><h2 id="rest-h">Everything else</h2><p>Invoices, photos, documents and bookings open on their own secure pages.</p></div>' . $tiles . '</div></section>';
	} else {
		$html .= '<section class="hubmain" aria-label="Client destinations"><div class="wrap">' . $tiles . '</div></section>'
			. '<div class="hubpad" aria-hidden="true"></div>';
	}
	return $html;
}

/**
 * Signed-in /hub/ is personal: never page-cached (LiteSpeed, Cloudflare, proxies). Signed-out
 * /hub/ stays cacheable (no per-user data).
 */
function uplinksync_hub_nocache() {
	if ( ! function_exists( 'is_page' ) || ! is_page( 'hub' ) || ! is_user_logged_in() ) {
		return;
	}
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	do_action( 'litespeed_control_set_nocache', 'client hub (signed in)' );
	nocache_headers();
}
add_action( 'template_redirect', 'uplinksync_hub_nocache', 1 );

/**
 * WooCommerce login form: carry a safe, same-site ?redirect_to= (e.g. /hub/) into the form's
 * `redirect` field, which WC_Form_Handler::process_login() honours after wp_validate_redirect().
 */
function uplinksync_hub_login_redirect_field() {
	$to = isset( $_GET['redirect_to'] ) ? (string) wp_unslash( $_GET['redirect_to'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput -- validated below, read-only.
	$to = uplinksync_hub_safe_redirect( $to );
	if ( '' !== $to ) {
		echo '<input type="hidden" name="redirect" value="' . esc_attr( $to ) . '">';
	}
}
add_action( 'woocommerce_login_form_end', 'uplinksync_hub_login_redirect_field' );

/**
 * Same-site path only ('/hub/'); anything else (other hosts, protocol-relative, schemes) -> ''.
 *
 * @param string $to Requested target.
 * @return string
 */
function uplinksync_hub_safe_redirect( $to ) {
	$to = trim( (string) $to );
	if ( '' === $to || '/' !== $to[0] || 0 === strpos( $to, '//' ) || false !== strpos( $to, '\\' ) || preg_match( '#[\x00-\x1f]#', $to ) ) {
		return '';
	}
	return $to;
}

/**
 * Signed-out My Account: one line that says what this login is for.
 * NOTE (review L14): when UplinkPortal takes over /hub/ with its default `woo` auth provider,
 * this same login becomes the portal sign-in — update this line then.
 */
function uplinksync_hub_login_intro() {
	echo '<p class="acct-intro">This sign-in is for the <strong>print shop</strong> only: your orders and downloads. Invoices, photos and documents each have their own sign-in on the <a href="/hub/">Client Hub</a>.</p>';
}
add_action( 'woocommerce_before_customer_login_form', 'uplinksync_hub_login_intro', 5 );
