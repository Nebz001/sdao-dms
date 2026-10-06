import { render, screen } from '@testing-library/react';
import type { ComponentProps, ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import StuckDocumentsIndex from '@/pages/admin/stuck-documents/index';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { get: vi.fn(), post: vi.fn() },
    Link: ({ href, children, ...props }: { href: string; children: ReactNode }) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
}));

// Lives outside resources/js/pages/ on purpose: the @inertiajs/vite resolver globs ./pages/**/*.tsx with no test exclusion, so a test file there would be bundled as a page chunk.

type Props = ComponentProps<typeof StuckDocumentsIndex>;

const row = {
    id: 1,
    title: 'Blood Donation Drive',
    formType: 'activity_proposal',
    formTypeLabel: 'Activity Proposal',
    organizationName: 'Red Cross Youth',
    college: 'No college',
    state: 'in_review' as const,
    waitingOn: 'Carolyn D. Matira',
    waitingOnLine: 'Dean, step 3 of 4',
    remindTo: 'Carolyn D. Matira',
    remindAvailableLabel: null,
    sinceDate: '9/24/2026',
    idleDays: 9,
    idleTone: 'warning' as const,
    href: '/review/activity-proposals/1',
};

function props(overrides: Partial<Props> = {}): Props {
    return {
        mode: 'documents',
        documents: {
            data: [row],
            meta: { current_page: 1, last_page: 1, from: 1, to: 1, total: 1 },
            links: { prev: null, next: null },
        },
        activities: null,
        filters: { waiting_on: null, approver: null, role: null, form_type: null, idle: null, search: '' },
        approvers: [],
        formTypes: [{ value: 'activity_proposal', label: 'Activity Proposal' }],
        stats: {
            total: 1,
            withApprovers: 1,
            returned: 0,
            overThirty: 0,
            idleLongest: null,
            buckets: { under_7: 0, '7_14': 1, '15_30': 0, over_30: 0 },
            medianDays: 9,
            holdingMost: null,
        },
        ...overrides,
    } as Props;
}

describe('Stuck Documents page', () => {
    it('hides "Clear filters" while no filter is set', () => {
        render(<StuckDocumentsIndex {...props()} />);

        expect(screen.queryByRole('button', { name: 'Clear filters' })).toBeNull();
    });

    it.each([
        ['waiting on', { waiting_on: 'org' }],
        ['approver', { approver: 'user:5' }],
        ['form type', { form_type: 'activity_proposal' }],
        ['idle length', { idle: 'over_30' }],
        ['search', { search: 'red' }],
    ])('shows "Clear filters" once a %s filter is set', (_name, filter) => {
        const base = props();
        render(<StuckDocumentsIndex {...base} filters={{ ...base.filters, ...filter }} />);

        expect(screen.getByRole('button', { name: 'Clear filters' })).toBeTruthy();
    });

    it('writes the idle badge in full words and shows the new column headings', () => {
        render(<StuckDocumentsIndex {...props()} />);

        expect(screen.getAllByText('9 days').length).toBeGreaterThan(0);

        for (const heading of ['Document', 'Organization', 'Waiting on', 'Since', 'Idle', 'Action']) {
            expect(screen.getAllByText(heading).length).toBeGreaterThan(0);
        }
    });

    it('disables Remind and says when the next one can go while a document is on its cooldown', () => {
        const base = props();
        const documents = { ...base.documents!, data: [{ ...row, remindAvailableLabel: '9/17/2026 8:00 PM' }] };
        render(<StuckDocumentsIndex {...base} documents={documents} />);

        const buttons = screen.getAllByRole('button', { name: /^Remind/ });
        expect(buttons.every((b) => (b as HTMLButtonElement).disabled)).toBe(true);
        expect(screen.getAllByText('Again after 9/17/2026 8:00 PM').length).toBeGreaterThan(0);
    });

    it('keeps Remind enabled when no reminder was sent in the last day', () => {
        render(<StuckDocumentsIndex {...props()} />);

        const buttons = screen.getAllByRole('button', { name: /^Remind/ });
        expect(buttons.some((b) => !(b as HTMLButtonElement).disabled)).toBe(true);
    });

    it('shows a calm message in the cards when nothing is stuck', () => {
        const base = props();
        render(
            <StuckDocumentsIndex
                {...base}
                documents={{ ...base.documents!, data: [], meta: { ...base.documents!.meta, total: 0 } }}
            />,
        );

        expect(screen.getByText('Nothing is stuck right now.')).toBeTruthy();
        expect(screen.getByText('No approver is holding a document right now.')).toBeTruthy();
    });
});
