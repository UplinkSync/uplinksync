#!/usr/bin/env python3
"""Provenance layer for store previews (owner decision 2026-10-28, hardened Option B).

build_preview(src, stem) -> JPEG bytes:
  1. hardened visible mark  (watermark_preview.mark_diagonal_hardened, 1200px long edge)
  2. invisible payload      imwatermark 0.2.0 dwtDctSvd, U-chroma channel, block 8,
                            strength adaptive 80..140 until the decode gate passes
                            (q80 + resize to 1024 and 768 wide, upscale back, decode).
                            Payload 48 bits = b"ULS" (UplinkSync tag) + uint16 print number
                            + CRC-8 -> a wrong/no mark is reported as "no mark", not garbage.
  3. ONE JPEG encode at q80 with EXIF (Artist/Copyright/Description) + XMP rights
     (dc:rights, xmpRights:Marked/WebStatement/UsageTerms, plus:Licensor URL,
     photoshop:Credit) ; then an IPTC-IIM APP13 segment is spliced in (no re-encode).

decode(path_or_array) -> (print_no or None, info). Geometry-locked: the image is resized
back to 1200 px on the long edge first (works for WP sub-sizes 1024/768/600 wide; does
NOT survive crops such as the 300x300 shop thumbnail, rotation or heavy edits).

Run with ~/ops-work-20261007/wm-venv/bin/python (imwatermark + PyWavelets; cv2/numpy reused
from the user site). rivaGan (torch+onnxruntime, ~1 GB) is stubbed out -- not used.
"""
import io, struct, sys, types

if 'imwatermark.rivaGan' not in sys.modules:          # avoid the torch import in imwatermark
    _s = types.ModuleType('imwatermark.rivaGan')

    class RivaWatermark:                                # noqa: D401
        def __init__(self, *a, **k):
            raise RuntimeError('rivaGan not installed (needs torch+onnxruntime)')
    _s.RivaWatermark = RivaWatermark
    sys.modules['imwatermark.rivaGan'] = _s

import cv2
import numpy as np
from PIL import Image
from imwatermark import WatermarkEncoder, WatermarkDecoder

import watermark_preview as wp

LONG_EDGE = 1200
QUALITY = 80
METHOD = 'dwtDctSvd'
BLOCK = 8
STRENGTHS = (80, 100, 120, 140)        # U-channel scale; first one that passes the gate wins
TAG = b'ULS'                           # = UplinkSync
CREATOR = 'UplinkSync LLC'
CREDIT = 'Aerial by UplinkSync'
LICENCE_URL = 'https://uplinksync.com/image-license/'
CONTACT = 'contact@uplinksync.com'
YEAR = '2026'


def crc8(b):
    c = 0
    for x in b:
        c ^= x
        for _ in range(8):
            c = ((c << 1) ^ 0x07) & 0xFF if c & 0x80 else (c << 1) & 0xFF
    return c


def payload(print_no):
    p = TAG + struct.pack('>H', int(print_no))
    return p + bytes([crc8(p)])


def _cfg(s):
    return dict(scales=[0, s, 0], block=BLOCK)


def _jpeg(a, q):
    ok, b = cv2.imencode('.jpg', a, [cv2.IMWRITE_JPEG_QUALITY, q])
    return cv2.imdecode(b, cv2.IMREAD_COLOR)


def _to_long(a, long_edge=LONG_EDGE, interp=cv2.INTER_CUBIC):
    h, w = a.shape[:2]
    if max(h, w) == long_edge:
        return a
    s = long_edge / float(max(h, w))
    return cv2.resize(a, (round(w * s), round(h * s)), interpolation=interp)


def _decode_bytes(bgr, s, prepared=False):
    d = WatermarkDecoder('bytes', 48)
    try:
        return d.decode(bgr if prepared else _to_long(bgr), METHOD, **_cfg(s))
    except Exception:
        return b''


def decode(src):
    """src: path, bytes or BGR array. Returns (print_no|None, dict)."""
    if isinstance(src, np.ndarray):
        bgr = src
    else:
        data = open(src, 'rb').read() if isinstance(src, str) else src
        bgr = cv2.imdecode(np.frombuffer(data, np.uint8), cv2.IMREAD_COLOR)
    seen = []
    # resize back to 1200 px; for small copies also try other kernels / a light blur
    # (tag + CRC-8 = 32 check bits, so extra attempts do not create false positives)
    variants = [('cubic', _to_long(bgr))]
    if max(bgr.shape[:2]) != LONG_EDGE:
        variants += [('linear', _to_long(bgr, interp=cv2.INTER_LINEAR)), ('lanczos', _to_long(bgr, interp=cv2.INTER_LANCZOS4)),
                     ('cubic+blur', cv2.GaussianBlur(variants[0][1], (3, 3), 0.6))]
    for vname, img in variants:
        for s in sorted(set(STRENGTHS)):   # decoder needs the embedding scale; try each
            b = _decode_bytes(img, s, prepared=True)
            seen.append(b.hex())
            if len(b) == 6 and b[:3] == TAG and crc8(b[:5]) == b[5]:
                return struct.unpack('>H', b[3:5])[0], {'decoded_at_scale': s, 'via': vname, 'raw': b.hex(), 'brand': 'UplinkSync'}
    return None, {'raw_tried': seen}


def gate(bgr, pl, s):
    """True if the payload survives q80 and WP-like downsizes (1024, 768 wide)."""
    h, w = bgr.shape[:2]
    j = _jpeg(bgr, QUALITY)
    if _decode_bytes(j, s) != pl:
        return False
    for tw, q in ((1024, 82), (768, 75)):
        r = _jpeg(cv2.resize(j, (tw, round(tw * h / w)), interpolation=cv2.INTER_AREA), q)
        if _decode_bytes(r, s) != pl:
            return False
    return True


def embed(rgb_img, print_no):
    bgr = cv2.cvtColor(np.asarray(rgb_img.convert('RGB')), cv2.COLOR_RGB2BGR)
    pl = payload(print_no)
    for s in STRENGTHS:
        e = WatermarkEncoder()
        e.set_watermark('bytes', pl)
        out = e.encode(bgr, METHOD, **_cfg(s))
        if gate(out, pl, s):
            return Image.fromarray(cv2.cvtColor(out, cv2.COLOR_BGR2RGB)), s
    raise RuntimeError('invisible mark failed the decode gate at every strength (print %s)' % print_no)


def _xmp(title, print_no):
    esc = lambda t: t.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')
    notice = 'Copyright %s %s. All rights reserved. Watermarked preview; removal of the watermark or of this copyright management information is prohibited (17 U.S.C. 1202).' % (YEAR, CREATOR)
    terms = 'Preview only - no licence granted. Licensed (watermark-free) files: %s' % LICENCE_URL
    return ('''<?xpacket begin="﻿" id="W5M0MpCehiHzreSzNTczkc9d"?>
<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
<rdf:Description rdf:about=""
 xmlns:dc="http://purl.org/dc/elements/1.1/"
 xmlns:xmpRights="http://ns.adobe.com/xap/1.0/rights/"
 xmlns:photoshop="http://ns.adobe.com/photoshop/1.0/"
 xmlns:Iptc4xmpCore="http://iptc.org/std/Iptc4xmpCore/1.0/xmlns/"
 xmlns:plus="http://ns.useplus.org/ldf/xmp/1.0/"
 xmpRights:Marked="True"
 xmpRights:WebStatement="{lic}"
 photoshop:Credit="{credit}"
 photoshop:Source="UplinkSync print {no:03d}"
 dc:identifier="uplinksync-print-{no:03d}">
<dc:creator><rdf:Seq><rdf:li>{creator}</rdf:li></rdf:Seq></dc:creator>
<dc:title><rdf:Alt><rdf:li xml:lang="x-default">{title}</rdf:li></rdf:Alt></dc:title>
<dc:rights><rdf:Alt><rdf:li xml:lang="x-default">{notice}</rdf:li></rdf:Alt></dc:rights>
<xmpRights:UsageTerms><rdf:Alt><rdf:li xml:lang="x-default">{terms}</rdf:li></rdf:Alt></xmpRights:UsageTerms>
<Iptc4xmpCore:CreatorContactInfo rdf:parseType="Resource"><Iptc4xmpCore:CiEmailWork>{mail}</Iptc4xmpCore:CiEmailWork><Iptc4xmpCore:CiUrlWork>https://uplinksync.com/</Iptc4xmpCore:CiUrlWork></Iptc4xmpCore:CreatorContactInfo>
<plus:Licensor><rdf:Seq><rdf:li rdf:parseType="Resource"><plus:LicensorName>{creator}</plus:LicensorName><plus:LicensorURL>{lic}</plus:LicensorURL></rdf:li></rdf:Seq></plus:Licensor>
<plus:CopyrightOwner><rdf:Seq><rdf:li rdf:parseType="Resource"><plus:CopyrightOwnerName>{creator}</plus:CopyrightOwnerName></rdf:li></rdf:Seq></plus:CopyrightOwner>
</rdf:Description></rdf:RDF></x:xmpmeta>
<?xpacket end="w"?>''').format(lic=LICENCE_URL, credit=CREDIT, no=int(print_no), creator=CREATOR, title=esc(title),
                                notice=esc(notice), terms=esc(terms), mail=CONTACT).encode('utf-8'), notice, terms


def _iptc_app13(title, print_no, notice, terms):
    def ds(rec, tag, val):
        v = val.encode('utf-8')
        return struct.pack('>BBBH', 0x1C, rec, tag, len(v)) + v
    iim = (ds(1, 90, '\x1b%G') + ds(2, 0, '\x00\x04') + ds(2, 5, title[:64]) + ds(2, 80, CREATOR)
           + ds(2, 110, CREDIT) + ds(2, 115, 'UplinkSync') + ds(2, 116, notice)
           + ds(2, 40, terms[:256]) + ds(2, 103, 'uplinksync-print-%03d' % int(print_no)))
    res = b'8BIM' + struct.pack('>H', 0x0404) + b'\x00\x00' + struct.pack('>I', len(iim)) + iim
    if len(iim) % 2:
        res += b'\x00'
    body = b'Photoshop 3.0\x00' + res
    return b'\xff\xed' + struct.pack('>H', len(body) + 2) + body


def _splice_app13(jpeg, seg):
    """Insert seg after SOI and any APP0/APP1/APP2 segments (pixel data untouched)."""
    i = 2
    while jpeg[i] == 0xFF and jpeg[i + 1] in (0xE0, 0xE1, 0xE2):
        i += 2 + struct.unpack('>H', jpeg[i + 2:i + 4])[0]
    return jpeg[:i] + seg + jpeg[i:]


def build_preview(src, stem, title=None, opacity=None):
    """src: path or file-like of the CLEAN graded master. stem: 'NNN-slug'. Returns (bytes, info)."""
    print_no = int(stem[:3])
    title = title or stem
    img = wp.render(src, 'diagonal-h', long_edge=LONG_EDGE, opacity=opacity, seed=stem)
    img, strength = embed(img, print_no)
    xmp, notice, terms = _xmp(title, print_no)
    ex = Image.Exif()
    ex[0x013B] = CREATOR                         # Artist
    ex[0x8298] = notice                          # Copyright
    ex[0x010E] = '%s - watermarked preview. %s' % (title, LICENCE_URL)   # ImageDescription
    buf = io.BytesIO()
    img.save(buf, 'JPEG', quality=QUALITY, optimize=True, progressive=True, exif=ex.tobytes(), xmp=xmp)
    data = _splice_app13(buf.getvalue(), _iptc_app13(title, print_no, notice, terms))
    got, _ = decode(data)                        # final self-check on the exact bytes shipped
    if got != print_no:
        raise RuntimeError('final decode mismatch for %s: %r' % (stem, got))
    return data, {'print_no': print_no, 'strength': strength, 'bytes': len(data), 'size': img.size}


if __name__ == '__main__':
    import argparse, os
    ap = argparse.ArgumentParser(description='render hardened previews from clean masters')
    ap.add_argument('srcs', nargs='+'); ap.add_argument('--out', required=True)
    a = ap.parse_args()
    os.makedirs(a.out, exist_ok=True)
    for s in a.srcs:
        st = wp.out_stem(s)
        data, info = build_preview(s, st)
        open(os.path.join(a.out, st + '-preview.jpg'), 'wb').write(data)
        print(st, info)
