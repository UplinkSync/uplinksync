<?php
/**
 * Title: Richer Quiet Light — Print shop (collections)
 * Slug: uplinksync-child/site-prints
 * Categories: uplinksync
 * Description: /prints/ body: aerial photo hero, the print collections rendered live from the product categories (covers are the products' watermarked previews, never a "-master-" rendition, which wp-content/.htaccess denies), full-catalog link and a licensing CTA.
 * Inserter: false
 */

$uls_cols = uplinksync_hub_collections();
?>
<!-- wp:html -->
<?php echo uplinksync_hub_backbar(); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup. ?>
<section class="hero dark phero slim" aria-labelledby="h1">
	<div class="hero-media"><?php echo uplinksync_site_img( 'uplinksync-hero-aerial-sunset', UPLINKSYNC_SITE_WIDE, '', '100vw', true ); // phpcs:ignore ?></div>
	<div class="wrap"><div>
		<p class="eyebrow">Aerial print shop</p>
		<h1 id="h1">Aerial prints, browsed by collection.</h1>
		<p class="lede">Every frame is our own drone work: color-graded, high-resolution and licensed for print. Choose a collection to open its gallery. Full-resolution files are delivered on purchase.</p>
	</div></div>
</section>
<section class="sec prints" aria-labelledby="coll-h"><div class="wrap">
	<div class="sec-head row"><div><p class="eyebrow">Collections</p><h2 id="coll-h">Choose a collection</h2><p>Watermarked previews are shown while you browse.</p></div><a class="tlink" href="/shop/">Browse the full catalog <?php echo uplinksync_site_arrow(); // phpcs:ignore ?></a></div>
	<?php echo uplinksync_hub_collection_cards( $uls_cols ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in inc/hub.php. ?>
	<p class="prints-acct">Bought a print before? Your files are in <a href="/my-account/downloads/">your store account</a>.</p>
</div></section>
<?php echo uplinksync_site_cta( 'Licensing something specific?', 'Tell us the place and the use and we’ll scope it: commercial and large-format licensing on request. Or email ' . uplinksync_site_email_link() . '.', UPLINKSYNC_SITE_BOOK_UAV ); // phpcs:ignore ?>
<!-- /wp:html -->
