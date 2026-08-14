# `snippets-export/` — read-only mirror of database-resident code

## What this is

The live site runs the **Code Snippets** plugin, which executes PHP stored as rows in the
`wp_snippets` database table. As of 2026-08-14 that is **67 snippets, ~173 KB of executable PHP**
that existed in **no repository, on no filesystem, and under no version control**.

This directory is a **mirror**, exported from the live host so that code can be *reviewed, diffed
and secret-scanned*. Full analysis:
`ecosystem-docs/current/117-detail-uplinksync-web-database-resident-code.md`.

## ⚠️ This is not the source of truth

| | |
|---|---|
| **Authoritative copy** | the `wp_snippets` database row on the live site |
| **This directory** | a point-in-time snapshot |
| **Editing a file here** | changes **nothing** on the site |
| **Deployed?** | **No.** Site-mode deploys rsync `wp-content/` only; this sits at the repo root and never reaches the host |

To change a snippet you must edit it in **wp-admin → Snippets**, then re-export. There is currently
no sync in either direction — that gap is deliberate and recorded as a Tier-A decision in
`ecosystem-docs/current/115-…-decision-framework.md`.

## Why it matters

Every safety control this project relies on operates on repo files, so before this export **all of
it covered 0 %** of this code: `php_lint`, `gitleaks_secret_scan`, `wp_config_secret_scan`, MR
review, `git revert`, and the deploy marker.

That is not a hypothetical gap. Among the active snippets are:

- **`082` — guided quote flow** (53 KB): the site's primary conversion mechanism
- **`081` — quote configurator** (20 KB): internal quote routing
- **`093` — client proofing gallery** (13 KB): an **access-control engine**, never security-reviewed
- **`112` — ULS DRM v1**: emits the HLS player on *every* page (see doc 117 §3)
- **`120` — Phase 3 IA redirects**: shapes URL behaviour the repo cannot explain

## Layout

`NNN-slug.php` — `id`-prefixed, one file per snippet, with a header recording `id`, `name`, `scope`
and whether it was `ACTIVE` at export time. Scopes `global` / `front-end` / `admin` / `single-use`
export as `.php` (all 66 pass `php -l`). The single `content`-scope snippet is a shortcode fragment,
not PHP, and exports as `.txt`.

## Secret scan

The export was scanned **before** being committed — deliberately, since committing credentials would
put them in git history permanently. Ten credential patterns (Stripe live/test, assigned literals,
bearer tokens, private keys, AWS, GitHub, GitLab, JWT, URL basic-auth) were checked across all
173 KB: **clean, no credential-shaped literals**.

`gitleaks_secret_scan` runs full-history on this repo, so from now on these files are covered
continuously.

## Refreshing

Re-export any time with (from the site root on the host):

```bash
wp --skip-plugins --skip-themes eval '
  $r = $GLOBALS["wpdb"]->get_results("SELECT id,name,scope,active,code FROM wp_snippets ORDER BY id", ARRAY_A);
  echo json_encode($r);'
```

Treat a diff here as a **record of a change someone made in wp-admin**, not as a change to deploy.
