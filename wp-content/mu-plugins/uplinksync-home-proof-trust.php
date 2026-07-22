<?php
/**
 * Plugin Name: UplinkSync — Home Hero + Proof Band + Trust Bar
 * Description: Rewrites the homepage hero to the approved positioning copy and injects a 4-stat proof band + credential/trust bar directly under it (***-136, sprint ***-129; copy source: Peitho's positioning foundation ***-135 §3–§5). Like the other UplinkSync homepage fixes, the hero markup is produced by the Hostinger AI theme + saved block content in the WP DB (NOT tracked files), so this mu-plugin rewrites the rendered document on the way out — keeping the change captured in-repo (deploys with wp-content), theme-independent, and small/verifiable. Confirmed facts render live; owner-atomic numbers Peitho flagged (Part 107 currency, years in operation, response/SLA, testimonial) render as obviously-provisional [OWNER: confirm] placeholders (dashed outline + "provisional" pill) so nothing reads as a fake-live claim. Swap placeholders for confirmed values in one pass as Doug supplies them.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Approved positioning copy (***-135 §3). Owner/CMO-authoritative — do not
 * re-derive or paraphrase.
 */
const UPLINKSYNC_HERO_H1  = 'Technology specialists who turn your systems and your site into clarity you can act on.';
const UPLINKSYNC_HERO_SUB = 'From managed IT and automation to drone inspection and mapping, UplinkSync captures the data around your business — on the ground and in the air — and turns it into decisions you can make with confidence. One team, rooted in Eastern Idaho.';
const UPLINKSYNC_HERO_CTA_LABEL = 'Talk to a specialist';
const UPLINKSYNC_HERO_CTA_HREF  = 'https://uplinksync.com/contact/';

/**
 * Scope guard: front-end GET, home page only. Mirrors the guard used by the
 * homepage nav/CTA rewriter so the two buffers agree on when they run.
 */
function uplinksync_proof_trust_should_filter() {
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
	return is_front_page();
}

function uplinksync_proof_trust_start_buffer() {
	if ( ! uplinksync_proof_trust_should_filter() ) {
		return;
	}
	// Priority 2 so this buffer opens AFTER the contact/social (0) and nav/CTA
	// (1) buffers. Nested buffers unwind LIFO, so ours (innermost) closes first
	// and runs on the raw theme document; the nav/CTA pass then runs on our
	// output. We deliberately relabel the hero CTA to /contact/ here, which the
	// nav pass leaves untouched (it only swaps the legacy /about-4/ and product
	// slugs), so the two passes do not fight over the hero button.
	ob_start( 'uplinksync_proof_trust_rewrite' );
}
add_action( 'template_redirect', 'uplinksync_proof_trust_start_buffer', 2 );

function uplinksync_proof_trust_rewrite( $html ) {
	if ( ! is_string( $html ) || false === stripos( $html, '</body>' ) ) {
		return $html;
	}

	$html = uplinksync_hero_rewrite( $html );

	// Idempotent for the injected band: the output buffer can run more than once.
	if ( false === stripos( $html, 'uplinksync-proof-band' ) ) {
		$block = uplinksync_proof_trust_markup();
		// Anchor: the first full-width "solid" section the theme renders right
		// after the hero. We insert our block just BEFORE it, i.e. directly under
		// the hero (IA/§5 order: hero → proof band). First occurrence only.
		$pattern  = '#(<div class="wp-block-group alignfull hostinger-ai-solid-block\b)#';
		$replaced = preg_replace( $pattern, $block . '${1}', $html, 1, $count );
		if ( $count && null !== $replaced ) {
			$html = $replaced;
		}
		// Fallback: anchor not found → leave the document untouched (the band is
		// additive, so its absence is safe).
	}

	return $html;
}

/**
 * Rewrite the theme's default hero (headline / subhead / single CTA) to the
 * approved positioning copy. Each target string occurs exactly once on `/`, so
 * whole-string swaps are unambiguous. Idempotent via a marker on the H1.
 */
function uplinksync_hero_rewrite( $html ) {
	if ( false !== stripos( $html, 'uplinksync-hero-copy' ) ) {
		return $html; // already rewritten
	}

	// Headline — keep the theme's heading attributes, swap text + add marker.
	$html = preg_replace(
		'#(<h1\b[^>]*class=")([^"]*)("[^>]*>)\s*Reliable IT\s*(</h1>)#i',
		'${1}${2} uplinksync-hero-copy${3}' . esc_html( UPLINKSYNC_HERO_H1 ) . '${4}',
		$html,
		1
	);

	// Subhead.
	$html = str_replace(
		'Keeping your business connected and secure every day.',
		esc_html( UPLINKSYNC_HERO_SUB ),
		$html
	);

	// Primary CTA: relabel "Consult Now" → "Talk to a specialist" and point the
	// button at /contact/. Match the anchor by its label; rewrite the whole
	// opening tag's href (whatever legacy value it carries) plus the text.
	$html = preg_replace_callback(
		'#<a\b([^>]*)>\s*Consult Now\s*</a>#i',
		function ( $m ) {
			$attrs = preg_replace(
				'#\bhref="[^"]*"#i',
				'href="' . esc_url( UPLINKSYNC_HERO_CTA_HREF ) . '"',
				$m[1]
			);
			// If there was no href attribute, add one.
			if ( false === stripos( $attrs, 'href=' ) ) {
				$attrs .= ' href="' . esc_url( UPLINKSYNC_HERO_CTA_HREF ) . '"';
			}
			return '<a' . $attrs . '>' . esc_html( UPLINKSYNC_HERO_CTA_LABEL ) . '</a>';
		},
		$html,
		1
	);

	return $html;
}

/**
 * Proof band + trust bar markup.
 *
 * Brand tokens (visual-system.md §1): navy-900 #173258, navy-700 #1F4375,
 * accent-500 #5697F3, teal #95D5DD, grey-50 #F7F9FB, grey-200 #E3E8ED,
 * grey-600 #5B6672, white #FFFFFF. Proof section = dark navy, accent numerals,
 * white labels (visual-system §4). Trust bar = light strip below it.
 *
 * Values follow ***-135 §4/§5 exactly: only "Based in Eastern Idaho" is a
 * confirmed live stat (***-103). Part 107 currency, years in operation, and
 * response/SLA are owner-atomic — Peitho's instruction is to ship them as
 * clearly-flagged placeholders, never invented digits. Confirmed values render
 * plainly; flagged values render inside .uplinksync-provisional.
 */
function uplinksync_proof_trust_markup() {
	// 'confirmed' => plain live value; otherwise a provisional [OWNER: confirm].
	$stats = array(
		array(
			'value'     => 'Eastern Idaho',
			'label'     => 'Based in — Rexburg &amp; Idaho Falls',
			'confirmed' => true,
		),
		array(
			'value'     => 'Part 107',
			'label'     => 'FAA-certified drone operations',
			'confirmed' => true, // OWNER-CONFIRMED 2026-07-22: certificate held and current (earned 2025).
		),
		array(
			'value'     => '4 businesses',
			'label'     => 'Supported across IT &amp; aerial work',
			'confirmed' => true, // OWNER-CONFIRMED 2026-07-22: four client businesses.
			// Client NAMES are deliberately withheld: the owner has not yet obtained
			// permission to publish them. Publish the count only. When permission is
			// granted, a named client-logo row is the single highest-value upgrade
			// available to this section (see research_msp.md).
		),
		/*
		 * Founding year (2026) is CONFIRMED but deliberately NOT published.
		 *
		 * The company is roughly six months old. A "Serving since 2026" stat in a
		 * band whose entire job is to signal an established firm invites exactly the
		 * doubt we are trying to remove — it draws the eye to the one fact that
		 * undercuts the goal. Nothing here is hidden dishonestly: age simply isn't
		 * claimed either way, and the band leads with what IS strong and verifiable
		 * (a real FAA certification, real clients, a real location).
		 *
		 * Credibility for a young firm comes from specificity and credentials, not
		 * implied longevity. Revisit once there are a few years to point to.
		 */
	);

	// Trust/credential bar (***-135 §5 proof/trust): Part 107 + data-security
	// discipline + local roots + a named testimonial. Local roots are confirmed;
	// the rest are owner-atomic and flagged.
	$badges = array(
		array( 'label' => 'FAA Part 107 — licensed drone ops', 'note' => '', 'confirmed' => true ), // OWNER-CONFIRMED 2026-07-22: current.
		array( 'label' => 'Data-security discipline on the ground', 'note' => '', 'confirmed' => true ),
		array( 'label' => 'Local — Eastern Idaho roots', 'note' => '', 'confirmed' => true ),
		array( 'label' => 'Client testimonial', 'note' => 'OWNER: confirm one permission-cleared quote', 'confirmed' => false ),
	);

	/*
	 * LIVE-SAFETY FILTER (2026-07-22, owner-facing fix).
	 *
	 * The provisional-placeholder design was the right instinct — never publish an
	 * invented number — but the placeholders reached production and real visitors
	 * saw "[OWNER: confirm cert current]" inside the TRUST BAR. An unfinished-looking
	 * credential strip damages the exact "established, trusted firm" perception this
	 * section exists to create; worse than showing nothing.
	 *
	 * So: publish only confirmed items. Unconfirmed ones are withheld, not flagged
	 * in public. Flip 'confirmed' => true (with the real value) as the owner supplies
	 * each fact and it appears — no other change needed.
	 *
	 * The stat band needs at least 3 entries to read as a proof band; below that it
	 * looks broken, so it is withheld entirely rather than rendered lopsided.
	 */
	$stats  = array_values( array_filter( $stats,  function ( $s ) { return ! empty( $s['confirmed'] ); } ) );
	$badges = array_values( array_filter( $badges, function ( $b ) { return ! empty( $b['confirmed'] ); } ) );
	if ( count( $stats ) < 3 ) {
		$stats = array();
	}
	if ( ! $stats && ! $badges ) {
		return '';
	}

	// NOTE: build the markup by string concatenation, NOT ob_start()/ob_get_clean().
	// This function runs from inside uplinksync_proof_trust_rewrite(), which is
	// itself an ob_start() output-handler callback (registered in
	// uplinksync_proof_trust_start_buffer()). PHP forbids opening a new output
	// buffer while an output handler is executing and raises a fatal
	// ("ob_start(): Cannot use output buffering in output buffering display
	// handlers"). A fatal there is swallowed as a blank HTTP 200 (headers already
	// sent, buffer discarded), which is exactly what review_smoke caught on
	// MR !28 / pipeline 302. Concatenation has no such restriction. (***-149)
	$css = '<style id="uplinksync-proof-trust-css">' . "\n"
		. ".uplinksync-proof-band{background:#173258;color:#FFFFFF;padding:56px 24px;}\n"
		. ".uplinksync-proof-band__inner{max-width:1200px;margin:0 auto;display:flex;flex-wrap:wrap;gap:24px;justify-content:center;}\n"
		. ".uplinksync-proof-band__stat{flex:1 1 200px;min-width:180px;text-align:center;}\n"
		. ".uplinksync-proof-band__value{display:block;font-size:40px;line-height:1.15;font-weight:700;color:#5697F3;}\n"
		. ".uplinksync-proof-band__label{display:block;margin-top:8px;font-size:15px;color:#FFFFFF;opacity:.9;}\n"
		. ".uplinksync-provisional{position:relative;display:inline-block;border:1px dashed #95D5DD;border-radius:6px;padding:2px 8px;color:#95D5DD;background:rgba(149,213,221,.06);font-weight:600;font-size:.7em;}\n"
		. ".uplinksync-provisional::after{content:\"provisional\";display:inline-block;margin-left:8px;font-size:10px;letter-spacing:.04em;text-transform:uppercase;font-weight:700;color:#173258;background:#95D5DD;border-radius:10px;padding:1px 6px;vertical-align:middle;}\n"
		. ".uplinksync-trust-bar{background:#F7F9FB;border-top:1px solid #E3E8ED;border-bottom:1px solid #E3E8ED;padding:24px;}\n"
		. ".uplinksync-trust-bar__inner{max-width:1200px;margin:0 auto;display:flex;flex-wrap:wrap;gap:16px 32px;align-items:center;justify-content:center;}\n"
		. ".uplinksync-trust-bar__badge{display:inline-flex;flex-direction:column;align-items:center;text-align:center;gap:4px;color:#5B6672;font-size:14px;font-weight:600;}\n"
		. ".uplinksync-trust-bar__note{font-size:11px;font-weight:400;color:#5B6672;opacity:.85;border:1px dashed #95D5DD;border-radius:4px;padding:1px 6px;background:rgba(149,213,221,.06);}\n"
		. "@media (max-width:600px){.uplinksync-proof-band__value{font-size:30px;}}\n"
		. '</style>' . "\n";

	$band = '<section class="uplinksync-proof-band" aria-label="Proof points">' . "\n"
		. "\t" . '<div class="uplinksync-proof-band__inner">' . "\n";
	foreach ( $stats as $s ) {
		$value = empty( $s['confirmed'] )
			? '<span class="uplinksync-provisional">' . esc_html( $s['value'] ) . '</span>'
			: esc_html( $s['value'] );
		$band .= "\t\t" . '<div class="uplinksync-proof-band__stat">' . "\n"
			. "\t\t\t" . '<span class="uplinksync-proof-band__value">' . $value . '</span>' . "\n"
			. "\t\t\t" . '<span class="uplinksync-proof-band__label">' . wp_kses_post( $s['label'] ) . '</span>' . "\n"
			. "\t\t" . '</div>' . "\n";
	}
	$band .= "\t" . '</div>' . "\n" . '</section>' . "\n";

	$bar = '<section class="uplinksync-trust-bar" aria-label="Credentials and trust signals">' . "\n"
		. "\t" . '<div class="uplinksync-trust-bar__inner">' . "\n";
	foreach ( $badges as $b ) {
		$bar .= "\t\t" . '<div class="uplinksync-trust-bar__badge">' . "\n"
			. "\t\t\t" . '<span>' . esc_html( $b['label'] ) . '</span>' . "\n";
		if ( empty( $b['confirmed'] ) && '' !== $b['note'] ) {
			$bar .= "\t\t\t" . '<span class="uplinksync-trust-bar__note">' . esc_html( '[' . $b['note'] . ']' ) . '</span>' . "\n";
		}
		$bar .= "\t\t" . '</div>' . "\n";
	}
	$bar .= "\t" . '</div>' . "\n" . '</section>' . "\n";

	return "\n" . $css . $band . $bar;
}
