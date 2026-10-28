<?php
/**
 * Title: Richer Quiet Light — About
 * Slug: uplinksync-child/site-about
 * Categories: uplinksync
 * Description: /about/ body (enrich/DESIGN-SPEC.md §5 + owner changes 2026-10-27): photo hero, proof band, the "Hi, I'm Doug" owner block (About only, real photo), values, certifications, remote-first band (properly overlaid), reviews (only when real), CTA.
 * Inserter: false
 */

$uls_a = uplinksync_site_arrow();
$uls_c = uplinksync_site_icon( 'check', 20 );
?>
<!-- wp:html -->
<section class="hero dark phero" aria-labelledby="h1">
	<div class="hero-media"><?php echo uplinksync_site_img( 'uplinksync-hero-town-dusk', UPLINKSYNC_SITE_WIDE, '', '100vw', true ); // phpcs:ignore ?></div>
	<div class="wrap">
		<div>
			<p class="eyebrow">Direct · Trustworthy · Tech-forward</p>
			<h1 id="h1"><?php echo uplinksync_site_slot( 'about_h1', 'One team for your systems, your site, and <em>the view from above.</em>' ); // phpcs:ignore ?></h1>
			<p class="lede"><?php echo uplinksync_site_lede( 'UplinkSync is an owner-run IT and aerial company. The person who scopes your project is the person who does the work, so you never have to explain things twice.' ); // phpcs:ignore ?></p>
			<div class="actions"><a class="btn btn-dk btn-lg" href="<?php echo esc_url( UPLINKSYNC_SITE_QUOTE ); ?>">Get a quote</a><a class="tlink" href="/contact/">Contact Doug <?php echo $uls_a; // phpcs:ignore ?></a></div>
		</div>
		<?php
		echo uplinksync_site_glass( // phpcs:ignore
			'How we work',
			array(
				array( 'user', 'Owner-run', 'You work with the person doing the job' ),
				array( 'card', 'Written plans, fixed prices', 'Agreed before any work starts' ),
				array( 'clock', 'One business day', 'Reply promise, from a person' ),
			)
		);
		?>
	</div>
</section>
<?php echo uplinksync_site_proof( uplinksync_site_proof_std() ); // phpcs:ignore ?>
<section class="sec" aria-labelledby="ab-h"><div class="wrap owner">
	<figure class="owner-photo"><?php echo uplinksync_site_img( 'uplinksync-about-owner', array( 480, 800, 1080 ), 'Doug, owner of UplinkSync, smiling at his desk with a laptop', '(min-width: 900px) 480px, 100vw' ); // phpcs:ignore ?><figcaption><b>Doug</b>Owner</figcaption></figure>
	<div>
		<p class="eyebrow">The person behind the work</p>
		<h2 id="ab-h">Hi, I’m Doug.</h2>
		<p class="intro">UplinkSync is owner-run: the person who scopes your project is the person who does the work. Certified, insured, and easy to reach.</p>
		<ul class="ticks">
			<li><?php echo $uls_c; // phpcs:ignore ?><span>Every request is tracked, and you deal with the same person who does the work.</span></li>
			<li><?php echo $uls_c; // phpcs:ignore ?><span>Plain-English plans, in writing, before any work starts.</span></li>
			<li><?php echo $uls_c; // phpcs:ignore ?><span>One team for the computers, the website and the view from above.</span></li>
		</ul>
		<ul class="chips"><li><?php echo uplinksync_site_icon( 'drone', 16 ); // phpcs:ignore ?>FAA Part 107</li><li><?php echo uplinksync_site_icon( 'badge', 16 ); // phpcs:ignore ?>CompTIA A+</li><li><?php echo uplinksync_site_icon( 'shield', 16 ); // phpcs:ignore ?>Insured</li></ul>
		<p class="actions">Email Doug: <a href="mailto:contact@uplinksync.com">contact@uplinksync.com</a></p>
	</div>
</div></section>
<section class="sec white" aria-labelledby="vl-h"><div class="wrap">
	<div class="sec-head"><p class="eyebrow">Values</p><h2 id="vl-h">What we stand for</h2></div>
	<div class="feats">
		<div class="feat reveal"><span class="ico lt lg"><?php echo uplinksync_site_icon( 'user', 24 ); // phpcs:ignore ?></span><h3>Direct</h3><p>You get a real person who knows your setup, and a straight answer.</p></div>
		<div class="feat reveal"><span class="ico lt lg"><?php echo uplinksync_site_icon( 'shield', 24 ); // phpcs:ignore ?></span><h3>Trustworthy</h3><p>Certified and insured. We do what we say, protect what you hand us, and explain things in plain English.</p></div>
		<div class="feat reveal"><span class="ico lt lg"><?php echo uplinksync_site_icon( 'spark', 24 ); // phpcs:ignore ?></span><h3>Tech-forward</h3><p>From managed IT to drone capture and automation, we use newer tools to give you clarity you can act on.</p></div>
	</div>
</div></section>
<section class="sec dark" aria-labelledby="cr-h"><div class="wrap">
	<div class="sec-head"><p class="eyebrow">Licensed &amp; certified</p><h2 id="cr-h">Certifications</h2></div>
	<div class="creds">
		<div class="cred"><span class="ico lg"><?php echo uplinksync_site_icon( 'drone', 24 ); // phpcs:ignore ?></span><div><h3>FAA Part 107</h3><p>Remote Pilot Certificate — our aerial photography, video and inspection work is flown legally and insured.</p></div></div>
		<div class="cred"><span class="ico lg"><?php echo uplinksync_site_icon( 'badge', 24 ); // phpcs:ignore ?></span><div><h3>CompTIA A+</h3><p>Industry-standard IT credential — the foundation behind our managed IT, device setup and ongoing support.</p></div></div>
		<div class="cred"><span class="ico lg"><?php echo uplinksync_site_icon( 'shield', 24 ); // phpcs:ignore ?></span><div><h3>Insured</h3><p>Coverage for IT and drone work; certificate of insurance on request.</p></div></div>
	</div>
</div></section>
<section class="cta dark" aria-labelledby="rf-h"><?php echo uplinksync_site_img( 'uplinksync-client-hub', UPLINKSYNC_SITE_CARD, '' ); // phpcs:ignore ?>
	<div class="wrap"><div><p class="eyebrow">How we work</p><h2 id="rf-h">Remote-first — on-site when arranged.</h2>
		<p>We work remotely by default. When a job needs boots on the ground — a rack rebuilt, a site walked, or a property flown — on-site work is available by arrangement. Written plans, fixed prices, and a reply within one business day.</p></div></div>
</section>
<?php
echo uplinksync_site_reviews( 'A note from a client' ); // phpcs:ignore -- renders nothing until real, permitted quotes exist.
echo uplinksync_site_hub(); // phpcs:ignore -- tint band between the two photo bands.
echo uplinksync_site_cta( 'Ready to work with someone who picks up?', 'A short form or a 15-minute call. You’ll get a scope, a price range and a timeline in writing — or email ' . uplinksync_site_email_link() . '.' ); // phpcs:ignore
?>
<!-- /wp:html -->
