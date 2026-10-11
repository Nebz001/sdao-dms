import type { FixItem, FixProps } from '@/lib/fix-resend';

/** One flagged item, as the server sends it. */
export const fixItem = (
    key: string,
    label: string,
    comment: string | null = null,
    kind: FixItem['kind'] = 'section',
): FixItem => ({
    key,
    label,
    comment,
    kind,
});

/** The `fix` prop of a returned document; with no items nothing is fixable and the whole form stays open. */
export function makeFix(
    items: FixItem[],
    overrides: Partial<FixProps> = {},
): FixProps {
    return {
        active: items.length > 0,
        formLabel: 'Renewal',
        organization: 'PICE',
        returnedAt: '2026-10-09T02:00:00Z',
        returnedBy: { name: 'Zaira Joy Enayo', role: 'SDAO' },
        role: 'SDAO',
        comment: 'Please fix these.',
        items,
        notes: [],
        resendLabel: 'Resend to SDAO',
        back: { href: '/track', title: 'Track Documents' },
        draft: null,
        draftUrl: '/documents/1/fix-draft',
        ...overrides,
    };
}
