<?php
/**
 * Plugin Name: UplinkSync — Quote-Only (NEUTRALIZED for storefront launch, ***-366)
 * Description: The public WooCommerce storefront was launched 2026-07-30 by explicit owner authorization. This file previously suppressed all WooCommerce pricing and add-to-cart affordances on the front end (quote-only, per the 2026-07-22 "no public pricing" directive) and repointed CTAs at /contact/. With the store live and public cart/checkout enabled, pricing and add-to-cart must render, so this is intentionally NEUTRALIZED (no hooks) rather than deleted (deploy rsync has no --delete). To RE-GATE, restore the v1.1.0 body from this MR's history / the recorded backup.
 * Version: 2.0.0-neutralized
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Intentionally no hooks. Prices and add-to-cart are public per ***-366.
// Re-gating = restore prior body.
