#!/usr/bin/env python3
"""***-176 export pipeline: watermarked web preview + full-res download deliverable.
Usage: export_photo.py <src.jpg> <outdir> <slug> [--preview-width N]
Produces: <slug>_preview.jpg (watermarked, sRGB, long-edge 1600) and <slug>_full.jpg (full-res, stripped GPS)."""
import sys, os
from PIL import Image, ImageDraw, ImageFont, ImageOps

def export(src, outdir, slug, preview_long=1600, wm_text="UplinkSync ©"):
    os.makedirs(outdir, exist_ok=True)
    im = Image.open(src)
    im = ImageOps.exif_transpose(im)  # honor orientation
    im = im.convert("RGB")

    # --- full-res deliverable: re-save clean (drops EXIF incl. GPS), max quality ---
    full_path = os.path.join(outdir, f"{slug}_full.jpg")
    im.save(full_path, "JPEG", quality=95, subsampling=0, optimize=True)

    # --- watermarked preview ---
    prev = im.copy()
    w,h = prev.size
    scale = preview_long/float(max(w,h))
    if scale < 1:
        prev = prev.resize((round(w*scale), round(h*scale)), Image.LANCZOS)
    pw,ph = prev.size
    draw = ImageDraw.Draw(prev, "RGBA")
    # diagonal tiled watermark
    fontsize = max(18, pw//22)
    try:
        font = ImageFont.truetype("/usr/share/fonts/truetype/freefont/FreeSansBold.ttf", fontsize)
    except Exception:
        font = ImageFont.load_default()
    # center label
    bbox = draw.textbbox((0,0), wm_text, font=font)
    tw,th = bbox[2]-bbox[0], bbox[3]-bbox[1]
    draw.text(((pw-tw)//2,(ph-th)//2), wm_text, font=font, fill=(255,255,255,110))
    # corner attribution
    small = ImageFont.truetype("/usr/share/fonts/truetype/freefont/FreeSans.ttf", max(12,pw//55)) if os.path.exists("/usr/share/fonts/truetype/freefont/FreeSans.ttf") else font
    draw.text((10, ph-max(12,pw//55)-14), "Preview — licensed download removes watermark", font=small, fill=(255,255,255,150))
    prev_path = os.path.join(outdir, f"{slug}_preview.jpg")
    prev.save(prev_path, "JPEG", quality=85, optimize=True)

    return full_path, prev_path, im.size, prev.size

if __name__ == "__main__":
    src, outdir, slug = sys.argv[1], sys.argv[2], sys.argv[3]
    fp, pp, fs, ps = export(src, outdir, slug)
    print(f"full: {fp} {fs} ({os.path.getsize(fp)//1024}KB)")
    print(f"prev: {pp} {ps} ({os.path.getsize(pp)//1024}KB)")
