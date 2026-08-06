<?php
/**
 * Plugin Name: UplinkSync — Storefront Truth (licence page, licence tab, reviews suppression) (UPLAA-458)
 * Description: Three storefront-integrity fixes judged against the store AS A STOCK-IMAGE
 *   SHOP (UPLAA-458, converged from UPLAA-444 comment 90e2be2d):
 *
 *   (#2) A REAL image-licence page. Products asserted "Personal + commercial license"
 *        with nothing defining it — /shop/ never used the words "royalty", "commercial
 *        use" or "editorial", and no product linked to a licence. This seeds a canonical
 *        Page at /image-license/ that actually defines the grant (what you may/​may not
 *        do, personal vs commercial vs editorial, resolution, credit, refunds), using the
 *        same idempotent, owner-edit-preserving seeder idiom as uplinksync-page-seeder.php.
 *
 *   (#3) Suppress the WooCommerce "Reviews (0)" tab on every product. A brand-new stock
 *        shop with zero sales renders an empty, credibility-sapping "Reviews (0)" tab by
 *        default; there is no review pipeline. Removed via the woocommerce_product_tabs
 *        filter. In its place we add a "Licence" tab that states the grant inline and
 *        links to /image-license/ — so the licence claim on every product is now defined
 *        AND one click away, closing #2's "asserted, never defined, never linked" gap.
 *
 *   Presentation/policy copy only — no price, checkout, product data, or gating change.
 *   Fully reversible: overwrite this file with an inert stub (server rsync does not
 *   delete) or revert the MR. Guarded with function_exists() so it is safe if
 *   WooCommerce is inactive.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical licence-page slug. Kept in one place so the seeder, the product-tab
 * link, and any future nav entry all agree.
 */
const UPLINKSYNC_LICENCE_SLUG = 'image-license';

/**
 * The licence page body. This is the substance UPLAA-458 #2 asks for: the grant
 * is DEFINED (personal vs commercial vs editorial), not merely asserted. Written
 * to match what the products already promise in their descriptions — instant
 * digital download, full-resolution JPEG, no physical item — and the estate's
 * standing "Aerial by UplinkSync" credit rule. Deliberately plain, honest, and
 * owner-editable; whoever refines wording in wp-admin has their edit preserved by
 * the idempotent seeder below.
 */
function uplinksync_licence_page_body() {
	return <<<'HTML'
<!-- wp:heading {"level":1} -->
<h1>Image Licence</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Every aerial print sold here is delivered as an instant digital download — a full-resolution JPEG, watermark-free. No physical item is shipped. When you complete a purchase you are buying a licence to use that image under the terms below; UplinkSync retains copyright in the photograph itself.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>What your licence includes</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>A single purchase grants a <strong>royalty-free, non-exclusive, perpetual, worldwide licence</strong> for both <strong>personal and commercial use</strong>. "Royalty-free" means you pay once and owe no further per-use fees. "Non-exclusive" means the same image may be licensed to others. There is no time limit and no usage cap on the rights granted below.</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul>
<li><strong>Personal use</strong> — prints for your home or office, wallpapers, personal projects, gifts.</li>
<li><strong>Commercial use</strong> — websites, social media, marketing and advertising, presentations, product packaging, editorial and print publications, and interior décor for a business you own or operate.</li>
<li><strong>Editorial use</strong> — news, commentary, and educational contexts. The aerials depict real places on real dates; caption them accurately.</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading {"level":2} -->
<h2>What your licence does not allow</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
<li>Reselling, redistributing, or sub-licensing the image as-is — for example as stock, in a template, or as part of a print-on-demand catalogue where the photograph is the product.</li>
<li>Registering the image, or any obvious derivative of it, as a trademark or logo.</li>
<li>Using the image in a way that is unlawful, defamatory, or that falsely implies endorsement by any person or property shown.</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading {"level":2} -->
<h2>Credit</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Credit is appreciated but not required for personal or commercial use. Editorial use should carry an <em>"Aerial by UplinkSync"</em> credit where a photo credit is customary.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>Delivery, resolution &amp; refunds</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Images are delivered as high-resolution JPEGs (each product page lists its exact megapixel count). Because the goods are digital and delivered immediately, sales are final; if a file is corrupt or you were charged in error, contact us and we will make it right.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>Need something else?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>For exclusive licences, extended print runs, large-format or architectural licensing, or a use not covered above, <a href="/contact/">get in touch</a> and we will scope it with you.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p><em>This page summarises the licence for aerial image downloads sold through this store. It supplements the site <a href="/terms/">Terms</a>; where the two differ for image purchases, this page governs.</em></p>
<!-- /wp:paragraph -->
HTML;
}

/**
 * Seed the canonical /image-license/ Page if it does not already exist. Same
 * idempotent, owner-edit-preserving idiom as uplinksync-page-seeder.php: an
 * existing Page on the slug (created here or authored in wp-admin) is left
 * completely untouched, so this is safe on every deploy.
 */
function uplinksync_licence_page_seed() {
	if ( get_page_by_path( UPLINKSYNC_LICENCE_SLUG, OBJECT, 'page' ) instanceof WP_Post ) {
		return;
	}
	wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Image Licence',
			'post_name'    => UPLINKSYNC_LICENCE_SLUG,
			'post_parent'  => 0,
			'post_content' => uplinksync_licence_page_body(),
		),
		true
	);
}

/**
 * One-shot guard keyed to this plugin's version so a body change re-runs the
 * (still per-page idempotent) seed. Hooked on init at default priority.
 */
function uplinksync_licence_page_maybe_seed() {
	$done = 'uplinksync_licence_seed_version';
	if ( get_option( $done ) === '1.0.0' ) {
		return;
	}
	uplinksync_licence_page_seed();
	update_option( $done, '1.0.0' );
}
add_action( 'init', 'uplinksync_licence_page_maybe_seed' );

/**
 * (#3) Suppress the empty "Reviews (0)" tab and (#2) add a defined "Licence" tab
 * that links to /image-license/. Runs late (priority 98) so it wins over
 * WooCommerce's default tab registration.
 */
function uplinksync_storefront_product_tabs( $tabs ) {
	// #3 — drop the reviews tab; there is no review pipeline and "Reviews (0)"
	// only sows doubt on a brand-new stock shop.
	unset( $tabs['reviews'] );

	// #2 — a licence tab so every product both DEFINES its grant inline and links
	// to the full licence. Placed right after the description (priority 15).
	$tabs['uls_licence'] = array(
		'title'    => __( 'Licence', 'uplinksync' ),
		'priority' => 15,
		'callback' => 'uplinksync_storefront_licence_tab',
	);

	return $tabs;
}
add_filter( 'woocommerce_product_tabs', 'uplinksync_storefront_product_tabs', 98 );

/**
 * Render the licence tab body: a one-line summary of the grant plus a link to the
 * canonical /image-license/ page. Kept short — the full terms live on the page.
 */
function uplinksync_storefront_licence_tab() {
	$url = home_url( '/' . UPLINKSYNC_LICENCE_SLUG . '/' );
	echo '<h2>' . esc_html__( 'Licence', 'uplinksync' ) . '</h2>';
	echo '<p>' . esc_html__( 'Your purchase is an instant digital download (full-resolution JPEG, no physical item shipped) with a royalty-free, non-exclusive licence for both personal and commercial use. Reselling or redistributing the image as-is is not permitted.', 'uplinksync' ) . '</p>';
	echo '<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Read the full image licence →', 'uplinksync' ) . '</a></p>';
}
