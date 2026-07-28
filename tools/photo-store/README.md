# Phase 2 drone-photo store tooling (***-176)

Prep + export pipeline for the sellable drone-photo catalogue.
Source (mounted, not in repo): `/mnt/propair/Landscape` — Cityscapes + Mountainscapes only.
Real-Estate/ is client work and NOT sellable.

## Scripts

- `build_manifest.py` — walks the source tree, reads JPEG dimensions from SOF
  markers (no external libs), parses location + capture date from paths/filenames,
  writes `candidate_manifest.csv` (one row per JPG).
- `enrich_manifest.py` — adds clean display location, draft per-image title
  (`<City> <Aerial View|Mountain Vista> No. NN`) and draft price by resolution
  tier (36MP→$29, 48–50MP→$39, 80MP+→$45). Draft only — owner confirms.
- `export_photo.py` — per-image export. Produces `<slug>_full.jpg` (full-res,
  EXIF/GPS stripped, q95) and `<slug>_preview.jpg` (long-edge 1600px, centered
  "UplinkSync ©" watermark + corner attribution, q85). Requires Pillow.

## candidate_manifest.csv

123 candidates: 39 Cityscapes, 84 Mountainscapes, all landscape. Owner marks the
`SELECT_yes` column to confirm the final sellable set, and may override
`draft_title` / `draft_price_usd`. Locations: Idaho Falls ID, Pocatello ID,
Kaysville UT, Soda Springs (labeled UT in source — verify), Palisades Reservoir ID.

## Grade-first pipeline (owner direction 2026-07-22)

Owner reversed the order: **produce the finished set first, select from real
previews** (not the manifest). New watermark = tiled arrow icon, not text.

- `assets/arrow-icon.png` / `arrow-icon-white.png` — the UplinkSync **arrow
  figure** isolated from `uplinksync-logo-transparent2.png` (icon only, no
  wordmark/text). The white silhouette is what gets tiled.
- `export_graded.py` — per-image grade + export. Order: (1) color-correct
  (clamped gray-world WB + 0.5% levels stretch + gentle contrast/saturation),
  (2) tiled full-field arrow-icon watermark on the preview (light/translucent,
  ~14% opacity, slight diagonal, faint dark emboss for legibility on bright &
  dark scenes), (3) clean graded full-res master staged separately.
  Outputs: `masters_clean/<slug>.jpg` (purchase deliverable, EXIF/GPS stripped),
  three size-based renditions (see below).
- `batch_export.py` — drives `export_graded` over the whole manifest, writes
  `run_report.csv`.

### Size-based watermark rule (owner, 2026-07-23)

Watermark **medium+ only** — thumbnails stay clean (a tiny browse thumb isn't
commercially usable), the enlarge/full view is what a thief would grab so it gets
the tiled arrow mark. `export_graded.export()` emits three renditions per image:

| Output | Long edge | Watermark |
|---|---|---|
| `thumbs/<slug>.jpg`        | 800px  | **clean** (browse/grid) |
| `large/<slug>.jpg`         | 1920px | **tiled arrow** (click-to-enlarge / hero) |
| `masters_clean/<slug>.jpg` | full   | **clean** (post-purchase deliverable) |

Opacity default is 0.22 (owner's earlier "make the arrow more visible" note);
the 2026-07-23 comment cites an 8–12% range — flagged to owner to reconcile,
`--opacity 0.12` renders the lighter variant.
- `contact_sheet.py` — paginated JPG selection sheets from `run_report.csv`
  (thumb + slug + draft title + price) plus `SELECTION.csv` for the owner to
  mark KEEP.

Pillow install (base image lacks it): `python3 -m pip install --user --break-system-packages Pillow`

Run (writes to the writable mirror, reads masters in place read-only):
```
python3 batch_export.py candidate_manifest.csv /mnt/propair/Landscape \
    /mnt/uplinksync/DroneProjects/Landscape/_store-previews --opacity 0.22
python3 contact_sheet.py /mnt/uplinksync/DroneProjects/Landscape/_store-previews
```

Fonts: uses `/usr/share/fonts/truetype/freefont/FreeSansBold.ttf`.

### Legacy
`export_photo.py` — the old single-image exporter with the text watermark
(pre-2026-07-22). Superseded by `export_graded.py`; kept for reference.
