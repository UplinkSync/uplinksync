<?php
/**
 * Plugin Name: UplinkSync — Shop Gate (NEUTRALIZED for storefront launch, ***-366)
 * Description: The public WooCommerce storefront was launched 2026-07-30 by explicit owner authorization, reversing the prior ***-25 / ***-101 "hide the store, MSP-first" decision. This file previously 301-gated /shop, /product-category, /product-tag and /my-account into branded pages and force-noindexed the Woo shop/cart/checkout/account pages. It is intentionally NEUTRALIZED (no hooks) rather than deleted, because the deploy rsync runs WITHOUT --delete and a delete would not propagate. To RE-GATE, restore the v1.0.0 body from this MR's history / the recorded backup.
 * Version: 2.0.0-neutralized
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Intentionally no hooks. The public storefront (/shop, /product-category,
// /product-tag, /my-account) is live per ***-366. Re-gating = restore prior body.
