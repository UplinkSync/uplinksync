#!/usr/bin/env python3
"""***-176 grade-first export pipeline.

Order of operations (owner-mandated 2026-07-22):
  1. Color-correct (auto-grade) every candidate BEFORE anything else.
  2. Watermark the graded preview with a light, translucent, full-field TILED
     grid of the UplinkSync arrow icon (preview only).
  3. Keep the graded, un-watermarked full-res master staged for purchase
     fulfillment (buyers get the clean file).

Reads masters in place (read-only share); writes to the writable mirror.

Rendition rule (owner-mandated 2026-07-23, size-based): watermark medium+ only.
Thumbnails stay clean and crisp (a tiny browse thumb isn't commercially usable);
the enlarged/full image is what a thief would grab, so that one is watermarked.

Outputs per image, under <outdir>:
  masters_clean/<slug>.jpg   graded full-res, EXIF/GPS stripped (purchase deliverable, CLEAN)
  thumbs/<slug>.jpg          graded, long-edge 800 (browse/grid, CLEAN — no watermark)
  large/<slug>.jpg           graded + tiled-watermark, long-edge 1920 (click-to-enlarge)

Usage:
  export_graded.py <src.jpg> <outdir> <slug> [--large-long N] [--thumb-long N] [--opacity 0.10]

Grading is a conservative auto-grade (white-balance + contrast/levels + gentle
saturation/clarity) tuned not to blow out skies. Deterministic, no per-image
hand-tuning — safe to batch across 123 frames.
"""
import sys, os, argparse
from PIL import Image, ImageOps, ImageEnhance, ImageDraw

ASSET_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "assets")
ARROW_WHITE = os.path.join(ASSET_DIR, "arrow-icon-white.png")


def auto_grade(im):
    """Conservative, deterministic color-correction.

    - Gray-world white balance (clamped so we never tint hard).
    - Per-channel levels stretch clipping 0.5% tails (autocontrast, preserves tone).
    - Small saturation + contrast lift for punch without going HDR-garish.
    """
    im = im.convert("RGB")

    # --- gray-world white balance (clamped) ---
    r, g, b = im.split()
    rm = _mean(r); gm = _mean(g); bm = _mean(b)
    gray = (rm + gm + bm) / 3.0
    if rm and gm and bm:
        def _clamp(f):  # limit correction to +-15% so we don't over-tint
            return max(0.85, min(1.15, f))
        r = r.point(lambda v, f=_clamp(gray / rm): min(255, v * f))
        g = g.point(lambda v, f=_clamp(gray / gm): min(255, v * f))
        b = b.point(lambda v, f=_clamp(gray / bm): min(255, v * f))
        im = Image.merge("RGB", (r, g, b))

    # --- levels stretch (clip 0.5% highlights/shadows), preserves midtones ---
    im = ImageOps.autocontrast(im, cutoff=0.5, preserve_tone=True)

    # --- gentle contrast + saturation + micro-brightness ---
    im = ImageEnhance.Contrast(im).enhance(1.06)
    im = ImageEnhance.Color(im).enhance(1.12)
    im = ImageEnhance.Brightness(im).enhance(1.01)
    return im


def _mean(ch):
    h = ch.histogram()
    tot = sum(h)
    if not tot:
        return 0
    return sum(i * n for i, n in enumerate(h)) / tot


def build_tile(icon_h_px, opacity):
    """Return an RGBA tile: one translucent arrow on transparent bg, scaled to
    icon_h_px tall with padding so the grid breathes.

    The mark is a white glyph over a soft dark drop-shadow (emboss) so it stays
    legible over both bright skies/snow and dark water at low opacity — a
    white-only mark disappears on light scenes."""
    src = Image.open(ARROW_WHITE).convert("RGBA")
    w, h = src.size
    scale = icon_h_px / float(h)
    glyph_a = src.split()[3].resize((max(1, round(w * scale)), icon_h_px), Image.LANCZOS)
    gw, gh = glyph_a.size

    # white layer
    white = Image.new("RGBA", (gw, gh), (255, 255, 255, 0))
    white.putalpha(glyph_a.point(lambda v: int(v * opacity)))
    # dark shadow layer, offset down-right and slightly softer
    shadow = Image.new("RGBA", (gw, gh), (10, 20, 40, 0))
    shadow.putalpha(glyph_a.point(lambda v: int(v * opacity * 0.85)))

    off = max(1, gh // 40)
    pad_x = round(gw * 0.5)
    pad_y = round(gh * 0.9)
    tile = Image.new("RGBA", (gw + pad_x + off, gh + pad_y + off), (0, 0, 0, 0))
    tile.alpha_composite(shadow, (pad_x // 2 + off, pad_y // 2 + off))
    tile.alpha_composite(white, (pad_x // 2, pad_y // 2))
    return tile


def tiled_watermark(prev, opacity):
    """Overlay a full-field, slightly-diagonal tiled arrow grid onto RGB image prev."""
    pw, ph = prev.size
    icon_h = max(28, pw // 16)  # scale mark to image; ~16 across the long edge
    tile = build_tile(icon_h, opacity)
    tw, th = tile.size

    # build an oversized layer, tile it, rotate slightly, crop to center
    import math
    diag = int(math.hypot(pw, ph)) + max(tw, th) * 2
    layer = Image.new("RGBA", (diag, diag), (0, 0, 0, 0))
    for y in range(0, diag, th):
        # brick offset every other row for an even, un-croppable field
        x_off = (tw // 2) if (y // th) % 2 else 0
        for x in range(-x_off, diag, tw):
            layer.paste(tile, (x, y), tile)
    layer = layer.rotate(-18, resample=Image.BICUBIC, expand=False)
    # center-crop to image size
    left = (diag - pw) // 2
    top = (diag - ph) // 2
    layer = layer.crop((left, top, left + pw, top + ph))

    out = prev.convert("RGBA")
    out = Image.alpha_composite(out, layer)
    return out.convert("RGB")


def _fit(im, long_edge):
    """Return a copy scaled so its long edge == long_edge (never upscales)."""
    w, h = im.size
    scale = long_edge / float(max(w, h))
    if scale >= 1:
        return im.copy()
    return im.resize((round(w * scale), round(h * scale)), Image.LANCZOS)


def export(src, outdir, slug, large_long=1920, thumb_long=800, opacity=0.22):
    """Emit the three size-based renditions per the owner's 2026-07-23 rule.

    Returns (master_path, thumb_path, large_path, master_size, thumb_size, large_size).
    """
    masters = os.path.join(outdir, "masters_clean")
    thumbs = os.path.join(outdir, "thumbs")
    large = os.path.join(outdir, "large")
    for d in (masters, thumbs, large):
        os.makedirs(d, exist_ok=True)

    im = Image.open(src)
    im = ImageOps.exif_transpose(im)   # honor camera orientation
    graded = auto_grade(im)

    # 1. graded CLEAN full-res master (no watermark, EXIF/GPS stripped) — buyer deliverable
    master_path = os.path.join(masters, f"{slug}.jpg")
    graded.save(master_path, "JPEG", quality=95, subsampling=0, optimize=True)

    # 2. graded CLEAN thumbnail (<=800px long edge) — browse/grid, no watermark
    thumb = _fit(graded, thumb_long)
    thumb_path = os.path.join(thumbs, f"{slug}.jpg")
    thumb.save(thumb_path, "JPEG", quality=85, optimize=True)

    # 3. graded WATERMARKED large (>=1920px long edge) — click-to-enlarge / hero
    lg = tiled_watermark(_fit(graded, large_long), opacity)
    large_path = os.path.join(large, f"{slug}.jpg")
    lg.save(large_path, "JPEG", quality=85, optimize=True)

    return master_path, thumb_path, large_path, graded.size, thumb.size, lg.size


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("src"); ap.add_argument("outdir"); ap.add_argument("slug")
    ap.add_argument("--large-long", type=int, default=1920)
    ap.add_argument("--thumb-long", type=int, default=800)
    ap.add_argument("--opacity", type=float, default=0.22)
    a = ap.parse_args()
    mp, tp, lp, ms, ts, ls = export(a.src, a.outdir, a.slug,
                                    a.large_long, a.thumb_long, a.opacity)
    print(f"master: {mp} {ms} ({os.path.getsize(mp)//1024}KB)")
    print(f"thumb:  {tp} {ts} ({os.path.getsize(tp)//1024}KB) CLEAN")
    print(f"large:  {lp} {ls} ({os.path.getsize(lp)//1024}KB) watermarked")
