<?php
/**
 * Plugin Name: UplinkSync — Unified Service Architecture (home)
 * Description: Rebuilds the homepage "Our Services" section so IT/MSP and drone/UAV read as ONE company's service architecture instead of two bolted-together sites (***-132, sprint ***-129). The live home page ends the IT narrative and then drops the visitor into a WooCommerce product grid of $299 "Drone Aerial Capture / Package / Inspection" tiles — an abrupt "IT service -> drone store" cutover that makes the site read as a shop. Per the positioning foundation (***-135, Peitho) drone is a capability/gallery, not a store, and the shop stays hidden. This mu-plugin swaps that product-collection grid for a unified "What we do" service family (the four real services, each tagged to the Capture -> Process -> Deliver -> Act spine and closing on the one outcome noun "clarity you can act on"), and injects the spine graphic — the cohesion mechanism that makes disparate services read as stages of one pipeline. Home hero/nav markup is produced by the Hostinger AI theme + saved block content in the WP DB (NOT tracked files), so, like the sibling homepage fixes, this rewrites the rendered document on the way out: captured in-repo, theme-independent, idempotent, safe fallback if the anchor drifts. No invented contact details or numbers; drone card routes to the canonical /services/ (200) because /drone-services/ still 301s into the hidden store.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scope guard: front-end GET, home page only. Mirrors the guard used by the
 * sibling homepage nav/CTA and proof-band rewriters so the buffers agree on
 * when they run.
 */
function uplinksync_unified_services_should_filter() {
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

function uplinksync_unified_services_start_buffer() {
	if ( ! uplinksync_unified_services_should_filter() ) {
		return;
	}
	// Priority 3 so this buffer opens AFTER contact/social (0), nav/CTA (1) and
	// proof/trust (2). Buffers unwind LIFO so ours closes first; the nav/CTA pass
	// (priority 1) still sees the finished document and rewrites any /product/*
	// or /drone-services/ links, including the ones this block emits, on its way
	// out. We deliberately depend on that: the drone card links to /services/
	// directly, but even if the DB content regresses the nav pass is a backstop.
	ob_start( 'uplinksync_unified_services_rewrite' );
}
add_action( 'template_redirect', 'uplinksync_unified_services_start_buffer', 3 );

/**
 * Return [start, end) byte offsets of the balanced <div> block that begins at
 * $open_pos (which must point at a literal "<div"). Returns false if the block
 * is unbalanced (never happens on well-formed theme output, but we bail safely
 * rather than mangle the document).
 */
function uplinksync_unified_balanced_div( $html, $open_pos ) {
	$len   = strlen( $html );
	$depth = 0;
	$i     = $open_pos;
	while ( $i < $len ) {
		$lt = strpos( $html, '<', $i );
		if ( false === $lt ) {
			return false;
		}
		if ( 0 === substr_compare( $html, '<div', $lt, 4 )
			&& isset( $html[ $lt + 4 ] )
			&& ( '>' === $html[ $lt + 4 ] || false !== strpos( " \t\r\n\f\v/", $html[ $lt + 4 ] ) ) ) {
			$depth++;
			$i = $lt + 4;
		} elseif ( 0 === substr_compare( $html, '</div>', $lt, 6 ) ) {
			$depth--;
			$i = $lt + 6;
			if ( 0 === $depth ) {
				return array( $open_pos, $i );
			}
		} else {
			$i = $lt + 1;
		}
	}
	return false;
}

function uplinksync_unified_services_rewrite( $html ) {
	if ( ! is_string( $html ) || false === stripos( $html, '</body>' ) ) {
		return $html;
	}
	// Idempotent: the output buffer can run more than once.
	if ( false !== stripos( $html, 'uplinksync-unified-services' ) ) {
		return $html;
	}

	// 1) Retitle the section heading. The saved block renders the section as
	//    "Our Services" (a store framing). Reframe it as one service family.
	//    Match the exact heading text inside its h2 so we do not touch any other
	//    "Our Services" occurrence.
	$html = str_replace(
		'>Our Services</h2>',
		'>What we do — two sensors, one system</h2>',
		$html
	);

	// 2) Replace the WooCommerce product-collection grid (the $299 drone tiles)
	//    with the unified service family + the Capture -> Process -> Deliver ->
	//    Act spine. Locate the product-collection block's own <div, then remove
	//    the whole balanced block and inject in its place.
	$marker = 'data-block-name="woocommerce/product-collection"';
	$mpos   = strpos( $html, $marker );
	if ( false === $mpos ) {
		// Anchor drifted (grid removed at the DB layer, or theme changed). The
		// heading reframe above is still a net improvement; return safely.
		return $html;
	}
	$open_pos = strrpos( substr( $html, 0, $mpos ), '<div' );
	if ( false === $open_pos ) {
		return $html;
	}
	$span = uplinksync_unified_balanced_div( $html, $open_pos );
	if ( false === $span ) {
		return $html;
	}
	list( $start, $end ) = $span;

	$block = uplinksync_unified_services_markup();
	$html  = substr( $html, 0, $start ) . $block . substr( $html, $end );

	return $html;
}

/**
 * The unified service-family + spine markup that replaces the store grid.
 *
 * Copy is verbatim from the positioning foundation (***-135, Peitho):
 *   - One outcome noun, reused: "clarity you can act on".
 *   - The four real services shown as one family, each tagged to the spine and
 *     closing with its Act outcome (no "shop", no prices, no /product/*).
 *   - The Capture -> Process -> Deliver -> Act spine graphic with the IT and
 *     drone mappings side by side: this is the cohesion mechanism that makes IT
 *     and drone feel like stages of one pipeline rather than two businesses.
 *   - One consultative CTA: "Talk to a specialist" -> /contact/ (never "Get a
 *     Quote"; canonical slug, no redirect hop).
 *
 * Design tokens are the locked brand palette (visual-system.md §1 / tokens.css):
 *   navy-900 #173258, navy-700 #1F4375, accent-500 #5697F3,
 *   accent-600 #2F6FC4 (AA on white), accent-teal #95D5DD,
 *   grey-50 #F7F9FB, grey-200 #E3E8ED, grey-600 #5B6672.
 * Classes are scoped under .uplinksync-unified-services so nothing leaks into
 * the theme's own blocks.
 */
function uplinksync_unified_services_markup() {
	$contact  = 'https://uplinksync.com/contact/';
	$services = array(
		array(
			'title' => 'Managed IT Services',
			'href'  => 'https://uplinksync.com/services/managed-it/',
			'desc'  => 'Endpoint setup, monitoring, security and support that keeps your business running — day in, day out.',
			'act'   => 'Systems you can trust, and clarity you can act on.',
		),
		array(
			'title' => 'Business Automation',
			'href'  => 'https://uplinksync.com/services/automation/',
			'desc'  => 'Tailored workflow integrations that remove the manual steps and let your team focus on the work that matters.',
			'act'   => 'Less busywork, and clarity you can act on.',
		),
		array(
			'title' => 'Web Development',
			'href'  => 'https://uplinksync.com/services/web/',
			'desc'  => 'Fast, secure, reliable sites and hosting that keep your online presence working as hard as you do.',
			'act'   => 'A presence that performs — clarity you can act on.',
		),
		array(
			'title' => 'Drone &amp; Aerial (UAV)',
			// Canonical services overview: /drone-services/ still 301s into the
			// hidden store, and the shop stays hidden, so the drone capability
			// links to /services/ (200, no /product/*). A capability/gallery, not
			// a shop — no prices, no "add to cart".
			'href'  => 'https://uplinksync.com/services/',
			'desc'  => 'FAA Part 107 flights for inspection, mapping and site documentation — turned into reports, orthomaps and 3D models.',
			'act'   => 'A decision you can make — clarity you can act on.',
		),
	);

	// The spine, with both mappings. Each stage carries the IT reading and the
	// drone reading so the two sides visibly converge on the same pipeline.
	$spine = array(
		array(
			'verb' => 'Capture',
			'it'   => 'Monitor endpoints, networks and telemetry',
			'air'  => 'Fly the site; collect aerial imagery &amp; sensor data',
		),
		array(
			'verb' => 'Process',
			'it'   => 'Manage, secure, patch and automate',
			'air'  => 'Analyze into maps, 3D models and measurements',
		),
		array(
			'verb' => 'Deliver',
			'it'   => 'Support, reporting and dashboards',
			'air'  => 'Reports, orthomaps and inspection findings',
		),
		array(
			'verb' => 'Act',
			'it'   => 'Optimize, harden and automate the next step',
			'air'  => 'Decide — repair, plan, document, comply',
		),
	);

	ob_start();
	?>
<div class="uplinksync-unified-services">
<style id="uplinksync-unified-services-css">
.uplinksync-unified-services{--uls-navy:#173258;--uls-navy700:#1F4375;--uls-accent:#5697F3;--uls-accent600:#2F6FC4;--uls-teal:#95D5DD;--uls-grey50:#F7F9FB;--uls-grey200:#E3E8ED;--uls-grey600:#5B6672;}
.uplinksync-unified-services .uls-intro{max-width:760px;margin:0 auto 40px;text-align:center;font-size:18px;line-height:1.6;color:var(--uls-grey600);padding:0 24px;}
.uplinksync-unified-services .uls-cards{max-width:1200px;margin:0 auto;display:grid;grid-template-columns:repeat(4,1fr);gap:20px;padding:0 24px;}
.uplinksync-unified-services .uls-card{display:flex;flex-direction:column;background:#fff;border:1px solid var(--uls-grey200);border-radius:8px;padding:24px;transition:box-shadow .15s ease,transform .15s ease;}
.uplinksync-unified-services .uls-card:hover{box-shadow:0 8px 24px rgba(23,50,88,.12);transform:translateY(-2px);}
.uplinksync-unified-services .uls-card h3{margin:0 0 8px;font-size:20px;line-height:1.25;font-weight:700;color:var(--uls-navy);}
.uplinksync-unified-services .uls-card__desc{margin:0 0 16px;font-size:15px;line-height:1.55;color:var(--uls-grey600);flex:1 1 auto;}
.uplinksync-unified-services .uls-card__spine{font-size:11px;letter-spacing:.06em;text-transform:uppercase;font-weight:700;color:var(--uls-accent600);margin:0 0 10px;}
.uplinksync-unified-services .uls-card__act{margin:0 0 16px;font-size:14px;line-height:1.5;color:var(--uls-navy);}
.uplinksync-unified-services .uls-card__act strong{color:var(--uls-accent600);}
.uplinksync-unified-services .uls-card__link{margin-top:auto;font-size:14px;font-weight:700;color:var(--uls-accent600);text-decoration:none;}
.uplinksync-unified-services .uls-card__link:hover{text-decoration:underline;}
.uplinksync-unified-services .uls-spine{max-width:1200px;margin:56px auto 0;padding:40px 24px;background:var(--uls-navy);border-radius:12px;color:#fff;}
.uplinksync-unified-services .uls-spine__head{text-align:center;margin:0 auto 8px;font-size:24px;font-weight:700;color:#fff;}
.uplinksync-unified-services .uls-spine__sub{text-align:center;max-width:640px;margin:0 auto 32px;font-size:15px;line-height:1.55;color:var(--uls-teal);}
.uplinksync-unified-services .uls-spine__row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;}
.uplinksync-unified-services .uls-stage{background:var(--uls-navy700);border:1px solid rgba(149,213,221,.25);border-radius:8px;padding:16px;position:relative;}
.uplinksync-unified-services .uls-stage__verb{font-size:15px;font-weight:700;color:var(--uls-accent);letter-spacing:.04em;text-transform:uppercase;margin:0 0 12px;}
.uplinksync-unified-services .uls-stage__leg{font-size:13px;line-height:1.45;color:#fff;margin:0 0 8px;}
.uplinksync-unified-services .uls-stage__leg span{display:block;font-size:10px;letter-spacing:.06em;text-transform:uppercase;font-weight:700;color:var(--uls-teal);margin-bottom:2px;}
.uplinksync-unified-services .uls-cta{max-width:1200px;margin:40px auto 0;text-align:center;padding:0 24px;}
.uplinksync-unified-services .uls-cta a{display:inline-block;background:var(--uls-accent600);color:#fff;font-weight:700;font-size:16px;text-decoration:none;padding:14px 32px;border-radius:8px;transition:background .15s ease;}
.uplinksync-unified-services .uls-cta a:hover{background:var(--uls-navy);}
@media (max-width:960px){.uplinksync-unified-services .uls-cards{grid-template-columns:repeat(2,1fr);}.uplinksync-unified-services .uls-spine__row{grid-template-columns:repeat(2,1fr);}}
@media (max-width:560px){.uplinksync-unified-services .uls-cards{grid-template-columns:1fr;}.uplinksync-unified-services .uls-spine__row{grid-template-columns:1fr;}}
</style>

	<p class="uls-intro">IT/MSP and drone/UAV are not two businesses. They are two sensors on one system — both capture information, turn it into something usable, and help you act on it. Same discipline, same deliverable: <strong>clarity you can act on.</strong></p>

	<div class="uls-cards">
		<?php foreach ( $services as $s ) : ?>
		<div class="uls-card">
			<p class="uls-card__spine">Capture &rarr; Process &rarr; Deliver &rarr; Act</p>
			<h3><?php echo wp_kses_post( $s['title'] ); ?></h3>
			<p class="uls-card__desc"><?php echo wp_kses_post( $s['desc'] ); ?></p>
			<p class="uls-card__act"><strong>Act:</strong> <?php echo wp_kses_post( $s['act'] ); ?></p>
			<a class="uls-card__link" href="<?php echo esc_url( $s['href'] ); ?>">Explore <?php echo wp_kses_post( $s['title'] ); ?> &rarr;</a>
		</div>
		<?php endforeach; ?>
	</div>

	<div class="uls-spine" aria-label="How we work — one pipeline, ground and air">
		<h3 class="uls-spine__head">How we work — one pipeline, ground and air</h3>
		<p class="uls-spine__sub">Every service we run maps onto the same four stages. Managed IT reads your network; drones read your site — same discipline, same deliverable.</p>
		<div class="uls-spine__row">
			<?php foreach ( $spine as $stage ) : ?>
			<div class="uls-stage">
				<p class="uls-stage__verb"><?php echo esc_html( $stage['verb'] ); ?></p>
				<p class="uls-stage__leg"><span>IT / Automation / Web</span><?php echo wp_kses_post( $stage['it'] ); ?></p>
				<p class="uls-stage__leg"><span>Drone / UAV</span><?php echo wp_kses_post( $stage['air'] ); ?></p>
			</div>
			<?php endforeach; ?>
		</div>
	</div>

	<p class="uls-cta"><a href="<?php echo esc_url( $contact ); ?>">Talk to a specialist</a></p>
</div>
	<?php
	return ob_get_clean();
}
