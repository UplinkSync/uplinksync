<?php
/**
 * Plugin Name: UplinkSync — Hide Header Login Icon (pre-launch)
 * Description: Hides the WooCommerce customer-account ("Login") icon in the site header while the photo store is pre-launch (***-357). Runs as an mu-plugin so it works regardless of active theme and is captured in-repo.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ***-357: the site header (uplinksync-child//header template part) renders a
 * WooCommerce `customer-account` icon with aria-label "Login". It points at
 * /my-account/, which currently 301-redirects to /contact/ — so a visible
 * "Login" affordance dumps the visitor on the contact form. There is no live
 * account system to send them to: the photo store is pre-launch and gated on
 * the owner's Stripe connect (***-178), so customer accounts are not a used
 * feature yet.
 *
 * Rather than leave a broken login target advertised, suppress the block on the
 * front end until the store goes live. This is intentionally the smallest,
 * fully reversible change: when accounts become real, delete this mu-plugin (or
 * flip the guard below) and the icon returns exactly as authored in the header
 * template part — no edit to the header markup itself is required.
 *
 * Implemented as a render_block filter so it is independent of how/where the
 * block is placed (header part is a DB-only custom template part, not in-repo).
 * Admin/editor are untouched so the block stays editable in the Site Editor.
 *
 * @param string $block_content The rendered block HTML.
 * @param array  $block         The parsed block.
 * @return string Empty string for the account block on the front end; unchanged otherwise.
 */
function uplinksync_hide_header_login_icon( $block_content, $block ) {
	if ( is_admin() ) {
		return $block_content;
	}
	if ( isset( $block['blockName'] ) && 'woocommerce/customer-account' === $block['blockName'] ) {
		return '';
	}
	return $block_content;
}
add_filter( 'render_block', 'uplinksync_hide_header_login_icon', 10, 2 );
