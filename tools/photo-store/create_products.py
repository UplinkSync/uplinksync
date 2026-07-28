#!/usr/bin/env python3
"""
***-178 — create WooCommerce digital-download products from the graded photo
catalogue.

For each of the confirmed catalogue rows (owner confirmed all 123 on ***-176):
  1. Upload the graded WATERMARKED preview  -> product featured image (public).
  2. Upload the graded CLEAN master under an unguessable content-hash filename
     -> the downloadable file (only reachable via Woo's tokenized, paid
     download endpoint because the filename is not derivable from the slug).
  3. Create a virtual + downloadable simple product (title, price, category,
     short description) with download_limit/expiry unset (unlimited, standard
     for photo licenses) and the master attached as its single download.

Idempotent + resumable: keeps a state file mapping slug -> {preview_id,
master_id, product_id}. Re-running skips slugs already fully created and only
does the missing steps. Safe to interrupt.

Slug is reconstructed deterministically from the committed manifest row order,
matching batch_export.py exactly:  {i:03d}-{cat[:4].lower()}-{slugify(location)}

Env: WORDPRESS_URL, WORDPRESS_USERNAME, WORDPRESS_APP_PASSWORD.

Usage:
  create_products.py <manifest.csv> <previews_master_root> [--limit N] [--dry-run]
"""
import argparse, base64, csv, hashlib, json, os, re, sys, time
import urllib.request, urllib.error

CATEGORY_ID = 22          # "Aerial Photography" (created on ***-178)
STATE_FILE = None         # set in main()

def slugify(s):
    s = re.sub(r"[^A-Za-z0-9]+", "-", s).strip("-").lower()
    return re.sub(r"-+", "-", s)

def _auth_header():
    u = os.environ["WORDPRESS_USERNAME"]; p = os.environ["WORDPRESS_APP_PASSWORD"]
    return "Basic " + base64.b64encode(f"{u}:{p}".encode()).decode()

BASE = os.environ["WORDPRESS_URL"].rstrip("/")
AUTH = _auth_header()

def _curl(method, path, *, json_data=None, binary_file=None, extra_headers=None):
    """Shell out to curl — Cloudflare fingerprints python-urllib TLS and 403/429s
    it, but lets curl through cleanly. Returns parsed JSON dict."""
    url = path if path.startswith("http") else f"{BASE}{path}"
    cmd = ["curl", "-sS", "-X", method, url,
           "-H", f"Authorization: {AUTH}",
           "--max-time", "300", "-w", "\n__HTTP__%{http_code}"]
    if extra_headers:
        for k, v in extra_headers.items():
            cmd += ["-H", f"{k}: {v}"]
    if json_data is not None:
        cmd += ["-H", "Content-Type: application/json",
                "--data-binary", "@-"]
        stdin = json.dumps(json_data).encode()
    elif binary_file is not None:
        cmd += ["-H", "Content-Type: image/jpeg",
                "--data-binary", f"@{binary_file}"]
        stdin = None
    else:
        stdin = None

    import subprocess
    for attempt in range(6):
        p = subprocess.run(cmd, input=stdin, capture_output=True)
        out = p.stdout.decode(errors="replace")
        marker = out.rfind("\n__HTTP__")
        code = out[marker + len("\n__HTTP__"):].strip() if marker >= 0 else ""
        body = out[:marker] if marker >= 0 else out
        try:
            code_i = int(code)
        except ValueError:
            code_i = 0
        if code_i in (200, 201):
            return json.loads(body)
        if code_i in (429, 500, 502, 503, 504, 0) and attempt < 5:
            time.sleep(6 * (attempt + 1)); continue
        raise RuntimeError(f"{method} {path} -> HTTP {code}: {body[:300]}")
    raise RuntimeError("unreachable")

def _req(method, path, *, data=None, headers=None, is_json=True):
    # kept for API symmetry; routes to curl
    return _curl(method, path, json_data=(data if is_json else None),
                 extra_headers=headers)

def upload_media(local_path, filename, title=None, alt=None):
    headers = {"Content-Disposition": f'attachment; filename="{filename}"'}
    res = _curl("POST", "/wp-json/wp/v2/media", binary_file=local_path,
                extra_headers=headers)
    mid = res["id"]
    # set title/alt for the featured image so the media library is tidy
    if title or alt:
        try:
            _req("POST", f"/wp-json/wp/v2/media/{mid}",
                 data={k: v for k, v in (("title", title), ("alt_text", alt)) if v})
        except Exception:
            pass
    # WordPress auto-scales images past the "big image" threshold (2560px):
    # source_url then points at the DOWNSCALED "-scaled.jpg". The full-res
    # original is retained at the un-suffixed URL. For the paid download we MUST
    # serve the original, so resolve it from media_details.original_image.
    src = res.get("source_url")
    orig_url = src
    md = res.get("media_details", {}) or {}
    orig_name = md.get("original_image")
    if orig_name and src:
        orig_url = src.rsplit("/", 1)[0] + "/" + orig_name
    return mid, {"source_url": src, "original_url": orig_url}

def load_state():
    if STATE_FILE and os.path.exists(STATE_FILE):
        return json.load(open(STATE_FILE))
    return {}

def save_state(state):
    tmp = STATE_FILE + ".tmp"
    json.dump(state, open(tmp, "w"), indent=1)
    os.replace(tmp, STATE_FILE)

def short_desc(row):
    return (f"Original aerial drone photograph — {row['draft_title']}. "
            f"{row['location']}, captured {row['capture_date']}. "
            f"{row['megapixels']} MP full-resolution JPEG, instant digital "
            f"download after purchase. Personal + commercial license; no physical "
            f"item is shipped.")

def main():
    global STATE_FILE
    ap = argparse.ArgumentParser()
    ap.add_argument("manifest")
    ap.add_argument("root", help="dir containing previews/ and masters_clean/")
    ap.add_argument("--limit", type=int, default=0)
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--pace", type=float, default=2.0,
                    help="seconds to sleep between products (Cloudflare pacing)")
    ap.add_argument("--state", default=None)
    a = ap.parse_args()

    STATE_FILE = a.state or os.path.join(os.path.dirname(os.path.abspath(a.manifest)),
                                         "create_products_state.json")
    rows = list(csv.DictReader(open(a.manifest)))
    if a.limit:
        rows = rows[: a.limit]
    state = load_state()

    created = skipped = failed = 0
    for i, r in enumerate(rows, 1):
        cat = r["category"]; loc = slugify(r["location"].split(",")[0])
        slug = f"{i:03d}-{cat[:4].lower()}-{loc}"
        prev = os.path.join(a.root, "previews", slug + ".jpg")
        mast = os.path.join(a.root, "masters_clean", slug + ".jpg")
        st = state.setdefault(slug, {})

        if st.get("product_id"):
            skipped += 1
            print(f"[{i}/{len(rows)}] {slug} already done (product {st['product_id']})")
            continue

        if not (os.path.exists(prev) and os.path.exists(mast)):
            print(f"[{i}/{len(rows)}] {slug} MISSING FILES prev={os.path.exists(prev)} mast={os.path.exists(mast)}")
            failed += 1; continue

        title = r["draft_title"]; price = str(r["draft_price_usd"])
        if a.dry_run:
            print(f"[{i}/{len(rows)}] DRY {slug} '{title}' ${price}")
            continue

        try:
            # unguessable master filename = slug + content hash (only the paid
            # Woo download link exposes it; raw uploads URL is not derivable)
            h = hashlib.sha256(open(mast, "rb").read()).hexdigest()[:16]
            master_name = f"{slug}-master-{h}.jpg"

            if not st.get("preview_id"):
                pid, purl = upload_media(prev, f"{slug}.jpg", title=title,
                                         alt=f"{title} — aerial drone photograph")
                st["preview_id"] = pid; st["preview_url"] = purl["source_url"]
                save_state(state)
            if not st.get("master_id"):
                mid, murl = upload_media(mast, master_name, title=f"{title} (full-res master)")
                st["master_id"] = mid
                # download must serve the FULL-RES original, not WP's -scaled copy
                st["master_url"] = murl["original_url"]
                st["master_scaled_url"] = murl["source_url"]
                save_state(state)

            product = {
                "name": title,
                "type": "simple",
                "status": "publish",
                "catalog_visibility": "visible",
                "regular_price": price,
                "virtual": True,
                "downloadable": True,
                "tax_status": "taxable",
                "categories": [{"id": CATEGORY_ID}],
                "images": [{"id": st["preview_id"]}],
                "short_description": short_desc(r),
                "description": short_desc(r),
                "downloads": [{"name": f"{title} (full-resolution)", "file": st["master_url"]}],
                "download_limit": -1,
                "download_expiry": -1,
                "sold_individually": True,
                "meta_data": [
                    {"key": "_***_photo_slug", "value": slug},
                    {"key": "_***_source_issue", "value": "***-178"},
                ],
            }
            res = _req("POST", "/wp-json/wc/v3/products", data=product)
            st["product_id"] = res["id"]; st["permalink"] = res.get("permalink")
            save_state(state)
            created += 1
            print(f"[{i}/{len(rows)}] OK {slug} -> product {res['id']} {res.get('permalink')}", flush=True)
            if a.pace: time.sleep(a.pace)
        except Exception as e:
            failed += 1
            print(f"[{i}/{len(rows)}] FAIL {slug}: {e}", flush=True)
            time.sleep(5)

    print(f"\nDONE created={created} skipped={skipped} failed={failed} "
          f"total_products={sum(1 for v in state.values() if v.get('product_id'))}")

if __name__ == "__main__":
    main()
