# Gallery watermark (***-245)

Applies the tiled UplinkSync arrow icon-grid watermark to the public
`/drone-services/` gallery's **medium+** renditions so full-size aerials
can't be lifted from the marketing site.

Owner rule (2026-07-23): **thumbnails clean, medium size or larger watermarked.**

| Rendition | File suffix | Watermark? |
|---|---|---|
| thumbnail / grid-browse (800w) | `*-thumb.*` | **clean** |
| full (1920w) | `*-full.*` | **icon-grid watermark** |
| hero (2560w) | `*-hero.*` | **icon-grid watermark** |

Mark matches the Phase-2 store previews (***-176): the isolated arrow glyph
(`arrow_icon.png`), tiled full-field, slight diagonal, faint dark emboss,
~12% opacity — light but un-croppable, readable on bright skies and dark water.

## Re-run

Requires `sharp` (Node). Point at the clean masters, then overwrite in place:

```
node watermark_gallery.mjs \
  path/to/*-full.webp path/to/*-full.jpg path/to/*-hero.webp path/to/*-hero.jpg
```

Clean gallery masters are staged at
`/mnt/uplinksync/DroneProjects/Landscape/_website-deliverables/stills-clean-gallery-masters/`
(and buyers/owner still get the unmarked store masters under `_store-previews/masters_clean/`).
The script is idempotent only against **clean** inputs — never re-run it on an
already-watermarked file, or the mark will double up.
