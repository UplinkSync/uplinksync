<?php
/**
 * Title: Richer Quiet Light — Store / account page header
 * Slug: uplinksync-child/store-head
 * Categories: uplinksync
 * Description: "Back to Client Hub" bar plus a slim navy page header for WooCommerce pages (My Account, cart, checkout, order received). Wrapped in .rq so the Richer Quiet Light rules style only this header; the WooCommerce content below keeps woocommerce.css.
 * Inserter: false
 */
// The header text depends on the query (account / cart / checkout / order received). Pattern files
// are included lazily and their content can be cached for the request before the main query runs
// (review L13), so this pattern only carries a token; inc/hub.php expands it in a
// render_block_core/html filter at render time, after the main query.
?>
<!-- wp:html -->
[uls_store_head]
<!-- /wp:html -->
