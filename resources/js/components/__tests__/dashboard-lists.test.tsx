import { render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import OldestInReviewCard from '@/components/oldest-in-review-card';
import OrgComplianceCard from '@/components/org-compliance-card';
import RecentActivityCard from '@/components/recent-activity-card';
import WeeklySubmissionsCard from '@/components/weekly-submissions-card';

vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children, ...props }: { href: string; children: React.ReactNode }) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
    router: { visit: vi.fn() },
}));

describe('RecentActivityCard', () => {
    const entries = [
        {
            id: 1,
            actorName: 'Anna Cruz',
            badge: 'approved' as const,
            organizationName: 'JPIA',
            summary: 'Activity Proposal: Accounting Week Kickoff',
            createdAt: new Date(Date.now() - 14 * 60_000).toISOString(),
            href: '/review/activity-proposals/1',
        },
        {
            id: 2,
            actorName: 'Maria Reyes',
            badge: 'returned' as const,
            organizationName: 'CODECS',
            summary: '3 sections flagged on After-Activity Report: Hack Day',
            createdAt: new Date(Date.now() - 3 * 3_600_000).toISOString(),
            href: '/review/reports/2',
        },
    ];

    it('shows initials, the actor, an uppercase outcome badge, the org and a two-part summary per row', () => {
        render(<RecentActivityCard entries={entries} viewAllHref="/admin/activity" />);

        const row = screen.getAllByRole('listitem')[0];
        expect(within(row).getByText('AC')).toBeInTheDocument();
        expect(within(row).getByText('Anna Cruz').className).toContain('font-semibold');
        expect(within(row).getByText('Approved').className).toContain('uppercase');
        expect(within(row).getByText('JPIA')).toBeInTheDocument();
        expect(within(row).getByText('14 min ago')).toBeInTheDocument();
        expect(within(row).getByRole('link', { name: 'Activity Proposal: Accounting Week Kickoff' })).toHaveAttribute(
            'href',
            '/review/activity-proposals/1',
        );
        expect(screen.getByText('Newest first')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'View all activity' })).toHaveAttribute('href', '/admin/activity');
    });

    it('never shows the old Completed or Advanced labels or an em dash', () => {
        const { container } = render(<RecentActivityCard entries={entries} viewAllHref="/admin/activity" />);

        expect(screen.queryByText(/completed|advanced/i)).toBeNull();
        expect(container.textContent).not.toContain('—');
    });

    it('has an empty state', () => {
        render(<RecentActivityCard entries={[]} viewAllHref="/admin/activity" />);

        expect(screen.getByText('Nothing has happened yet')).toBeInTheDocument();
    });
});

describe('OldestInReviewCard', () => {
    const documents = [
        {
            id: 1,
            title: 'Activity Proposal: Leadership Summit',
            organizationName: 'SABM Council',
            approverName: 'Dr. Maria Reyes',
            idleDays: 11,
            tier: 'stale' as const,
            href: '/review/activity-proposals/1',
        },
    ];

    it('shows the document, the org, who it is with, and the idle pill as text', () => {
        render(<OldestInReviewCard documents={documents} viewAllHref="/admin/stuck-documents" />);

        expect(screen.getByText('Activity Proposal: Leadership Summit')).toBeInTheDocument();
        expect(screen.getByText('SABM Council')).toBeInTheDocument();
        expect(screen.getByText('at Dr. Maria Reyes')).toBeInTheDocument();
        expect(screen.getByText('11 d idle')).toBeInTheDocument();
        expect(screen.getByText('Idle time counted from the last real action, not from the last edit')).toBeInTheDocument();
    });

    it('drops the In Review badge and the ago text', () => {
        render(<OldestInReviewCard documents={documents} viewAllHref="/admin/stuck-documents" />);

        expect(screen.queryByText(/^in review$/i)).toBeNull();
        expect(screen.queryByText(/ago/)).toBeNull();
    });
});

describe('OrgComplianceCard', () => {
    const base = {
        renewalSeason: false,
        targetYear: '2026-2027',
        done: 18,
        total: 24,
        pendingTotal: 6,
        pending: [{ organizationId: 1, organizationName: 'JFINEX', count: 3, href: '/admin/organizations?search=JFINEX' }],
        viewAllHref: '/admin/organizations',
    };

    it('shows the renewed count, a meter and the pending rows', () => {
        render(<OrgComplianceCard data={base} />);

        expect(screen.getByText('Renewed this academic year')).toBeInTheDocument();
        expect(screen.getByText('18 / 24')).toBeInTheDocument();
        expect(screen.getByRole('meter')).toHaveAttribute('aria-valuenow', '75');
        expect(screen.getByText('ORGANIZATIONS WITH PENDING ITEMS'.toLowerCase(), { exact: false })).toBeInTheDocument();
        expect(screen.getByText('3 pending')).toBeInTheDocument();
        expect(screen.getByText('and 5 more organizations')).toBeInTheDocument();
        expect(screen.queryByRole('status')).toBeNull();
    });

    it('makes renewal season prominent and measures against next year', () => {
        render(<OrgComplianceCard data={{ ...base, renewalSeason: true, targetYear: '2027-2028' }} />);

        expect(screen.getByRole('status')).toHaveTextContent('Renewal season is open. Organizations can renew for A.Y. 2027 to 2028 now.');
        expect(screen.getByText('Renewed for A.Y. 2027 to 2028')).toBeInTheDocument();
    });

    it('keeps the old All caught up message inside the card when everyone is covered', () => {
        render(<OrgComplianceCard data={{ ...base, done: 24, pending: [], pendingTotal: 0 }} />);

        expect(screen.getByText('All caught up. Every organization has renewed for A.Y. 2026 to 2027.')).toBeInTheDocument();
        expect(screen.getByText(/Nothing pending/)).toBeInTheDocument();
    });
});

describe('WeeklySubmissionsCard', () => {
    const weeks = [
        { label: 'W1', start: '2026-09-14', end: '2026-09-21', count: 2, current: false, href: '/a' },
        { label: 'Now', start: '2026-09-21', end: '2026-09-28', count: 7, current: true, href: '/b' },
    ];

    it.each([
        [5, '5 more than last week'],
        [-2, '2 fewer than last week'],
        [0, 'Same as last week'],
    ])('words a change of %i as "%s"', (delta, text) => {
        render(
            <WeeklySubmissionsCard data={{ termLabel: '1st Term', weeks, thisWeek: 7, lastWeek: 2, delta }} />,
        );

        expect(screen.getByText(text)).toBeInTheDocument();
        expect(screen.getByText('1st Term, all form types')).toBeInTheDocument();
        expect(screen.getByText('7')).toBeInTheDocument();
    });

    it('gives keyboard and screen reader users a link per week', () => {
        render(<WeeklySubmissionsCard data={{ termLabel: '1st Term', weeks, thisWeek: 7, lastWeek: 2, delta: 5 }} />);

        expect(screen.getByRole('link', { name: /Now, week of 2026-09-21: 7 submitted/ })).toHaveAttribute('href', '/b');
    });

    it('says when the term has not started instead of drawing an empty chart', () => {
        render(<WeeklySubmissionsCard data={{ termLabel: '2nd Term', weeks: [], thisWeek: 0, lastWeek: null, delta: null }} />);

        expect(screen.getByText('This term has not started yet')).toBeInTheDocument();
    });
});
