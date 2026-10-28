<?php
/**
 * Title: Richer Quiet Light — Footer
 * Slug: uplinksync-child/site-footer
 * Categories: footer
 * Description: Site-wide footer (enrich/DESIGN-SPEC.md §3): brand blurb + credential chips, Services, Company, Client hub (from the inc/hub.php registry), legal row with email, the published phone (UPLINKSYNC_PHONE, hidden when empty) and the empty location slot.
 * Inserter: false
 */

$uls_phone = uplinksync_site_phone_link();
?>
<!-- wp:group {"className":"uls-ql-footer uls-on-dark","layout":{"type":"default"}} -->
<div class="wp-block-group uls-ql-footer uls-on-dark"><!-- wp:group {"className":"uls-ql-wrap","layout":{"type":"default"}} -->
<div class="wp-block-group uls-ql-wrap"><!-- wp:html -->
<div class="uls-ql-fcols">
	<div class="uls-ql-fbrand">
		<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/uplinksync-logo-lockup.webp' ); ?>" width="182" height="30" alt="UplinkSync" loading="lazy" decoding="async">
		<p>Managed IT, websites and FAA Part 107 aerial work — one owner-run team, plain English, a reply within one business day.</p>
		<ul class="uls-ql-fchips"><li>FAA Part 107</li><li>CompTIA A+</li><li>Fully insured</li></ul>
	</div>
	<nav aria-label="Services"><h2>Services</h2><ul><li><a href="/services/managed-it/">Managed IT &amp; security</a></li><li><a href="/services/#what">Microsoft 365 &amp; networking</a></li><li><a href="/services/web/">Websites &amp; hosting</a></li><li><a href="/drone-services/">Aerial photo &amp; video</a></li></ul></nav>
	<nav aria-label="Company"><h2>Company</h2><ul><li><a href="/about/">About</a></li><li><a href="/contact/">Contact</a></li><li><a href="/privacy-policy/">Privacy</a></li><li><a href="/terms/">Terms</a></li></ul></nav>
	<nav aria-label="Client hub"><h2>Client hub</h2><ul><li><a href="/hub/">Client Hub home</a></li><?php echo uplinksync_hub_footer_items(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in inc/hub.php. ?></ul></nav>
</div>
<!-- /wp:html -->

<!-- wp:pattern {"slug":"uplinksync-child/location-line"} /-->

<!-- wp:html -->
<div class="uls-ql-legal"><span><a href="mailto:contact@uplinksync.com">contact@uplinksync.com</a><?php echo $uls_phone; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in uplinksync_site_phone_link(). ?></span><span>© <?php echo esc_html( gmdate( 'Y' ) ); ?> UplinkSync LLC</span></div>
<!-- /wp:html --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
