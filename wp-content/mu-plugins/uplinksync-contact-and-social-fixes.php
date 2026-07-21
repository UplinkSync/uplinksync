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
	 * 1. EMAIL — remove the broken Cloudflare obfuscation entirely.
	 *
	 * This site is not behind Cloudflare, so the /cdn-cgi/l/email-protection
	 * decoder 404s and the address renders as "[email protected]". Replace the
	 * whole anchor (both the span-class form and the "#hash" href-only form)
	 * with a plain mailto:. The data-cfemail hash differs per page, hence the
	 * regex rather than a literal.
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
	// Any leftover literal placeholders / i18n keys, just in case.
	$html = str_replace(
		array( 'email@email.com', 'trans-contact_email' ),
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
function uplinksync_social_set_href( $html, $services, $url ) {
	$alt = implode( '|', array_map( 'preg_quote', $services ) );
	$pattern = '#(<li\b[^>]*class="[^"]*wp-social-link-(?:' . $alt . ')\b[^"]*"[^>]*>.*?<a\b[^>]*\bhref=")[^"]*(")#is';
	return preg_replace( $pattern, '${1}' . $url . '${2}', $html );
}
