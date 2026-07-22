<?php
/**
 * ***-186: Serve site media from biz-immich (media.uplinksync.com).
 *
 * Owner decision 2026-07-22: expose curated aerial work through Immich PUBLIC
 * SHARE LINKS rather than buying a video CDN or pushing 27GB onto Hostinger
 * shared hosting. The SSO question was verified anonymously and confirmed by
 * the owner: binding the `immich` app to the business groups in Authentik did
 * NOT gate public share links — anonymous GET / returns 200 with no redirect
 * to the Authentik outpost, and Immich's own per-key auth answers the shares.
 *
 * This file is the version-controlled PLUMBING for that decision. It registers
 * a `[immich_share]` shortcode that renders a responsive, lazy-loaded embed of
 * an Immich public share URL. Publishing a curated album then becomes a
 * one-line content edit — drop the share URL into the shortcode — with no code
 * change and no new plugin (NextGEN Gallery Pro stays uninstalled; the site is
 * being made lighter, not heavier).
 *
 * TWO OWNER CONSTRAINTS ARE ENFORCED IN CODE, not left to editorial discipline:
 *
 *   1. SHARE LINKS ONLY. The `url` must resolve to the media host
 *      (media.uplinksync.com) on an Immich `/share/...` path. Anything that
 *      would require a session — an asset/library URL, a different host — is
 *      rejected and renders nothing on the public page. If an asset needs auth
 *      to load, it is the wrong asset for a public surface.
 *
 *   2. NOTHING FROM Real-Estate. `/mnt/propair/Real-Estate` is per-client work
 *      with no publication permission. This shortcode cannot reach the NAS at
 *      all — it only embeds an already-public share URL the owner curated from
 *      the cleared `Landscape/` source — so restricted client work cannot leak
 *      through this path.
 *
 * No media is published by this file. It provides the mechanism; the owner
 * supplies curated Landscape share URLs once ***-160 inventory editing is
 * complete.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The single approved media host. Immich lives here; this is the only origin a
 * public embed on uplinksync.com may point at. Keeping it a constant makes the
 * allowlist auditable and one edit away from a host migration.
 */
if ( ! defined( 'UPLINKSYNC_MEDIA_HOST' ) ) {
	define( 'UPLINKSYNC_MEDIA_HOST', 'media.uplinksync.com' );
}

/**
 * Validate that a URL is a public Immich share on the approved media host.
 *
 * Enforces owner constraint #1 (share links only). Returns the normalised URL
 * when valid, or an empty string otherwise so callers render nothing rather
 * than leaking a session-gated or off-host URL onto a public page.
 *
 * @param string $url Candidate share URL.
 * @return string Normalised https URL on the media host, or '' if invalid.
 */
function uplinksync_immich_validate_share_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}

	$parts = wp_parse_url( $url );
	if ( empty( $parts['host'] ) || empty( $parts['path'] ) ) {
		return '';
	}

	// Host must be exactly the approved media host (no subdomain smuggling).
	if ( strtolower( $parts['host'] ) !== UPLINKSYNC_MEDIA_HOST ) {
		return '';
	}

	// Scheme must be https (or protocol-relative, which we upgrade). Immich is
	// served over TLS; a public page must never embed cleartext.
	$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';
	if ( '' !== $scheme && 'https' !== $scheme ) {
		return '';
	}

	// Path must be an Immich public share. Immich exposes shares under /share/.
	if ( 0 !== strpos( $parts['path'], '/share/' ) ) {
		return '';
	}

	// Rebuild a clean https URL from the vetted parts (drops any credentials,
	// preserves path + query, which carries the share key).
	$clean = 'https://' . UPLINKSYNC_MEDIA_HOST . $parts['path'];
	if ( ! empty( $parts['query'] ) ) {
		$clean .= '?' . $parts['query'];
	}

	return esc_url_raw( $clean );
}

/**
 * [immich_share] — embed a curated Immich public share album/asset.
 *
 * Usage (content edit, no code change):
 *   [immich_share url="https://media.uplinksync.com/share/<key>" title="Aerial — Coastline"]
 *
 * Attributes:
 *   url    (required) Immich public share URL on media.uplinksync.com/share/...
 *   title  (optional) Accessible label for the embed frame.
 *   ratio  (optional) Aspect ratio as W/H, default 16/9. Bounded to sane values.
 *
 * Renders a responsive, lazy-loaded iframe. If the URL fails validation the
 * shortcode renders an HTML comment only — never a broken or session-gated
 * frame on the public page.
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML.
 */
function uplinksync_immich_share_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'url'   => '',
			'title' => 'Aerial media',
			'ratio' => '16/9',
		),
		$atts,
		'immich_share'
	);

	$url = uplinksync_immich_validate_share_url( $atts['url'] );
	if ( '' === $url ) {
		// Invalid/off-host/non-share URL: emit a diagnostic comment, render
		// nothing visible. Fails safe for the people who matter.
		return '<!-- immich_share: rejected URL (must be a public share on ' . esc_html( UPLINKSYNC_MEDIA_HOST ) . '/share/...) -->';
	}

	// Sanitise the ratio to a "W/H" of small positive integers; fall back to 16/9.
	$ratio = preg_replace( '/[^0-9\/]/', '', (string) $atts['ratio'] );
	if ( ! preg_match( '#^\d{1,2}/\d{1,2}$#', $ratio ) ) {
		$ratio = '16/9';
	}

	$title = sanitize_text_field( $atts['title'] );

	// Enqueue the embed stylesheet only when the shortcode is actually used.
	wp_enqueue_style(
		'uplinksync-immich-embed',
		get_stylesheet_directory_uri() . '/assets/css/immich-embed.css',
		array(),
		uplinksync_child_asset_ver( 'assets/css/immich-embed.css' )
	);

	$style = 'aspect-ratio: ' . esc_attr( str_replace( '/', ' / ', $ratio ) ) . ';';

	return sprintf(
		'<figure class="uls-immich-share"><div class="uls-immich-share__frame" style="%1$s">' .
		'<iframe src="%2$s" title="%3$s" loading="lazy" referrerpolicy="no-referrer-when-downgrade" ' .
		'allow="fullscreen" allowfullscreen sandbox="allow-scripts allow-same-origin allow-popups allow-forms"></iframe>' .
		'</div></figure>',
		$style,
		esc_url( $url ),
		esc_attr( $title )
	);
}
add_shortcode( 'immich_share', 'uplinksync_immich_share_shortcode' );
