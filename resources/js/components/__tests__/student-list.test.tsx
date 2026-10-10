import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { CalendarPlus } from 'lucide-react';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ClientListPage from '@/components/client-list-page';
import type { ListRow } from '@/components/client-list-page';
import { statusNote, tabCounts } from '@/lib/student-list';

const pageMock = vi.hoisted(() => ({ url: '/activity-proposals' }));

vi.mock('@inertiajs/react', () => ({
    Link: ({
        children,
        href,
        ...rest
    }: {
        children: ReactNode;
        href: unknown;
        [key: string]: unknown;
    }) => (
        <a
            href={typeof href === 'string' ? href : (href as { url: string }).url}
            {...rest}
        >
            {children}
        </a>
    ),
    usePage: () => ({ props: {}, url: pageMock.url }),
}));

const rows: ListRow[] = [
    { id: 1, title: 'Hack Night', status: 'approved', created_at: '2026-09-06T00:00:00Z', current_approver: null },
    { id: 2, title: 'Orientation', status: 'in_review', created_at: '2026-09-05T00:00:00Z', current_approver: 'Adviser' },
    { id: 3, title: 'Robotics Fair', status: 'returned', created_at: '2026-09-04T00:00:00Z', current_approver: 'SDAO' },
    { id: 4, title: 'Half Done', status: 'draft', created_at: '2026-09-03T00:00:00Z', current_approver: null },
];

function renderList(
    items: ListRow[] = rows,
    canStart = { enabled: true, reason: null as string | null },
    url = '/activity-proposals',
) {
    pageMock.url = url;

    render(
        <ClientListPage
            kind="proposal"
            icon={CalendarPlus}
            title="Activity Proposals"
            subtitle="Ask approval"
            searchPlaceholder="Search by activity name"
            startLabel="New Proposal"
            startHref="/activity-proposals/create"
            canStart={canStart}
            rows={items}
            dateLabel="Submitted"
            withDrafts
            empty={{
                title: 'No proposals yet',
                description: 'Ask for approval first.',
                steps: ['Fill in', 'Add details', 'Send'],
            }}
            rowAction={(row) => ({ label: 'View', href: `/p/${row.id}` })}
        />,
    );
}

afterEach(cleanup);

describe('list tabs and search', () => {
    it('counts every status, with All holding everything', () => {
        expect(
            tabCounts(rows.map((r) => r.status)),
        ).toEqual({ all: 4, in_review: 1, approved: 1, returned: 1, draft: 1 });
    });

    it('shows the count on each tab and narrows the rows when one is chosen', async () => {
        const user = userEvent.setup();

        renderList();

        expect(screen.getByRole('radio', { name: 'All, 4' })).toBeInTheDocument();
        expect(screen.getByRole('radio', { name: 'In review, 1' })).toBeInTheDocument();
        expect(screen.getByText('Showing 4 of 4')).toBeInTheDocument();

        await user.click(screen.getByRole('radio', { name: 'Returned, 1' }));

        expect(screen.getByText('Robotics Fair')).toBeInTheDocument();
        expect(screen.queryByText('Hack Night')).not.toBeInTheDocument();
        expect(screen.getByText('Showing 1 of 4')).toBeInTheDocument();
    });

    it('filters by search and the tab counts follow the search', async () => {
        const user = userEvent.setup();

        renderList();

        await user.type(
            screen.getByRole('searchbox', { name: 'Search by activity name' }),
            'robot',
        );

        expect(screen.getByText('Robotics Fair')).toBeInTheDocument();
        expect(screen.queryByText('Orientation')).not.toBeInTheDocument();
        expect(screen.getByRole('radio', { name: 'All, 1' })).toBeInTheDocument();
        expect(screen.getByRole('radio', { name: 'Approved, 0' })).toBeInTheDocument();
    });

    it('says nothing matches and clears the filters on request', async () => {
        const user = userEvent.setup();

        renderList();

        await user.type(
            screen.getByRole('searchbox', { name: 'Search by activity name' }),
            'zzzz',
        );
        expect(screen.getByText('Nothing matches')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Clear filters' }));
        expect(screen.getByText('Hack Night')).toBeInTheDocument();
    });

    it('opens the Drafts tab from ?tab=draft, and only shows it when a draft exists', () => {
        renderList(rows, { enabled: true, reason: null }, '/activity-proposals?tab=draft');

        expect(screen.getByRole('radio', { name: 'Drafts, 1' })).toBeChecked();
        expect(screen.getByText('Half Done')).toBeInTheDocument();
        expect(screen.queryByText('Hack Night')).not.toBeInTheDocument();

        cleanup();
        renderList(rows.filter((r) => r.status !== 'draft'));

        expect(screen.queryByRole('radio', { name: /Drafts/ })).not.toBeInTheDocument();
    });
});

describe('rows', () => {
    it('keeps status and the View button in their own columns, with a real "Now with" line', () => {
        renderList();

        const row = screen.getByText('Orientation').closest('li') as HTMLElement;

        expect(within(row).getByText(/^in review$/i)).toBeInTheDocument();
        expect(within(row).getByText('Now with: Adviser')).toBeInTheDocument();
        expect(within(row).getByRole('link', { name: 'View Orientation' })).toHaveAttribute(
            'href',
            '/p/2',
        );
        expect(screen.queryByText(/CURRENT APPROVER/i)).not.toBeInTheDocument();
    });

    it('never shows a placeholder when the approver is unknown', () => {
        expect(statusNote('in_review', 'proposal', null)).toBeNull();
        expect(statusNote('returned', 'proposal', null)).toBe('Sent back for changes');
        expect(statusNote('approved', 'proposal', null)).toBe('You can hold this activity');
    });
});

describe('start button and empty state', () => {
    it('shows the header button only when the student may start one', () => {
        renderList();
        expect(screen.getByRole('link', { name: 'New Proposal' })).toHaveAttribute(
            'href',
            '/activity-proposals/create',
        );

        cleanup();
        renderList(rows, { enabled: false, reason: 'Already filed for 1st Term' });

        expect(screen.queryByRole('link', { name: 'New Proposal' })).not.toBeInTheDocument();
        expect(screen.getByText('Already filed for 1st Term')).toBeInTheDocument();
    });

    it('shows the steps and the start button on an empty list when allowed', () => {
        renderList([]);

        expect(screen.getByText('No proposals yet')).toBeInTheDocument();
        expect(screen.getByText('Add details')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'New Proposal' })).toBeInTheDocument();
    });

    it('explains when it opens instead of offering a start button', () => {
        renderList([], { enabled: false, reason: 'Opens in 3rd term' });

        expect(screen.queryByRole('link', { name: 'New Proposal' })).not.toBeInTheDocument();
        expect(screen.getByText('Opens in 3rd term')).toBeInTheDocument();
    });
});
