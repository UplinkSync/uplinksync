# DB-STEPS — hand the "Get a quote" estimator from Code Snippets #82/#84 to the mu-plugin

MR: `feat/quiet-light-estimator`. Run on **staging first**, then production only after owner approval.
Orchestrator-run (WP-CLI on the target host). Nothing here deletes data; every step is reversible.

## What changes
| Row | Name | Before | After |
|---|---|---|---|
| 81 | UPLAA-337 Quote configurator (admin-ajax `uls337_target_quote` → internal target-quote e-mail) | active | **stays active** (the new flow still posts to it) |
| 82 | UPLAA-337 Guided quote flow `[uls_quote_flow]` | active | **inactive** |
| 84 | UPLAA-337 Unified quote modal | active | **inactive** |

Until 82/84 are inactive, `mu-plugins/uplinksync-estimator.php` stays **dormant** (it reads `wp_snippets`
read-only and renders nothing), so deploying the files alone changes nothing on the front end. As soon as both rows
are inactive it takes over (within 5 min, or immediately after step 4's transient delete).

## 0. Pre-checks (read-only)
```sh
cd <wp-root>
PFX=$(wp db prefix)
wp db query "SELECT id, name, active, modified FROM ${PFX}snippets WHERE id IN (81,82,84)"
# Refresh the repo mirror of the live code (read-only) and diff it against snippets-export/:
for id in 81 82 84; do wp db query "SELECT code FROM ${PFX}snippets WHERE id=$id" --skip-column-names > /tmp/snippet-$id-live.php; done
# Expected: 82's only difference from snippets-export/082-* is the disclaimer wording ("can change", already used by the mu-plugin).
ls wp-content/mu-plugins/uplinksync-estimator.php wp-content/mu-plugins/uplinksync-estimator/   # files deployed?
```

## 1. Backup (keep both files with the change record)
```sh
TS=$(date +%Y%m%d-%H%M%S)
wp db export ~/bak-estimator-$TS-snippets.sql --tables=${PFX}snippets
wp eval 'global $wpdb; echo wp_json_encode( $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}snippets WHERE id IN (81,82,84)", ARRAY_A ), JSON_PRETTY_PRINT );' > ~/bak-estimator-$TS-snippets-81-82-84.json
```

## 2. Deactivate 82 and 84 (NOT 81)
```sh
wp db query "UPDATE ${PFX}snippets SET active = 0 WHERE id IN (82,84)"
wp db query "SELECT id, active FROM ${PFX}snippets WHERE id IN (81,82,84)"   # expect 81=1, 82=0, 84=0
```
(If the Code Snippets WP-CLI commands exist on the host, `wp snippet deactivate 82 84` is equivalent.)

## 3. Flush caches
```sh
wp cache flush
wp transient delete uls_est_legacy_snippets
wp litespeed-purge all     # LiteSpeed page cache (skip if the command is absent)
```
Cloudflare is DYNAMIC for these pages today; purge it too if that ever changes.

## 4. Verify (no booking!)
```sh
B=https://staging.uplinksync.com   # add basic auth on staging
curl -s $B/ | grep -c 'id="uls-qmodal"'                          # 1
curl -s $B/ | grep -c 'uplinksync-estimator/estimator.js'         # 1
curl -s $B/request-quote/ | grep -c 'id="uls-qf"'                 # 1
curl -s $B/ | grep -c 'embed/embed.js\|id="uls-book-modal"'      # 0 — the old booking dialog + Cal.com embed are gone site-wide
curl -s $B/ | grep -o '<a[^>]*data-uls-book-open="uls-qmodal"[^>]*>' | head -3   # "Book a call" links: real hrefs, native trigger
U="$B/wp-json/uplinksync/v1/slots?event=it&start=$(date -u +%F)&end=$(date -u -d '+14 days' +%F)"
curl -s "$U" | head -c 300                                         # {"ok":true,"event":"it","lengthMinutes":30,"slots":[...],"nonce":...}
curl -s -o /dev/null -w '%{http_code}\n' $B/wp-json/wp/v2/users   # 401 — the REST lock is still on for everything else
# Staging is not behind Cloudflare (openresty, direct), so per-IP limits there key on the peer address.
# Page-cache check needs TWO requests (the first is always a MISS):
curl -sI "$U" >/dev/null; curl -sI "$U" | grep -i 'cache-control\|x-litespeed-cache\|cf-cache-status'
#   expect: cache-control: no-store, private ...; NO "x-litespeed-cache: hit"; cf-cache-status DYNAMIC/BYPASS (never HIT)
```
Belt-and-braces (recommended, admin UI, not required): LiteSpeed Cache → Excludes → "Do Not Cache URIs" add
`/wp-json/uplinksync/`; and confirm no Cloudflare "Cache Everything" rule matches `/wp-json/*`.

Owner-side controls (Cal.com Cloud event settings for it-consult 7469726 and uav-consult 7469727; currently OFF per the
public event-type read on 2026-10-27): "Requires booker email verification", "Limit booking frequency" (e.g. 3/day). These
also cover direct use of Cal.com's public API, which the site's limits cannot. Site daily cap: option
`uls_estimator_daily_cap` (default 20 booking attempts/day).

In a browser: Get a quote → steps 1–2 → step 3 shows the calendar in your time zone. "Book a call" / "Book time with
us" open the same modal in its "Book a call" mode (details → calendar); the call length shown is what Cal.com reports.
Note: until step 2 runs the estimator is dormant, so those links simply navigate to book.uplinksync.com (no old dialog). **Do not press "Confirm booking"
on staging or production** — the Cal.com event types are live and a confirmed slot is a real booking on Doug's calendar
(the Cal.com confirmation e-mails go out). Booking itself was verified locally against a mocked api.cal.com.

## Undo (any time)
```sh
wp db query "UPDATE ${PFX}snippets SET active = 1 WHERE id IN (82,84)"
wp cache flush; wp transient delete uls_est_legacy_snippets; wp litespeed-purge all
```
The mu-plugin sees 82/84 active again and goes dormant; the old flow is back. Hard kill switch without a DB change:
`define( 'ULS_ESTIMATOR_DISABLE', true );` in wp-config.php **plus** reactivating 82/84 (otherwise there is no estimator).
If a row was ever edited by mistake, restore it from the step 1 SQL/JSON backup.
