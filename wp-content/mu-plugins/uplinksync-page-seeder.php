<?php
/**
 * Plugin Name: UplinkSync — Canonical Page Seeder
 * Description: Creates the ***-101 Information Architecture §1 locked-slug Pages (***-104) so they return 200 instead of 301-collapsing to `/`. Idempotent: each Page is only inserted if a post with that slug does not already exist, so it is safe to run on every deploy and never clobbers owner edits made in wp-admin. Runs as an mu-plugin so the page structure is captured in-repo (deploys with wp-content) and needs no wp-admin/REST credentials — the same repo-captured idiom as uplinksync-canonical-redirects.php. This closes the repo↔live drift the issue warns about: the canonical page set now lives in version control, not only in the WP database.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bump this when the seed definition changes so the one-shot guard re-runs the
 * pass. The pass itself is still per-page idempotent (get_page_by_path check),
 * so a re-run only creates pages that are genuinely missing — it never
 * duplicates or overwrites an existing page's edited content.
 */
const UPLINKSYNC_PAGE_SEED_VERSION = '1.2.0'; // 1.2.0: /for/real-estate/ seed body carries owner Option A figures (UPLAA-475); the LIVE page is updated by the uplinksync-real-estate-figures.php DB migration (this seeder is insert-only). 1.1.0: +web-design, +hosting, +for/real-estate bundle (UPLAA-456).
const UPLINKSYNC_PAGE_SEED_OPTION  = 'uplinksync_page_seed_version';

/**
 * The locked page set (IA §1). Order matters: `services` must be created before
 * its children so their `post_parent` resolves and the canonical
 * `/services/<child>/` URLs are correct.
 *
 * `body` is intentionally minimal, spec-anchored starter content:
 *   - real structural copy where it is an established fact (headings, the
 *     mailto:, the CF7 shortcode placeholder for /contact),
 *   - explicit, VISIBLE `pending` markers for every owner-gated fact (IA §5/§7)
 *     so the page is honest in staging and cannot silently ship a placeholder.
 * Final marketing copy (***-20) and the CF7 form id (***-42) are owner/host
 * artifacts; whoever fills them edits the Page in wp-admin — and because the
 * seeder is idempotent, that edit is preserved on the next deploy.
 */
function uplinksync_page_seed_definitions() {
	$published_email = 'contact@uplinksync.com'; // owner-authoritative (***-104, 2026-07-21); NOT dirwin@ (that is the internal CF7 recipient only).

	return array(
		// --- Services overview (parent for the three service pages) -----------
		array(
			'slug'   => 'services',
			'title'  => 'Services',
			'parent' => 0,
			'body'   => "<!-- wp:heading {\"level\":1} -->\n<h1>What we do</h1>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Local, human IT and automation partners for small and mid-sized businesses. Explore our services below.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:list -->\n<ul>\n<li><a href=\"/services/managed-it/\">Managed IT Services</a></li>\n<li><a href=\"/services/automation/\">Business Automation</a></li>\n<li><a href=\"/services/web/\">Web Development</a></li>\n</ul>\n<!-- /wp:list -->",
		),

		// --- Primary service: Managed IT (anchor page, hosts the mini quote form)
		array(
			'slug'   => 'managed-it',
			'title'  => 'Managed IT Services',
			'parent' => 'services',
			'body'   => "<!-- wp:heading {\"level\":1} -->\n<h1>Managed IT Services</h1>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Proactive, local IT support that keeps your business running — monitoring, security, helpdesk, and strategy from a partner who picks up the phone.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><!-- ***-20 copy pending: feature list + \"why local\" narrative. --></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><!-- ***-42 mini quote form: insert [contact-form-7 id=\"...\" html_id=\"quote-form-mini\"] here once the CF7 \"Quote Mini Form\" entity exists (see docs/quote-form-build-guide.md §5). --></p>\n<!-- /wp:paragraph -->",
		),

		// --- Secondary services ----------------------------------------------
		array(
			'slug'   => 'automation',
			'title'  => 'Business Automation',
			'parent' => 'services',
			'body'   => "<!-- wp:heading {\"level\":1} -->\n<h1>Business Automation</h1>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>We connect the tools you already use and automate the busywork between them — so your team spends time on customers, not copy-paste.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><!-- ***-20 copy pending. --></p>\n<!-- /wp:paragraph -->",
		),
		array(
			'slug'   => 'web',
			'title'  => 'Web Development',
			'parent' => 'services',
			'body'   => "<!-- wp:heading {\"level\":1} -->\n<h1>Web Development</h1>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Fast, secure, maintainable websites — built and hosted by the same team that manages your IT, so there is one number to call when it matters.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><!-- ***-20 copy pending. --></p>\n<!-- /wp:paragraph -->",
		),

		// --- About (trust/story; ***-20 copy) ------------------------------
		array(
			'slug'   => 'about',
			'title'  => 'About',
			'parent' => 0,
			'body'   => "<!-- wp:heading {\"level\":1} -->\n<h1>About UplinkSync</h1>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>We are a local IT and automation partner — trustworthy, tech-forward, and genuinely reachable. <!-- ***-20 founder/company narrative pending (company \"we\" voice). --></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><strong>What we stand for:</strong> Local · Trustworthy · Tech-forward.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><a href=\"/contact/#quote-form\">Ready to work with us? Get a free quote →</a></p>\n<!-- /wp:paragraph -->",
		),

		// --- Contact (hosts the master quote form; slug matches is_page('contact'))
		array(
			'slug'   => 'contact',
			'title'  => 'Contact',
			'parent' => 0,
			'body'   => "<!-- wp:heading {\"level\":1} -->\n<h1>Get a Free Quote</h1>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Local, human IT &amp; automation partners. Tell us what you are dealing with and we will be in touch within one business day.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><!-- ***-42 master quote form: insert [contact-form-7 id=\"...\" html_id=\"quote-form\"] here once the CF7 \"Master Quote Form\" entity exists (see docs/quote-form-build-guide.md §1). The quote-form.css/js in the child theme are already keyed to #quote-form. --></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":2} -->\n<h2>Prefer to reach out directly?</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>✉️ <a href=\"mailto:{$published_email}\">{$published_email}</a><br>\n📞 +1 (208) 995-2704</p>\n<!-- /wp:paragraph -->",
		),

		// --- Web design (top-level line landing page; ***-456) -----------------
		// UPLAA-456: `/web-design/` previously 301-collapsed to `/` because no
		// Page owned the slug. Seeding a real Page returns 200 and gives the
		// web/hosting line a front door of its own (the homepage only had a
		// combined "Web & Hosting" card). Distinct from /services/web/, which is
		// the IT-catalogue child; this is the standalone marketing landing the
		// converged decision (UPLAA-444) asked to un-bury.
		array(
			'slug'   => 'web-design',
			'title'  => 'Web Design & Development',
			'parent' => 0,
			'body'   => "<!-- wp:heading {\"level\":1} -->\n<h1>Web design &amp; development</h1>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Fast, secure, easy-to-run websites — designed, built, and hosted by the same local team that manages your IT. One number to call when it matters, no hand-offs between a designer, a host, and a support desk who have never spoken.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":2} -->\n<h2>What you get</h2>\n<!-- /wp:heading -->\n\n<!-- wp:list -->\n<ul>\n<li>A site built for your business, not a template you have to fight.</li>\n<li>Hosting, backups, and security handled by us — see <a href=\"/hosting/\">Hosting</a>.</li>\n<li>Ongoing changes and support from a partner who picks up the phone.</li>\n</ul>\n<!-- /wp:list -->\n\n<!-- wp:paragraph -->\n<p><!-- ***-20 copy pending: portfolio proof. Smiles &amp; Service (smilesandservice.com) is a real build we can show as a case example — \"web + hosting for Smiles &amp; Service\" where the WORK is the proof — but it is a family business and must NOT be presented as an independent third-party testimonial (UPLAA-444 R). --></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><a href=\"/contact/#quote-form\">Get a free quote →</a></p>\n<!-- /wp:paragraph -->",
		),

		// --- Hosting (top-level line landing page; ***-456) --------------------
		// UPLAA-456: un-301 `/hosting/`. Companion front door to /web-design/.
		array(
			'slug'   => 'hosting',
			'title'  => 'Website Hosting & Care',
			'parent' => 0,
			'body'   => "<!-- wp:heading {\"level\":1} -->\n<h1>Hosting &amp; care</h1>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Managed hosting from the team that built your site and manages your IT — backups, security updates, uptime monitoring, and a real person to call. Not a control panel and a ticket queue in another time zone.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":2} -->\n<h2>What's included</h2>\n<!-- /wp:heading -->\n\n<!-- wp:list -->\n<ul>\n<li>Managed updates, backups, and security monitoring.</li>\n<li>One partner for site, hosting, and IT — see <a href=\"/web-design/\">Web design</a>.</li>\n<li>Local support that answers the phone.</li>\n</ul>\n<!-- /wp:list -->\n\n<!-- wp:paragraph -->\n<p><!-- ***-20 copy pending: plan tiers / price. Owner-gated (UPLAA-444: a stated price is a design goal but the numbers are the owner's to set). Leave price OUT until Doug provides it rather than inventing a figure. --></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><a href=\"/contact/#quote-form\">Get a free quote →</a></p>\n<!-- /wp:paragraph -->",
		),

		// --- \"For\" parent (namespaces the audience bundle pages; ***-456) -----
		// Empty container so /for/real-estate/ resolves as a nested slug. Kept
		// noindex-worthy and minimal; the audience pages under it are the real
		// destinations. Future audience lanes (e.g. /for/martial-arts-academies/,
		// UPLAA-457) attach here as children.
		array(
			'slug'   => 'for',
			'title'  => 'Who we build for',
			'parent' => 0,
			'body'   => "<!-- wp:heading {\"level\":1} -->\n<h1>Who we build for</h1>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Tailored engagements for the businesses we work with most.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:list -->\n<ul>\n<li><a href=\"/for/real-estate/\">Real estate groups &amp; brokerages</a></li>\n</ul>\n<!-- /wp:list -->",
		),

		// --- For Real Estate (the buyable bundle; ***-456, ***-444) ------------
		// THE core deliverable. The homepage's \"one pipeline\" line is being
		// retired as an abstraction about the company's shape (Peitho, separate
		// issue). Here the cross-line claim is LEGITIMATE because it is one buyer
		// genuinely purchasing all four lines — site, hosting, IT, listing media.
		// Say it as an engagement a brokerage can buy, concretely.
		//
		// PHOTOGRAPHY GAP (MEASURED, UPLAA-444 comment 1): /drone-services/ has
		// ZERO property/listing imagery — 122 scenic prints, no property work. So
		// there is NO real listing photo to place here. Per design-standard §7 /
		// visual-system §5, the image component is deliberately LEFT OUT (no stock,
		// no generated \"listing photos\") and the request is raised to the owner on
		// the issue. The <!-- ***-456 photography pending --> marker holds its place.
		array(
			'slug'   => 'real-estate',
			'title'  => 'For Real Estate',
			'parent' => 'for',
			'body'   => "<!-- wp:heading {\"level\":1} -->\n<h1>Everything a brokerage needs, from one desk</h1>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Your website, your hosting, your day-to-day IT, and your listing photography and video — bought as one engagement, run by one local team. No stitching together a web designer, a host, an IT contractor, and a drone operator who have never met.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":2} -->\n<h2>The four lines, as one engagement</h2>\n<!-- /wp:heading -->\n\n<!-- wp:list -->\n<ul>\n<li><strong>A website built to sell listings</strong> — designed and built for your agency. <a href=\"/web-design/\">Web design &amp; development →</a></li>\n<li><strong>Hosting &amp; care</strong> — managed, backed up, and monitored so it is never down when a buyer is looking. <a href=\"/hosting/\">Hosting →</a></li>\n<li><strong>IT support for the office</strong> — the phones, the machines, the accounts, handled locally. <a href=\"/services/managed-it/\">Managed IT →</a></li>\n<li><strong>Listing photography &amp; video</strong> — FAA Part 107 aerial and ground media for your listings. <a href=\"/drone-services/\">See drone &amp; listing media →</a></li>\n</ul>\n<!-- /wp:list -->\n\n<!-- wp:paragraph -->\n<p><!-- ***-456 photography pending: NO property/listing imagery exists yet (/drone-services/ is 122 scenic prints, zero property work — MEASURED, UPLAA-444). Do NOT substitute stock or generated listing photos. Insert a real listing photo/video gallery here once the owner supplies property media. Component intentionally omitted until then (design-standard §7 / visual-system §5). --></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":2} -->\n<h2>What it costs and how fast</h2>\n<!-- /wp:heading -->\n\n<!-- wp:list -->\n<ul>\n<li><strong>\$1,500 to build</strong> — one-time, for a website designed and built for your brokerage.</li>\n<li><strong>\$175/month</strong> — hosting, care, and day-to-day support once you're live.</li>\n<li><strong>IDX / MLS listing feed</strong> — integrated and billed pass-through (at cost).</li>\n<li><strong>10-day delivery</strong> — from kickoff to launch.</li>\n</ul>\n<!-- /wp:list -->\n\n<!-- wp:paragraph -->\n<p>Listing photography &amp; video and your coverage area are quoted per brokerage — <a href=\"/contact/#quote-form\">tell us about your listings</a> and we'll scope it with you. <!-- UPLAA-475 owner-gated: per-listing media pricing + coverage-area list are the owner's to set; left as a visible ask rather than invented (owner decision 2026-08-08: Option A pricing applied, media/coverage remain owner-supplied). --></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><a href=\"/contact/#quote-form\">Talk to a specialist about your brokerage →</a></p>\n<!-- /wp:paragraph -->",
		),
	);
}

/**
 * Insert any missing locked-slug Pages. Per-page idempotent via
 * get_page_by_path(): an existing Page (by slug) is left completely untouched,
 * so owner edits in wp-admin always win. Only genuinely-absent pages are created.
 */
function uplinksync_page_seed_run() {
	$slug_to_id = array();

	foreach ( uplinksync_page_seed_definitions() as $page ) {
		$existing = get_page_by_path( $page['slug'], OBJECT, 'page' );
		if ( $existing instanceof WP_Post ) {
			// Already present (created here on a prior run, or authored in wp-admin).
			$slug_to_id[ $page['slug'] ] = $existing->ID;
			continue;
		}

		// Resolve parent slug -> ID. If the parent is somehow still missing,
		// fall back to top level rather than failing the insert; the canonical
		// URL is corrected on the next run once the parent exists.
		$parent_id = 0;
		if ( ! empty( $page['parent'] ) ) {
			if ( isset( $slug_to_id[ $page['parent'] ] ) ) {
				$parent_id = $slug_to_id[ $page['parent'] ];
			} else {
				$parent_page = get_page_by_path( $page['parent'], OBJECT, 'page' );
				if ( $parent_page instanceof WP_Post ) {
					$parent_id = $parent_page->ID;
				}
			}
		}

		$new_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $page['title'],
				'post_name'    => $page['slug'],
				'post_parent'  => $parent_id,
				'post_content' => $page['body'],
			),
			true
		);

		if ( ! is_wp_error( $new_id ) ) {
			$slug_to_id[ $page['slug'] ] = $new_id;
		}
	}
}

/**
 * One-shot guard: only walk the seed pass when the stored version differs from
 * the current one (fresh install or a bumped definition). Hooked on `init` at
 * the default priority so post types are registered. The pass is itself
 * idempotent, so this guard is purely a performance optimisation (avoids a
 * handful of get_page_by_path lookups on every request).
 */
function uplinksync_page_seed_maybe_run() {
	if ( get_option( UPLINKSYNC_PAGE_SEED_OPTION ) === UPLINKSYNC_PAGE_SEED_VERSION ) {
		return;
	}
	uplinksync_page_seed_run();
	update_option( UPLINKSYNC_PAGE_SEED_OPTION, UPLINKSYNC_PAGE_SEED_VERSION );
}
add_action( 'init', 'uplinksync_page_seed_maybe_run' );
