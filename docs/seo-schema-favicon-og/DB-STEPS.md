# SEO schema / favicon / og:image — host + DB steps

The three fixes are **code** (`wp-content/mu-plugins/uplinksync-seo-meta.php` + theme assets) and work
with no DB change. The steps below are: one host file copy (needed for `/favicon.ico`), cache purges,
and two optional DB tidy-ups. Run with `hostsh.py staging|prod` (it exports `$WP` and `cd`s into it).

## Root cause (for the record)
Admin Site Enhancements "Obfuscate Author Slugs" (`obfuscate_author_slugs = true`) filters `author_link` with
`str_replace( '/' . $author_slug, '/' . <encrypted id>, $link )`. Most pages have `post_author = 0`, so core passes an
**empty** slug and every `/` in `https://uplinksync.com/author/` gets the hash appended:
`https:/<h>/<h>uplinksync.com/<h>author/<h>`. Rank Math uses that as the Person `@id`/`url` and the Article author.
Reproduce: `wp eval 'var_dump(get_author_posts_url(0));'`.
The mu-plugin repairs `author_link` (priority 11, after ASE) and, in `rank_math/json_ld` (priority 25), drops the nameless
Person and credits the Article to the Organization. Nothing stored in the DB is corrupted.

## 1. Backup (record current values; tiny)
```bash
P=$(wp db prefix)
mkdir -p ~/bak-seo-$(date +%Y%m%d%H%M) && cd ~/bak-seo-* && \
  wp --path="$WP" option get site_icon > site_icon.txt && \
  wp --path="$WP" db query "SELECT ID,post_name,post_author FROM ${P}posts WHERE post_type='page' AND post_author=0" --skip-column-names > pages-author0.tsv && \
  ls -la; cd "$WP"
```

## 2. `/favicon.ico` (REQUIRED for a 200; the web server answers `.ico` statically, WordPress never sees it)
```bash
test -e "$WP/favicon.ico" || cp "$WP/wp-content/themes/uplinksync-child/assets/images/brand/favicon/favicon.ico" "$WP/favicon.ico"
chmod 644 "$WP/favicon.ico"
```
Undo: `rm "$WP/favicon.ico"` (only if step 2 created it).

## 3. Purge caches (REQUIRED)
```bash
wp cache flush
wp rank-math cache flush 2>/dev/null || true
wp litespeed-purge all 2>/dev/null || true
```

## 4. OPTIONAL — make wp-admin's Site Icon match the brand mark
The code already serves the theme icon while the stored icon is the `generated-test` image (or none). To make the
Customizer show the same mark (after this, the uploaded icon is used and the code override stands down):
```bash
ID=$(wp media import "$WP/wp-content/themes/uplinksync-child/assets/images/brand/favicon/icon-512.png" --title="UplinkSync site icon" --porcelain)
wp option update site_icon "$ID"
```
Undo: `wp option update site_icon "$(cat ~/bak-seo-*/site_icon.txt)"` (was `36` on staging and prod).
Note: WordPress crops a transparent-cornered tile; the theme override uses the same art, so either way looks the same.

## 5. OPTIONAL — give author-less pages a real author
Not needed for the schema fix. If done, Rank Math emits a Person named after user 1's display name
(currently "UplinkSync"), with an obfuscated `/author/<hex>/` URL, so only do it together with an owner decision on
that display name.
```bash
P=$(wp db prefix)
wp db query "UPDATE ${P}posts SET post_author=1 WHERE post_type='page' AND post_author=0"
```
Undo: `cut -f1 ~/bak-seo-*/pages-author0.tsv | xargs -I{} wp db query "UPDATE ${P}posts SET post_author=0 WHERE ID={}"`.
Root-cause note: `uplinksync-page-seeder.php` inserts pages on `init` with no current user, which is why they get author 0.

## 6. Verify
```bash
for p in / /about/ /services/ /drone-services/ /contact/; do
  curl -s "https://uplinksync.com$p" | grep -oE 'https:/[0-9a-f]{16,}' | head -1      # expect nothing
  curl -s "https://uplinksync.com$p" | grep -oE '<meta property="og:image" content="[^"]+"'
done
curl -s -o /dev/null -w '%{http_code}\n' https://uplinksync.com/favicon.ico        # 200
curl -s https://uplinksync.com/ | grep -oE '<link rel="(icon|apple-touch-icon)"[^>]+>'  # brand/favicon/… not generated-test
```
Then Google Rich Results Test on `/about/` (no errors) and a LinkedIn Post Inspector / Facebook Sharing Debugger
re-scrape of `/`, `/drone-services/`, `/contact/` so the new cards replace the cached empty previews.
