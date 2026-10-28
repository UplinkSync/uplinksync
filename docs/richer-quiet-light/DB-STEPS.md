# DB steps — Richer Quiet Light site (branch `feat/richer-quiet-light-site`)

Run by the orchestrator over the Hostinger SSH account, **staging first** (`~/domains/uplinksync.com/public_html/staging`),
then production (`~/domains/uplinksync.com/public_html`) only after owner approval (gate C). Run each block from the
WordPress root. `$B` is a fresh backup directory. Nothing here deletes content: every change is a status flip or a
meta change with a JSON backup and a one-line undo.

```sh
B=~/bak-rql-site-$(date +%Y%m%d%H%M); mkdir -p "$B"
```

## 0. Why these steps exist

The five pages are now rendered by theme files (`templates/front-page.html`, `page-services.html`,
`page-drone-services.html`, `page-about.html`, `page-contact.html` → `patterns/site-*.php`). WordPress ignores a theme
template when (a) a Site-Editor-saved copy of it exists in the DB (`wp_template` / `wp_template_part`), or (b) the page
has a custom template assigned in `_wp_page_template`. The theme only trims the legacy CSS/GSAP when the resolved
template carries the `uls-site` marker, so a missed step leaves the old page **styled as before** (not unstyled) — but
it will not show the new design.

## 1. Inventory (read-only)

```sh
wp post list --post_type=page --name=home,services,drone-services,about,contact,request-quote --post_status=any \
  --fields=ID,post_name,post_status --format=table
wp option get page_on_front
wp post list --post_type=wp_template,wp_template_part --post_status=any --fields=ID,post_name,post_status --format=table
```

Expected overrides (same IDs on staging and prod as of 2026-10-27): `1266` front-page, `828` header, `399` footer
(already drafted on prod 10-27 — confirm still `draft`), `410` page-drone-services (still `publish`). Also look for any
`page-services`, `page-about`, `page-contact` rows.

## 2. Draft the DB template overrides that shadow the theme files

For each ID found in step 1 among: page-drone-services (410), page-services, page-about, page-contact, and — if any
are back to `publish` — front-page (1266), header (828), footer (399):

```sh
for ID in 410; do   # add any other IDs from step 1
  wp post get $ID --format=json > "$B/wp_template-$ID.json"
  wp post update $ID --post_status=draft
done
```

(REST `POST /wp/v2/templates/{id}` with `status:draft` is silently ignored — use WP-CLI.)
**Undo:** `wp post update <ID> --post_status=publish`.

## 3. Clear custom page-template assignments on the four inner pages

```sh
for SLUG in services drone-services about contact; do
  ID=$(wp post list --post_type=page --name=$SLUG --field=ID)
  T=$(wp post meta get $ID _wp_page_template 2>/dev/null)
  echo "$SLUG $ID template='${T}'" | tee -a "$B/page-templates.txt"
  if [ -n "$T" ] && [ "$T" != "default" ]; then wp post meta delete $ID _wp_page_template; fi
done
```

**Undo:** for each line in `$B/page-templates.txt` with a value: `wp post meta update <ID> _wp_page_template '<value>'`.
The page content itself is NOT edited (it simply stops rendering); keep it as the rollback source.

## 4. Contact form re-seed (automatic) — back up first

`inc/quote-form-seed.php` bumps `UPLINKSYNC_QUOTE_FORM_VERSION` to `3`; on the first request after deploy the theme
rewrites the master CF7 form (pill service picker, optional phone, "Send my request"). Field names are unchanged, so
the mail template and Flamingo keep working.

```sh
FID=$(wp option get uplinksync_quote_master_form_id)
wp post get $FID --format=json > "$B/cf7-master-$FID.json"
wp post meta get $FID _form > "$B/cf7-master-$FID-form.txt"
# after deploy + one page view:
wp option get uplinksync_quote_form_seeded_version   # expect 3
```

**Undo:** `wp post meta update $FID _form "$(cat $B/cf7-master-$FID-form.txt)"`, or roll the theme back and
`wp option delete uplinksync_quote_form_seeded_version` (the old seeder re-applies version 2 on the next request).

## 5. Flush caches

```sh
wp cache flush
wp litespeed-purge all
```
(Cloudflare: the stylesheet URL is versioned by file mtime, so no edge purge is needed for CSS; purge HTML if a page
still shows the old markup.)

## 6. Verify

```sh
for P in / /services/ /drone-services/ /about/ /contact/; do
  H=$(curl -s "https://staging.uplinksync.com$P" -u "$STAGING_BASIC_AUTH")   # credentials from Vault, not inline
  echo "$P marker=$(echo "$H" | grep -c 'uls-site rq') 4biz=$(echo "$H" | grep -c '4 businesses') \
gsap=$(echo "$H" | grep -c 'gsap') ticketq=$(echo "$H" | grep -ci 'ticket queue')"
done
```
Expect `marker=1 4biz=0 gsap=0 ticketq=0` on all five. Then open each page and the "Get a quote" / "Book a call" /
"Estimate your project" triggers (estimator modal + booking dialog must still open).

## 7. Optional switches (owner decisions, no deploy needed)

```sh
# Publish the phone number (header, footer, contact page, Organization schema). Empty/unset = hidden everywhere.
wp option update uplinksync_phone '(555) 555-0100'          # or define('UPLINKSYNC_PHONE', '...') in wp-config.php
# Real, permitted client quotes (render nothing until at least one exists; max 3 shown):
wp option update uplinksync_reviews '[{"quote":"…","name":"First name","role":"Role, Business"}]' --format=json
```
**Undo:** `wp option delete uplinksync_phone` / `wp option delete uplinksync_reviews`.

## Notes for the orchestrator
- DB snippet 108 (`[uls_browse_collections]`) is no longer rendered on /drone-services/ — the gallery is file-driven
  (own stills, location-free captions, "Full gallery" → /prints/). The snippet can stay active (unused) or be
  deactivated later.
- DB snippet 110 auto-loops `.uls-air-videocard--9x16` players; the new vertical clips deliberately do not use that
  class, so they only play when pressed.
- The old DB page content of the four inner pages (with the internal HTML comments) remains in the DB but is no
  longer output. A later clean-up may blank it; not required.

## 8. QA follow-ups (2026-10-28) — no DB change required
- Mini-cart: removed in code from the header on non-store pages (WooCommerce Block Hooks filter + render filter).
  Store pages (shop, product, cart, checkout, account, /prints/) keep it. Nothing to do in the DB.
- Share/schema image: the Rank Math home image setting points at `…/aerial-stills/idaho-falls-full.jpg`. The theme
  now swaps place-named image URLs for the page hero on the five pages. Optionally also change the Rank Math image to
  a place-free file in wp-admin (Rank Math → Titles & Meta → Global/Homepage); undo = set it back.
- `/request-quote/` now uses `templates/page-request-quote.html`. If a Site Editor override `page-request-quote`
  exists, draft it as in §2. If the page has a custom `_wp_page_template`, clear it as in §3.
