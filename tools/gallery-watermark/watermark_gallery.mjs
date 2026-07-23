import sharp from 'sharp';
import { readFileSync } from 'fs';
import path from 'path';

// Tiled arrow icon-grid watermark for the public drone gallery (***-245).
// Matches the store-preview look: light translucent arrow glyph, tiled full-field,
// slight diagonal, faint dark emboss so it reads on both bright skies and dark water.
// Applied to medium+ renditions (full 1920w, hero 2560w). Thumbnails stay clean.

const ARROW = '/tmp/arrow_icon.png';
const OPACITY = 0.12;          // ~12% — light but present
const TILE_LONG_EDGE = 150;    // glyph long-edge px within each tile cell
const GAP = 190;               // spacing between tile cells (px)
const ANGLE = -18;             // slight diagonal

async function buildTile() {
  // Scale arrow to target long edge
  const meta = await sharp(ARROW).metadata();
  const scale = TILE_LONG_EDGE / Math.max(meta.width, meta.height);
  const w = Math.round(meta.width * scale);
  const h = Math.round(meta.height * scale);
  const glyph = await sharp(ARROW).resize(w, h).ensureAlpha().raw().toBuffer({ resolveWithObject: true });

  // Emboss: dark shadow copy offset +1px, then the light glyph on top.
  const light = await sharp(ARROW).resize(w, h)
    .ensureAlpha()
    .composite([{ // multiply glyph alpha down to OPACITY via a flat black tint? Instead modulate later.
      input: Buffer.from([255, 255, 255, 255]), raw: { width: 1, height: 1, channels: 4 }, tile: true, blend: 'dest-in'
    }])
    .png().toBuffer();
  return { light, w, h };
}

async function makeWatermarkLayer(width, height) {
  const { light, w, h } = await buildTile();

  // Dark emboss variant (glyph silhouette darkened) and light variant.
  const darkGlyph = await sharp(light)
    .modulate({ brightness: 0.15 })   // near-black, keeps original alpha shape
    .png().toBuffer();

  const cell = GAP;
  const cols = Math.ceil(width / cell) + 2;
  const rows = Math.ceil(height / cell) + 2;

  const composites = [];
  for (let r = 0; r < rows; r++) {
    for (let c = 0; c < cols; c++) {
      const x = Math.round(c * cell + (r % 2) * cell / 2) - w;
      const y = Math.round(r * cell) - h;
      // emboss shadow +1px
      composites.push({ input: darkGlyph, left: x + 1, top: y + 1 });
      composites.push({ input: light, left: x, top: y });
    }
  }

  // Compose onto transparent canvas, rotate for diagonal, center-crop to size.
  const big = Math.round(Math.max(width, height) * 1.5);
  let layer = sharp({ create: { width: big, height: big, channels: 4, background: { r: 0, g: 0, b: 0, alpha: 0 } } })
    .composite(composites.filter(c => c.left > -w && c.top > -h && c.left < big && c.top < big));
  layer = sharp(await layer.png().toBuffer()).rotate(ANGLE, { background: { r: 0, g: 0, b: 0, alpha: 0 } });
  const rbuf = await layer.png().toBuffer();
  const rmeta = await sharp(rbuf).metadata();
  const left = Math.round((rmeta.width - width) / 2);
  const top = Math.round((rmeta.height - height) / 2);
  const cropped = await sharp(rbuf).extract({ left, top, width, height }).ensureAlpha().png().toBuffer();

  // Scale whole-layer alpha to OPACITY by multiplying with a flat alpha mask.
  const faded = await sharp(cropped)
    .composite([{
      input: {
        create: { width, height, channels: 4, background: { r: 255, g: 255, b: 255, alpha: OPACITY } }
      }, blend: 'dest-in'
    }])
    .png().toBuffer();
  return faded;
}

async function run() {
  const files = process.argv.slice(2);
  for (const f of files) {
    const base = await sharp(f).ensureAlpha();
    const meta = await base.metadata();
    const layer = await makeWatermarkLayer(meta.width, meta.height);
    const composited = sharp(await base.png().toBuffer()).composite([{ input: layer, blend: 'over' }]);
    const ext = path.extname(f).toLowerCase();
    let out;
    if (ext === '.webp') out = await composited.webp({ quality: 82 }).toBuffer();
    else out = await composited.flatten({ background: '#000' }).jpeg({ quality: 86, mozjpeg: true }).toBuffer();
    const { writeFileSync } = await import('fs');
    writeFileSync(f, out);
    console.log('watermarked', path.basename(f), meta.width + 'x' + meta.height, out.length + 'b');
  }
}
run();
