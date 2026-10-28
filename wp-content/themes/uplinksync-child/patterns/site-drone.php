<?php
/**
 * Title: Richer Quiet Light — Drone & Aerial
 * Slug: uplinksync-child/site-drone
 * Categories: uplinksync
 * Description: /drone-services/ body (enrich/DESIGN-SPEC.md §5): hero + "Every shoot includes", drone proof band, what you receive, own-work gallery with filters (location-free captions), reel + vertical cuts (play only on press), how a shoot works, FAQ, CTA.
 * Inserter: false
 */

$uls_a     = uplinksync_site_arrow();
$uls_stills = get_stylesheet_directory_uri() . '/assets/images/site/';

/**
 * Inline Immich player (inc/immich-embed.php). Native controls, preload="none": it only
 * plays when pressed. Not the uls-air-videocard--9x16 class, which DB snippet 110 auto-loops.
 */
$uls_video = static function ( $asset, $share, $poster, $ratio, $label ) {
	if ( ! function_exists( 'uplinksync_immich_video_shortcode' ) ) {
		return '';
	}
	return uplinksync_immich_video_shortcode(
		array(
			'asset'  => $asset,
			'share'  => $share,
			'poster' => $poster,
			'ratio'  => $ratio,
			'label'  => $label,
		)
	);
};
$uls_poster = wp_make_link_relative( $uls_stills );
?>
<!-- wp:html -->
<section class="hero dark long" aria-labelledby="h1">
	<div class="hero-media"><?php echo uplinksync_site_img( 'uplinksync-hero-aerial-sunset', UPLINKSYNC_SITE_WIDE, '', '100vw', true ); // phpcs:ignore ?></div>
	<div class="wrap">
		<div>
			<p class="eyebrow">Aerial imagery &amp; inspection · FAA Part 107</p>
			<h1 id="h1"><?php echo uplinksync_site_slot( 'drone_h1', 'Property and listing photo and video — <em>delivered ready to use</em>' ); // phpcs:ignore ?></h1>
			<p class="lede"><?php echo uplinksync_site_lede( 'High-resolution aerial photos and cinematic video of your property, project or listing, flown by an FAA Part 107 certified, insured operator and produced in-house. Every frame in the gallery below is our own work.' ); // phpcs:ignore ?></p>
			<div class="actions"><a class="btn btn-dk btn-lg" href="<?php echo esc_url( UPLINKSYNC_SITE_QUOTE ); ?>">Get a drone quote</a><a class="tlink" href="#work">See the work <?php echo $uls_a; // phpcs:ignore ?></a></div>
		</div>
		<?php
		echo uplinksync_site_glass( // phpcs:ignore
			'Every shoot includes',
			array(
				array( 'badge', 'Certified operator', 'FAA Part 107, flown legally' ),
				array( 'shield', 'Insured', 'Certificate of insurance on request' ),
				array( 'image', 'Finished files', 'Colour-graded, 16:9 and 9:16' ),
			)
		);
		?>
	</div>
</section>
<?php
echo uplinksync_site_proof( // phpcs:ignore
	array(
		array( 'drone', 'FAA Part 107', 'Certified remote pilot' ),
		array( 'shield', 'Insured', 'Certificate of insurance on request' ),
		array( 'image', 'In-house', 'Flown, edited and delivered by us' ),
		array( 'clock', 'One business day', 'Reply with availability' ),
	)
);
?>
<section class="sec" aria-labelledby="rc-h"><div class="wrap">
	<div class="sec-head"><p class="eyebrow">What you receive</p><h2 id="rc-h">Finished files you can use straight away</h2><p>Every shoot is delivered as finished files — flown and produced in-house by an FAA Part 107 certified operator.</p></div>
	<div class="cards c3">
	<?php
	foreach ( array(
		array( 'img' => 'aerial-new-homes', 'widths' => UPLINKSYNC_SITE_AIR, 'icon' => 'home', 'title' => 'Listing photo & video', 'text' => 'High-resolution stills and cinematic reels of your property, project or event — colour-graded and ready for listings, marketing and social.', 'href' => UPLINKSYNC_SITE_QUOTE, 'alt' => 'Aerial view over rows of new homes', 'tags' => array( 'Stills', 'Reels', 'Social cuts' ), 'more' => 'Get a quote' ),
		array( 'img' => 'uplinksync-drone-inspection', 'widths' => UPLINKSYNC_SITE_CARD, 'icon' => 'map', 'title' => 'Inspection, mapping & survey', 'text' => 'Roof and structure inspection, mapping and site survey — for when you need documentation rather than marketing media.', 'href' => UPLINKSYNC_SITE_QUOTE, 'alt' => 'Drone pilot on a rooftop with a controller, the drone hovering in front of mountains', 'tags' => array( 'Roof', 'Mapping', 'Survey' ), 'more' => 'Get a quote' ),
		array( 'img' => 'aerial-park', 'widths' => UPLINKSYNC_SITE_AIR, 'icon' => 'orbit', 'title' => 'Site overview', 'text' => 'Full top-down and orbit coverage of a site or acreage — the context shot that shows scale, boundaries and layout at a glance.', 'href' => UPLINKSYNC_SITE_QUOTE, 'alt' => 'Aerial view of a park with a large playground', 'tags' => array( 'Top-down', 'Orbit' ), 'more' => 'Get a quote' ),
	) as $uls_card ) {
		echo uplinksync_site_card( $uls_card ); // phpcs:ignore
	}
	?>
	</div>
</div></section>
<section class="sec white" id="work" aria-labelledby="wk-h"><div class="wrap">
	<div class="sec-head"><p class="eyebrow">Real work · own footage</p><h2 id="wk-h">The work — browse &amp; license</h2><p>Every frame is our own cleared drone work, shown with watermarked previews. Full-resolution files and licensing on request.</p></div>
	<ul class="filters" data-uls-filters hidden aria-label="Filter the gallery">
		<li><button type="button" data-f="all" aria-pressed="true">All</button></li>
		<li><button type="button" data-f="land" aria-pressed="false">Landscapes</button></li>
		<li><button type="button" data-f="homes" aria-pressed="false">Neighbourhoods</button></li>
		<li><button type="button" data-f="sites" aria-pressed="false">Sites &amp; water</button></li>
	</ul>
	<div class="gallery" data-uls-gallery>
		<?php
		$uls_tiles = array(
			array( 'aerial-reservoir', array( 480, 800, 1280 ), 'land', 'Reservoir and autumn hills', 'Aerial view of a reservoir below forested autumn hills' ),
			array( 'aerial-park', UPLINKSYNC_SITE_AIR, 'sites', 'Park and playground', 'Aerial view of a park with a colourful playground' ),
			array( 'aerial-riverfront', UPLINKSYNC_SITE_AIR, 'sites', 'River and town', 'Aerial view of a river weir beside a town' ),
			array( 'aerial-neighbourhood', UPLINKSYNC_SITE_AIR, 'homes', 'Neighbourhood overview', 'Aerial view of a neighbourhood and a main road' ),
			array( 'aerial-new-homes', UPLINKSYNC_SITE_AIR, 'homes', 'New-build community', 'Aerial view over rows of new homes' ),
		);
		foreach ( $uls_tiles as $uls_i => $uls_t ) {
			printf(
				'<figure data-cat="%1$s">%2$s<figcaption>%3$s</figcaption></figure>',
				esc_attr( $uls_t[2] ),
				uplinksync_site_img( $uls_t[0], $uls_t[1], $uls_t[4], 0 === $uls_i ? '(min-width: 900px) 66vw, 100vw' : '(min-width: 640px) 33vw, 50vw' ), // phpcs:ignore
				esc_html( $uls_t[3] )
			);
		}
		?>
		<a class="more-tile" href="/prints/">Full gallery →</a>
	</div>
	<p class="actions"><a class="btn btn-primary" href="/prints/">Browse the full gallery</a><span class="muted">Gallery frames: Aerial by UplinkSync — uplinksync.com</span></p>
</div></section>
<section class="sec dark" aria-labelledby="mo-h"><div class="wrap">
	<div class="sec-head"><p class="eyebrow">In motion · cinematic reel</p><h2 id="mo-h">See it in motion</h2><p>A cinematic reel cut from the same in-house aerial work. It plays only when you press play.</p></div>
	<div class="reel"><?php echo $uls_video( 'b688e5d2-aa13-49a8-9683-70400bd89196', 'https://media.uplinksync.com/share/_b-4s67XtAtfb48X7zHBot_WlLXccranYqYfqh-jnCcG4sW6aWp2TqqFku4-hfYYUSA', $uls_poster . 'uplinksync-aerial-site-sunset-1280.webp', '16/9', 'Cinematic aerial reel — press play to watch our own footage' ); // phpcs:ignore ?></div>
	<div class="sec-head" style="margin:56px 0 24px"><h3>Vertical / social</h3><p>Short vertical cuts, ready for reels and stories — framed 9:16.</p></div>
	<div class="verticals">
		<?php
		echo $uls_video( '7be5a074-5852-430d-a2a3-ac70f97553e9', 'https://media.uplinksync.com/share/0nvSX6X4yoadOQNUWD17pFYZ67a5lmxnf5hOpxIvPNOaJw94WdhY0c_s7S83Wgq6fOY', $uls_poster . 'uplinksync-vertical-site-460.webp', '9/16', 'Vertical aerial clip 1' ); // phpcs:ignore
		echo $uls_video( 'cf524131-01cf-41cc-bcab-ecd4cb72ad9c', 'https://media.uplinksync.com/share/twvinN24QDAPlsz-lcH6-vN2EbBCPxOhIoQTrgtT6rLqRdcljOVZgofcAQCkm2D4DfE', $uls_poster . 'uplinksync-vertical-town-460.webp', '9/16', 'Vertical aerial clip 2' ); // phpcs:ignore
		echo $uls_video( 'b7a3c631-d9eb-4bd9-a9dc-f51f316d096a', 'https://media.uplinksync.com/share/UgXWsDIyaPcYKRRlV7vqsYoNn2-ZkvUZJxJZ4w-mVVgataU-hWjMZGgZN41ia3huuh0', $uls_poster . 'uplinksync-vertical-pilot-460.webp', '9/16', 'Vertical aerial clip 3' ); // phpcs:ignore
		?>
	</div>
</div></section>
<?php
echo uplinksync_site_steps( // phpcs:ignore
	'How a shoot works',
	'From booking to files',
	'We hold final authority on whether a flight is safe and legal — and reschedule rather than fly in unsafe conditions.',
	array(
		array( 'Tell us the property and the goal', 'Listing, progress record, inspection or event — and when you need it.', '' ),
		array( 'Get a plan and a price', 'Flight plan, airspace check and a written price within one business day.', 'Within one business day' ),
		array( 'Receive finished files', 'Colour-graded photos and video, delivered to your client gallery.', 'Ready to publish' ),
	),
	'st-h'
);
?>
<section class="sec flush" aria-labelledby="fq-h"><div class="wrap">
	<div class="sec-head"><p class="eyebrow">Common questions</p><h2 id="fq-h">Before you book</h2></div>
	<div class="faq">
		<details open><summary>Are you licensed to fly commercially?</summary><p>Yes. Our pilot holds an FAA Remote Pilot Certificate (Part 107), and we treat regulatory compliance as non-negotiable — we’ll delay or reschedule rather than fly in unsafe or restricted conditions.</p></details>
		<details><summary>Are you insured?</summary><p>We carry appropriate coverage for our drone operations and can provide a certificate of insurance on request.</p></details>
		<details><summary>What do we actually get — raw footage or a finished product?</summary><p>Finished files: the colour-graded photos and video agreed in your written quote, ready for listings, marketing and social.</p></details>
	</div>
</div></section>
<?php
echo uplinksync_site_reviews(); // phpcs:ignore -- renders nothing until real, permitted quotes exist.
echo uplinksync_site_cta( 'Tell us about the property.', 'Property and listing photo and video, plus events — with inspection, mapping and survey also available. We’ll follow up with availability and licensing terms.', UPLINKSYNC_SITE_BOOK_UAV ); // phpcs:ignore
?>
<!-- /wp:html -->
