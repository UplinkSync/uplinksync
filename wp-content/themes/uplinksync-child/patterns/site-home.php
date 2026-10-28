<?php
/**
 * Title: Richer Quiet Light — Home
 * Slug: uplinksync-child/site-home
 * Categories: uplinksync
 * Description: Homepage body (enrich/DESIGN-SPEC.md §5): photo hero + glass panel, proof band, four service cards, own aerial work, how it works, reviews (only when real ones exist), client hub, photo CTA. The "Hi, I'm Doug" owner block lives on About only (owner decision 2026-10-27).
 * Inserter: false
 */

$uls_a = uplinksync_site_arrow();
?>
<!-- wp:html -->
<section class="hero dark" aria-labelledby="h1">
	<div class="hero-media"><?php echo uplinksync_site_img( 'uplinksync-hero-town-dusk', UPLINKSYNC_SITE_WIDE, '', '100vw', true ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts. ?></div>
	<div class="wrap">
		<div>
			<p class="eyebrow"><?php echo uplinksync_site_slot( 'eyebrow', 'Managed IT · Websites · Aerial photo &amp; video' ); // phpcs:ignore ?></p>
			<h1 id="h1"><?php echo uplinksync_site_slot( 'home_h1', 'IT that just works — <em>and a person who answers.</em>' ); // phpcs:ignore ?></h1>
			<p class="lede"><?php echo uplinksync_site_lede( 'One small team for your computers, network, website and aerial photography. Plain English, fixed plans, and a reply within one business day.' ); // phpcs:ignore ?></p>
			<div class="actions"><a class="btn btn-dk btn-lg" href="<?php echo esc_url( UPLINKSYNC_SITE_QUOTE ); ?>">Get a quote</a><a class="tlink" href="<?php echo esc_url( UPLINKSYNC_SITE_BOOK_IT ); ?>">Prefer to talk? Book a call <?php echo $uls_a; // phpcs:ignore ?></a></div>
		</div>
		<?php
		echo uplinksync_site_glass( // phpcs:ignore WordPress.Security.EscapeOutput
			'What you can count on',
			array(
				array( 'clock', 'A reply within one business day', 'From the person who does the work' ),
				array( 'card', 'Fixed, written pricing', 'Scope and price range before we start' ),
				array( 'orbit', 'Ground and air, one team', 'IT, web and FAA Part 107 aerial work' ),
			)
		);
		?>
	</div>
</section>
<?php echo uplinksync_site_proof( uplinksync_site_proof_std() ); // phpcs:ignore ?>
<section class="sec" aria-labelledby="sv-h"><div class="wrap">
	<div class="sec-head row"><div><p class="eyebrow">What we do</p><h2 id="sv-h">Four services, one team that knows your setup</h2><p>Pick one, or let us look after all of it — the same person scopes it and does the work.</p></div><a class="btn btn-outline" href="/services/">All services</a></div>
	<div class="cards c4">
	<?php
	$uls_sizes = '(min-width: 900px) 25vw, (min-width: 640px) 50vw, 100vw';
	foreach ( array(
		array( 'img' => 'uplinksync-services-managed-it', 'widths' => UPLINKSYNC_SITE_CARD9, 'icon' => 'monitor', 'title' => 'Managed IT & security', 'text' => 'Your computers set up properly, watched, backed up, and fixed fast when something breaks.', 'href' => '/services/managed-it/', 'alt' => 'Technician checking a server aisle on a tablet', 'sizes' => $uls_sizes ),
		array( 'img' => 'uplinksync-services-automation', 'widths' => UPLINKSYNC_SITE_CARD9, 'icon' => 'mail', 'title' => 'Microsoft 365 & networking', 'text' => 'Email, accounts, Wi-Fi and the network, set up properly and kept tidy.', 'href' => '/services/#what', 'alt' => 'Hands on a laptop beside a dashboard on a large monitor', 'sizes' => $uls_sizes ),
		array( 'img' => 'uplinksync-services-web', 'widths' => UPLINKSYNC_SITE_CARD9, 'icon' => 'globe', 'title' => 'Websites & hosting', 'text' => 'A fast, secure site built and looked after by the same people who answer your calls.', 'href' => '/services/web/', 'alt' => 'Person at a desk reviewing a website layout on a monitor', 'sizes' => $uls_sizes ),
		array( 'img' => 'uplinksync-drone-inspection', 'widths' => UPLINKSYNC_SITE_CARD, 'icon' => 'drone', 'title' => 'Aerial photo & video', 'text' => 'Listing photos, progress records and inspections from above, delivered ready to use.', 'href' => '/drone-services/', 'alt' => 'Drone pilot on a rooftop flying a small drone at sunset', 'sizes' => $uls_sizes ),
	) as $uls_card ) {
		echo uplinksync_site_card( $uls_card ); // phpcs:ignore
	}
	?>
	</div>
</div></section>
<section class="sec dark" aria-labelledby="cs-h"><div class="wrap">
	<div class="sec-head row"><div><p class="eyebrow">Ground and air</p><h2 id="cs-h">One team, from the server room to the sky</h2><p>The same person who looks after your systems flies your property. Our own drone footage is in the gallery.</p></div><a class="tlink" href="/drone-services/#work">See our own drone work <?php echo $uls_a; // phpcs:ignore ?></a></div>
	<div class="cases">
		<a class="case big reveal" href="/drone-services/"><?php echo uplinksync_site_img( 'uplinksync-aerial-site-sunset', array( 480, 800, 1280 ), 'Elevated view of a modern commercial building and car park at sunset', '(min-width: 900px) 58vw, 100vw' ); // phpcs:ignore ?><span class="in"><span class="kicker">Aerial photo &amp; video</span><h3>Properties and sites, from above</h3><p>Listing photos, progress records and inspections, delivered as finished files.</p></span></a>
		<a class="case reveal" href="/services/managed-it/"><?php echo uplinksync_site_img( 'uplinksync-office-dusk', array( 480, 800, 1280 ), 'Office building at dusk with a person working at a lit window', '(min-width: 900px) 42vw, 100vw' ); // phpcs:ignore ?><span class="in"><span class="kicker">Managed IT</span><h3>Your office, looked after</h3><p>Setup, monitoring and backups, with a person to call.</p></span></a>
	</div>
</div></section>
<?php
echo uplinksync_site_steps( // phpcs:ignore
	'Simple process',
	'How it works',
	'From first message to a plan you can act on.',
	array(
		array( 'Tell us what you need', 'A short form or a 15-minute call.', 'Takes about 5 minutes' ),
		array( 'Get a clear plan', 'Scope, price range and timeline, in writing, within one business day.', 'Within one business day' ),
		array( 'We take it from there', 'Then we look after it, so you can get back to work.', 'Ongoing, fixed plans' ),
	),
	'st-h'
);
echo uplinksync_site_reviews(); // phpcs:ignore -- renders nothing until real, permitted quotes exist.
echo uplinksync_site_service_area(); // phpcs:ignore -- nothing in neutral mode.
echo uplinksync_site_hub(); // phpcs:ignore
echo uplinksync_site_cta( 'Tell us what’s getting in the way.', 'A short form or a 15-minute call. You’ll get a scope, a price range and a timeline in writing — or email ' . uplinksync_site_email_link() . '.' ); // phpcs:ignore
?>
<!-- /wp:html -->
