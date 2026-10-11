import { render, screen, within } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import QueueFilterTabs from '@/components/review-queue/filter-tabs';
import ReviewQueuePage from '@/components/review-queue/review-queue-page';
import type { ReviewQueueConfig } from '@/components/review-queue/types';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Deferred: ({ children }: { children: ReactNode }) => <>{children}</>,
    Link: ({
        children,
        href,
        ...rest
    }: {
        children: ReactNode;
        href: string;
        [key: string]: unknown;
    }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
    router: { get: vi.fn() },
}));

vi.mock('@/hooks/use-document-updates', () => ({
    useDocumentUpdates: () => undefined,
}));

const counts = { pending: 3, overdue: 1, approved: 4, returned: 2 };
const hrefFor = (value: string | null) =>
    value === null ? '/review/registrations' : `/review/registrations?filter=${value}`;

const config: ReviewQueueConfig = {
    headTitle: 'Review: Registrations',
    title: 'Registration Review Queue',
    subtitle: 'Organization registrations waiting for SDAO review',
    noun: 'registration',
    typeLabel: 'Registration',
    emptyDescription: 'Submitted registrations will show up here.',
    showRoute: (id) => `/review/registrations/${id}`,
    indexUrl: hrefFor,
};

const pageProps = {
    queue: [],
    extraColumnLabel: 'College',
    filterLabel: null,
    academicYear: '2026-2027',
    tabCounts: counts,
    pagination: {
        meta: { from: null, to: null, total: 0 },
        links: { prev: null, next: null },
    },
};

describe('QueueFilterTabs', () => {
    it('shows the five tabs, counts on the first four, and marks the open one', () => {
        render(
            <QueueFilterTabs
                label="Filter registrations"
                filter="approved"
                counts={counts}
                hrefFor={hrefFor}
            />,
        );

        const nav = screen.getByRole('navigation', { name: 'Filter registrations' });
        const links = within(nav).getAllByRole('link');

        expect(links.map((l) => l.textContent)).toEqual([
            'Pending3',
            'Overdue1',
            'Approved4',
            'Returned2',
            'All decisions',
        ]);
        expect(within(nav).getByRole('link', { name: /Approved/ })).toHaveAttribute('aria-current', 'page');
        expect(within(nav).getByRole('link', { name: /Pending/ })).not.toHaveAttribute('aria-current');
        expect(within(nav).getByRole('link', { name: /Overdue/ })).toHaveAttribute(
            'href',
            '/review/registrations?filter=overdue',
        );
    });
});

describe('ReviewQueuePage tabs', () => {
    it('shows one pending table with its empty state on the Pending tab', () => {
        render(
            <ReviewQueuePage config={config} filter={null} rows={[]} {...pageProps} />,
        );

        expect(screen.getByText('Pending your action')).toBeInTheDocument();
        expect(screen.getByText('Nothing to review')).toBeInTheDocument();
        expect(screen.queryByText('Recently decided')).not.toBeInTheDocument();
    });

    it('names the Overdue tab and its empty state', () => {
        render(
            <ReviewQueuePage config={config} filter="overdue" rows={[]} {...pageProps} />,
        );

        expect(screen.getByText('Nothing overdue')).toBeInTheDocument();
    });

    it('lists decisions with their result, date and decider on a history tab, with pagination in the card', () => {
        render(
            <ReviewQueuePage
                config={config}
                filter="decided"
                rows={[
                    {
                        id: 9,
                        organization: 'Computing Society',
                        college: 'School of Computing',
                        result: 'rejected',
                        decided_at: '2026-10-01T08:00:00+08:00',
                        decided_by: 'Carl Justin Magpantay',
                        href: '/review/registrations/9',
                    },
                ]}
                {...pageProps}
                filterLabel="All decisions"
                pagination={{
                    meta: { from: 1, to: 1, total: 1 },
                    links: { prev: null, next: null },
                }}
            />,
        );

        // The tab and the table card's title both read "All decisions".
        expect(screen.getAllByText('All decisions').length).toBeGreaterThanOrEqual(2);
        expect(screen.getAllByText('Computing Society')[0]).toBeInTheDocument();
        expect(screen.getAllByText('Rejected')[0]).toBeInTheDocument();
        expect(screen.getAllByText('Carl Justin Magpantay')[0]).toBeInTheDocument();
        expect(screen.getByText('Showing 1 to 1 of 1')).toBeInTheDocument();
    });
});
