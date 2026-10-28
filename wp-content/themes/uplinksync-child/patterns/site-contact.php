<?php
/**
 * Title: Richer Quiet Light — Contact
 * Slug: uplinksync-child/site-contact
 * Categories: uplinksync
 * Description: /contact/ body (enrich/DESIGN-SPEC.md §5): slim photo hero with promise chips, the master quote form (CF7, inc/quote-form-seed.php) in a card overlapping the hero, side panels (email, phone if published, book a call, client hub), what happens next, CTA.
 * Inserter: false
 */

$uls_a     = uplinksync_site_arrow();
$uls_form  = shortcode_exists( 'uplinksync_quote_form' ) ? do_shortcode( '[uplinksync_quote_form]' ) : '';
$uls_phone = uplinksync_site_phone_link( 'big-mail' );
?>
<!-- wp:html -->
<section class="hero dark phero slim" aria-labelledby="h1">
	<div class="hero-media"><?php echo uplinksync_site_img( 'uplinksync-hero-aerial-sunset', UPLINKSYNC_SITE_WIDE, '', '100vw', true ); // phpcs:ignore ?></div>
	<div class="wrap"><div>
		<p class="eyebrow">Direct · human · one business day</p>
		<h1 id="h1"><?php echo uplinksync_site_slot( 'contact_h1', 'Get a free quote.' ); // phpcs:ignore ?></h1>
		<p class="lede"><?php echo uplinksync_site_lede( 'Tell us what you’re dealing with and a person will reply within one business day. No obligation, no sales script.' ); // phpcs:ignore ?></p>
		<ul class="hero-meta"><li><?php echo uplinksync_site_icon( 'clock', 18 ); // phpcs:ignore ?>Reply within one business day</li><li><?php echo uplinksync_site_icon( 'card', 18 ); // phpcs:ignore ?>Free, written quote</li><li><?php echo uplinksync_site_icon( 'shield', 18 ); // phpcs:ignore ?>Certified &amp; insured</li></ul>
	</div></div>
</section>
<section class="sec flush contact-wrap" aria-label="Contact form"><div class="wrap contact-grid">
	<div class="formcard" id="quote">
		<h2>Tell us about your project</h2>
		<?php
		if ( '' !== trim( wp_strip_all_tags( $uls_form ) ) || false !== strpos( $uls_form, '<form' ) ) {
			echo $uls_form; // phpcs:ignore WordPress.Security.EscapeOutput -- CF7 output.
		} else {
			echo '<p class="muted">Email <a href="mailto:contact@uplinksync.com">contact@uplinksync.com</a> and a person will reply within one business day.</p>';
		}
		?>
	</div>
	<div class="side">
		<div class="panel dk"><span class="ico"><?php echo uplinksync_site_icon( 'mail', 20 ); // phpcs:ignore ?></span><h3>Prefer to reach out directly?</h3><a class="big-mail" href="mailto:contact@uplinksync.com">contact@uplinksync.com</a><?php echo $uls_phone ? '<br>' . $uls_phone : ''; // phpcs:ignore ?><p>We typically reply within one business day.</p></div>
		<div class="panel"><span class="ico lt"><?php echo uplinksync_site_icon( 'cal', 20 ); // phpcs:ignore ?></span><h3>Rather book a time?</h3><p>Pick a 15-minute slot and talk it through.</p><a class="btn btn-outline" href="<?php echo esc_url( UPLINKSYNC_SITE_BOOK_IT ); ?>">Book a 15-minute call</a></div>
		<div class="panel"><span class="ico lt"><?php echo uplinksync_site_icon( 'user', 20 ); // phpcs:ignore ?></span><h3>Already a client?</h3><p><a href="https://billing.uplinksync.com/client/login">Pay an invoice</a> · <a href="https://media.uplinksync.com">Your photos &amp; video</a> · <a href="/prints/">Print shop</a></p></div>
	</div>
</div></section>
<?php
echo uplinksync_site_steps( // phpcs:ignore
	'After you send',
	'What happens next',
	'The simple path from your first message to a free quote you can act on.',
	array(
		array( 'Tell us what’s going on', 'Share your project through the form, by email, or book a time — whatever’s easiest for you.', 'About 2 minutes' ),
		array( 'A person replies within one business day', 'Every request is tracked, and you deal with the same person who does the work.', 'Within one business day' ),
		array( 'You get a clear, free quote', 'We scope the work and send it in writing, so you know exactly what you’re getting.', 'No obligation' ),
	),
	'st-h'
);
?>
<section class="sec flush" aria-labelledby="fq-h"><div class="wrap">
	<div class="sec-head"><p class="eyebrow">Common questions</p><h2 id="fq-h">Before you send</h2></div>
	<div class="faq">
		<details><summary>Is the quote really free?</summary><p>Yes. You get a scope, a price range and a timeline in writing, with no obligation.</p></details>
		<details><summary>Do you work remotely?</summary><p>Mostly, because it is faster and cheaper for you. On-site visits are available by arrangement when a job needs hands-on attention.</p></details>
		<details><summary>I’m an existing client. Where do I go?</summary><p>Pay an invoice, get your files or book time from the client hub links on this page.</p></details>
	</div>
</div></section>
<?php
echo uplinksync_site_service_area(); // phpcs:ignore -- nothing in neutral mode.
echo uplinksync_site_cta( 'Rather book a time than fill in a form?', 'Pick a 15-minute call — or email ' . uplinksync_site_email_link() . '.' ); // phpcs:ignore
?>
<!-- /wp:html -->
