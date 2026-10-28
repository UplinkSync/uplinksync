#!/usr/bin/env python3
"""Build the share (og:image) cards and the favicon set shipped in the child theme.

usage: python3 tools/seo/make_seo_images.py <owner-images-dir> <brand-favicons-dir>

<owner-images-dir>   the approved owner set (imagery/incoming-20261027/*.png)
<brand-favicons-dir> NAS 50_Brand/Logos/Web-Favicons (android-chrome-512x512.png etc.)

Outputs (committed, so the site never depends on this script at runtime):
  wp-content/themes/uplinksync-child/assets/images/seo/og-<page>.jpg   1200x630, <= 300 KB
  wp-content/themes/uplinksync-child/assets/images/brand/favicon/*     16/32/180/192/512 PNG + favicon.ico

The NAS favicons are a white/blue arrow on transparency, which vanishes on light browser
tabs, so each size is re-rendered from the 512 master on a logo-navy rounded tile.
"""
import os
import sys

from PIL import Image, ImageDraw, ImageFont

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
THEME = os.path.join(ROOT, "wp-content", "themes", "uplinksync-child")
OUT_OG = os.path.join(THEME, "assets", "images", "seo")
OUT_ICON = os.path.join(THEME, "assets", "images", "brand", "favicon")
LOCKUP = os.path.join(THEME, "assets", "images", "uplinksync-logo-lockup.png")
FONT_HEAD = os.path.join(THEME, "assets", "fonts", "Quicksand-latin-var.woff2")
FONT_BODY = os.path.join(THEME, "assets", "fonts", "DMSans-latin-var.woff2")

NAVY_950 = (10, 31, 60)     # #0a1f3c palette navy-950
NAVY = (23, 50, 88)         # #173258 palette ink / logo navy
ON_DARK = (251, 251, 251)   # #fbfbfb palette on-dark
ON_DARK_MUTED = (178, 198, 226)  # #b2c6e2 palette on-dark-muted
TEAL = (147, 209, 232)      # #93d1e8 palette teal

W, H = 1200, 630
MAX_BYTES = 300 * 1024

# page key -> (source image, focal x 0..1 [or (x, y)], headline, sub line). No place names, no "local".
CARDS = {
    "home": ("hero-bg_utah_A.png", 0.62, "Managed IT, web\n& aerial services", "Owner-run · Plain answers · Clear quotes"),
    "services": ("cybersecurity_A.png", 0.70, "IT support, websites\n& automation", "Managed IT · Security · Web · Workflows"),
    "drone": ("drone-inspection_utah_A.png", 0.40, "Aerial photo, video\n& inspections", "Photo · Video · Roof & site inspections"),
    "about": ("about-owner_D.png", (0.42, 0.40), "Meet the owner\nbehind UplinkSync", "IT technician and drone pilot"),
    "contact": ("cta-band_A.png", 0.62, "Get a quote\nor book a call", "Tell us what you need. We reply with a plan."),
    "default": ("hero-aerial_utah_A.png", 0.60, "Managed IT, web\n& aerial services", "Owner-run · Plain answers · Clear quotes"),
}


def font(path, size, weight):
    f = ImageFont.truetype(path, size)
    try:
        f.set_variation_by_axes([weight])
    except Exception:  # static font or no variation support: use as is
        pass
    return f


def cover(img, focal_x, focal_y=0.5):
    scale = max(W / img.width, H / img.height)
    img = img.resize((round(img.width * scale), round(img.height * scale)), Image.LANCZOS)
    left = min(max(0, round(img.width * focal_x - W / 2)), img.width - W)
    top = min(max(0, round(img.height * focal_y - H / 2)), img.height - H)
    return img.crop((left, top, left + W, top + H))


def scrim():
    """Left-to-right navy gradient: solid-ish behind the text, clear over the photo."""
    s = Image.new("RGBA", (W, H))
    px = s.load()
    for x in range(W):
        t = x / W
        a = 236 if t < 0.30 else max(0, round(236 * (1 - (t - 0.30) / 0.42)))
        for y in range(H):
            px[x, y] = NAVY_950 + (a,)
    return s


def card(src_dir, key):
    name, focal, head, sub = CARDS[key]
    fx, fy = focal if isinstance(focal, tuple) else (focal, 0.5)
    src = Image.open(os.path.join(src_dir, name)).convert("RGB")
    if src.height > src.width:
        # Portrait (owner photo): full-height panel on the right, faded into a navy field,
        # so the face is never under the text scrim.
        ph = src.resize((round(src.width * H / src.height), H), Image.LANCZOS).convert("RGBA")
        base = Image.new("RGBA", (W, H), NAVY_950 + (255,))
        base.alpha_composite(ph, (W - ph.width, 0))
        fade = Image.new("RGBA", (160, H))
        for x in range(160):
            for y in range(H):
                fade.putpixel((x, y), NAVY_950 + (round(255 * (1 - x / 160)),))
        base.alpha_composite(fade, (W - ph.width, 0))
    else:
        base = cover(src, fx, fy).convert("RGBA")
        base.alpha_composite(scrim())
    d = ImageDraw.Draw(base)
    logo = Image.open(LOCKUP).convert("RGBA")
    lw = 330
    logo = logo.resize((lw, round(logo.height * lw / logo.width)), Image.LANCZOS)
    base.alpha_composite(logo, (64, 64))
    d.rectangle((64, 64 + logo.height + 34, 64 + 56, 64 + logo.height + 38), fill=TEAL)
    hf = font(FONT_HEAD, 62, 700)
    y = 64 + logo.height + 64
    for line in head.split("\n"):
        d.text((64, y), line, font=hf, fill=ON_DARK)
        y += 76
    d.text((64, y + 18), sub, font=font(FONT_BODY, 28, 500), fill=ON_DARK_MUTED)
    d.text((64, H - 64 - 26), "uplinksync.com", font=font(FONT_BODY, 24, 600), fill=TEAL)
    out = os.path.join(OUT_OG, "og-%s.jpg" % key)
    rgb = base.convert("RGB")
    for q in (86, 82, 78, 74, 70):
        rgb.save(out, "JPEG", quality=q, optimize=True, progressive=True)
        if os.path.getsize(out) <= MAX_BYTES:
            break
    print("%-34s %6.1f KB" % (os.path.relpath(out, ROOT), os.path.getsize(out) / 1024))


def icons(fav_dir):
    mark = Image.open(os.path.join(fav_dir, "android-chrome-512x512.png")).convert("RGBA")
    master = Image.new("RGBA", (512, 512), (0, 0, 0, 0))
    ImageDraw.Draw(master).rounded_rectangle((0, 0, 511, 511), radius=96, fill=NAVY + (255,))
    inner = mark.resize((400, 400), Image.LANCZOS)
    master.alpha_composite(inner, (56, 56))
    sizes = {"icon-512.png": 512, "icon-270.png": 270, "icon-192.png": 192, "favicon-32.png": 32, "favicon-16.png": 16}
    for fn, sz in sizes.items():
        master.resize((sz, sz), Image.LANCZOS).save(os.path.join(OUT_ICON, fn), optimize=True)
    # Apple applies its own corner mask and dislikes transparency: full-bleed navy square.
    apple = Image.new("RGBA", (180, 180), NAVY + (255,))
    apple.alpha_composite(mark.resize((144, 144), Image.LANCZOS), (18, 18))
    apple.convert("RGB").save(os.path.join(OUT_ICON, "apple-touch-icon.png"), optimize=True)
    master.save(os.path.join(OUT_ICON, "favicon.ico"), sizes=[(16, 16), (32, 32), (48, 48)])
    for fn in sorted(os.listdir(OUT_ICON)):
        print("%-34s %6.1f KB" % ("favicon/" + fn, os.path.getsize(os.path.join(OUT_ICON, fn)) / 1024))


if __name__ == "__main__":
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    os.makedirs(OUT_OG, exist_ok=True)
    os.makedirs(OUT_ICON, exist_ok=True)
    for k in CARDS:
        card(sys.argv[1], k)
    icons(sys.argv[2])
