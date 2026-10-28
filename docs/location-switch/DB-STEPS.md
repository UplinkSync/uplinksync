# Location switch — DB steps (run by the orchestrator; nothing here runs from CI)

Switch: option `uplinksync_location_mode` (`neutral` default | `utah`), overridable by
`define( 'UPLINKSYNC_LOCATION_MODE', 'utah' );` in wp-config.php (constant wins; use `'neutral'` as the no-DB emergency rollback).
Utah mode **refuses to activate** (stays neutral, red admin notice, `wp option update ... utah` is rejected) until
`uplinksync_location_utah_ready` is set (option, or constant `UPLINKSYNC_LOCATION_UTAH_READY`).

## Why this exists
The neutraliser (`uplinksync-location-neutral.php`) hides Idaho-era copy at render time; the database still holds it.
Utah mode switches the neutraliser OFF, so every stored Idaho string would go live. The stored copy must be rewritten first.

## 0. Nothing changes until step 5
Deploying this MR is a no-op in the default state (neutral). Only the head JSON-LD gains `founder` (and `telephone` if `UPLINKSYNC_PHONE` is set).

## 1. Backup (staging first, then prod)
```
wp db export /root/backups/pre-location-switch-$(date +%Y%m%d-%H%M).sql.gz --add-drop-table
```
Undo for everything below: `wp db import <that file>` (or restore only the touched posts from it).

## 2. Find what must be rewritten (read-only)
Identity strings: `Idaho`, `Boise`, `Rexburg`, `Ammon`, `Blackfoot`, `Eastern Idaho`, `Lakeharbor`, `(208) 995-2704`, `83703`, and "local"-as-claim wording.
```
wp search-replace 'Idaho' 'Idaho' --all-tables --dry-run --report-changed-only      # counts per table/column
wp db query "SELECT ID,post_type,post_name FROM wp_posts WHERE post_status IN ('publish','draft','private') AND (post_content REGEXP 'Idaho|Boise|Rexburg|Ammon|Blackfoot|Lakeharbor|995-2704' OR post_title REGEXP 'Idaho|Rexburg') ORDER BY post_type,ID"
wp db query "SELECT post_id,meta_key FROM wp_postmeta WHERE meta_value REGEXP 'Idaho|Boise|Rexburg|Ammon|Blackfoot' AND meta_key REGEXP 'rank_math|_wp_attachment_image_alt|_yoast' "
wp db query "SELECT term_id,taxonomy FROM wp_term_taxonomy WHERE description REGEXP 'Idaho|Rexburg|Boise'"
wp db query "SELECT id,name FROM wp_snippets WHERE code REGEXP 'Idaho|Boise|Rexburg'"      # Code Snippets (84 estimator label, 108 collection tiles)
```
Known locations (from the neutraliser's exact-match table): front-page `wp_template` 1266; drone FAQ `wp_template` 410 (visible text + FAQPage JSON-LD); pages 278, 279, 1256, 1262, 1291 (Rank Math title/description/focus keyword); About 383 + its attachment alt/caption; Contact page; legal pages (Terms "Idaho law / Bonneville County", Privacy "Business address ... Boise"); product_cat 26 and 29 descriptions; vertical-reel captions + attachment alt; Code Snippet 84 (estimator label "Distance from Idaho Falls") and 108 (tile alt).

**Allowed to remain** (photo/collection subjects, not service-area claims): `Pocatello`, `Idaho Falls`, `Kaysville`, `Saratoga Springs`, `Palisades Reservoir` as product/collection/gallery names and URL slugs.

## 3. Rewrite the stored copy (per object, via wp-cli, after owner approves wording)
The five pages (Home, Services, Drone & Aerial, About, Contact) are file-driven (MR !181) and already use the `[uplinksync_location_line]` / `[uplinksync_service_area]` slots: do NOT put slots into their DB content. This step covers the remaining DB objects only (legal pages, product_cat, Rank Math meta, other pages). Use neutral wording from `enrich/page-copy.md`:
```
wp post get <ID> --field=post_content > /tmp/<ID>.before.html          # keep as the per-post undo
wp post update <ID> /tmp/<ID>.after.html
wp post meta update <ID> rank_math_title '<neutral title>'             # same for rank_math_description / rank_math_focus_keyword
wp term update product_cat 26 --description='<neutral>' ; wp term update product_cat 29 --description='<neutral>'
```
Legal pages: replace the Idaho governing-law / Bonneville County sentence and the Boise mailbox with the Utah-appropriate text **only after the owner/counsel supplies it**; until then use the neutral sentences the neutraliser emits today. Do not publish a home address (service-area business).
Estimator (Snippet 84) is owned by MR-2: its label must become "Estimated travel" there; do not edit it from this MR.
Contact form: the required Phone field follows the phone decision (`UPLINKSYNC_PHONE`, validated by uplinksync-phone.php).

## 4. Verify the stored copy is clean (must print 0 rows)
```
wp db query "SELECT COUNT(*) FROM wp_posts WHERE post_status='publish' AND post_content REGEXP 'Idaho|Boise|Rexburg|Ammon|Blackfoot|Lakeharbor|995-2704' AND post_type NOT IN ('product','attachment','revision')"
wp db query "SELECT COUNT(*) FROM wp_postmeta WHERE meta_key REGEXP '^rank_math_(title|description|focus_keyword)$' AND meta_value REGEXP 'Idaho|Boise|Rexburg|Ammon|Blackfoot'"
wp db query "SELECT COUNT(*) FROM wp_term_taxonomy WHERE description REGEXP 'Idaho|Rexburg|Boise'"
```
Then, still in neutral mode, `curl` the five pages and confirm rendered HTML/JSON-LD has no Idaho strings except the allowed photo subjects.

## 5. Flip (only when the owner is established in Utah, move 2026-11-06)
```
wp option update uplinksync_location_utah_ready 1
wp option update uplinksync_location_base_city '<city>'            # optional; without it the block says nothing about being "based in"
wp option update uplinksync_location_travel_policy '<text>'        # optional
wp option update uplinksync_location_mode utah
wp cache flush ; wp rank-math cache flush 2>/dev/null ; (LiteSpeed/Redis purge if present)
```
Check: titles/meta changed on home, services, drone, about, contact; JSON-LD in Rich Results Test (ProfessionalService, no address); `[uplinksync_service_area]` renders; `grep -ci idaho` on the five pages = 0 (excluding photo-subject collections).
Rollback (no DB needed): add `define( 'UPLINKSYNC_LOCATION_MODE', 'neutral' );` to wp-config.php; or `wp option update uplinksync_location_mode neutral`; or `wp option delete uplinksync_location_utah_ready` (mode then refuses utah). Stored-copy rewrites from step 3 are neutral-compatible, so they do not need undoing.

## Options / constants reference
| Name | Kind | Default | Effect |
|---|---|---|---|
| `uplinksync_location_mode` / `UPLINKSYNC_LOCATION_MODE` | option / constant (wins) | `neutral` | `utah` only exact string |
| `uplinksync_location_utah_ready` / `UPLINKSYNC_LOCATION_UTAH_READY` | option / constant | unset | gate; unset/`0` = utah refused |
| `UPLINKSYNC_PHONE` (option `uplinksync_phone` fallback) | constant | empty | schema `telephone` (both modes) when set |
| `uplinksync_location_base_city`, `uplinksync_location_travel_policy` | option | empty | service-area block wording |
| filter `uplinksync_location_utah_cities` | filter | 13 cities | trim list to places actually served |
