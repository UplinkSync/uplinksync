# DB steps — interim Client Hub (branch `feat/client-hub-interim`)

The orchestrator runs these over the Hostinger SSH account. Run them on **staging first** (`~/domains/uplinksync.com/public_html/staging`).
Run them on production only after owner approval. Run every block from the WordPress root. `$B` is a fresh backup directory.
Nothing here deletes content. Every change is a meta or status flip with a backup and a one-line undo.

Prerequisite: !181 (Richer Quiet Light site) is merged and live; this branch is rebased on `main`.

```sh
B=~/bak-client-hub-$(date +%Y%m%d%H%M); mkdir -p "$B"
```

## 0. What changes and why the DB matters

These are file-driven now:
- `/hub/` uses `templates/page-hub.html` → `patterns/site-hub.php`.
- `/prints/` uses `templates/page-prints.html` → `patterns/site-prints.php`. Its covers are the watermarked previews; the old `-master-…-1024x576` covers return 403.
- `/my-account/` uses `templates/page-my-account.html`.
- Cart, checkout, order received, product, shop and the collection pages get the child header and footer plus a "Back to Client Hub" bar.

WordPress ignores a theme template in two cases:
- **(a)** the page has a custom `_wp_page_template`. Live, `/hub/` (ID 1285) and `/prints/` (ID 964) both use `no-title`, seen in the body class `page-template-no-title`.
- **(b)** a Site-Editor copy exists in the DB (`wp_template` / `wp_template_part`).

If a step is missed, the page stays exactly as it is today. It does not break.

## 1. Inventory (read-only)

```sh
wp post list --post_type=page --name=hub,prints,my-account,cart,checkout,shop --post_status=any --fields=ID,post_name,post_status --format=table
for S in hub prints my-account cart checkout; do ID=$(wp post list --post_type=page --name=$S --field=ID); echo "$S $ID '$(wp post meta get $ID _wp_page_template 2>/dev/null)'"; done
wp post list --post_type=wp_template,wp_template_part --post_status=publish --fields=ID,post_name,post_status --format=table
```
In the `wp_template` list, look for any of these: `page-hub`, `page-prints`, `page-my-account`, `page-cart`, `cart`, `page-checkout`, `checkout`, `order-confirmation`, `single-product`, `archive-product`, `taxonomy-product_cat`. In the `wp_template_part` list, look for `header` and `footer`; !181 already drafted 828 and 399.

## 2. Clear the `no-title` template on /hub/ and /prints/

```sh
for S in hub prints my-account; do
  ID=$(wp post list --post_type=page --name=$S --field=ID)
  T=$(wp post meta get $ID _wp_page_template 2>/dev/null)
  echo "$S $ID template='${T}'" | tee -a "$B/page-templates.txt"
  if [ -n "$T" ] && [ "$T" != "default" ]; then wp post meta delete $ID _wp_page_template; fi
done
```
**Undo:** for each line in `$B/page-templates.txt` that has a value, run `wp post meta update <ID> _wp_page_template '<value>'`.
The page content is not edited. It just stops rendering, and it stays in the DB as the rollback source.

## 3. Draft any DB template that shadows the files (only the IDs found in step 1)

```sh
for ID in <ids>; do wp post get $ID --format=json > "$B/wp_template-$ID.json"; wp post update $ID --post_status=draft; done
```
**Undo:** `wp post update <ID> --post_status=publish`.

## 4. Theme pattern cache + page caches

`style.css` is bumped to 1.1.1, which invalidates WordPress's theme-pattern transient. The commands below force it anyway: new pattern files stay unregistered for up to 30 min otherwise, and `/hub/` and `/prints/` would render an empty `<main>`.

```sh
wp eval 'wp_get_theme()->delete_pattern_cache(); echo "pattern cache cleared\n";'
wp cache flush
wp litespeed-purge all
```
Cloudflare: purge the HTML of `/hub/`, `/prints/`, `/my-account/`, `/shop/` and `/cart/` if they still show old markup. CSS URLs are versioned by mtime.

## 5. Verify

```sh
H() { curl -s "https://staging.uplinksync.com$1" -u "$STAGING_BASIC_AUTH"; }   # credentials from Vault, never inline
echo "hub tiles=$(H /hub/ | grep -c 'class="htile"') sign=$(H /hub/ | grep -c 'sign.uplinksync.com/sign_in')"   # expect tiles=6 sign>=1
echo "prints cards=$(H /prints/ | grep -c 'class="pcard"') master=$(H /prints/ | grep -c -- '-master-')"         # expect cards=5 master=0
for P in /my-account/ /cart/ /shop/ /product-category/pocatello/; do echo "$P backbar=$(H $P | grep -c 'Back to Client Hub')"; done   # expect 1 each
H /prints/ | grep -o 'https://[^"]*/uploads/[^"]*\.jpg' | sort -u | while read U; do echo "$(curl -s -o /dev/null -w '%{http_code}' -u "$STAGING_BASIC_AUTH" "$U") $U"; done   # expect all 200
```
Then check by eye at 1280 and 390:
- **/hub/:** pay, photos and sign open in a new tab; "Book a call" opens the booking modal.
- **/my-account/:** signed out, then signed in (orders and downloads).
- **/shop/:** the header nav is white, not blue.
- **In-content links** (product short description and breadcrumbs, cart item name, My Account intro and "Lost your password?"): accent blue `#0c5fc1` on light, and "Add to cart" / "Select options" labels white on navy.
- **Cart and checkout:** each shows the header, the back bar and the store header.

## 5b. /hub/ signed in (no DB change)
- `/hub/` is one page for both states. Signed out: public tiles + "Sign in to your client hub" (`/my-account/?redirect_to=%2Fhub%2F`, back to /hub/ after login). Signed in: the UplinkPortal shell (`uplinkportal_render_portal()`, when the plugin is active) on top, tiles below as "Everything else"; without the plugin, a welcome header + tiles.
- Signed in, the page sends `Cache-Control: no-store, private`, defines `DONOTCACHEPAGE` and calls `litespeed_control_set_nocache`. Check: `curl -sI -b "<logged-in cookie>" https://staging.uplinksync.com/hub/ | grep -i cache-control` → `no-store`; signed out → cacheable as before. Cloudflare must bypass on `wordpress_logged_in_*` (prod rule, T4).
- `/portal/` → `/hub/` alias stays as is (snippet 120).

## 6. Nothing else in the DB

- Code Snippets **045** (preview → master map) and **108** stay as they are.
- `wp-content/.htaccess` (UPLAA-412 master deny) is unchanged. The covers now use files it allows.
- WooCommerce settings are untouched. Registration stays as configured: on prod the register form is off, and the template styles it if it is ever turned on.
