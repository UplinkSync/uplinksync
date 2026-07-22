<?php
/**
 * Plugin Name: UplinkSync — Unified Service Architecture: connective spine (home)
 * Description: Fixes the owner's core complaint that the homepage opens as an IT company and then lurches straight into a drone product grid with no connective tissue (***-132, sprint ***-129). Rather than rebuild the page, this INJECTS ONE small section immediately before the "Our Services" heading — the exact IT→drone seam — carrying the approved positioning foundation verbatim (***-135, Peitho): the "two sensors, one system" umbrella intro and the Capture → Process → Deliver → Act spine graphic with both the IT and drone mappings side by side. That spine is the cohesion mechanism: it makes IT/MSP and drone/UAV read as stages of ONE pipeline instead of two bolted-together businesses. The heading and the WooCommerce grid below it are left completely untouched — this is additive only. Home markup is produced by the Hostinger AI theme + saved block content in the WP DB (NOT tracked files), so, like the sibling homepage fixes, this rewrites the rendered document on the way out: captured in-repo, theme-independent, idempotent, and a safe no-op if the anchor drifts. No invented facts or numbers; the single CTA is the consultative "Talk to a specialist" → canonical /contact/ (200, no redirect hop).
 *
 * INCIDENT NOTE (2026-07-22): the first attempt on this ticket blanked the
 * homepage. It ran a full-page rebuild that located the product-collection
 * block, walked balanced <div> depth, DELETED the whole block and injected a
 * replacement — a large, fragile ob_start transform running alongside the other
 * buffering mu-plugins. This version deliberately does the opposite: it only
 * ever str_replace()s a single, exact heading string with (that same string +
 * our section prepended). It removes nothing, parses no structure, and if the
 * anchor is absent it returns the document byte-for-byte unchanged. The
 * store→gallery grid conversion is a separate, later increment (its own MR).
 *
 * Version: 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exact heading string that opens the drone product grid on the live home page,
 * captured from the rendered document 2026-07-22. This is the IT→drone seam:
 * the IT narrative ends above it and the WooCommerce product grid begins below
 * it. We inject our connective section immediately BEFORE this heading, so the
 * visitor reads "two sensors, one system" + the shared pipeline before ever
 * reaching the services grid.
 *
 * The style/class attributes are part of the match so we bind to the header
 * instance specifically and never to some other "Our Services" text elsewhere.
 * If the theme output drifts and this exact string is absent, the rewrite is a
 * safe no-op (see uplinksync_unified_services_rewrite()).
 */
const UPLINKSYNC_UNIFIED_ANCHOR = '<h2 class="wp-block-heading has-text-align-center hostinger-ai-title has-x-large-font-size" style="margin-top:0;margin-bottom:var(--wp--preset--spacing--60)">Our Services</h2>';

/**
 * Marker used both as the idempotency guard and as the wrapper class. If it is
 * already present in the buffer, the section has been injected on this pass and
 * we must not inject it again (output buffers can run more than once, and a
 * double-apply would duplicate the section).
 */
const UPLINKSYNC_UNIFIED_MARKER = 'uplinksync-unified-services';

/**
 * Scope guard: front-end HTML GET, home page only. Mirrors the guards used by
 * the sibling homepage mu-plugins (logo, nav/CTA, contact/social) so all the
 * buffers agree on when they run.
 */
function uplinksync_unified_services_should_filter() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return false;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}
	if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
		return false;
	}
	if ( is_feed() || is_embed() || is_robots() || is_trackback() ) {
		return false;
	}
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) {
		return false;
	}
	return is_front_page();
}

function uplinksync_unified_services_start_buffer() {
	if ( ! uplinksync_unified_services_should_filter() ) {
		return;
	}
	// Priority 3 so this buffer opens AFTER contact/social (0), logo/nav (1) and
	// primary-nav (2). Buffers unwind LIFO, so ours closes first and the later
	// nav/CTA passes still see the finished document; our injected CTA links to
	// the canonical /contact/ directly, so it needs no rewriting by them.
	ob_start( 'uplinksync_unified_services_rewrite' );
}
add_action( 'template_redirect', 'uplinksync_unified_services_start_buffer', 3 );

/**
 * The one and only transform: prepend the connective section to the "Our
 * Services" heading. Additive, idempotent, and a byte-for-byte no-op when the
 * anchor is absent.
 *
 * @param string $html Buffered document.
 * @return string
 */
function uplinksync_unified_services_rewrite( $html ) {
	if ( ! is_string( $html ) || '' === $html ) {
		return $html;
	}
	// Idempotency: already injected on this (or a prior nested) pass — leave it.
	if ( false !== strpos( $html, UPLINKSYNC_UNIFIED_MARKER ) ) {
		return $html;
	}
	// Anchor absent (theme drift, grid removed at the DB layer, non-home doc that
	// slipped the guard) → return unchanged. We never mangle a document we do not
	// recognise.
	if ( false === strpos( $html, UPLINKSYNC_UNIFIED_ANCHOR ) ) {
		return $html;
	}

	$section = uplinksync_unified_services_markup();

	// Single, bounded replacement: prepend our section, keep the heading intact.
	// str_replace with a 4th "count" arg lets us assert exactly one hit happened.
	$count   = 0;
	$updated = str_replace(
		UPLINKSYNC_UNIFIED_ANCHOR,
		$section . UPLINKSYNC_UNIFIED_ANCHOR,
		$html,
		$count
	);
	if ( 1 !== $count ) {
		// Defensive: the anchor should be unique. If it somehow matched more than
		// once, do nothing rather than inject duplicates.
		return $html;
	}
	return $updated;
}

/**
 * The connective section markup. Copy is verbatim from the approved positioning
 * foundation (***-135, Peitho):
 *   - Umbrella idea: "two sensors on one system".
 *   - One outcome noun, reused: "clarity you can act on".
 *   - The Capture → Process → Deliver → Act spine with the IT and drone mappings
 *     side by side — the mechanism that makes the two sides converge.
 *   - One consultative CTA: "Talk to a specialist" → /contact/ (never "Get a
 *     Quote"; canonical slug, no redirect hop, no /product/*).
 *
 * No prices, no shop, no invented numbers, no owner-atomic claims. Design tokens
 * are the locked brand palette (visual-system.md §1). All classes are scoped
 * under .uplinksync-unified-services so nothing leaks into the theme's blocks.
 *
 * @return string
 */
function uplinksync_unified_services_markup() {
	$contact = 'https://uplinksync.com/contact/';

	// The four spine stages, each carrying both readings so IT and drone visibly
	// map onto the same pipeline. Text is verbatim from ***-135 §1.
	$spine = array(
		array(
			'verb' => 'Capture',
			'it'   => 'Monitor endpoints, networks, telemetry',
			'air'  => 'Fly the site; collect aerial imagery &amp; sensor data',
		),
		array(
			'verb' => 'Process',
			'it'   => 'Manage, secure, patch, automate',
			'air'  => 'Analyze into maps, 3D models, measurements',
		),
		array(
			'verb' => 'Deliver',
			'it'   => 'Support, reporting, dashboards',
			'air'  => 'Reports, orthomaps, inspection findings',
		),
		array(
			'verb' => 'Act',
			'it'   => 'Optimize, harden, automate the next step',
			'air'  => 'Decide — repair, plan, document, comply',
		),
	);

	ob_start();
	?>
<div class="uplinksync-unified-services alignfull" role="region" aria-label="How UplinkSync works — one pipeline, ground and air">
<style id="uplinksync-unified-services-css">
.uplinksync-unified-services{--uls-navy:#173258;--uls-navy700:#1F4375;--uls-accent:#5697F3;--uls-accent600:#2F6FC4;--uls-teal:#95D5DD;--uls-grey50:#F7F9FB;--uls-grey200:#E3E8ED;--uls-grey600:#5B6672;box-sizing:border-box;padding:64px 24px 8px;}
.uplinksync-unified-services *,.uplinksync-unified-services *::before,.uplinksync-unified-services *::after{box-sizing:border-box;}
.uplinksync-unified-services .uls-eyebrow{max-width:820px;margin:0 auto 12px;text-align:center;font-size:13px;letter-spacing:.08em;text-transform:uppercase;font-weight:700;color:var(--uls-accent600);}
.uplinksync-unified-services .uls-lede{max-width:820px;margin:0 auto;text-align:center;font-size:20px;line-height:1.55;color:var(--uls-navy);}
.uplinksync-unified-services .uls-lede strong{color:var(--uls-accent600);}
.uplinksync-unified-services .uls-spine{max-width:1160px;margin:36px auto 0;padding:40px 24px;background:var(--uls-navy);border-radius:14px;}
.uplinksync-unified-services .uls-spine__head{text-align:center;margin:0 auto 6px;font-size:24px;line-height:1.25;font-weight:700;color:#fff;}
.uplinksync-unified-services .uls-spine__sub{text-align:center;max-width:680px;margin:0 auto 28px;font-size:15px;line-height:1.55;color:var(--uls-teal);}
.uplinksync-unified-services .uls-spine__row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;align-items:stretch;}
.uplinksync-unified-services .uls-stage{background:var(--uls-navy700);border:1px solid rgba(149,213,221,.25);border-radius:10px;padding:18px;}
.uplinksync-unified-services .uls-stage__verb{display:flex;align-items:center;gap:8px;font-size:15px;font-weight:700;color:var(--uls-accent);letter-spacing:.05em;text-transform:uppercase;margin:0 0 12px;}
.uplinksync-unified-services .uls-stage__num{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:var(--uls-accent600);color:#fff;font-size:12px;line-height:1;}
.uplinksync-unified-services .uls-stage__leg{font-size:13px;line-height:1.45;color:#fff;margin:0 0 10px;}
.uplinksync-unified-services .uls-stage__leg:last-child{margin-bottom:0;}
.uplinksync-unified-services .uls-stage__leg span{display:block;font-size:10px;letter-spacing:.06em;text-transform:uppercase;font-weight:700;color:var(--uls-teal);margin-bottom:3px;}
.uplinksync-unified-services .uls-cta{max-width:1160px;margin:26px auto 0;text-align:center;}
.uplinksync-unified-services .uls-cta a{display:inline-block;background:var(--uls-accent600);color:#fff;font-weight:700;font-size:16px;line-height:1;text-decoration:none;padding:15px 32px;border-radius:8px;transition:background .15s ease;}
.uplinksync-unified-services .uls-cta a:hover,.uplinksync-unified-services .uls-cta a:focus-visible{background:var(--uls-navy);}
@media (max-width:900px){.uplinksync-unified-services .uls-spine__row{grid-template-columns:repeat(2,1fr);}}
@media (max-width:560px){.uplinksync-unified-services{padding:44px 18px 8px;}.uplinksync-unified-services .uls-lede{font-size:18px;}.uplinksync-unified-services .uls-spine{padding:28px 18px;}.uplinksync-unified-services .uls-spine__row{grid-template-columns:1fr;}}
</style>

	<p class="uls-eyebrow">Technology specialists — for the ground and the air</p>
	<p class="uls-lede">IT/MSP and drone/UAV are not two businesses. They are <strong>two sensors on one system</strong> — both capture information, turn it into something usable, and help you act on it. Managed IT reads your network; drones read your site. Same discipline, same deliverable: <strong>clarity you can act on.</strong></p>

	<div class="uls-spine">
		<h3 class="uls-spine__head">How we work — one pipeline, ground and air</h3>
		<p class="uls-spine__sub">Every service we run maps onto the same four stages, whether the sensor is on your network or over your site.</p>
		<div class="uls-spine__row">
			<?php $n = 1; foreach ( $spine as $stage ) : ?>
			<div class="uls-stage">
				<p class="uls-stage__verb"><span class="uls-stage__num" aria-hidden="true"><?php echo (int) $n; ?></span><?php echo esc_html( $stage['verb'] ); ?></p>
				<p class="uls-stage__leg"><span>IT / Automation / Web</span><?php echo wp_kses_post( $stage['it'] ); ?></p>
				<p class="uls-stage__leg"><span>Drone / UAV</span><?php echo wp_kses_post( $stage['air'] ); ?></p>
			</div>
			<?php $n++; endforeach; ?>
		</div>
	</div>

	<p class="uls-cta"><a href="<?php echo esc_url( $contact ); ?>">Talk to a specialist</a></p>
</div>
	<?php
	return (string) ob_get_clean();
}
