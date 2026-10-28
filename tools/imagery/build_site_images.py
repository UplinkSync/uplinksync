#!/usr/bin/env python3
"""Build the Richer Quiet Light theme images (IMAGE-PROMPTS.md §D pipeline).

Usage: python3 tools/imagery/build_site_images.py <owner-incoming-dir> <legacy-cards-dir>

  <owner-incoming-dir>  NAS /50_Brand/Photography/Website/2026-10/incoming/ (the *_A/_B/_D.png originals)
  <legacy-cards-dir>    the current 900 px service-card photos (managed-it/automation/web .jpg) and drone.jpg

Writes WebP srcset files to wp-content/themes/uplinksync-child/assets/images/site/:
quality 78 (re-encoded at 70 if > 200 KB), method 5, metadata stripped, sRGB, never upscaled.
Re-running is deterministic; sources are never modified.
"""
import os
import sys

from PIL import Image

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
THEME = os.path.join(ROOT, "wp-content/themes/uplinksync-child")
OUT = os.path.join(THEME, "assets/images/site")
STILLS = os.path.join(THEME, "assets/media/aerials/stills")
LIMIT = 200 * 1024

CARD = (480, 768, 1024, 1600)
WIDE = (768, 1280, 1920)


def jobs(inc, legacy):
    # (source, output stem, widths, aspect (w/h) or None to keep, vertical focus 0..1)
    return [
        (os.path.join(inc, "hero-bg_utah_A.png"), "uplinksync-hero-town-dusk", WIDE, None, .5),
        (os.path.join(inc, "hero-aerial_utah_A.png"), "uplinksync-hero-aerial-sunset", WIDE, None, .5),
        (os.path.join(inc, "cta-band_A.png"), "uplinksync-cta-band", WIDE, None, .5),
        (os.path.join(inc, "hosting-care_A.png"), "uplinksync-services-hosting-care", CARD, 16 / 9, .5),
        (os.path.join(inc, "cybersecurity_A.png"), "uplinksync-services-cybersecurity", CARD, 16 / 9, .5),
        (os.path.join(inc, "client-hub_B.png"), "uplinksync-client-hub", CARD, 16 / 9, .5),
        (os.path.join(inc, "drone-inspection_utah_A.png"), "uplinksync-drone-inspection", CARD, 16 / 9, .5),
        (os.path.join(inc, "about-owner_D.png"), "uplinksync-about-owner", (480, 800, 1080), 1.0, .2),
        (os.path.join(legacy, "managed-it.jpg"), "uplinksync-services-managed-it", (480, 768, 900), 16 / 9, .5),
        (os.path.join(legacy, "automation.jpg"), "uplinksync-services-automation", (480, 768, 900), 16 / 9, .5),
        (os.path.join(legacy, "web.jpg"), "uplinksync-services-web", (480, 768, 900), 16 / 9, .5),
        # Clean (unwatermarked) owner images re-used for the aerial card, case cards and the
        # drone reel / vertical posters (owner decision 2026-10-28: no watermarked stills in
        # marketing slots).
        (os.path.join(inc, "hero-aerial_utah_A.png"), "uplinksync-aerial-site-sunset", (480, 800, 1280), 16 / 9, .5),
        (os.path.join(inc, "hero-bg_utah_A.png"), "uplinksync-office-dusk", (480, 800, 1280), 16 / 9, .5),
        (os.path.join(inc, "hero-aerial_utah_A.png"), "uplinksync-vertical-site", (300, 460), 9 / 16, .5, .62),
        (os.path.join(inc, "hero-bg_utah_A.png"), "uplinksync-vertical-town", (300, 460), 9 / 16, .5, .86),
        (os.path.join(inc, "drone-inspection_utah_A.png"), "uplinksync-vertical-pilot", (300, 460), 9 / 16, .5, .32),
    ] + [
        # Own drone stills (watermarked previews; gallery only). Place-free file names.
        (os.path.join(STILLS, src + "-full.jpg"), "aerial-" + name,
         (480, 800, 1280) if src == "palisades" else (480, 800, 1024), 16 / 9 if src == "palisades" else 4 / 3, .5)
        for src, name in (("palisades", "reservoir"), ("pocatello", "park"), ("kaysville", "neighbourhood"),
                          ("saratoga-springs", "new-homes"), ("idaho-falls", "riverfront"))
    ]


def crop(im, aspect, focus, hfocus=.5):
    if not aspect:
        return im
    w, h = im.size
    if w / h > aspect:  # too wide: trim sides around hfocus (0 = left, 1 = right)
        nw = round(h * aspect)
        x = round((w - nw) * hfocus)
        return im.crop((x, 0, x + nw, h))
    nh = round(w / aspect)
    y = round((h - nh) * focus)
    return im.crop((0, y, w, y + nh))


def save(im, path):
    for q in (78, 70, 62, 55, 50):
        im.save(path, "WEBP", quality=q, method=5)
        if os.path.getsize(path) <= LIMIT:
            return q
    raise SystemExit("still over budget: " + path)


def main(inc, legacy):
    os.makedirs(OUT, exist_ok=True)
    for job in jobs(inc, legacy):
        src, stem, widths, aspect, focus = job[:5]
        im = crop(Image.open(src).convert("RGB"), aspect, focus, *job[5:])
        for w in widths:
            w = min(w, im.size[0])  # never upscale
            h = round(im.size[1] * w / im.size[0])
            path = os.path.join(OUT, f"{stem}-{w}.webp")
            q = save(im.resize((w, h), Image.LANCZOS), path)
            print(f"{os.path.basename(path):48s} {w}x{h}  q{q}  {os.path.getsize(path) // 1024} KB")


if __name__ == "__main__":
    if len(sys.argv) != 3:
        raise SystemExit(__doc__)
    main(sys.argv[1], sys.argv[2])
