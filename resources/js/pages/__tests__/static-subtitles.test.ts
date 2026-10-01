import { readdirSync, readFileSync } from 'node:fs';
import { join, sep } from 'node:path';
import { describe, expect, it } from 'vitest';

/**
 * A page header sublabel is the same text for every user and every state;
 * anything dynamic goes in a PageNotice under the header. This reads the page
 * sources so a data-driven sublabel cannot sneak back in.
 */
const PAGES_DIR = join(__dirname, '..');

function pageFiles(dir: string): string[] {
    return readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
        const path = join(dir, entry.name);

        if (entry.isDirectory()) {
            return entry.name === '__tests__' ? [] : pageFiles(path);
        }

        return entry.name.endsWith('.tsx') ? [path] : [];
    });
}

const files = pageFiles(PAGES_DIR).map((path) => ({
    path: path.slice(PAGES_DIR.length + 1).split(sep).join('/'),
    source: readFileSync(path, 'utf8'),
}));

describe('page header sublabels', () => {
    it('only passes a plain string literal as the PageHeader subtitle', () => {
        const offenders = files.filter(({ source }) =>
            /subtitle=\{/.test(source),
        );

        expect(offenders.map((f) => f.path)).toEqual([]);
    });

    it('builds every app page header with PageHeader, not a hand-made h1', () => {
        const allowed = ['welcome.tsx', 'errors/error.tsx'];
        const offenders = files.filter(
            ({ path, source }) =>
                !allowed.includes(path) &&
                // Settings pages keep a screen-reader-only h1 under the shared layout.
                /<h1(?![^>]*sr-only)/.test(source),
        );

        expect(offenders.map((f) => f.path)).toEqual([]);
    });

    it('never builds a sublabel from a template literal inside PageHeader', () => {
        const offenders = files.filter(({ source }) =>
            /<PageHeader[^>]*subtitle=`/.test(source),
        );

        expect(offenders.map((f) => f.path)).toEqual([]);
    });
});
