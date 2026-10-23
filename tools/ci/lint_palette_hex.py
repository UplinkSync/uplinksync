#!/usr/bin/env python3
"""lint_palette_hex — palette ALLOWLIST gate for the child theme's own CSS.

Replaces the old deny-list (forbidden-hex) lint. Every hex colour literal used in a
declaration value under wp-content/themes/uplinksync-child/assets/css/ must be one of the
owner-approved Quiet Light / logo-palette values (PALETTE below, mirrored from theme.json).

Legacy files that still carry pre-Quiet-Light values are frozen in LEGACY: each may keep
exactly the off-palette values it has today and nothing new. Those files are deleted or
re-skinned by MR-C (chore/quiet-light-cleanup); when a file goes, drop its LEGACY entry.
A new CSS file gets the palette only.

The specific deny rules from the old lint are kept on top (they apply even to legacy files),
plus the retired "royal" blue. Comments are stripped first, so rationale and migration notes
may still name any value.

Usage: python3 tools/ci/lint_palette_hex.py [--selftest] [root]
"""
import glob
import os
import re
import sys

CSS_GLOB = "wp-content/themes/uplinksync-child/assets/css/**/*.css"

# Owner-approved palette (theme.json slugs -> hex). Light / Daylight is the site default.
PALETTE = {
    "#f3f6fa",  # page
    "#ffffff",  # surface, on-accent
    "#eaeef4",  # surface-sunken
    "#e2eeff",  # surface-tint
    "#dbe2eb",  # line
    "#77879f",  # line-strong
    "#173258",  # ink, nav, hero (logo navy)
    "#46628a",  # ink-muted
    "#20406c",  # navy-800
    "#0a1f3c",  # navy-950, on-accent-dark
    "#fbfbfb",  # on-dark
    "#b2c6e2",  # on-dark-muted
    "#0c5fc1",  # accent, info
    "#00499c",  # accent-hover
    "#7db3ff",  # accent-on-dark
    "#a4caff",  # accent-on-dark-hover
    "#93d1e8",  # teal (signature only)
    "#579af6",  # brand-blue (logo / large shapes on navy only)
    "#0b7643",  # success
    "#885700",  # warning
    "#ba2b2e",  # danger
}

# Frozen pre-Quiet-Light values, per file (basename). MR-C deletes or re-skins these files.
LEGACY = {
    "drone-gallery.css": {"#0a1a3a", "#55607a", "#7cc4ff"},
    "estimate-book.css": {"#14294a", "#2a4a72", "#95d5dd"},
    "hero.css": {"#0b1b33", "#2e7ef0", "#2f6fc4", "#95d5dd"},
    "immich-embed.css": {"#0b0f14", "#0b1b33"},
    "quote-form.css": {"#1f4375", "#2f6fc4", "#5b6672", "#e3e8ed"},
    "split-horizon.css": {
        "#081020", "#0b1b33", "#102a4c", "#1c355c", "#1c447b", "#1f4375", "#22518f",
        "#2a4a72", "#2f6fc4", "#5697f3", "#7f92ad", "#93a6c4", "#95d5dd", "#c7d4e6",
        "#eaf1fb",
    },
}

# Deny rules that apply everywhere, legacy files included: (label, regex, allowed(line), fix).
DENY = (
    (
        "LinkedIn brand blue (#0d66c2/#0a66c2)",
        re.compile(r"#0[da]66c2\b", re.I),
        lambda line: False,
        "Use var(--uplinksync-white) for the footer glyph (see brand-blocks.css section 7).",
    ),
    (
        "deprecated navy (#1E3155)",
        re.compile(r"#1e3155\b", re.I),
        lambda line: False,
        "Use var(--navy-900) / var(--color-nav) (#173258).",
    ),
    (
        "raw accent-500 (#5697F3)",
        re.compile(r"#5697f3\b", re.I),
        lambda line: bool(re.search(r"--accent-500\s*:\s*#5697f3\b", line, re.I)),
        "Use var(--color-brand-blue) for logo/large shapes on navy, var(--color-accent) for links.",
    ),
    (
        "retired royal blue (#0d266b)",
        re.compile(r"#0d266b\b", re.I),
        lambda line: False,
        "Royal is deprecated (not in the logo). Use var(--color-nav) (#173258).",
    ),
    (
        "off-token pill radius (9999px)",
        re.compile(r"border-radius\s*:[^;{}]*\b9999px\b", re.I),
        lambda line: False,
        "Use var(--radius-pill) (999px).",
    ),
)

COMMENT = re.compile(r"/\*.*?\*/", re.S)
DECL = re.compile(r"([-\w]+)\s*:\s*([^;{}]+)(?=[;}])")
HEX = re.compile(r"#([0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{4}|[0-9a-fA-F]{3})\b")


def norm(h):
    """#abc / #abcd / #aabbcc / #aabbccdd -> #aabbcc (alpha variants of a palette value pass)."""
    h = h.lower().lstrip("#")
    if len(h) in (3, 4):
        h = "".join(c * 2 for c in h[:3])
    return "#" + h[:6]


def lint_source(name, src):
    """Return a list of (label, remediation, location) findings for one file's CSS text."""
    hits = []
    clean = COMMENT.sub("", src)
    lines = clean.splitlines()
    for i, line in enumerate(lines, 1):
        for label, pattern, allowed, fix in DENY:
            if pattern.search(line) and not allowed(line):
                hits.append((label, fix, f"{name}:{i}: {line.strip()}"))
    allowed_set = PALETTE | LEGACY.get(os.path.basename(name), set())
    for m in DECL.finditer(clean):
        for raw in HEX.findall(m.group(2)):
            if norm(raw) not in allowed_set:
                line_no = clean.count("\n", 0, m.start()) + 1
                hits.append((
                    f"off-palette colour #{raw}",
                    "Use a palette token (var(--color-*) / var(--wp--preset--color--*)); "
                    "see tools/ci/lint_palette_hex.py PALETTE.",
                    f"{name}:{line_no}: {m.group(0).strip()}",
                ))
    return hits


def selftest():
    ok = True

    def expect(desc, cond):
        nonlocal ok
        print(("  PASS  " if cond else "  FAIL  ") + desc)
        ok = ok and cond

    expect("palette value passes", not lint_source("new.css", ".a{color:#0C5FC1}"))
    expect("short + alpha palette forms pass", not lint_source("new.css", ".a{color:#fff;background:#173258cc}"))
    expect("off-palette value fails in a new file", bool(lint_source("new.css", ".a{color:#123456}")))
    expect("legacy value passes only in its own file",
           not lint_source("hero.css", ".a{color:#2e7ef0}") and bool(lint_source("new.css", ".a{color:#2e7ef0}")))
    expect("royal fails even in a legacy file", bool(lint_source("split-horizon.css", ".a{color:#0d266b}")))
    expect("comments are ignored", not lint_source("new.css", "/* was #123456 */ .a{color:#0c5fc1}"))
    expect("id selectors are not colours", not lint_source("new.css", "#bad .a{color:#0c5fc1}"))
    expect("--accent-500 legacy definition allowed",
           not lint_source("split-horizon.css", ":root{--accent-500: #5697F3;}"))
    expect("9999px pill radius fails", bool(lint_source("new.css", ".a{border-radius:9999px}")))
    return ok


def main(argv):
    if "--selftest" in argv:
        sys.exit(0 if selftest() else 1)
    root = next((a for a in argv[1:] if not a.startswith("-")), ".")
    hits = []
    files = sorted(glob.glob(os.path.join(root, CSS_GLOB), recursive=True))
    for f in files:
        with open(f, encoding="utf-8", errors="replace") as fh:
            hits.extend(lint_source(os.path.relpath(f, root), fh.read()))
    if hits:
        sys.stderr.write("FAIL: off-palette / forbidden colour value(s) in child-theme CSS.\n")
        for label, fix, loc in hits:
            sys.stderr.write(f"- {label}: {loc}\n  Remediation: {fix}\n")
        sys.exit(1)
    print(f"PASS: {len(files)} child-theme CSS file(s) use only palette values (legacy files frozen).")


if __name__ == "__main__":
    main(sys.argv)
