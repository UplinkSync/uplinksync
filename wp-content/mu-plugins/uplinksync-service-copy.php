<?php
/**
 * Plugin Name: UplinkSync — Service-page Body Copy (***-270 item 4 / ***-329)
 * Description: One-shot, idempotent DATABASE migration that fills the four canonical
 *   service pages — /services/ (overview), /services/managed-it/,
 *   /services/automation-2/, /services/web-2/ — with the owner-APPROVED body copy from
 *   ***-270 #document-service-copy-draft (request_confirmation 84d01cfc accepted
 *   2026-07-25). Every line is derived only from existing site materials (brand-vision.md,
 *   the seeded ledes, the IA doc); no invented metrics, clients, SLAs, or guarantees. The
 *   single visible `[OWNER: …]` marker on the managed-it draft is DROPPED here (not
 *   published), exactly as the confirmation promised ("I'll drop any [OWNER:…] markers you
 *   haven't filled rather than publish them").
 *
 *   WHY A DB MIGRATION AND NOT A RUNTIME REWRITE: the page bodies live in the WP DB
 *   (post_content), not in the repo, and live REST is 401-locked to agents. Runtime
 *   output-buffer (ob_start) rewrites are BANNED here — they blanked production twice
 *   (see uplinksync-unified-services.php [DISABLED AGAIN]). This plugin registers NO output
 *   buffer and touches NO render path. It runs once on `init`, edits post_content via
 *   wp_update_post, records a version flag, and never runs again. Same credential-free,
 *   repo-captured DB-write idiom already proven safe by uplinksync-page-seeder.php
 *   (wp_insert_post on init) and uplinksync-homepage-rhythm.php (MR !83).
 *
 *   OWNER EDITS ALWAYS WIN (fail-safe by construction): each page is written ONLY when its
 *   current content still carries a known PLACEHOLDER signature — i.e. the seeded stub the
 *   owner has not touched. If the owner (or anyone) has already edited a page, its signature
 *   no longer matches and the migration makes NO change to it. It cannot blank a page: it
 *   does not participate in rendering, and a non-matching page is a no-op, not a partial
 *   write. The version flag is recorded regardless so the pass never thrashes.
 *
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const UPLINKSYNC_SERVICE_COPY_VERSION = '1.0.0';
const UPLINKSYNC_SERVICE_COPY_OPTION  = 'uplinksync_service_copy_version';

/**
 * The approved body copy, keyed by canonical page path.
 *
 *   - `paths`      : path candidates to resolve, in preference order. The -2 variants are
 *                    the linked canonical pages (item 3 / MR !82 301s point the clean slugs
 *                    at them); we fall back to the clean slug only if the -2 page is absent.
 *   - `signatures` : substrings that appear ONLY in the untouched seeded placeholder. The
 *                    page is rewritten only if at least one still matches — proof it has not
 *                    been owner-edited. (Seeded stubs live in uplinksync-page-seeder.php.)
 *   - `body`       : the approved replacement post_content (markers stripped).
 */
function uplinksync_service_copy_definitions() {
	$defs = array();

	// 1. /services/ — overview. Keeps H1 + linked cards; adds framing. Placeholder lede
	//    "Explore our services below." is unique to the seeded stub.
	$defs[] = array(
		'paths'      => array( 'services' ),
		'signatures' => array( 'Explore our services below.' ),
		'body'       => "<!-- wp:heading {\"level\":1} -->\n<h1>What we do</h1>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Local, human IT and automation partners for small and mid-sized businesses. One team for the tech your business runs on — so there's one number to call when it matters.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Whether you need day-to-day IT you can rely on, want to automate the busywork between your tools, or need a website that actually works, we handle it in-house. Start below, or <a href=\"/contact/#quote-form\">tell us what you're dealing with</a>.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:list -->\n<ul>\n<li><a href=\"/services/managed-it/\">Managed IT Services</a> — proactive support, security, and strategy.</li>\n<li><a href=\"/services/automation-2/\">Business Automation</a> — connect your tools, cut the copy-paste.</li>\n<li><a href=\"/services/web-2/\">Web Development</a> — fast, secure sites built by the team that runs your IT.</li>\n</ul>\n<!-- /wp:list -->",
	);

	// 2. /services/managed-it/ — primary. The [OWNER: …] response-time/pricing paragraph is
	//    DROPPED (not published). CF7 mini-form build marker (***-42) is retained as-is.
	$defs[] = array(
		'paths'      => array( 'services/managed-it', 'managed-it' ),
		'signatures' => array( '***-20 copy pending' ),
		'body'       => "<!-- wp:heading {\"level\":1} -->\n<h1>Managed IT Services</h1>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Proactive, local IT support that keeps your business running — monitoring, security, helpdesk, and strategy from a partner who picks up the phone.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":2} -->\n<h2>If your IT only gets attention when it breaks, you're already behind</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Most small businesses don't have an IT problem — they have an \"it works until it doesn't\" problem. You notice IT when email goes down, when a laptop won't boot before a client meeting, or when you're not sure whether last night's backup actually ran. We take that off your plate: we watch your systems continuously, fix small things before they become outages, and give you one reliable place to call when something does go wrong.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":2} -->\n<h2>What's included</h2>\n<!-- /wp:heading -->\n\n<!-- wp:list -->\n<ul>\n<li><strong>Proactive monitoring</strong> — we watch for problems so you don't have to.</li>\n<li><strong>Security</strong> — patching, protection, and sensible safeguards for a small team.</li>\n<li><strong>Helpdesk</strong> — a real person who knows your setup, not a ticket queue.</li>\n<li><strong>IT strategy</strong> — plain-language advice on what to fix now and what can wait.</li>\n</ul>\n<!-- /wp:list -->\n\n<!-- wp:heading {\"level\":2} -->\n<h2>Why local matters</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>We're not a national call center. We know the region, we can show up in person, and you talk to the same people every time. When your IT partner is local, \"we'll send someone out\" isn't a two-week wait.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><a href=\"/contact/#quote-form\">Get a free assessment →</a></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><!-- ***-42 mini quote form: insert [contact-form-7 id=\"...\" html_id=\"quote-form-mini\"] here once the CF7 \"Quote Mini Form\" entity exists (see docs/quote-form-build-guide.md §5). --></p>\n<!-- /wp:paragraph -->",
	);

	// 3. /services/automation-2/ — Business Automation (canonical linked variant).
	$defs[] = array(
		'paths'      => array( 'services/automation-2', 'automation-2', 'services/automation', 'automation' ),
		'signatures' => array( '***-20 copy pending' ),
		'body'       => "<!-- wp:heading {\"level\":1} -->\n<h1>Business Automation</h1>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>We connect the tools you already use and automate the busywork between them — so your team spends time on customers, not copy-paste.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":2} -->\n<h2>Your team is doing work software should be doing</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Re-typing the same order into three systems. Copying data from email into a spreadsheet. Chasing an approval that should just happen. None of it is hard — it's just slow, and it adds up. Automation isn't about replacing people; it's about giving your team back the hours they lose to work the computer could be doing.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":2} -->\n<h2>How we approach it</h2>\n<!-- /wp:heading -->\n\n<!-- wp:list -->\n<ul>\n<li>We start with the tools you already have — no rip-and-replace.</li>\n<li>We find the handoffs that eat the most time and automate those first.</li>\n<li>We build it to be maintainable, and we're the same team that supports your IT, so it doesn't become an orphaned script no one understands.</li>\n</ul>\n<!-- /wp:list -->\n\n<!-- wp:paragraph -->\n<p>Because we also run your IT, automation is an extension of the systems we already manage — not a bolt-on from a vendor who doesn't know your setup.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><a href=\"/contact/#quote-form\">Tell us what's eating your team's time →</a></p>\n<!-- /wp:paragraph -->",
	);

	// 4. /services/web-2/ — Web Development (canonical linked variant).
	$defs[] = array(
		'paths'      => array( 'services/web-2', 'web-2', 'services/web', 'web' ),
		'signatures' => array( '***-20 copy pending' ),
		'body'       => "<!-- wp:heading {\"level\":1} -->\n<h1>Web Development</h1>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Fast, secure, maintainable websites — built and hosted by the same team that manages your IT, so there is one number to call when it matters.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":2} -->\n<h2>A website is infrastructure, not a one-time project</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Plenty of businesses have a site someone built years ago that nobody can safely touch now. When your website and your IT are handled by the same team, updates, security, and hosting aren't three separate vendors pointing at each other — they're one partner who already knows your business.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":2} -->\n<h2>What you get</h2>\n<!-- /wp:heading -->\n\n<!-- wp:list -->\n<ul>\n<li><strong>Fast</strong> — quick load times, because speed is part of looking professional.</li>\n<li><strong>Secure</strong> — kept patched and monitored, the same way we treat the rest of your IT.</li>\n<li><strong>Maintainable</strong> — built so it can be updated, not frozen the day it launches.</li>\n</ul>\n<!-- /wp:list -->\n\n<!-- wp:paragraph -->\n<p><a href=\"/contact/#quote-form\">Get a quote for your site →</a></p>\n<!-- /wp:paragraph -->",
	);

	return $defs;
}

/**
 * Resolve a service page by trying each candidate path in order. Uses
 * get_page_by_path (full-hierarchy match); falls back to a leaf post_name lookup
 * so a page nested under an unexpected parent is still found.
 */
function uplinksync_service_copy_resolve( array $paths ) {
	foreach ( $paths as $path ) {
		$page = get_page_by_path( $path, OBJECT, 'page' );
		if ( $page instanceof WP_Post ) {
			return $page;
		}
	}
	// Leaf fallback: last path segment as a bare slug.
	foreach ( $paths as $path ) {
		$slug  = substr( strrchr( '/' . $path, '/' ), 1 );
		$found = get_posts(
			array(
				'post_type'      => 'page',
				'name'           => $slug,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
			)
		);
		if ( ! empty( $found ) && $found[0] instanceof WP_Post ) {
			return $found[0];
		}
	}
	return null;
}

/**
 * True only if the page still carries an untouched placeholder signature — i.e.
 * the owner has not edited it. Guarantees owner edits are never overwritten.
 */
function uplinksync_service_copy_is_placeholder( $content, array $signatures ) {
	foreach ( $signatures as $sig ) {
		if ( '' !== $sig && false !== strpos( $content, $sig ) ) {
			return true;
		}
	}
	return false;
}

function uplinksync_service_copy_run() {
	foreach ( uplinksync_service_copy_definitions() as $def ) {
		$page = uplinksync_service_copy_resolve( $def['paths'] );
		if ( ! ( $page instanceof WP_Post ) ) {
			continue; // Page absent — no-op.
		}

		// Owner-edited? (no matching placeholder signature) => leave it alone.
		if ( ! uplinksync_service_copy_is_placeholder( $page->post_content, $def['signatures'] ) ) {
			continue;
		}

		// Already the approved copy? => idempotent no-op.
		if ( trim( $page->post_content ) === trim( $def['body'] ) ) {
			continue;
		}

		wp_update_post(
			array(
				'ID'           => $page->ID,
				'post_content' => $def['body'],
			),
			true
		);
	}
}

/**
 * One-shot guard. The pass is itself idempotent (placeholder gate + equality
 * check both no-op after the first apply), so this is belt-and-suspenders and
 * avoids re-reading the pages on every request once applied.
 */
function uplinksync_service_copy_maybe_run() {
	if ( get_option( UPLINKSYNC_SERVICE_COPY_OPTION ) === UPLINKSYNC_SERVICE_COPY_VERSION ) {
		return;
	}
	uplinksync_service_copy_run();
	update_option( UPLINKSYNC_SERVICE_COPY_OPTION, UPLINKSYNC_SERVICE_COPY_VERSION );
}
add_action( 'init', 'uplinksync_service_copy_maybe_run', 20 );
