import csv, re

IN = "/tmp/***176_candidate_manifest.csv"

# Clean location display names
LOC_MAP = {
    "ID IdahoFalls": "Idaho Falls, ID",
    "ID Pocatello BrooklynsPlayground": "Pocatello, ID",
    "UT Kaysville": "Kaysville, UT",
    "UT SodaSprings": "Soda Springs, ID",  # note: filename says UT but Soda Springs is ID — flag for owner
    "Palisades Idaho": "Palisades Reservoir, ID",
}

# Price tier by megapixels (full-res download license). Draft — owner confirms.
def price_for_mp(mp):
    try: mp = float(mp)
    except: return 25
    if mp >= 80: return 45
    if mp >= 48: return 39
    return 29

rows = []
with open(IN) as f:
    r = csv.DictReader(f)
    rows = list(r)

# per-location sequence numbering
seq = {}
for row in rows:
    loc_raw = row["location"]
    disp = LOC_MAP.get(loc_raw, loc_raw)
    row["location"] = disp
    key = disp
    seq[key] = seq.get(key, 0) + 1
    n = seq[key]
    subject = "Aerial View" if row["category"]=="Cityscapes" else "Mountain Vista"
    city = disp.split(",")[0]
    row["draft_title"] = f"{city} {subject} No. {n:02d}"
    row["draft_price_usd"] = price_for_mp(row["megapixels"])

with open(IN,"w",newline="") as f:
    w = csv.DictWriter(f, fieldnames=list(rows[0].keys()))
    w.writeheader()
    w.writerows(rows)

print("enriched", len(rows), "rows")
for row in rows[:2] + rows[40:42] + rows[-2:]:
    print(f'  {row["draft_title"]:34s} ${row["draft_price_usd"]}  {row["megapixels"]}MP  {row["filename"][:30]}')
# price distribution
from collections import Counter
print("price dist:", Counter(r["draft_price_usd"] for r in rows))
