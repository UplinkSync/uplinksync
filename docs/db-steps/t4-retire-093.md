# DB-STEPS — retire Code Snippets row #93 (UPLAA-179 proofing gallery)

MR: `feat/t4-retire-093`. Run on **staging first**; production requires a separate owner-approved run. These steps deactivate the row only — they do not delete it, albums, or attachments.

The replacement destination is the DroneOps portal + Invoice Ninja. The deployed plugin provides a 302 compatibility redirect for former proofing page, category, product, and diagnostic REST URLs to `/hub/`.

## 0. Pre-checks (read-only)

```sh
cd <wp-root>
PFX=$(wp db prefix)
wp db query "SELECT id, name, active, scope, priority, modified FROM ${PFX}snippets WHERE id = 93"
wp db query "SELECT code FROM ${PFX}snippets WHERE id = 93" --skip-column-names > /tmp/snippet-93-live.php
diff -u wp-content/snippets-export/093-uplaa-179-client-proofing-gallery-access-engine-uplaa-clie.php /tmp/snippet-93-live.php
```

`snippets-export/` is a mirror and can be stale. Review the live-row diff before deactivation; if it differs materially, stop and update/review this MR before proceeding. Confirm the plugin file `wp-content/plugins/uplinksync-site-code/snippets/093-uls-proofing-retired.php` is deployed and the manifest includes id 93.

Do not delete the aurumgrove/respberry albums, attachment IDs 1177–1243, or run a Cloudflare purge: each is owner-gated and out of scope.

## 1. Backup

```sh
TS=$(date +%Y%m%d-%H%M%S)
wp db export ~/bak-t4-retire-093-$TS-snippets.sql --tables=${PFX}snippets
wp eval 'global $wpdb; echo wp_json_encode( $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}snippets WHERE id = 93", ARRAY_A ), JSON_PRETTY_PRINT );' > ~/bak-t4-retire-093-$TS-snippet-93.json
```

Keep both backup files with the change record.

## 2. Deactivate row 93 (do not delete it)

```sh
wp db query "UPDATE ${PFX}snippets SET active = 0 WHERE id = 93"
wp db query "SELECT id, active FROM ${PFX}snippets WHERE id = 93"   # expect 93=0
```

## 3. Flush compiled snippet cache

```sh
wp cache flush
```

LiteSpeed's object cache can retain compiled snippet code; do this after each active/inactive change.

## 4. Verify on staging first

```sh
B=https://staging.uplinksync.com
curl -sSI "$B/aurumgrove-proofing/" | grep -Ei '^(HTTP|location:)'   # 302 + /hub/
curl -sSI "$B/respberry-proofing/" | grep -Ei '^(HTTP|location:)'   # 302 + /hub/
curl -sSI "$B/wp-json/uplaa179/v1/status" | grep -Ei '^(HTTP|location:)' # 302 + /hub/
curl -s -o /dev/null -w '%{http_code}\n' "$B/wp-json/wp/v2/users/wc/store/" # 401, never 404
```

Run the local F1 and M1/M2 tests from this MR before the staging run. Test anonymous, client, and admin callers against `wp/v2/media` and `wc/store/v1/products*`; ensure former proofing requests redirect and that the existing DR-006 media control still fails closed without row 93.

## Rollback

```sh
wp db query "UPDATE ${PFX}snippets SET active = 1 WHERE id = 93"
wp cache flush
```

The original database row resumes ownership. The plugin shim detects row 93's
runtime symbols and no-ops in its `template_redirect` and `rest_api_init`
callbacks, so it neither pre-empts the gallery gate nor registers a competing
catch-all route. Leave the shim installed for a later re-retirement; do not
delete row 93.
