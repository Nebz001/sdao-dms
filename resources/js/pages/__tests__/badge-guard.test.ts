import { readFileSync, readdirSync, statSync } from 'node:fs';
import path from 'node:path';
import { describe, expect, it } from 'vitest';

/**
 * No page styles its own badge. A page shows a status with StatusBadge,
 * ActionBadge, OrganizationStatusBadge and the other wrappers in
 * components/status-badge.tsx, and a name or label with TagBadge. This guard
 * reads PAGES ONLY (resources/js/pages). It does not read the navbar, the
 * sidebar or any other component.
 *
 * Two things are flagged in a page:
 *   1. an import of the raw shadcn badge, `@/components/ui/badge`;
 *   2. the old solid badge recipe, `border-transparent bg-<tone> text-...`.
 *
 * Pages that have not been migrated yet are listed in PENDING. The list only
 * shrinks: a page in it that no longer violates fails the test until it is
 * removed, and a page not in it that violates fails immediately. When the last
 * slice is done this list is empty and the allowance is deleted.
 */
const PENDING = new Set<string>([
    'review/activity-proposals/show.tsx',
]);

const PAGES_DIR = path.resolve(__dirname, '..');

function pageFiles(dir: string): string[] {
    return readdirSync(dir).flatMap((entry) => {
        const full = path.join(dir, entry);

        if (statSync(full).isDirectory()) {
            return entry === '__tests__' ? [] : pageFiles(full);
        }

        return full.endsWith('.tsx') ? [full] : [];
    });
}

function violations(source: string): string[] {
    const found: string[] = [];

    if (/from ['"]@\/components\/ui\/badge['"]/.test(source)) {
        found.push('imports @/components/ui/badge');
    }

    if (/border-transparent bg-(success|info|warning|destructive)\b/.test(source)) {
        found.push('uses the solid badge recipe');
    }

    return found;
}

describe('pages do not style their own badges', () => {
    const files = pageFiles(PAGES_DIR).map((file) => ({
        relative: path.relative(PAGES_DIR, file).split(path.sep).join('/'),
        source: readFileSync(file, 'utf8'),
    }));

    it('finds the pages directory', () => {
        expect(files.length).toBeGreaterThan(40);
    });

    it('has no new page that imports a raw badge or a solid badge recipe', () => {
        const offenders = files
            .filter((f) => !PENDING.has(f.relative))
            .map((f) => ({ file: f.relative, found: violations(f.source) }))
            .filter((f) => f.found.length > 0);

        expect(offenders).toEqual([]);
    });

    it('keeps the pending list honest: every listed page still violates', () => {
        const stale = [...PENDING].filter((file) => {
            const page = files.find((f) => f.relative === file);

            return !page || violations(page.source).length === 0;
        });

        expect(stale).toEqual([]);
    });
});
