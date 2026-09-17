#!/usr/bin/env node
// Generates the "Add to Home Screen" PNG icons for the web app.
//
// Design source: the same monogram as the tab favicon (src/app/icon.svg) —
// a #2563eb (Tailwind primary-600) square with a white "D" drawn as an
// evenodd path. Only the corners differ, on purpose:
//   - the tab favicon keeps rounded corners (transparent outside), and
//   - the launcher icons are full-bleed squares, so iOS/Android can apply
//     their own mask (this also makes them maskable safe-zone compliant).
//
// Regenerate with:  node apps/web/scripts/generate-icons.mjs
// Requires sharp (declared in apps/web devDependencies; also shipped as one
// of next's optionalDependencies).

import { mkdir } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const webRoot = join(dirname(fileURLToPath(import.meta.url)), '..');

let sharp;
try {
  sharp = (await import('sharp')).default;
} catch (error) {
  console.error('generate-icons: sharp is required to rasterise the icons.');
  console.error('Install it with:  npm install --save-dev sharp');
  console.error(error.message);
  process.exit(1);
}

// Monogram geometry, in a 64-unit viewBox — keep in sync with
// src/app/icon.svg. The outer subpath is the solid "D"; the inner subpath
// is its counter (hole); both wind the same way, so fill-rule="evenodd"
// is load-bearing (default nonzero would fill the counter solid).
const D_PATH = 'M20 48 V16 H28 A16 16 0 0 1 28 48 Z M27 41 V23 A9 9 0 0 1 27 41 Z';

const svgFor = (size) =>
  `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 64 64">
  <rect width="64" height="64" fill="#2563eb"/>
  <path fill="#ffffff" fill-rule="evenodd" d="${D_PATH}"/>
</svg>`;

const targets = [
  // iOS Safari "Add to Home Screen" (Next.js apple-icon file convention).
  { file: 'src/app/apple-icon.png', size: 180 },
  // Android/Chrome, referenced by src/app/manifest.ts (served from public/).
  { file: 'public/icon-192.png', size: 192 },
  { file: 'public/icon-512.png', size: 512 },
];

for (const { file, size } of targets) {
  const out = join(webRoot, file);
  await mkdir(dirname(out), { recursive: true });
  await sharp(Buffer.from(svgFor(size))).png({ compressionLevel: 9 }).toFile(out);
  console.log(`generate-icons: wrote ${file} (${size}x${size})`);
}
