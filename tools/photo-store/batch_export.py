#!/usr/bin/env python3
"""***-176 batch driver: grade + tiled-watermark every candidate.

Reads candidate_manifest.csv, exports each JPG via export_graded.export().
Slug = zero-padded index + category prefix + short location, unique & stable.

Owner rendition rule (2026-07-23, size-based):
  masters_clean/<slug>.jpg   graded clean full-res (purchase deliverable, CLEAN)
  thumbs/<slug>.jpg          graded, long-edge 800 (browse/grid, CLEAN)
  large/<slug>.jpg           graded + tiled watermark, long-edge 1920 (enlarge)

Writes a run report CSV (slug -> title, price, dims, files) for the contact sheet.

Usage: batch_export.py <manifest.csv> <src_root> <outdir> [--opacity 0.14]
  <src_root> is the read-only masters root (e.g. /mnt/propair/Landscape).
  Manifest relpath is relative to that root.
"""
import sys, os, csv, re, argparse
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from export_graded import export


def slugify(s):
    s = re.sub(r"[^A-Za-z0-9]+", "-", s).strip("-").lower()
    return re.sub(r"-+", "-", s)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("manifest"); ap.add_argument("src_root"); ap.add_argument("outdir")
    ap.add_argument("--opacity", type=float, default=0.22)
    ap.add_argument("--limit", type=int, default=0)
    a = ap.parse_args()

    rows = list(csv.DictReader(open(a.manifest)))
    if a.limit:
        rows = rows[: a.limit]
    os.makedirs(a.outdir, exist_ok=True)
    report_path = os.path.join(a.outdir, "run_report.csv")
    report = open(report_path, "w", newline="")
    w = csv.writer(report)
    w.writerow(["slug", "category", "location", "capture_date", "draft_title",
                "draft_price_usd", "megapixels", "src_relpath",
                "thumb_rel", "large_rel", "master_rel"])

    n = len(rows)
    done = 0
    for i, r in enumerate(rows, 1):
        cat = r["category"]
        loc = slugify(r["location"].split(",")[0])
        slug = f"{i:03d}-{cat[:4].lower()}-{loc}"
        src = os.path.join(a.src_root, r["relpath"])
        if not os.path.exists(src):
            print(f"[{i}/{n}] MISSING {src}", flush=True)
            continue
        try:
            mp, tp, lp, ms, ts, ls = export(src, a.outdir, slug, opacity=a.opacity)
            done += 1
            print(f"[{i}/{n}] {slug}  master{ms} thumb{ts} large{ls}", flush=True)
            w.writerow([slug, cat, r["location"], r["capture_date"],
                        r["draft_title"], r["draft_price_usd"], r["megapixels"],
                        r["relpath"], f"thumbs/{slug}.jpg",
                        f"large/{slug}.jpg", f"masters_clean/{slug}.jpg"])
        except Exception as e:
            print(f"[{i}/{n}] ERROR {slug}: {e}", flush=True)
    report.close()
    print(f"\nDONE {done}/{n} exported. report: {report_path}")


if __name__ == "__main__":
    main()
