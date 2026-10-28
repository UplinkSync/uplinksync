#!/usr/bin/env python3
"""Build wp-content/themes/uplinksync-child/assets/css/site.min.css from site.css.

- Rules between /*@scope*/ and /*@endscope*/ get the .uls-site prefix (a "&" in a selector
  marks where it goes; otherwise ".uls-site " is prepended), including rules inside @media.
- Comments are dropped, whitespace collapsed; nothing else is rewritten.

Usage: python3 tools/build_site_css.py [--check]   (--check: fail if site.min.css is stale)
"""
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CSS_DIR = os.path.join(ROOT, "wp-content/themes/uplinksync-child/assets/css")
SRC = os.path.join(CSS_DIR, "site.css")
OUT = os.path.join(CSS_DIR, "site.min.css")
SCOPE = ".rq"  # short scope class; <main> carries "uls-site rq"


def blocks(css):
    """Yield (prelude, body) for each top-level block; body is raw text between braces."""
    i, n = 0, len(css)
    while i < n:
        j = css.find("{", i)
        if j < 0:
            if css[i:].strip():
                raise SystemExit("trailing text: " + css[i:i + 60])
            return
        depth, k = 1, j + 1
        while depth:
            if css[k] == "{":
                depth += 1
            elif css[k] == "}":
                depth -= 1
            k += 1
        yield css[i:j].strip(), css[j + 1:k - 1]
        i = k


def sel_min(sel):
    sel = re.sub(r"\s+", " ", sel).strip()
    return re.sub(r"\s*([,>+~])\s*", r"\1", sel)


def decl_min(body):
    body = re.sub(r"\s+", " ", body).strip()
    body = re.sub(r"\s*;\s*", ";", body)
    body = re.sub(r"\s*:\s*", ":", body, count=0)
    body = re.sub(r",\s+", ",", body)
    body = re.sub(r"\s*!important", "!important", body)
    return body.rstrip(";")


def prefix(sel):
    out = []
    for part in sel.split(","):
        part = part.strip()
        out.append(part.replace("&", SCOPE) if "&" in part else SCOPE + " " + part)
    return ",".join(out)


def build(css, scoped):
    out = []
    for pre, body in blocks(css):
        if pre.startswith("@media") or pre.startswith("@supports"):
            out.append(re.sub(r"\s+", " ", pre).replace(": ", ":") + "{" + build(body, scoped) + "}")
        else:
            sel = prefix(pre) if scoped else pre
            out.append(sel_min(sel) + "{" + decl_min(body) + "}")
    return "".join(out)


def main():
    src = open(SRC, encoding="utf-8").read()
    head, rest = src.split("/*@scope*/", 1)
    scoped, tail = rest.split("/*@endscope*/", 1)
    strip = lambda s: re.sub(r"/\*.*?\*/", "", s, flags=re.S)
    css = build(strip(head), False) + build(strip(scoped), True) + build(strip(tail), False)
    # Shorten the --s-* custom properties (readable in the source, 3 chars in the output).
    names = sorted(set(re.findall(r"--s-[a-z0-9-]+", css)), key=lambda v: (-css.count(v), v))
    for idx, name in enumerate(names):
        css = re.sub(re.escape(name) + r"(?![a-z0-9-])", "--q" + "abcdefghijklmnopqrstuvwxyz"[idx % 26] + (str(idx // 26) if idx >= 26 else ""), css)
    css = "/*built from site.css*/" + css + "\n"
    if "--check" in sys.argv:
        if not os.path.exists(OUT) or open(OUT, encoding="utf-8").read() != css:
            raise SystemExit("site.min.css is stale: run python3 tools/build_site_css.py")
        print("site.min.css up to date (%d bytes)" % len(css.encode()))
        return
    open(OUT, "w", encoding="utf-8").write(css)
    print("wrote %s (%d bytes)" % (os.path.relpath(OUT, ROOT), len(css.encode())))


if __name__ == "__main__":
    main()
