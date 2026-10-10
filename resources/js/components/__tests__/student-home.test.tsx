import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type {
    NeedsActionData,
    StudentDashboardMeta,
    StudentKpis,
    TrackerData,
} from '@/components/student-dashboard';
import StudentHome, { HomeStatusBanner } from '@/components/student-home';
import { buildNavSections } from '@/lib/nav-config';
import {
    bannerState,
    buildHomeCards,
    filterHomeCards,
    greetingFor,
} from '@/lib/student-home';

const pageMock = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));

vi.mock('@inertiajs/react', () => ({
    Link: ({
        children,
        href,
        ...rest
    }: {
        children: ReactNode;
        href: unknown;
        className?: string;
        [key: string]: unknown;
    }) => (
        <a
            href={
                typeof href === 'string' ? href : (href as { url: string }).url
            }
            {...rest}
        >
            {children}
        </a>
    ),
    router: { reload: vi.fn() },
    usePage: () => ({ props: pageMock.props, url: '/dashboard' }),
}));

const navCounts = {
    stuck: 0,
    review: {
        registrations: 0,
        renewals: 0,
        calendars: 0,
        reports: 0,
        proposals: 0,
    },
    accounts: { pending: 0, officerChanges: 0 },
    documents: {
        registrations: 1,
        renewals: 0,
        calendars: 2,
        proposals: 5,
        reports: 0,
        history: 0,
    },
};

const officerAuth = {
    user: { id: 1, name: 'Carl Andrew', first_name: 'Carl' },
    roles: [
        {
            role: 'student',
            school_id: null,
            program_id: null,
            organization_id: null,
        },
    ],
    isActiveOfficer: true,
    canProposeOrganization: false,
    organization: {
        id: 1,
        name: 'CA Org',
        logoUrl: null,
        school: { id: 1, name: 'Senior High School' },
    },
};

const plainStudentAuth = {
    user: { id: 2, name: 'Pia Reyes', first_name: 'Pia' },
    roles: [
        {
            role: 'student',
            school_id: null,
            program_id: null,
            organization_id: null,
        },
    ],
    isActiveOfficer: false,
    canProposeOrganization: true,
    organization: null,
};

const meta: StudentDashboardMeta = {
    organizationName: 'CA Org',
    organizationStatus: 'active',
    officerPosition: 'President',
    academicYear: '2026-2027',
    termLabel: '1st Term',
    historyHref: '/document-history',
};

function titlesFor(auth: Record<string, unknown>): string[] {
    return buildHomeCards(buildNavSections(auth as never, navCounts)).map(
        (card) => card.title,
    );
}

function renderHome(auth: Record<string, unknown>, withMeta = true) {
    pageMock.props = { auth, navCounts };

    render(
        <StudentHome
            meta={withMeta ? meta : null}
            banner={<div>banner</div>}
        />,
    );
}

afterEach(cleanup);

describe('home cards by role', () => {
    it('gives an active officer all six cards', () => {
        expect(titlesFor(officerAuth)).toEqual([
            'Submit',
            'My Documents',
            'Review',
            'Venue Calendar',
            'My Organization',
            'Request Officer Change',
        ]);
    });

    it('hides Review, My Organization and Request Officer Change from a student with no organization', () => {
        const titles = titlesFor(plainStudentAuth);

        expect(titles).toEqual([
            'Submit',
            'My Documents',
            'Venue Calendar',
            'Join an Organization',
        ]);
        expect(titles).not.toContain('Review');
        expect(titles).not.toContain('Request Officer Change');
    });

    it('computes option counts from the nav config and sums the document badges', () => {
        const cards = buildHomeCards(
            buildNavSections(officerAuth as never, navCounts),
        );
        const submit = cards.find((c) => c.title === 'Submit');
        const documents = cards.find((c) => c.title === 'My Documents');

        expect(submit?.items).toHaveLength(5);
        expect(documents?.items).toHaveLength(6);
        expect(documents?.badge).toBe(8);
    });

    it('sends a group with a single sub page straight to it', () => {
        const cards = buildHomeCards(
            buildNavSections(officerAuth as never, navCounts),
        );
        const review = cards.find((c) => c.title === 'Review');

        expect(review?.items).toBeNull();
        expect(review?.href).toBeTruthy();
    });
});

describe('search filtering', () => {
    const cards = buildHomeCards(
        buildNavSections(officerAuth as never, navCounts),
    );

    it('matches a card by its title or description', () => {
        expect(filterHomeCards(cards, 'venue').map((c) => c.title)).toEqual([
            'Venue Calendar',
        ]);
    });

    it('keeps a card whose option matches the search', () => {
        const result = filterHomeCards(cards, 'renewal');

        expect(result.map((c) => c.title)).toContain('Submit');
        expect(result.map((c) => c.title)).toContain('My Documents');
    });

    it('returns nothing for a query that matches nothing', () => {
        expect(filterHomeCards(cards, 'zzzz')).toEqual([]);
    });
});

describe('StudentHome page', () => {
    beforeEach(() => {
        pageMock.props = {};
    });

    it('greets by first name and shows only the pills that apply', () => {
        renderHome(officerAuth);

        expect(screen.getByRole('heading', { level: 1 }).textContent).toMatch(
            /^Good (morning|afternoon|evening), Carl$/,
        );
        expect(screen.getByText('President')).toBeInTheDocument();
        expect(screen.getByText('Senior High School')).toBeInTheDocument();
        expect(screen.getByText('Active organization')).toBeInTheDocument();
    });

    it('hides the school pill for an organization with no school', () => {
        renderHome({
            ...officerAuth,
            organization: { ...officerAuth.organization, school: null },
        });

        expect(
            screen.queryByText('Senior High School'),
        ).not.toBeInTheDocument();
    });

    it('shows no pills for a student without an organization', () => {
        renderHome(plainStudentAuth, false);

        expect(screen.queryByLabelText('Your role')).not.toBeInTheDocument();
        expect(screen.getByText('Join an Organization')).toBeInTheDocument();
    });

    it('filters the cards as the user types and shows an empty state', async () => {
        const user = userEvent.setup();

        renderHome(officerAuth);

        const search = screen.getByRole('searchbox', {
            name: 'Search forms and pages',
        });

        await user.type(search, 'calendar');
        expect(screen.getByText('Venue Calendar')).toBeInTheDocument();
        expect(screen.queryByText('My Organization')).not.toBeInTheDocument();

        await user.clear(search);
        await user.type(search, 'qqqq');
        expect(
            screen.getByText('Nothing matches your search'),
        ).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Clear search' }));
        expect(screen.getByText('My Organization')).toBeInTheDocument();
    });

    it('sends a card with several options to its hub page, never a dropdown', () => {
        renderHome(officerAuth);

        const submit = screen.getByRole('link', { name: /Submit/ });

        expect(submit).toHaveAttribute('href', '/submit');
        expect(screen.getByRole('link', { name: /My Documents/ })).toHaveAttribute(
            'href',
            '/my-documents',
        );
        expect(screen.queryByRole('menu')).not.toBeInTheDocument();
        expect(within(submit).getByText('5 options')).toBeInTheDocument();
    });

    it('makes a one-page card a plain link', () => {
        renderHome(officerAuth);

        expect(
            screen.getByRole('link', { name: /Venue Calendar/ }),
        ).toHaveAttribute('href', expect.stringContaining('/calendar'));
    });
});

describe('status banner', () => {
    const kpis = (needsRevision: number) =>
        ({ needsRevision: { count: needsRevision, href: '#' } }) as StudentKpis;
    const tracker = (total: number): TrackerData => ({ total, items: [] });
    const needsAction = (total: number): NeedsActionData => ({
        total,
        items: [],
    });

    it('says caught up and counts documents in the chain, singular', () => {
        render(
            <HomeStatusBanner
                needsAction={needsAction(0)}
                tracker={tracker(1)}
                kpis={kpis(0)}
                trackHref="/document-history"
            />,
        );

        expect(screen.getByText('You’re all caught up')).toBeInTheDocument();
        expect(
            screen.getByText(
                'Nothing needs your action. 1 document is moving through the approval chain.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /Track my documents/ }),
        ).toHaveAttribute('href', '/document-history');
    });

    it('uses the plural for several documents', () => {
        expect(
            bannerState({
                needsActionTotal: 0,
                returnedCount: 0,
                draftCount: 0,
                inChainTotal: 3,
            }).description,
        ).toBe(
            'Nothing needs your action. 3 documents are moving through the approval chain.',
        );
    });

    it('warns with the count when something needs action, never the caught up message', () => {
        render(
            <HomeStatusBanner
                needsAction={needsAction(3)}
                tracker={tracker(3)}
                kpis={kpis(2)}
                trackHref="/document-history"
            />,
        );

        expect(
            screen.getByText('3 documents need your action'),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                '2 returned for revision and 1 unfinished draft. 1 document is moving through the approval chain.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.queryByText('You’re all caught up'),
        ).not.toBeInTheDocument();
        expect(screen.getByRole('status')).toHaveAttribute(
            'data-state',
            'needs-action',
        );
    });

    it('gets the singular right for one document needing action', () => {
        expect(
            bannerState({
                needsActionTotal: 1,
                returnedCount: 1,
                draftCount: 0,
                inChainTotal: 1,
            }).title,
        ).toBe('1 document needs your action');
    });
});

describe('greetingFor', () => {
    it('follows the hour in Asia/Manila, not the local clock', () => {
        // 00:30 UTC is 08:30 in Manila; 10:00 UTC is 18:00; 05:00 UTC is 13:00.
        expect(greetingFor(new Date('2026-10-09T00:30:00Z'))).toBe(
            'Good morning',
        );
        expect(greetingFor(new Date('2026-10-09T05:00:00Z'))).toBe(
            'Good afternoon',
        );
        expect(greetingFor(new Date('2026-10-09T10:00:00Z'))).toBe(
            'Good evening',
        );
    });
});
