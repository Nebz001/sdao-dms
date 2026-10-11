import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

/**
 * Rasterizes the light-background NU Lipa logo to the PNG the printed forms
 * embed (resources/views/components/print/document-logo.blade.php). dompdf's
 * SVG support is weak, so the print views never touch the SVG. 1320px wide is
 * ~600 dpi at the 55mm print width: sharp on paper, small enough to embed as
 * a base64 data URI. Re-run (`npm run images:print-logo`) if the logo changes.
 */
const SOURCE = fileURLToPath(
    new URL('../public/images/logo/nulp-logo-light-bg.svg', import.meta.url),
);
const OUTPUT = fileURLToPath(
    new URL('../public/print/nulp-logo-light-bg.png', import.meta.url),
);

await sharp(SOURCE, { density: 300 })
    .resize({ width: 1320 })
    .png({ compressionLevel: 9 })
    .toFile(OUTPUT);

console.log(`Wrote ${OUTPUT}`);
