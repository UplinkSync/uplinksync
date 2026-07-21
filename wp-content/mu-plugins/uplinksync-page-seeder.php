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
const UPLINKSYNC_PAGE_SEED_VERSION = '1.0.0';
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
