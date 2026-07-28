import os, struct, csv, re, datetime

ROOT = "/mnt/propair/Landscape"

def jpeg_dims(path):
    # Read width/height from first SOF marker without external libs
    try:
        with open(path, "rb") as f:
            if f.read(2) != b"\xff\xd8":
                return (None, None)
            while True:
                b = f.read(1)
                if not b:
                    return (None, None)
                if b != b"\xff":
                    continue
                # skip fill bytes
                marker = f.read(1)
                while marker == b"\xff":
                    marker = f.read(1)
                m = marker[0]
                # SOF markers: C0-CF except C4,C8,CC
                if m in (0xC0,0xC1,0xC2,0xC3,0xC5,0xC6,0xC7,0xC9,0xCA,0xCB,0xCD,0xCE,0xCF):
                    f.read(3)  # length(2)+precision(1)
                    h = struct.unpack(">H", f.read(2))[0]
                    w = struct.unpack(">H", f.read(2))[0]
                    return (w, h)
                else:
                    ln = struct.unpack(">H", f.read(2))[0]
                    f.seek(ln-2, 1)
    except Exception as e:
        return (None, None)

def parse_location(subdir):
    # e.g. ID-IdahoFalls, ID-Pocatello_BrooklynsPlayground-20250927, UT-Kaysville, Palisades-Idaho_20250927
    name = subdir
    # strip trailing date token
    m = re.search(r'[_-](\d{8})$', name)
    date = None
    if m:
        date = m.group(1)
        name = name[:m.start()]
    loc = name.replace('_', ' ').replace('-', ' ').strip()
    return loc, date

def parse_date_from_file(fn, folder_date):
    # DJI_20250927072917_..., dji_fly_20260110_160852_...
    m = re.search(r'(20\d{6})', fn)
    if m:
        return m.group(1)
    return folder_date

def fmt_date(d):
    if not d: return ""
    try:
        return datetime.datetime.strptime(d, "%Y%m%d").strftime("%Y-%m-%d")
    except: return d

rows = []
for cat in ("Cityscapes","Mountainscapes"):
    catdir = os.path.join(ROOT, cat)
    for sub in sorted(os.listdir(catdir)):
        subpath = os.path.join(catdir, sub)
        if not os.path.isdir(subpath): continue
        loc, folder_date = parse_location(sub)
        for fn in sorted(os.listdir(subpath)):
            if not fn.lower().endswith(".jpg"): continue
            fp = os.path.join(subpath, fn)
            w,h = jpeg_dims(fp)
            mp = round(w*h/1_000_000,1) if w and h else ""
            orient = ""
            if w and h:
                orient = "landscape" if w>=h else "portrait"
            cdate = fmt_date(parse_date_from_file(fn, folder_date))
            size_mb = round(os.path.getsize(fp)/1_048_576,1)
            rows.append({
                "category": cat,
                "location": loc,
                "capture_date": cdate,
                "relpath": os.path.relpath(fp, ROOT),
                "filename": fn,
                "width": w or "",
                "height": h or "",
                "megapixels": mp,
                "orientation": orient,
                "size_mb": size_mb,
                "SELECT_yes": "",     # owner marks x/yes
                "draft_title": "",
                "draft_price_usd": "",
            })

out = "/tmp/***176_candidate_manifest.csv"
with open(out,"w",newline="") as f:
    wtr = csv.DictWriter(f, fieldnames=list(rows[0].keys()))
    wtr.writeheader()
    wtr.writerows(rows)

print("rows:", len(rows))
print("out:", out)
# summary
from collections import Counter
print("by category:", Counter(r["category"] for r in rows))
print("by location:", Counter(r["location"] for r in rows))
print("orientation:", Counter(r["orientation"] for r in rows))
print("MP distinct:", sorted(set(r["megapixels"] for r in rows)))
