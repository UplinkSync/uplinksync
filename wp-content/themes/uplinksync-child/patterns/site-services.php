<?php
/**
 * Title: Richer Quiet Light — Services
 * Slug: uplinksync-child/site-services
 * Categories: uplinksync
 * Description: /services/ body (enrich/DESIGN-SPEC.md §5): photo hero + "At a glance", proof band, path chooser, six image cards (incl. Hosting & Care + Cybersecurity), credentials band (replaces the "4 businesses served" counter), what happens next, estimator teaser, reviews (only when real), CTA.
 * Inserter: false
 */

$uls_a        = uplinksync_site_arrow();
$uls_estimate = UPLINKSYNC_SITE_QUOTE . '#uls-estimator';
?>
<!-- wp:html -->
<section class="hero dark phero" aria-labelledby="h1">
	<div class="hero-media"><?php echo uplinksync_site_img( 'uplinksync-hero-aerial-sunset', UPLINKSYNC_SITE_WIDE, '', '100vw', true ); // phpcs:ignore ?></div>
	<div class="wrap">
		<div>
			<p class="eyebrow">Ground and air · one team</p>
			<h1 id="h1"><?php echo uplinksync_site_slot( 'services_h1', 'Our services' ); // phpcs:ignore ?></h1>
			<p class="lede"><?php echo uplinksync_site_lede( 'Managed IT, websites and hosting, cybersecurity and FAA Part 107 aerial work, from one small team. We keep your technology running and help you get more from it.' ); // phpcs:ignore ?></p>
			<div class="actions"><a class="btn btn-dk btn-lg" href="<?php echo esc_url( UPLINKSYNC_SITE_QUOTE ); ?>">Get a quote</a><a class="tlink uls-est-trigger" href="<?php echo esc_url( $uls_estimate ); ?>">Estimate your project <?php echo $uls_a; // phpcs:ignore ?></a></div>
		</div>
		<?php
		echo uplinksync_site_glass( // phpcs:ignore
			'At a glance',
			array(
				array( 'monitor', '6 services', 'IT, automation, web, hosting, security, aerial' ),
				array( 'clock', 'One business day', 'Reply and written scope' ),
				array( 'card', 'Fixed plans', 'Monthly IT or one-off projects' ),
			)
		);
		?>
	</div>
</section>
<?php echo uplinksync_site_proof( uplinksync_site_proof_std() ); // phpcs:ignore ?>
<section class="sec" aria-labelledby="pt-h"><div class="wrap">
	<div class="sec-head"><p class="eyebrow">Find your path</p><h2 id="pt-h">What brings you here?</h2><p>Pick the track that fits and we’ll take you straight to the details.</p></div>
	<div class="paths">
		<div class="path reveal"><span class="ico lt lg"><?php echo uplinksync_site_icon( 'server', 24 ); // phpcs:ignore ?></span><h3>Technology &amp; operations</h3><p>Managed IT, automation, web and security: the systems that keep your day running.</p><a class="btn btn-primary" href="#what">Explore IT &amp; operations</a></div>
		<div class="path reveal"><span class="ico lt lg"><?php echo uplinksync_site_icon( 'drone', 24 ); // phpcs:ignore ?></span><h3>Aerial imaging &amp; inspection</h3><p>Photos, video and inspections from an FAA Part 107 certified pilot, captured safely and to spec.</p><a class="btn btn-primary" href="/drone-services/">Explore aerial services</a></div>
		<div class="path alt reveal"><span class="ico lt lg"><?php echo uplinksync_site_icon( 'spark', 24 ); // phpcs:ignore ?></span><h3>Not sure yet?</h3><p>Get an instant ballpark with our estimator, or just tell us what you’re working on — we’ll point you the right way.</p><a class="btn btn-outline uls-est-trigger" href="<?php echo esc_url( $uls_estimate ); ?>">Estimate your project</a></div>
	</div>
</div></section>
<section class="sec white" id="what" aria-labelledby="wd-h"><div class="wrap">
	<div class="sec-head"><p class="eyebrow">What we do</p><h2 id="wd-h">Six services, one consistent standard</h2><p>Every service comes with the same promise: a person who answers, a plan in writing, and someone who looks after it afterwards.</p></div>
	<div class="cards c3">
	<?php
	foreach ( array(
		array( 'img' => 'uplinksync-services-managed-it', 'widths' => UPLINKSYNC_SITE_CARD9, 'icon' => 'monitor', 'title' => 'Managed IT Services', 'text' => 'Computers that start up, updates that happen, and someone to call when they don’t. Setup, monitoring, backups and remote or on-site support.', 'href' => '/services/managed-it/', 'alt' => 'Technician checking a server aisle on a tablet', 'tags' => array( 'Monitoring', 'Backups', 'Help desk' ) ),
		array( 'img' => 'uplinksync-services-automation', 'widths' => UPLINKSYNC_SITE_CARD9, 'icon' => 'flow', 'title' => 'Business Automation', 'text' => 'Stop retyping the same data. We connect your tools so routine tasks run themselves.', 'href' => '/services/automation/', 'alt' => 'Hands on a laptop beside a workflow dashboard on a large monitor', 'tags' => array( 'Workflows', 'Integrations' ) ),
		array( 'img' => 'uplinksync-services-web', 'widths' => UPLINKSYNC_SITE_CARD9, 'icon' => 'globe', 'title' => 'Web Development', 'text' => 'A clear, fast website that explains what you do and makes it easy to contact you.', 'href' => '/services/web/', 'alt' => 'Person at a desk reviewing a website layout on a monitor', 'tags' => array( 'Design', 'Build', 'SEO basics' ) ),
		array( 'img' => 'uplinksync-services-hosting-care', 'widths' => UPLINKSYNC_SITE_CARD, 'icon' => 'server', 'title' => 'Hosting & Care', 'text' => 'Hosting for the sites we build: backups, security updates, uptime monitoring, and a real person to call.', 'href' => '/hosting/', 'alt' => 'Technician checking server racks on a tablet in a small server room', 'tags' => array( 'Backups', 'Updates', 'Uptime' ) ),
		array( 'img' => 'uplinksync-services-cybersecurity', 'widths' => UPLINKSYNC_SITE_CARD, 'icon' => 'lock', 'title' => 'Cybersecurity', 'text' => 'The practical basics done well: password management, single sign-on, patching, and monitoring of your network and devices.', 'href' => '/services/managed-it/', 'alt' => 'Two colleagues reviewing security dashboards on two monitors', 'tags' => array( 'Monitoring', 'Passwords', 'SSO' ) ),
		array( 'img' => 'uplinksync-drone-inspection', 'widths' => UPLINKSYNC_SITE_CARD, 'icon' => 'drone', 'title' => 'Drone Photography & Inspection', 'text' => 'FAA Part 107 photos, video and inspections for marketing and records.', 'href' => '/drone-services/', 'alt' => 'Drone pilot on a rooftop flying a small drone at sunset', 'tags' => array( 'Photo', 'Video', 'Inspection' ) ),
	) as $uls_card ) {
		echo uplinksync_site_card( $uls_card ); // phpcs:ignore
	}
	?>
	</div>
</div></section>
<section class="sec dark" aria-labelledby="cr-h"><div class="wrap">
	<div class="sec-head"><p class="eyebrow">Licensed &amp; certified</p><h2 id="cr-h">Credentials behind the work</h2><p>Working with small businesses across retail, martial arts and estate sales.</p></div>
	<div class="creds four">
		<div class="cred"><span class="ico lg"><?php echo uplinksync_site_icon( 'drone', 24 ); // phpcs:ignore ?></span><div><h3>FAA Part 107</h3><p>Certified commercial drone pilot — aerial work flown legally and insured.</p></div></div>
		<div class="cred"><span class="ico lg"><?php echo uplinksync_site_icon( 'badge', 24 ); // phpcs:ignore ?></span><div><h3>CompTIA A+</h3><p>Industry-certified IT support behind our managed services and endpoint work.</p></div></div>
		<div class="cred"><span class="ico lg"><?php echo uplinksync_site_icon( 'shield', 24 ); // phpcs:ignore ?></span><div><h3>Fully insured</h3><p>Certificate of insurance available on request for your project.</p></div></div>
		<div class="cred"><span class="ico lg"><?php echo uplinksync_site_icon( 'clock', 24 ); // phpcs:ignore ?></span><div><h3>One business day</h3><p>A reply from a person and a written scope.</p></div></div>
	</div>
</div></section>
<?php
echo uplinksync_site_steps( // phpcs:ignore
	'Next steps',
	'What happens next',
	'The simple path from your first message to a free quote you can act on.',
	array(
		array( 'Tell us what you’re dealing with', 'Share your project through the form, by email, or book a time — whatever’s easiest for you.', 'About 5 minutes' ),
		array( 'A person replies within one business day', 'Every request is tracked, and you deal with the same person who does the work.', 'Within one business day' ),
		array( 'You get a free quote', 'We scope the work and send you a clear, free quote so you know exactly what you’re getting.', 'No obligation' ),
	),
	'st-h'
);
?>
<section class="sec flush" id="estimate" aria-labelledby="es-h"><div class="wrap">
	<div class="teaser">
		<div><p class="eyebrow">Instant ballpark · no obligation</p><h2 id="es-h">Estimate your project</h2>
			<p class="muted">Answer a few quick questions for an indicative estimate. It’s a starting range, not a quote — we confirm the firm figure with you directly.</p>
			<ul class="ticks"><li><?php echo uplinksync_site_icon( 'check', 20 ); // phpcs:ignore ?><span>Every plan is quoted in writing before work starts.</span></li><li><?php echo uplinksync_site_icon( 'check', 20 ); // phpcs:ignore ?><span>Standard, rush or same-day scheduling.</span></li></ul></div>
		<div class="acts"><a class="btn btn-primary btn-lg uls-est-trigger" href="<?php echo esc_url( $uls_estimate ); ?>">Start the estimator</a><a class="btn btn-outline" href="/contact/">Or just tell us what you need</a></div>
	</div>
</div></section>
<?php
echo uplinksync_site_reviews(); // phpcs:ignore -- renders nothing until real, permitted quotes exist.
echo uplinksync_site_service_area(); // phpcs:ignore -- nothing in neutral mode.
echo uplinksync_site_cta( 'Tell us what’s getting in the way.', 'A short form or a 15-minute call. You’ll get a scope, a price range and a timeline in writing — or email ' . uplinksync_site_email_link() . '.' ); // phpcs:ignore
?>
<!-- /wp:html -->
