# Licence tiers — DB steps (staging DONE 2026-10-28; production = owner-gated)

Code (this MR) is inert until the products are migrated and the flag is set: tier UI keys off variable
products with `pa_licence`; the v2 licence page/tab text and the checkout fields key off option
`uls_licence_tiers_v2 = 1`.

## Production gates (all must be cleared first)
1. Counsel signs off licence v2.0 (`uplinksync-licence-tiers/licence-page.php` + `config.php` tier text) and
   `docs/legal-drafts/commissioned-work-licence-v2.md`.
2. Accountant answers sales tax for digital licences (ID now, UT from 11-06) and the Woo base address.
3. DR-006 G5: the migrate script sets downloads-require-login + no guest checkout + 5 downloads / 30 days.
   Before prod, remove `uplinksync-hide-account-login.php` (header account icon) so buyers can reach My Account.
4. Stripe live end-to-end test on prod with a real low-value purchase + refund (staging Stripe is inactive with
   live keys, never in test mode — no Stripe test was possible there).

## Steps (run from the WP root; prod IDs differ, the script finds products by category)
```bash
# 0. backup (wp db export dies on this host)
TS=$(date +%Y%m%d%H%M); D=~/bak-licence-tiers-$TS; mkdir -p $D; chmod 700 $D
MYSQL_PWD="$(wp config get DB_PASSWORD)" mysqldump --single-transaction --no-tablespaces \
  -h "$(wp config get DB_HOST)" -u "$(wp config get DB_USER)" "$(wp config get DB_NAME)" | gzip > $D/db.sql.gz
# 1. copy tools/licence-tiers/{migrate,undo}.php to $D, then dry run (expect: would migrate 122, skipped 0)
wp eval-file $D/migrate.php dry  $D/full
# 2. optional one-product trial + undo, then the real run
wp eval-file $D/migrate.php apply $D/trial <product_id> && wp eval-file $D/undo.php $D/trial
wp eval-file $D/migrate.php apply $D/full
# 3. verify: 122 variable products, 4 variations each, min $35 / max $399; 488 variations
wp eval '$c=[];foreach(wc_get_products(["status"=>"publish","limit"=>-1,"category"=>["aerial-photography"]]) as $p){$k=$p->get_type()."/".count($p->get_children())."/".$p->get_price();$c[$k]=($c[$k]??0)+1;} print_r($c);'
```
What `migrate.php apply` changes: creates global attribute `pa_licence` (terms personal / commercial-credit /
commercial / extended); converts each simple photo product to variable with 4 variations ($35/$79/$119/$399,
virtual, downloadable, same master file, limit 5, expiry 30 days), default Personal; replaces "Personal +
commercial license" in descriptions; sets options `woocommerce_downloads_require_login=yes`,
`woocommerce_enable_guest_checkout=no`, `woocommerce_enable_signup_and_login_from_checkout=yes`,
`uls_licence_tiers_v2=1`. Logs: `migrate-log.tsv`, `content.jsonl`, `options.tsv`.

## Undo
```bash
wp eval-file $D/undo.php $D/full   # deletes variations, restores simple type/price/copy/options, drops pa_licence if it created it
```
Proven on staging (product 811: apply → undo → apply → undo, byte-identical copy, price 45, type simple).
Last resort: `zcat $D/db.sql.gz | mysql ...`. Page 1265 content is NOT changed (v2 text is rendered by the
mu-plugin behind the flag); `wp option delete uls_licence_tiers_v2` alone reverts the page/tab/checkout text.
