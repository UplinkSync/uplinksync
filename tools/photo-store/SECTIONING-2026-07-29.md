# ***-178 — store sectioned by location (applied to live, 2026-07-29)

Owner approved "sections by location." This records what was applied to the LIVE
store (uplinksync.com). **The store stays gated** — WooCommerce coming-soon was
NOT touched; `/shop/`, the new location archives, and the draft `/prints/` page
all redirect anonymous visitors (verified after the change).

## 1. Count reconciliation (the 123-vs-153 question)

Live `wc/v3/products?status=any` → **152 products total**:

| Set | Count | Status | Notes |
|---|---:|---|---|
| Aerial prints, category "Aerial Photography" (id 22) | **122** | publish | the sellable store |
| Legacy service products ("Drone …/Service/Essential Package") | 30 | **draft** | uncategorised, out of scope, pre-existing |
| **Total** | **152** | | ≈ the "~153" the owner recalled |

- **True store count = 122 published aerial prints.**
- **No duplicates** among the 122 — unique `_***_photo_slug`, title and wp-slug on every one.
- Palisades is **83, not 84** — the earlier "No.26" privacy delete removed one.
- The 30 legacy drafts contain repeated names among themselves (several "Drone Video
  Package"), but they are **draft, uncategorised, and unrelated to the print store**.
  Left untouched and flagged — NOT deleted (ambiguous legacy; owner call).

## 2. Sub-categories created (parent = Aerial Photography, id 22)

| id | Sub-category | Slug | Prints | Theme |
|---:|---|---|---:|---|
| 25 | Palisades Reservoir | `palisades-reservoir` | 83 | Mountainscapes |
| 26 | Pocatello | `pocatello` | 20 | Cityscapes |
| 27 | Idaho Falls | `idaho-falls` | 10 | Cityscapes |
| 28 | Kaysville | `kaysville` | 5 | Cityscapes |
| 29 | Soda Springs | `soda-springs` | 4 | Cityscapes |

All 122 published products were assigned to their location sub-category **and kept in
parent id 22**, so the parent archive still lists everything. Reproduced by
`assign_location_categories.py` (idempotent).

**Thin locations:** Kaysville (5) and Soda Springs (4) each get their own tile for now
(SEO completeness). If five uneven tiles look off, fold both under one "Utah & Idaho
towns" tile with an in-archive location filter — owner's call.

## 3. Gallery index page

- Page **id 964**, slug `prints`, title "Aerial Print Gallery", **status: draft** (gated).
- House block markup: dark hero (`uls-bg-dark`) + light location-card grid (`uls-card`,
  each card → `/product-category/<slug>/`) + `uls-cta-band` navy-wash CTA to `/contact/`.
- Preview while logged in: `https://uplinksync.com/?page_id=964` (or the editor Preview).
- **Cover image per location + intro copy are placeholders** — each card shows a house
  navy-wash gradient panel labelled "Cover image to follow." NO fabricated photos/claims.
  These need Doug/Cadmus (see below).

## 4. Owner / Cadmus follow-ups

- **Cover image per location** (5) — pick the hero frame for each tile.
- **Per-location intro copy** (one line each) + index copy in the house voice.
- Publish decision for `/prints/` (stays draft/gated until launch); add to footer
  (tertiary), not primary nav, per `drone-gallery-store-strategy.md`.
- Pricing display stays **"From …"** — tiles show "From $29" / "From $39", no firm
  per-print numbers.
- The two live-launch blockers remain owner-only and were NOT touched: connect Stripe
  live keys, then turn off WooCommerce coming-soon.

## 5. Reversal

- Backups: `/tmp/store-backup-20260729/` at apply time (products-before.json,
  categories-before.json). Category re-assignment is non-destructive.
- To undo sectioning: delete sub-cats 25–29
  (`DELETE wc/v3/products/categories/<id>?force=true`); products remain in parent 22,
  returning the store to the single-grid state. Trash page 964 to remove the index.
