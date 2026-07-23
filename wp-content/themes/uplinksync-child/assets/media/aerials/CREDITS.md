# UplinkSync Air/Drone Section — Cleared Aerial Deliverables

**Issue:** ***-201 · **Source clearance:** ***-160 (owner cleared all 5 `Landscape` shoots for public/marketing use).

## Usage rights (owner constraint — REQUIRED)
- **Personal/spec work, attribution required.** Every published asset must carry a visible credit.
- **Credit line:** `Aerial by UplinkSync — uplinksync.com`
- Stills carry the credit in EXIF (`Artist` / `Copyright` / `ImageDescription`); video carries a burned-in on-screen credit.
- **No client material** used. All frames are from the owner-cleared `Landscape` set only. `Real-Estate` / `Events` / `_Sort` were excluded.
- Source share is **READ-ONLY**; no RAW was copied around or modified. Deliverables were rendered from camera-original masters read in place.

## Deliverables

### Gallery stills (`stills/`)
Developed from camera-original JPEG masters (gentle contrast/saturation/sharpen; EXIF orientation honored). Each frame in WebP + progressive JPEG.

| Frame | Location | Sizes |
|---|---|---|
| `palisades` (hero) | Palisades, Idaho — Mountainscape | hero 2560w, full 1920w, thumb 800w |
| `pocatello` | Pocatello, Idaho — Brooklyn's Playground | full 1920w, thumb 800w |
| `kaysville` | Kaysville, Utah | full 1920w, thumb 800w |
| `saratoga-springs` | Saratoga Springs, Utah | full 1920w, thumb 800w |
| `idaho-falls` | Idaho Falls, Idaho | full 1920w, thumb 800w |

Primary hero = Palisades `DJI_20250927094432_0023_D.JPG` (per WEBSITE_SHORTLIST.md).

> **Video hosting (owner decision, ***-186 / ***-203).** The hero reel and
> social clips are **NOT committed** into this repo. Per the owner's call, site
> video is served from biz-immich (`media.uplinksync.com`) via public share
> links, embedded on the page with the `[immich_share]` shortcode — this keeps
> tens of MB of video off Hostinger shared hosting and out of the deploy rsync.
> Full-res masters stay on the writable mirror for licensing; the reel/social
> embeds land in a follow-up content MR once the owner supplies the share URLs.
> Only the stills (WebP + progressive JPEG) are committed here.

### Hero reel (`video/`, served via Immich share — not committed)
`uplinksync-air-hero-reel-1080p.mp4` — 36s, 1920×1080 @30fps, H.264, faststart. Six graded segments (Palisades ×4, Pocatello ×2) trimmed mid-clip from the original `DJI_*.MP4` masters (NOT the flagged `dji_fly_*_video_d_logm.MP4` re-exports). Fade in/out + persistent lower-third credit. Kept on the writable mirror; published as an Immich share embed.

### Social clips (`social/`, served via Immich share — not committed) — 9:16 vertical, 1080×1920 @30fps
- `uplinksync-air-palisades-reservoir-9x16.mp4`
- `uplinksync-air-palisades-ridge-9x16.mp4`
- `uplinksync-air-pocatello-playground-9x16.mp4`

Center-cropped from 4K masters, graded, stacked credit at bottom.

## Source frames (read-only masters, referenced in place)
- Palisades: `/mnt/propair/Landscape/Mountainscapes/Palisades-Idaho_20250927/`
- Pocatello: `/mnt/propair/Landscape/Cityscapes/ID-Pocatello_BrooklynsPlayground-20250927/`
- Kaysville: `/mnt/propair/Landscape/Cityscapes/UT-Kaysville/`
- Saratoga Springs: `/mnt/propair/Landscape/Cityscapes/UT-SaratogaSprings/`
- Idaho Falls: `/mnt/propair/Landscape/Cityscapes/ID-IdahoFalls/`

## Develop/export tooling
`sharp` (stills) + static `ffmpeg` 7.0.2 (video) run from the workspace — no system installs, no writes to the read-only share. Note: the static ffmpeg build lacks `drawtext`; credits were rendered as PNG overlays and composited.

## Publish path
These files are the develop/export deliverable. **Nothing is published from this task.** The site change lands via the normal MR + `render_smoke` gate, wiring these assets into the Air/drone section and replacing the generated key art. Attribution must remain wherever they appear.
