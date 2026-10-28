<?php
/**
 * Title: Client Hub back bar
 * Slug: uplinksync-child/hub-backbar
 * Categories: uplinksync
 * Description: Slim "Back to Client Hub" + "Print shop" bar for the shop, collection and product pages (no page header; those pages bring their own).
 * Inserter: false
 */
?>
<!-- wp:html -->
<div class="rq store-top alignfull"><?php echo uplinksync_hub_backbar( 'store' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup. ?></div>
<!-- /wp:html -->
