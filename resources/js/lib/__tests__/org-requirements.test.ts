import { describe, expect, it } from 'vitest';
import { buildRequirementRows, peopleSummary } from '@/lib/org-requirements';
import type { RequirementItem } from '@/lib/org-requirements';

function items(met: Partial<Record<string, boolean>>): RequirementItem[] {
    return [
        'registration_approved',
        'adviser_bound',
        'president_bound',
        'secretary_bound',
        'renewal_filed',
    ].map((key) => ({ key, label: key, met: met[key] ?? false }));
}

describe('My Organization requirements', () => {
    it('counts the four standing requirements and drops the renewal row out of season', () => {
        const rows = buildRequirementRows(
            items({
                registration_approved: true,
                adviser_bound: true,
                president_bound: true,
            }),
            false,
            'CA Org',
            '2026-2027',
        );

        expect(rows.total).toBe(4);
        expect(rows.doneCount).toBe(3);
        expect(rows.open.map((r) => r.key)).toEqual(['secretary_bound']);
    });

    it('adds the renewal row only while renewal applies', () => {
        const rows = buildRequirementRows(
            items({
                registration_approved: true,
                adviser_bound: true,
                president_bound: true,
            }),
            true,
            'CA Org',
            '2026-2027',
        );

        expect(rows.total).toBe(5);
        expect(rows.doneCount).toBe(3);
        expect(rows.open.map((r) => r.key)).toEqual([
            'secretary_bound',
            'renewal_filed',
        ]);
        expect(rows.open[1]).toMatchObject({
            title: 'File a renewal for next year',
            description: 'Keeps CA Org active after 2026-2027',
            action: { label: 'Start renewal', target: 'renewal' },
        });
    });

    it('points a missing secretary or president at an officer change request', () => {
        const rows = buildRequirementRows(items({}), false, 'CA Org', null);
        const secretary = rows.open.find((r) => r.key === 'secretary_bound');
        const president = rows.open.find((r) => r.key === 'president_bound');

        expect(secretary?.title).toBe('Add a secretary');
        expect(secretary?.action?.target).toBe('officer-change');
        expect(president?.action?.target).toBe('officer-change');
    });

    it('offers no button for a missing adviser, since only SDAO can assign one', () => {
        const rows = buildRequirementRows(items({}), false, 'CA Org', null);

        expect(rows.open.find((r) => r.key === 'adviser_bound')?.action).toBeNull();
    });

    it('relabels the met items as chips', () => {
        const rows = buildRequirementRows(
            items({ registration_approved: true, adviser_bound: true }),
            false,
            'CA Org',
            null,
        );

        expect(rows.done.map((d) => d.label)).toEqual([
            'Registration approved',
            'Adviser assigned',
        ]);
    });

    it('is fully done with nothing open when everything is met', () => {
        const rows = buildRequirementRows(
            items({
                registration_approved: true,
                adviser_bound: true,
                president_bound: true,
                secretary_bound: true,
                renewal_filed: true,
            }),
            false,
            'CA Org',
            null,
        );

        expect(rows).toMatchObject({ doneCount: 4, total: 4, open: [] });
    });

    it('gets singular and plural right in the people summary', () => {
        expect(peopleSummary(1, 1)).toBe('1 officer, 1 adviser');
        expect(peopleSummary(2, 0)).toBe('2 officers, 0 advisers');
    });
});
