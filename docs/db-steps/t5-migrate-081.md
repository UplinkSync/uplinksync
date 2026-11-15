# DB-STEPS — migrate Code Snippets row #81 (UPLAA-337 target-quote handler)

MR: `feat/t5-migrate-081`. Run on **staging first**; production requires a separate owner-approved run. Row 81 is deactivated, never deleted. Rows 82 and 84 are already supplied by `mu-plugins/uplinksync-estimator`; this run moves only their remaining admin-ajax dependency, row 81.

The plugin file is a behaviour-identical port. Do not combine endpoint hardening with this cutover; it is a separate, stacked MR after differential parity is accepted.

## 0. Pre-checks (read-only)

```sh
cd <wp-root>
PFX=$(wp db prefix)
wp db query "SELECT id, name, active, scope, priority, modified FROM ${PFX}snippets WHERE id = 81"
wp db query "SELECT code FROM ${PFX}snippets WHERE id = 81" --skip-column-names > /tmp/snippet-81-live.php
diff -u wp-content/snippets-export/081-uplaa-337-quote-configurator-internal-target-quote-routing.php /tmp/snippet-81-live.php
```

`snippets-export/` is only a mirror and may be stale. The live row must be diffed and any material difference reviewed before deactivation. Confirm the plugin file and manifest id 81 are deployed. Record the live row's priority; it must remain 10.

## 1. Backup

```sh
TS=$(date +%Y%m%d-%H%M%S)
wp db export ~/bak-t5-migrate-081-$TS-snippets.sql --tables=${PFX}snippets
wp eval 'global $wpdb; echo wp_json_encode( $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}snippets WHERE id = 81", ARRAY_A ), JSON_PRETTY_PRINT );' > ~/bak-t5-migrate-081-$TS-snippet-81.json
```

Keep both backup files with the change record.

## 2. Differential test on staging (doc 123)

Compare build A (row 81 active, plugin id 81 disabled through `uls_site_code_enabled`) with build B (plugin id 81 enabled, row 81 inactive). Switch plugin first, then the database row; run `wp cache flush` after every A ⇄ B transition.

For both `/services/` and `/request-quote/`, run the doc 123 registration, HTTP-contract, mail-capture, and browser assertions. Include the full branch matrix, no-price paths, CTA capture phase, Cal.com prefill/fallback, and shared-Cal dialog sequence. Do **not** complete a Cal.com booking.

Install only a temporary observational `pre_wp_mail` capture hook at a priority below the staging no-mail blocker (`PHP_INT_MAX`). It must capture `$atts` and return `null`; leave the outbound-mail blocker active. Assert the recipient, subject, Reply-To, body markers, contact-only target text, and that the honeypot sends no captured mail. Remove the shim and verify its removal before cutover. Never re-enable outbound mail on staging.

## 3. Deactivate row 81 (only after build B passes)

```sh
wp db query "UPDATE ${PFX}snippets SET active = 0 WHERE id = 81"
wp db query "SELECT id, active FROM ${PFX}snippets WHERE id = 81"   # expect 81=0
wp cache flush
```

LiteSpeed's object cache can retain compiled snippet code; flushing is mandatory after the state change.

## 4. Verify on staging first

Re-run the doc 123 Layer 1 registration assertions and Layer 2 direct HTTP contract, then one complete non-booking journey on each entry page with the mail-capture shim still observationally active. Confirm the shim is removed immediately afterward. Keep a result log with the build-A/build-B differential results and the staging cache flushes.

## Rollback

```sh
wp db query "UPDATE ${PFX}snippets SET active = 1 WHERE id = 81"
wp cache flush
```

Disable plugin id 81 with the `uls_site_code_enabled` filter during the rollback verification, then repeat one non-booking journey. The original database row resumes sole authority; do not leave both implementations enabled after the verification window.
