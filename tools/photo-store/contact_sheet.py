#!/usr/bin/env python3
"""***-176 selection contact sheet.

Builds paginated JPG contact sheets from the batch run_report.csv so the owner
can mark the final sellable set from finished watermarked previews (not a
spreadsheet). Each cell shows the watermarked preview thumbnail with its slug,
draft title, and draft price captioned below.

Usage: contact_sheet.py <outdir> [--cols 4] [--rows 5]
  <outdir> is the batch output dir (contains previews/ and run_report.csv).
  Writes contact_sheet/sheet_NN.jpg (+ index.csv the owner marks).
"""
import os, csv, argparse, math
from PIL import Image, ImageDraw, ImageFont

FONT_DIR = "/usr/share/fonts/truetype/freefont"


def font(name, size):
    p = os.path.join(FONT_DIR, name)
    return ImageFont.truetype(p, size) if os.path.exists(p) else ImageFont.load_default()


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("outdir")
    ap.add_argument("--cols", type=int, default=4)
    ap.add_argument("--rows", type=int, default=5)
    a = ap.parse_args()

    rows = list(csv.DictReader(open(os.path.join(a.outdir, "run_report.csv"))))
    sheetdir = os.path.join(a.outdir, "contact_sheet")
    os.makedirs(sheetdir, exist_ok=True)

    # cell geometry
    thumb_w, thumb_h = 460, 259           # 16:9 preview thumb
    cap_h = 66                            # caption band
    pad = 22
    cell_w = thumb_w + pad
    cell_h = thumb_h + cap_h + pad
    margin = 40
    header_h = 92
    cols, per = a.cols, a.cols * a.rows

    f_slug = font("FreeSansBold.ttf", 20)
    f_title = font("FreeSans.ttf", 19)
    f_price = font("FreeSansBold.ttf", 21)
    f_head = font("FreeSansBold.ttf", 34)
    f_sub = font("FreeSans.ttf", 20)

    npages = math.ceil(len(rows) / per)
    idx = open(os.path.join(sheetdir, "SELECTION.csv"), "w", newline="")
    iw = csv.writer(idx)
    iw.writerow(["KEEP_yes", "slug", "draft_title", "draft_price_usd",
                 "category", "location", "sheet_page"])

    for p in range(npages):
        chunk = rows[p * per:(p + 1) * per]
        nrows = math.ceil(len(chunk) / cols)
        W = margin * 2 + cols * cell_w
        H = margin + header_h + nrows * cell_h + margin
        sheet = Image.new("RGB", (W, H), (245, 246, 248))
        d = ImageDraw.Draw(sheet)
        d.rectangle([0, 0, W, header_h], fill=(23, 50, 88))  # brand navy
        d.text((margin, 22), "UplinkSync Aerial Prints — Selection Sheet",
               font=f_head, fill=(255, 255, 255))
        d.text((margin, 62), f"Page {p+1}/{npages} · mark KEEP in SELECTION.csv · "
               f"watermark is preview-only (buyers get clean full-res)",
               font=f_sub, fill=(175, 200, 235))

        for i, r in enumerate(chunk):
            cx = margin + (i % cols) * cell_w
            cy = margin + header_h + (i // cols) * cell_h
            # thumbnail
            pth = os.path.join(a.outdir, r.get("thumb_rel") or r.get("preview_rel"))
            try:
                th = Image.open(pth).convert("RGB")
                th.thumbnail((thumb_w, thumb_h), Image.LANCZOS)
                sheet.paste(th, (cx, cy))
                d.rectangle([cx, cy, cx + th.size[0], cy + th.size[1]],
                            outline=(200, 205, 212), width=1)
            except Exception as e:
                d.rectangle([cx, cy, cx + thumb_w, cy + thumb_h], fill=(220, 60, 60))
            # caption
            ty = cy + thumb_h + 6
            d.text((cx, ty), r["slug"], font=f_slug, fill=(23, 50, 88))
            title = r["draft_title"]
            if len(title) > 34:
                title = title[:33] + "…"
            d.text((cx, ty + 24), title, font=f_title, fill=(40, 44, 52))
            d.text((cx + thumb_w - 64, ty), f"${r['draft_price_usd']}",
                   font=f_price, fill=(30, 120, 70))
            iw.writerow(["", r["slug"], r["draft_title"], r["draft_price_usd"],
                         r["category"], r["location"], p + 1])

        out = os.path.join(sheetdir, f"sheet_{p+1:02d}.jpg")
        sheet.save(out, "JPEG", quality=88, optimize=True)
        print(f"sheet {p+1}/{npages}: {out} ({len(chunk)} imgs, {os.path.getsize(out)//1024}KB)")

    idx.close()
    print(f"\nDONE {npages} sheets, {len(rows)} images. SELECTION.csv in {sheetdir}")


if __name__ == "__main__":
    main()
