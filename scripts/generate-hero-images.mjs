import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

/**
 * Generates the WebP derivatives the landing page hero (resources/js/pages/
 * welcome.tsx) loads via `srcset`, from the single source photo at
 * public/images/nulp-building.png. Re-run this (`npm run images:hero`)
 * whenever that source photo is swapped or re-cropped — it is not a
 * one-off, the derivatives are regenerated output and are safe to
 * overwrite.
 */
const SOURCE = fileURLToPath(
    new URL('../public/images/nulp-building.png', import.meta.url),
);

const WIDTHS = [960, 1600, 2400];

for (const width of WIDTHS) {
    const outPath = fileURLToPath(
        new URL(
            `../public/images/nulp-building-${width}.webp`,
            import.meta.url,
        ),
    );

    await sharp(SOURCE)
        .resize({ width })
        .webp({ quality: 82 })
        .toFile(outPath);

    console.log(`wrote nulp-building-${width}.webp`);
}
