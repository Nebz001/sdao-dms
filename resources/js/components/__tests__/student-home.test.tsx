import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { StudentDashboardMeta } from '@/components/student-dashboard';
import StudentHome, { HomeStatusBanner } from '@/components/student-home';
import { buildNavSections } from '@/lib/nav-config';
import {
    buildHomeCards,
    filterHomeCards,
    greetingFor,
} from '@/lib/student-home';
import { bannerFor } from '@/lib/track';
import type { TrackSummary } from '@/lib/track';

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
    const base: TrackSummary = {
        returned: 0,
        drafts: 0,
        moving: 0,
        approved: 0,
        approvedYear: 0,
        stopped: 0,
        total: 0,
        movingByStep: [],
    };
    const summary = (overrides: Partial<TrackSummary>): TrackSummary => ({ ...base, ...overrides });
    const href = () => screen.getByRole('link').getAttribute('href');

    it('is orange when something needs action, opens the Needs your action tab, and counts what is moving', () => {
        render(<HomeStatusBanner summary={summary({ returned: 1, moving: 1, total: 2 })} />);

        expect(screen.getByText('1 document needs your action')).toBeInTheDocument();
        expect(
            screen.getByText('1 returned for revision. 1 document is moving through the approval chain.'),
        ).toBeInTheDocument();
        expect(screen.getByRole('status')).toHaveAttribute('data-tone', 'orange');
        expect(href()).toContain('/track?tab=action');
        expect(screen.getByRole('link', { name: /Track my documents/ })).toBeInTheDocument();
    });

    it('counts returned documents and unfinished drafts together, in the plural', () => {
        render(<HomeStatusBanner summary={summary({ returned: 2, drafts: 1, moving: 3, total: 6 })} />);

        expect(screen.getByText('3 documents need your action')).toBeInTheDocument();
        expect(
            screen.getByText(
                '2 returned for revision. 1 unfinished draft. 3 documents are moving through the approval chain.',
            ),
        ).toBeInTheDocument();
    });

    it('is NU blue when documents are moving and nothing needs action, naming where each one is', () => {
        render(
            <HomeStatusBanner
                summary={summary({
                    moving: 2,
                    total: 2,
                    movingByStep: [
                        { label: 'College Dean', count: 1 },
                        { label: 'SDAO', count: 1 },
                    ],
                })}
            />,
        );

        expect(screen.getByText('2 documents are moving')).toBeInTheDocument();
        expect(
            screen.getByText('Nothing needs your action. 1 is with the College Dean, 1 is with SDAO.'),
        ).toBeInTheDocument();
        expect(screen.getByRole('status')).toHaveAttribute('data-tone', 'blue');
        expect(href()).toContain('/track?tab=moving');
    });

    it('gets the singular right for one moving document and for several at one step', () => {
        const { unmount } = render(
            <HomeStatusBanner
                summary={summary({ moving: 1, total: 1, movingByStep: [{ label: 'Adviser', count: 1 }] })}
            />,
        );
        expect(screen.getByText('1 document is moving')).toBeInTheDocument();
        unmount();

        render(
            <HomeStatusBanner
                summary={summary({ moving: 3, total: 3, movingByStep: [{ label: 'SDAO', count: 3 }] })}
            />,
        );
        expect(screen.getByText('Nothing needs your action. 3 are with SDAO.')).toBeInTheDocument();
    });

    it('is green when everything is approved, and opens the Approved tab', () => {
        render(<HomeStatusBanner summary={summary({ approved: 4, approvedYear: 4, total: 4 })} />);

        expect(screen.getByText('All documents approved')).toBeInTheDocument();
        expect(screen.getByText('You’re all caught up. 4 documents approved this term.')).toBeInTheDocument();
        expect(screen.getByRole('status')).toHaveAttribute('data-tone', 'green');
        expect(screen.getByRole('link', { name: /See document routing/ })).toBeInTheDocument();
        expect(href()).toContain('/track?tab=approved');
    });

    it('says one approved document in the singular', () => {
        render(<HomeStatusBanner summary={summary({ approved: 1, approvedYear: 1, total: 1 })} />);

        expect(screen.getByText('You’re all caught up. 1 document approved this term.')).toBeInTheDocument();
    });

    it('is a gray banner with a Start a proposal button when nothing was ever filed', () => {
        render(<HomeStatusBanner summary={summary({})} />);

        expect(screen.getByText('No documents yet this term')).toBeInTheDocument();
        expect(screen.getByText('Start with an activity proposal. You can track it here once it is sent.')).toBeInTheDocument();
        expect(screen.getByRole('status')).toHaveAttribute('data-tone', 'gray');
        expect(href()).toContain('/activity-proposals/create');
        expect(screen.getByRole('link', { name: /Start a proposal/ })).toBeInTheDocument();
    });

    it('still renders, quietly, when the only documents were stopped', () => {
        render(<HomeStatusBanner summary={summary({ stopped: 2, total: 2 })} />);

        expect(screen.getByRole('status')).toHaveAttribute('data-tone', 'gray');
        expect(href()).toContain('/track');
    });

    it('picks the state in order: action beats moving beats approved', () => {
        expect(bannerFor(summary({ returned: 1, moving: 2, approvedYear: 3, total: 6 })).kind).toBe('action');
        expect(bannerFor(summary({ moving: 2, approvedYear: 3, total: 5 })).kind).toBe('moving');
        expect(bannerFor(summary({ approvedYear: 3, total: 3 })).kind).toBe('approved');
        expect(bannerFor(summary({})).kind).toBe('empty');
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
