#!/usr/bin/env python3
"""
***-178 — organise the aerial-print store into location sub-categories.

Owner approved "sections by location" (2026-07-29). This script is the
reproducible, reversible record of what was applied to the LIVE store:

  1. Creates five sub-categories under "Aerial Photography" (id 22), one per
     real location surfaced by the catalogue reconciliation.
  2. Assigns every PUBLISHED aerial-print product to its location sub-category,
     keeping it in parent id 22 as well so the parent archive still lists all.

It does NOT touch product status, pricing, the 30 legacy DRAFT service products,
or the store gating (WooCommerce coming-soon stays ON). Re-running is safe:
existing sub-categories are reused by slug, assignments are idempotent.

Reconciliation (live, 2026-07-29, `wc/v3/products?status=any`):
  152 products total = 122 PUBLISH (all in cat 22) + 30 DRAFT legacy
  "Drone …/Service Package" products (uncategorised, out of scope).
  The ~153 the owner recalled = these 152. No duplicates among the 122
  (unique _***_photo_slug, title and wp slug). Palisades is 83 not 84 —
  the earlier "No.26" privacy delete removed one.

  Location split of the 122 published prints:
    Palisades Reservoir, ID  83   (Mountainscapes)
    Pocatello, ID            20   (Cityscapes)
    Idaho Falls, ID          10   (Cityscapes)
    Kaysville, UT             5   (Cityscapes)
    Soda Springs, ID          4   (Cityscapes)

Auth: WordPress application password (Basic auth), same as create_products.py.
  Env: WORDPRESS_URL, WORDPRESS_USERNAME, WORDPRESS_APP_PASSWORD.
  (curl backend — Cloudflare 403s python-urllib's TLS fingerprint.)

REVERSAL: delete the five sub-categories (wc/v3/products/categories/<id>?force=true).
  Products remain in parent id 22, so the store returns to the single-grid state.
  Location assignment is otherwise non-destructive.
"""
import base64, json, os, re, subprocess, sys

BASE = os.environ["WORDPRESS_URL"].rstrip("/")
AUTH = "Basic " + base64.b64encode(
    f'{os.environ["WORDPRESS_USERNAME"]}:{os.environ["WORDPRESS_APP_PASSWORD"]}'.encode()
).decode()

# Real locations surfaced by reconciliation → (display name, slug, US state note).
SUBCATS = [
    ("Palisades Reservoir", "palisades-reservoir", "Aerial prints over Palisades Reservoir, Idaho."),
    ("Pocatello",           "pocatello",           "Aerial prints over Pocatello, Idaho."),
    ("Idaho Falls",         "idaho-falls",         "Aerial prints over Idaho Falls, Idaho."),
    ("Kaysville",           "kaysville",           "Aerial prints over Kaysville, Utah."),
    ("Soda Springs",        "soda-springs",        "Aerial prints over Soda Springs, Idaho."),
]
# location string (product meta / catalogue) → sub-category slug
LOC_SLUG = {
    "Palisades Reservoir, ID": "palisades-reservoir",
    "Pocatello, ID": "pocatello",
    "Idaho Falls, ID": "idaho-falls",
    "Kaysville, UT": "kaysville",
    "Soda Springs, ID": "soda-springs",
}
PARENT = 22  # Aerial Photography


def curl(method, path, payload=None):
    url = path if path.startswith("http") else f"{BASE}{path}"
    cmd = ["curl", "-sS", "-X", method, url, "-H", f"Authorization: {AUTH}", "--max-time", "180"]
    if payload is not None:
        cmd += ["-H", "Content-Type: application/json", "-d", json.dumps(payload)]
    out = subprocess.run(cmd, capture_output=True, text=True).stdout
    return json.loads(out)


def meta(product, key):
    for m in product.get("meta_data", []):
        if m.get("key") == key:
            return m.get("value")
    return None


def location_of(product):
    """Prefer the _***_photo_slug→catalogue join; fall back to the title prefix."""
    slug = meta(product, "_***_photo_slug")
    if slug and slug in CATALOGUE:
        return CATALOGUE[slug]["location"]
    m = re.match(r"^(.*?)\s+(Aerial View|Mountain Vista|Aerial)\b", product["name"])
    prefix = m.group(1).strip() if m else None
    for loc in LOC_SLUG:
        if prefix and loc.startswith(prefix):
            return loc
    return None


def load_catalogue():
    import csv
    path = os.path.join(os.path.dirname(__file__), "store_catalogue.csv")
    return {r["slug"]: r for r in csv.DictReader(open(path))} if os.path.exists(path) else {}


def ensure_subcats():
    existing = {c["slug"]: c["id"] for c in curl("GET", "/wp-json/wc/v3/products/categories?per_page=100")}
    ids = {}
    for name, slug, desc in SUBCATS:
        if slug in existing:
            ids[slug] = existing[slug]
            print(f"exists  id={existing[slug]:>4}  {name}")
            continue
        r = curl("POST", "/wp-json/wc/v3/products/categories",
                 {"name": name, "slug": slug, "parent": PARENT, "description": desc})
        ids[slug] = r["id"]
        print(f"created id={r['id']:>4}  {name}")
    return ids


def assign(subcat_ids):
    products, page = [], 1
    while True:
        d = curl("GET", f"/wp-json/wc/v3/products?per_page=100&status=publish&page={page}")
        if not isinstance(d, list) or not d:
            break
        products += d
        if len(d) < 100:
            break
        page += 1
    updates, unmapped = [], []
    for p in products:
        loc = location_of(p)
        if not loc:
            unmapped.append((p["id"], p["name"]))
            continue
        updates.append({"id": p["id"], "categories": [{"id": PARENT}, {"id": subcat_ids[LOC_SLUG[loc]]}]})
    print(f"published={len(products)}  to-assign={len(updates)}  unmapped={len(unmapped)}")
    for u in unmapped:
        print("  UNMAPPED:", u)
    done = 0
    for i in range(0, len(updates), 40):
        r = curl("POST", "/wp-json/wc/v3/products/batch", {"update": updates[i:i + 40]})
        done += len(r.get("update", [])) if isinstance(r, dict) else 0
    print(f"assigned={done}")
    return not unmapped


if __name__ == "__main__":
    CATALOGUE = load_catalogue()
    ids = ensure_subcats()
    ok = assign(ids)
    print("subcat ids:", ids)
    sys.exit(0 if ok else 1)
