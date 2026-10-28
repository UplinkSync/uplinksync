#!/usr/bin/env python3
"""Lighter store-preview watermarks (owner decision 2026-10-28).

The Phase-2 previews (export_graded.py, ***-176) carry a full-field TILED arrow grid
(~22% opacity). It protects well but obstructs skies, so the shop grid was swapped
to the CLEAN master's 300x300 (snippet 045) -- which DR-006 does not allow. The owner
will switch the shop/prints/product images to watermarked previews ONLY with a
less-obstructive mark. This module renders three candidate marks from a CLEAN,
already-graded master (do NOT re-grade: masters_clean / WP "-master-...-scaled.jpg"
are the graded deliverable):

  corner    small "Aerial by UplinkSync" arrow+wordmark, bottom-right, low opacity.
            Least obstructive; trivially cropped, and the shop's 300x300 centre
            crop never shows it.
  diagonal  ONE centred mark (arrow + UPLINKSYNC + "preview"), slight diagonal,
            low-contrast white glyph with a faint dark emboss so it reads on bright
            skies and dark water alike. Sits over the subject, so cropping it out
            destroys the picture; survives the 300x300 centre crop.
  strip     thin translucent bottom credit band with text. Clean picture area,
            but one crop removes it.

Usage (single file or many; output name = input stem with -master-<hex>[-scaled] removed):
  watermark_preview.py --variant diagonal --out OUTDIR SRC.jpg [SRC2.jpg ...]
Options: --long 1600 (output long edge; current previews are 1600x900), --quality 85,
         --opacity (variant default), --suffix "" (appended to the output stem).
Requires Pillow. Fonts: URW Gothic Demi (geometric, close to the site's Quicksand),
falls back to Liberation Sans Bold / FreeSansBold / PIL default.
"""
import argparse, math, os, re, sys
from PIL import Image, ImageDraw, ImageFilter, ImageFont, ImageOps

HERE = os.path.dirname(os.path.abspath(__file__))
ARROW_WHITE = os.path.join(HERE, "assets", "arrow-icon-white.png")
FONT_CANDIDATES = [
    "/usr/share/fonts/opentype/urw-base35/URWGothic-Demi.otf",
    "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf",
    "/usr/share/fonts/truetype/freefont/FreeSansBold.ttf",
]
DEFAULT_OPACITY = {"corner": 0.55, "diagonal": 0.24, "strip": 0.90}


def _font(px):
    for f in FONT_CANDIDATES:
        if os.path.exists(f):
            return ImageFont.truetype(f, px)
    return ImageFont.load_default()


def _fit(im, long_edge):
    w, h = im.size
    s = long_edge / float(max(w, h))
    if s >= 1:
        return im.copy()
    return im.resize((round(w * s), round(h * s)), Image.LANCZOS)


def _arrow_alpha(h):
    a = Image.open(ARROW_WHITE).convert("RGBA").split()[3]
    w = max(1, round(a.size[0] * h / float(a.size[1])))
    return a.resize((w, h), Image.LANCZOS)


def _lockup_alpha(text_px, words, sub=None, gap_ratio=0.35):
    """Alpha mask of [arrow] WORDS (optionally a small sub-line under WORDS)."""
    f = _font(text_px)
    asc, desc = f.getmetrics()
    tw = round(f.getlength(words))
    arrow = _arrow_alpha(round(text_px * 1.05))
    gap = round(text_px * gap_ratio)
    sub_f = _font(max(8, round(text_px * 0.36))) if sub else None
    sub_h = (sum(sub_f.getmetrics()) + round(text_px * 0.08)) if sub else 0
    W = arrow.size[0] + gap + tw
    H = max(arrow.size[1], asc + desc) + sub_h
    m = Image.new("L", (W, H), 0)
    m.paste(arrow, (0, (asc + desc - arrow.size[1]) // 2 + round(text_px * 0.05)), arrow)
    d = ImageDraw.Draw(m)
    d.text((arrow.size[0] + gap, 0), words, font=f, fill=255)
    if sub:
        sw = sub_f.getlength(sub)
        # tracked-out small caps feel: add spacing between letters
        d.text((arrow.size[0] + gap + (tw - sw) / 2, asc + desc + round(text_px * 0.04)), sub, font=sub_f, fill=255)
    return m


def _emboss(base_rgba, mask, pos, white_a, dark_a, blur, off):
    """Composite a white glyph over a soft dark shadow at pos. mask is an L image."""
    W, H = base_rgba.size
    layer = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    full = Image.new("L", (W, H), 0)
    full.paste(mask, pos)
    sh = full.filter(ImageFilter.GaussianBlur(blur)) if blur else full
    shadow = Image.new("RGBA", (W, H), (8, 18, 36, 0))
    shadow.putalpha(sh.point(lambda v: int(v * dark_a)))
    layer.alpha_composite(shadow, (off, off))
    white = Image.new("RGBA", (W, H), (255, 255, 255, 0))
    white.putalpha(full.point(lambda v: int(v * white_a)))
    layer.alpha_composite(white)
    return Image.alpha_composite(base_rgba, layer)


def mark_corner(im, opacity):
    W, H = im.size
    px = max(12, round(H * 0.030))                  # ~27px text on a 1600x900 preview
    m = _lockup_alpha(px, "Aerial by UplinkSync")
    margin = round(H * 0.035)
    pos = (W - m.size[0] - margin, H - m.size[1] - margin)
    return _emboss(im.convert("RGBA"), m, pos, opacity, opacity * 0.6, max(1, px // 10), max(1, px // 14)).convert("RGB")


def mark_diagonal(im, opacity):
    W, H = im.size
    px = max(20, round(min(W, H) * 0.085))          # ~76px wordmark on 1600x900
    m = _lockup_alpha(px, "UplinkSync", sub="P R E V I E W")
    target_w = round(min(W, H) * 0.92)               # keep the whole mark inside the centre square
    if m.size[0] > target_w:
        s = target_w / float(m.size[0])
        m = m.resize((target_w, max(1, round(m.size[1] * s))), Image.LANCZOS)
    m = m.rotate(18, resample=Image.BICUBIC, expand=True)
    pos = ((W - m.size[0]) // 2, (H - m.size[1]) // 2)
    # thin-stroke look: white fill at `opacity`, dark emboss a touch weaker so it reads on snow/sky
    return _emboss(im.convert("RGBA"), m, pos, opacity, opacity * 0.75, max(1, px // 18), max(1, px // 22)).convert("RGB")


def mark_strip(im, opacity):
    W, H = im.size
    bh = max(18, round(H * 0.065))
    base = im.convert("RGBA")
    band = Image.new("RGBA", (W, bh), (11, 27, 51, round(255 * 0.50)))
    base.alpha_composite(band, (0, H - bh))
    px = round(bh * 0.42)
    m = _lockup_alpha(px, "Aerial by UplinkSync", gap_ratio=0.3)
    pos = (round(bh * 0.45), H - bh + (bh - m.size[1]) // 2)
    base = _emboss(base, m, pos, opacity, 0, 0, 0)
    f = _font(round(bh * 0.33))
    txt = "Watermarked preview · clean full-resolution print file on purchase"
    tw = f.getlength(txt)
    if tw < W - m.size[0] - bh * 2:
        d = ImageDraw.Draw(base)
        d.text((W - tw - round(bh * 0.45), H - bh + (bh - sum(f.getmetrics())) // 2), txt, font=f,
               fill=(255, 255, 255, round(255 * opacity * 0.75)))
    return base.convert("RGB")


# --------------------------------------------------------------------------------------
# Hardened Option B (owner decision 2026-10-28: "make it difficult for an AI agent to
# remove the watermark"). Honest limit: nothing makes removal IMPOSSIBLE for a determined
# inpainting model; this raises the cost and adds provenance (see WATERMARK-ROLLOUT.md).
#  * per-image deterministic jitter of angle / scale / centre (defeats multi-image
#    template estimation, i.e. averaging many previews to recover one alpha mask)
#  * the mark is fine structure, not a flat alpha shape: thin light/dark outline +
#    a noisy interior whose strength follows local luminance -> no single (colour, alpha)
#    pair exists, so classic alpha-unblending / template subtraction leaves residue
#  * a faint micro-pattern ("UplinkSync" micro text) spread over the mid-tones of the
#    whole frame, at a different angle and random phase -> cropping the big mark out
#    still leaves marked pixels everywhere
#  * invisible payload (imwatermark dwtDctSvd) + IPTC/XMP/EXIF rights metadata are
#    added by build_preview() after the visible marks.
# --------------------------------------------------------------------------------------
JITTER_SALT = "uls-wm-2026-10-28"


def _rng(seed):
    import hashlib, random
    return random.Random(int(hashlib.sha256((JITTER_SALT + str(seed)).encode()).hexdigest()[:16], 16))


def mark_diagonal_hardened(im, opacity, seed="x"):
    import numpy as np
    R = _rng(seed)
    W, H = im.size
    S = min(W, H)
    px = max(20, round(S * 0.085))
    m = _lockup_alpha(px, "UplinkSync", sub="P R E V I E W")
    target_w = round(S * 0.92 * R.uniform(0.86, 1.0))
    s = target_w / float(m.size[0])
    m = m.resize((target_w, max(1, round(m.size[1] * s))), Image.LANCZOS)
    m = m.rotate(18 + R.uniform(-5.0, 5.0), resample=Image.BICUBIC, expand=True)
    cx = W // 2 + round(R.uniform(-0.035, 0.035) * S)
    cy = H // 2 + round(R.uniform(-0.035, 0.035) * S)
    full = Image.new("L", (W, H), 0)
    full.paste(m, (cx - m.size[0] // 2, cy - m.size[1] // 2))
    A = np.asarray(full, dtype=np.float32) / 255.0                       # glyph coverage
    grow = np.asarray(full.filter(ImageFilter.MaxFilter(3)), dtype=np.float32) / 255.0
    shrink = np.asarray(full.filter(ImageFilter.MinFilter(3)), dtype=np.float32) / 255.0
    edge = np.clip(grow - shrink, 0, 1)                                  # thin outline band
    off = max(1, px // 30)
    shadow = np.roll(np.roll(edge, off, 0), off, 1) * (1 - A)            # thin dark rim, down-right
    img = np.asarray(im.convert("RGB"), dtype=np.float32)
    Y = (0.299 * img[..., 0] + 0.587 * img[..., 1] + 0.114 * img[..., 2]) / 255.0
    Yb = np.asarray(Image.fromarray((Y * 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(3)),
                    dtype=np.float32) / 255.0
    nrng = np.random.default_rng(R.getrandbits(32))
    tex = nrng.normal(0, 1, (H, W)).astype(np.float32)
    tex = np.asarray(Image.fromarray(np.clip(tex * 40 + 128, 0, 255).astype(np.uint8))
                     .filter(ImageFilter.GaussianBlur(0.7)), dtype=np.float32) / 255.0   # fine grain ~0..1
    tex = 0.55 + 0.9 * (tex - 0.5)                                        # ~0.3..0.8, mean .55
    # interior lighten: stronger on dark ground, weaker on bright sky (where the rim carries it)
    k_in = opacity * tex * (0.50 + 0.60 * (1 - Yb)) * A * (1 - edge * 0.5)
    k_edge = opacity * 0.85 * edge * (0.7 + 0.6 * Yb)                      # crisp light outline
    lighten = np.clip(k_in + k_edge, 0, 0.9)[..., None]
    out = img + (255 - img) * lighten
    dark = (opacity * 0.75 * shadow * (0.4 + 0.8 * Yb))[..., None]
    out = out * (1 - dark) + np.array([8, 18, 36], np.float32) * dark
    out = _micro_pattern(out, Yb, R, S)
    return Image.fromarray(np.clip(out + 0.5, 0, 255).astype(np.uint8))


def _micro_pattern(arr, Yb, R, S, strength=5.0):
    """Faint tiled micro 'UplinkSync' text over the mid-tones (+-strength levels)."""
    import numpy as np
    H, W = Yb.shape
    tpx = max(9, round(S * 0.016))
    f = _font(tpx)
    word = "UplinkSync"
    tw = round(f.getlength(word)) + tpx * 2
    th = tpx * 3
    D = int(math.hypot(W, H)) + th * 2
    tile = Image.new("L", (D, D), 0)
    d = ImageDraw.Draw(tile)
    ph = R.randrange(tw)
    for j, y in enumerate(range(-th, D, th)):
        for x in range(-tw - ph + (j % 2) * tw // 2, D, tw):
            d.text((x, y), word, font=f, fill=255)
    tile = tile.rotate(-24 + R.uniform(-6, 6), resample=Image.BICUBIC)
    l, t = (D - W) // 2, (D - H) // 2
    P = np.asarray(tile.crop((l, t, l + W, t + H)), dtype=np.float32) / 255.0
    mid = np.exp(-((Yb - 0.5) / 0.22) ** 2)                               # mid-tone weight
    sign = np.where(Yb > 0.5, -1.0, 1.0)                                  # darken lights / lighten darks
    return arr + (strength * P * mid * sign)[..., None]


VARIANTS = {"corner": mark_corner, "diagonal": mark_diagonal, "strip": mark_strip,
            "diagonal-h": mark_diagonal_hardened}


DEFAULT_OPACITY["diagonal-h"] = 0.24


def render(src, variant, long_edge=1600, opacity=None, seed="x"):
    im = ImageOps.exif_transpose(Image.open(src)).convert("RGB")
    im = _fit(im, long_edge)
    op = DEFAULT_OPACITY[variant] if opacity is None else opacity
    if variant == "diagonal-h":
        return mark_diagonal_hardened(im, op, seed)
    return VARIANTS[variant](im, op)


def out_stem(path):
    stem = os.path.splitext(os.path.basename(path))[0]
    return re.sub(r"-master-[0-9a-f]+(-scaled)?$", "", stem)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("srcs", nargs="+")
    ap.add_argument("--variant", choices=sorted(VARIANTS), required=True)
    ap.add_argument("--out", required=True)
    ap.add_argument("--long", type=int, default=1600)
    ap.add_argument("--quality", type=int, default=85)
    ap.add_argument("--opacity", type=float)
    ap.add_argument("--suffix", default="")
    a = ap.parse_args()
    os.makedirs(a.out, exist_ok=True)
    for s in a.srcs:
        o = os.path.join(a.out, out_stem(s) + a.suffix + ".jpg")
        render(s, a.variant, a.long, a.opacity).save(o, "JPEG", quality=a.quality, optimize=True, progressive=True)
        print(o)


if __name__ == "__main__":
    sys.exit(main())
