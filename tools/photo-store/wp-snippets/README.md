# WP Code Snippets (live-deployed)

These PHP snippets are deployed via the **Code Snippets** plugin on live
`uplinksync.com` (REST: `/wp-json/code-snippets/v1/snippets`). They are checked
in here for durability/review; the live activation is the source of truth.

## ***247-store-clean-grid-thumbs.php
- **Issue:** ***-247 (owner checkbox `ded52157` Option A, 2026-07-23).
- **Live snippet id:** 45, scope `front-end`, active.
- **What it does:** For the 123 aerial products, serves the CLEAN master
  attachment for small grid/browse thumbnail sizes; medium+ (single image,
  click-to-enlarge, zoom) keep the WATERMARKED preview. Purchase download
  (clean master) unchanged.
- **Why a display swap, not a GD watermark filter:** each product already has
  both a watermarked preview attachment AND a clean full-res master attachment
  (uploaded on ***-178). The only rule violation was the grid thumb deriving
  from the watermarked preview — so we point small sizes at the already-present
  clean master instead of re-rendering anything.
- **Revert:** deactivate snippet 45 (POST `/snippets/45/deactivate`).
- **Verified live 2026-07-23:** single-product page serves
  `...-master-...-300x300.jpg` (clean) as the gallery thumb and
  `...-600x338/1024/1536/full.jpg` (watermarked preview) for medium+.
