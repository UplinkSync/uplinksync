<?php
/**
 * Plugin Name: UplinkSync — Contact Details & Social Link Fixes
 * Description: Repairs placeholder contact info and dead social links in the live front-end HTML (***-101). The contact email, phone number and social URLs are stored in the WordPress database / parent-theme template output, NOT in this repo, so a static file edit cannot reach them. This mu-plugin rewrites the rendered page on the way out, keeping the fix captured in-repo (deploys with wp-content) and independent of the active theme. Values are owner-authoritative (Doug Irwin, 2026-07-21) — do not re-derive.
 * Version: 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owner-supplied facts (***-101 wake comment, 2026-07-21). Exact values —
 * the phone keeps its +1 country code; the tel: target is E.164.
 */
const UPLINKSYNC_CONTACT_EMAIL       = 'contact@uplinksync.com';
const UPLINKSYNC_CONTACT_PHONE_HUMAN = '+1 (208) 995-2704';
const UPLINKSYNC_CONTACT_PHONE_E164  = '+12089952704';

/**
 * Owner-confirmed social profile URLs (***-104 wake comment, 2026-07-21).
 * Use verbatim — the Facebook value is a /people/<slug>/<id>/ profile link,
 * NOT a vanity page; do not "tidy" it or it 404s. Instagram is intentionally
 * absent: the owner has not supplied it, and IA policy is to render nothing at
 * all (no icon, no "#" href) rather than a link that goes nowhere. X/Twitter
 * and TikTok do not exist and are removed below.
 */
const UPLINKSYNC_SOCIAL_LINKEDIN = 'https://www.linkedin.com/company/uplinksync/';
const UPLINKSYNC_SOCIAL_FACEBOOK = 'https://www.facebook.com/people/UplinkSync-LLC/61588243996549/';

/**
 * Rewrite the finished HTML document.
 *
 * Why an output buffer and not a content/nav filter: the broken strings come
 * from at least three different render paths (a Cloudflare-obfuscated email
 * anchor injected late, a block-editor paragraph with the placeholder phone,
 * and an untranslated parent-theme menu template emitting literal `trans-*`
 * i18n keys). A single shutdown-time pass over the whole document catches all
 * of them regardless of which subsystem produced them.
 *
 * Scope guard: front-end GET requests for HTML only. Never touches wp-admin,
 * REST, AJAX, feeds, or the block editor.
 */
function uplinksync_contact_social_should_filter() {
	if ( is_admin() || wp_doing_ajax() || wp_is_json_request() ) {
		return false;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}
	if ( is_feed() || is_embed() ) {
		return false;
	}
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
		return false;
	}
	return true;
}

function uplinksync_contact_social_start_buffer() {
	if ( ! uplinksync_contact_social_should_filter() ) {
		return;
	}
	ob_start( 'uplinksync_contact_social_rewrite' );
}
add_action( 'template_redirect', 'uplinksync_contact_social_start_buffer', 0 );

/**
 * The rewrite itself. Only runs on documents that actually look like the
 * front-end HTML page (has a </body>), so buffered non-HTML responses pass
 * through untouched.
 */
function uplinksync_contact_social_rewrite( $html ) {
	if ( ! is_string( $html ) || false === stripos( $html, '</body>' ) ) {
		return $html;
	}

	$email = UPLINKSYNC_CONTACT_EMAIL;

	/* ---------------------------------------------------------------------
	 * 1. EMAIL — replace the broken Cloudflare obfuscation, and stop the edge
	 *    from simply re-applying it.
	 *
	 * The site IS behind Cloudflare (`server: cloudflare`, a cf-ray header, a
	 * live /cdn-cgi/trace), and Scrape Shield's Email Address Obfuscation is
	 * on for the zone. That rewrite happens at the EDGE, after WordPress has
	 * rendered — which is why replacing the anchor here was never enough on
	 * its own: we emitted a clean mailto: and Cloudflare obfuscated it again
	 * on the way out, so the address kept rendering as "[email protected]"
	 * against a /cdn-cgi/l/email-protection decoder that 404s.
	 *
	 * Cloudflare documents an origin-side opt-out (<!--email_off-->), which is
	 * applied by uplinksync_email_exempt_mailtos() as the LAST step of this
	 * filter. It deliberately runs over the whole finished document rather than
	 * only over the anchors built here: pages carry mailto: links this plugin
	 * never touches -- /contact/ has one saved directly in the page content --
	 * and those get obfuscated at the edge just the same. Exempting only our
	 * own output fixed the home page and left /contact/ broken.
	 *
	 * The data-cfemail hash differs per page, hence the regex rather than a
	 * literal, and both emitted forms are handled.
	 * ------------------------------------------------------------------- */
	$mailto = '<a href="mailto:' . $email . '">' . $email . '</a>';

	// Form A: <a href="/cdn-cgi/l/email-protection" class="__cf_email__" data-cfemail="...">[email protected]</a>
	$html = preg_replace(
		'#<a\b[^>]*class="[^"]*__cf_email__[^"]*"[^>]*>.*?</a>#is',
		$mailto,
		$html
	);
	// Form B: <a href="/cdn-cgi/l/email-protection#HASH">trans-contact_email</a> (untranslated menu template)
	$html = preg_replace(
		'#<a\b[^>]*href="/cdn-cgi/l/email-protection[^"]*"[^>]*>.*?</a>#is',
		$mailto,
		$html
	);
	// The untranslated footer template emits <a href="mailto:trans-encoded_email">,
	// so the label was being corrected while the link target kept the i18n key --
	// a live anchor that mailed nobody. Repair the href before the labels.
	$html = str_replace( 'mailto:trans-encoded_email', 'mailto:' . $email, $html );

	// Any leftover literal placeholders / i18n keys, just in case.
	$html = str_replace(
		array( 'email@email.com', 'trans-contact_email', 'trans-encoded_email' ),
		$email,
		$html
	);

	/* ---------------------------------------------------------------------
	 * 2. PHONE — set the real number and make it a tel: link.
	 *
	 * Rendered block shows the bare placeholder "(123) 123 123" (no link).
	 * The untranslated menu template emits <a href="tel:trans-encoded_phone">
	 * trans-contact_phone</a>. Handle both.
	 * ------------------------------------------------------------------- */
	$tel_anchor = '<a href="tel:' . UPLINKSYNC_CONTACT_PHONE_E164 . '">' . UPLINKSYNC_CONTACT_PHONE_HUMAN . '</a>';

	// Placeholder in the block paragraph (bare text, optional trailing space).
	$html = preg_replace( '/\(123\)\s*123\s*123\s?/', $tel_anchor, $html );

	// Untranslated menu template link target + label.
	$html = str_replace( 'tel:trans-encoded_phone', 'tel:' . UPLINKSYNC_CONTACT_PHONE_E164, $html );
	$html = str_replace(
		array( 'trans-contact_phone', 'trans-encoded_phone' ),
		UPLINKSYNC_CONTACT_PHONE_HUMAN,
		$html
	);

	/* ---------------------------------------------------------------------
	 * 3. SOCIAL — owner-confirmed 2026-07-21 (***-104):
	 *    - LinkedIn + Facebook: set the real profile URLs (confirmed).
	 *    - Instagram: render NOTHING — no icon, no "#" href, no disabled
	 *      state — until the owner supplies the URL. An icon that goes
	 *      nowhere is the same defect we are fixing, so we drop the whole
	 *      <li>/anchor rather than leave a "#" placeholder.
	 *    - X/Twitter + TikTok: remove entirely (accounts do not exist).
	 *
	 * Two markup styles on the site:
	 *   a) block social-links list: <li class="... wp-social-link-x ...">…</li>
	 *   b) untranslated template: hrefs like trans-social_tiktok_url
	 * ------------------------------------------------------------------- */

	// (a) LinkedIn + Facebook — point the anchor inside the matching <li> at
	//     the real profile URL. The default pattern ships these with href="#";
	//     rewrite the href of the <a> nested in the service <li>.
	$html = uplinksync_social_set_href( $html, array( 'linkedin' ), UPLINKSYNC_SOCIAL_LINKEDIN );
	$html = uplinksync_social_set_href( $html, array( 'facebook' ), UPLINKSYNC_SOCIAL_FACEBOOK );

	// (a2) LinkedIn was never in the theme's social list, so there is no href
	//      for (a) to rewrite -- set_href can only edit an element that already
	//      exists. Insert a LinkedIn <li> after Facebook when it is absent.
	$html = uplinksync_social_ensure_linkedin( $html );

	// (b) Remove Instagram (pending), plus X/Twitter and TikTok (do not exist).
	//     Drop the whole <li> in wp-block-social-links.
	$html = preg_replace(
		'#<li\b[^>]*class="[^"]*wp-social-link-(?:instagram|x|twitter|tiktok)\b[^"]*"[^>]*>.*?</li>#is',
		'',
		$html
	);

	// (c) Remove anchors pointing at bare Instagram/X/Twitter/TikTok homepages
	//     (and untranslated tokens), wherever they appear outside the list above.
	$html = preg_replace(
		'#<a\b[^>]*href="https?://(?:www\.)?(?:instagram|x|twitter|tiktok)\.com/?"[^>]*>.*?</a>#is',
		'',
		$html
	);
	$html = preg_replace(
		'#<a\b[^>]*href="[^"]*trans-social_(?:instagram|tiktok|twitter)_url[^"]*"[^>]*>.*?</a>#is',
		'',
		$html
	);

	$html = uplinksync_contact_linkify_email( $html );

	/* ---------------------------------------------------------------------
	 * 4. FOOTER — the same untranslated parent-theme template leaks its raw
	 *    i18n keys as visible text on every page: the four footer column
	 *    headings render literally as "trans-menu", "trans-contacts",
	 *    "trans-socials" and "trans-newsletter", and the copyright line reads
	 *    "© trans-current-year trans-all-rights-reserved".
	 *
	 * These are the same defect class as the contact placeholders above, just
	 * in a different part of the template, so they are repaired the same way.
	 * The year is generated rather than hardcoded so the footer does not go
	 * stale on 1 January.
	 * ------------------------------------------------------------------- */
	$html = str_replace(
		array(
			'trans-menu',
			'trans-contacts',
			'trans-socials',
			'trans-newsletter',
			'trans-current-year',
			'trans-all-rights-reserved',
		),
		array(
			'Menu',
			'Contact',
			'Follow Us',
			'Newsletter',
			gmdate( 'Y' ),
			'All rights reserved.',
		),
		$html
	);

	// The WhatsApp icon points at the literal placeholder https://trans-whatsapp-number.
	// The owner listed Facebook, Instagram and LinkedIn only, so there is no
	// number to fill in. Same rule as Instagram/X/TikTok above: remove it rather
	// than ship an icon that goes nowhere.
	$html = preg_replace(
		'#<li\b[^>]*class="[^"]*wp-social-link-whatsapp\b[^"]*"[^>]*>.*?</li>#is',
		'',
		$html
	);
	$html = preg_replace(
		'#<a\b[^>]*href="[^"]*trans-whatsapp-number[^"]*"[^>]*>.*?</a>#is',
		'',
		$html
	);

	// LAST: exempt every mailto: on the finished page from edge obfuscation.
	// Must stay last so it also covers anchors introduced above.
	$html = uplinksync_email_exempt_mailtos( $html );

	return $html;
}

/**
 * Set the href of the <a> inside a wp-block-social-links <li> whose class
 * names one of $services. Leaves everything else (icon SVG, screen-reader
 * label) untouched. If the service <li> is not present, the HTML is returned
 * unchanged — we never inject a new link, only repair an existing one.
 *
 * @param string   $html     Full document.
 * @param string[] $services Service slugs to match in wp-social-link-<slug>.
 * @param string   $url      Real profile URL to set.
 * @return string
 */
/**
 * Insert a LinkedIn entry into each wp-block-social-links list that does not
 * already have one, cloning the markup shape of the Facebook item so it picks
 * up the same block styling. Idempotent: a list that already contains
 * wp-social-link-linkedin is left alone.
 */
function uplinksync_social_ensure_linkedin( $html ) {
	if ( false === stripos( $html, 'wp-block-social-links' ) ) {
		return $html;
	}
	$svg = '<svg width="24" height="24" viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M19.7,3H4.3C3.582,3,3,3.582,3,4.3v15.4C3,20.418,3.582,21,4.3,21h15.4c0.718,0,1.3-0.582,1.3-1.3V4.3C21,3.582,20.418,3,19.7,3z M8.339,18.338H5.667v-8.59h2.672V18.338z M7.004,8.574c-0.857,0-1.549-0.694-1.549-1.548c0-0.855,0.691-1.548,1.549-1.548c0.854,0,1.547,0.694,1.547,1.548C8.551,7.881,7.858,8.574,7.004,8.574z M18.339,18.338h-2.669V14.16c0-0.996-0.017-2.277-1.387-2.277c-1.389,0-1.601,1.086-1.601,2.207v4.248h-2.667v-8.59h2.559v1.174h0.037c0.356-0.675,1.227-1.387,2.526-1.387c2.703,0,3.203,1.779,3.203,4.092V18.338z"></path></svg>';
	$li  = '<li class="wp-social-link wp-social-link-linkedin wp-block-social-link">'
	     . '<a href="' . esc_url( UPLINKSYNC_SOCIAL_LINKEDIN ) . '" class="wp-block-social-link-anchor"'
	     . ' target="_blank" rel="noopener"><span class="screen-reader-text">LinkedIn</span>'
	     . $svg . '</a></li>';

	return preg_replace_callback(
		'#(<ul\b[^>]*class="[^"]*wp-block-social-links[^"]*"[^>]*>)(.*?)(</ul>)#is',
		static function ( $m ) use ( $li ) {
			if ( false !== stripos( $m[2], 'wp-social-link-linkedin' ) ) {
				return $m[0]; // already present
			}
			// place it directly after the Facebook item when there is one
			$inner = preg_replace(
				'#(<li\b[^>]*class="[^"]*wp-social-link-facebook\b[^"]*"[^>]*>.*?</li>)#is',
				'${1}' . $li,
				$m[2],
				1,
				$count
			);
			if ( ! $count ) {
				$inner = $m[2] . $li; // no Facebook item: append
			}
			return $m[1] . $inner . $m[3];
		},
		$html
	);
}

/**
 * Mark a fragment exempt from Cloudflare's Email Address Obfuscation.
 *
 * Cloudflare skips anything wrapped in these two comments. Without it the edge
 * re-obfuscates every mailto: we emit, which is what kept the published address
 * broken no matter what the origin rendered. The comments are inert HTML
 * everywhere else, so this stays correct if the zone setting is ever disabled
 * or the site moves off Cloudflare.
 */
function uplinksync_email_exempt( $fragment ) {
	return '<!--email_off-->' . $fragment . '<!--email_on-->';
}

/**
 * Wrap every mailto: anchor in the finished document in the Cloudflare opt-out.
 *
 * Scope is deliberately the whole page, not just the anchors this plugin
 * builds. Any mailto: Cloudflare sees gets rewritten into a __cf_email__ span
 * backed by a /cdn-cgi/l/email-protection decoder that 404s on this site, and
 * plenty of the page's mailto: links come from saved page content or the theme
 * rather than from here.
 *
 * Skips anchors already exempt so repeated passes are a no-op -- the output
 * buffer filter can run more than once, and nested comments would break the
 * markers.
 */
function uplinksync_email_exempt_mailtos( $html ) {
	if ( false === stripos( $html, 'mailto:' ) ) {
		return $html;
	}
	return preg_replace_callback(
		'#(<!--email_off-->\s*)?<a\b[^>]*\bhref="mailto:[^"]*"[^>]*>.*?</a>#is',
		function ( $m ) {
			// Already wrapped — leave it exactly as it is.
			if ( ! empty( $m[1] ) ) {
				return $m[0];
			}
			return uplinksync_email_exempt( $m[0] );
		},
		$html
	);
}

/**
 * Linkify a bare, unlinked contact address. The edge-obfuscation exemption is
 * applied afterwards by uplinksync_email_exempt_mailtos(), which sweeps the
 * whole document. Never double-wraps an address already inside an anchor.
 */
function uplinksync_contact_linkify_email( $html ) {
	$email = UPLINKSYNC_CONTACT_EMAIL;
	if ( false === stripos( $html, $email ) ) {
		return $html;
	}
	return preg_replace(
		'#(?<!mailto:)(?<![\w.@-])' . preg_quote( $email, '#' ) . '(?![\w.@-])(?![^<]*</a>)#i',
		'<a href="mailto:' . $email . '">' . $email . '</a>',
		$html
	);
}

function uplinksync_social_set_href( $html, $services, $url ) {
	$alt = implode( '|', array_map( 'preg_quote', $services ) );
	$pattern = '#(<li\b[^>]*class="[^"]*wp-social-link-(?:' . $alt . ')\b[^"]*"[^>]*>.*?<a\b[^>]*\bhref=")[^"]*(")#is';
	return preg_replace( $pattern, '${1}' . $url . '${2}', $html );
}
