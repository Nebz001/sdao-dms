import { cleanup, render, screen, within } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import HubPage from '@/components/hub-page';
import type { HubChip } from '@/components/hub-page';

const pageMock = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));

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
    router: { reload: vi.fn() },
    usePage: () => ({ props: pageMock.props, url: '/submit' }),
}));

const navCounts = {
    stuck: 0,
    review: { registrations: 0, renewals: 0, calendars: 0, reports: 0, proposals: 0 },
    accounts: { pending: 0, officerChanges: 0 },
    documents: {
        registrations: 1,
        renewals: 0,
        calendars: 1,
        proposals: 2,
        reports: 0,
        history: 4,
    },
};

const officerAuth = {
    user: { id: 1 },
    roles: [{ role: 'student', school_id: null, program_id: null, organization_id: null }],
    isActiveOfficer: true,
    canProposeOrganization: false,
};

const foundingStudentAuth = {
    user: { id: 2 },
    roles: [],
    isActiveOfficer: false,
    canProposeOrganization: true,
};

function renderHub(
    hub: 'submit' | 'my-documents' | 'review',
    auth: Record<string, unknown>,
    chips: Record<string, HubChip> = {},
    organizationName: string | null = 'CA Org',
) {
    pageMock.props = { auth, navCounts };

    render(
        <HubPage hub={hub} organizationName={organizationName} chips={chips} />,
    );
}

afterEach(cleanup);

describe('Submit hub', () => {
    it('lists the nav config options as a grid of links to the forms', () => {
        renderHub('submit', officerAuth);

        expect(
            screen.getByRole('heading', { level: 1, name: 'Submit' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Choose the form you want to file for CA Org'),
        ).toBeInTheDocument();

        const titles = screen
            .getAllByRole('link')
            .map((link) => link.textContent ?? '');

        for (const title of [
            'Registration',
            'Renewal',
            'Activity Calendar',
            'Activity Proposal',
            'Report',
        ]) {
            expect(titles.some((t) => t.startsWith(title))).toBe(true);
        }

        expect(screen.getByRole('link', { name: /^Renewal/ })).toHaveAttribute(
            'href',
            '/renewals/create',
        );
    });

    it('shows a status chip only where the server sent one', () => {
        renderHub('submit', officerAuth, {
            report: { label: '1 activity needs a report', tone: 'warning' },
        });

        expect(screen.getByText('1 activity needs a report')).toBeInTheDocument();
        expect(screen.queryByText(/Filed for/)).not.toBeInTheDocument();
        expect(screen.queryByText(/drafts? saved/)).not.toBeInTheDocument();
    });

    it('adds Continue a Draft, linking to the drafts tab, only when a draft exists', () => {
        renderHub('submit', officerAuth, {
            draft: { label: '2 drafts saved', tone: 'neutral' },
        });

        const draft = screen.getByRole('link', { name: /^Continue a Draft/ });

        expect(draft).toHaveAttribute('href', expect.stringContaining('tab=draft'));
        expect(within(draft).getByText('2 drafts saved')).toBeInTheDocument();

        cleanup();
        renderHub('submit', officerAuth);

        expect(screen.queryByText('Continue a Draft')).not.toBeInTheDocument();
    });

    it('offers a student with no organization only the registration', () => {
        renderHub('submit', foundingStudentAuth, {}, null);

        expect(screen.getByRole('link', { name: /^Registration/ })).toBeInTheDocument();
        expect(screen.queryByText('Renewal')).not.toBeInTheDocument();
        expect(screen.queryByText('Activity Proposal')).not.toBeInTheDocument();
        expect(
            screen.getByText('Choose the form you want to file'),
        ).toBeInTheDocument();
    });

    it('keeps the one-line tip', () => {
        renderHub('submit', officerAuth);

        expect(screen.getByRole('link', { name: 'venue calendar' })).toBeInTheDocument();
    });
});

describe('My Documents hub', () => {
    it('shows each option with its document count', () => {
        renderHub('my-documents', officerAuth);

        const proposals = screen.getByRole('link', { name: /^Proposals/ });

        expect(within(proposals).getByText('2')).toBeInTheDocument();
        expect(proposals).toHaveAttribute('href', '/activity-proposals');
        expect(
            within(screen.getByRole('link', { name: /^Document History/ })).getByText(
                '4',
            ),
        ).toBeInTheDocument();
    });
});

describe('Review hub', () => {
    it('says so plainly when the account has nothing in it', () => {
        renderHub('review', foundingStudentAuth, {}, null);

        expect(screen.getByText('Nothing to open here yet')).toBeInTheDocument();
    });
});
