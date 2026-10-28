#!/usr/bin/env python3
"""Verify the invisible payload + rights metadata of store previews AS SERVED.
usage: wm-venv/bin/python verify_preview_mark.py [--auth user:pass] [--expect NNN] URL_or_PATH ...
For URLs the image is fetched with a browser-like Accept header (so a CDN/LiteSpeed WebP or
re-encode would be caught). Decoding resizes back to 1200 px on the long edge (geometry-locked:
full image or WP sub-sizes OK; crops such as the 300x300 shop thumbnail are NOT decodable).
Exit 1 if any input fails to decode (or decodes to a different print than --expect / its filename)."""
import argparse, base64, json, os, re, sys, urllib.request
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import preview_provenance as pp
from PIL import Image, IptcImagePlugin
import io

UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130 Safari/537.36'


def fetch(u, auth):
    h = {'User-Agent': UA, 'Accept': 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8'}
    if auth:
        h['Authorization'] = 'Basic ' + base64.b64encode(auth.encode()).decode()
    r = urllib.request.urlopen(urllib.request.Request(u, headers=h), timeout=60)
    return r.read(), {k: r.headers.get(k) for k in ('content-type', 'cf-cache-status', 'x-litespeed-cache')}


def main():
    ap = argparse.ArgumentParser(); ap.add_argument('inputs', nargs='+')
    ap.add_argument('--auth'); ap.add_argument('--expect', type=int)
    a = ap.parse_args(); bad = 0
    for s in a.inputs:
        hdr = {}
        try:
            data, hdr = fetch(s, a.auth) if s.startswith('http') else (open(s, 'rb').read(), {})
        except Exception as e:                                   # 404 etc. -> report, keep going
            print(json.dumps({'in': s, 'ok': False, 'error': str(e)[:120]})); bad += 1; continue
        im = Image.open(io.BytesIO(data))
        no, info = pp.decode(data)
        m = re.search(r'/(\d{3})-[^/]*$', s)
        exp = a.expect if a.expect is not None else (int(m.group(1)) if m else None)
        ok = no is not None and (exp is None or no == exp)
        iptc = IptcImagePlugin.getiptcinfo(im) or {}
        x = im.info.get('xmp', b'') or b''
        meta = {'exif_copyright': bool(im.getexif().get(0x8298)), 'xmp_webstatement': b'WebStatement=' in x,
                'iptc_copyright': (2, 116) in iptc}
        print(json.dumps({'in': s, 'ok': ok, 'print_no': no, 'expected': exp, 'size': im.size, 'format': im.format,
                          **meta, **{k: v for k, v in hdr.items() if v}, **({'decoded_at_scale': info.get('decoded_at_scale')} if no else {})}))
        bad += not ok
    sys.exit(1 if bad else 0)


if __name__ == '__main__':
    main()
