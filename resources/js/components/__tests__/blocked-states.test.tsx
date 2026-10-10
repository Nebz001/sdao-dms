import { cleanup, render, screen } from '@testing-library/react';
import { CalendarClock } from 'lucide-react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import BlockedState from '@/components/blocked-state';
import { PendingVerificationBlocked } from '@/components/student-blocked';
import CreateActivityCalendar from '@/pages/activity-calendars/create';
import CreateActivityProposal from '@/pages/activity-proposals/create';
import JoinOrganization from '@/pages/organizations/join/create';
import RequestOfficerChange from '@/pages/organizations/officer-change/create';
import CreateRegistration from '@/pages/registrations/create';
import CreateRenewal from '@/pages/renewals/create';
import CreateReport from '@/pages/reports/create';

const shared = vi.hoisted(() => ({
    auth: {
        user: { id: 1, name: 'Carl Andrew', first_name: 'Carl', last_name: 'Andrew', account_status: 'verified' },
        roles: [] as Array<{ role: string }>,
        organization: null,
        canProposeOrganization: true,
    },
}));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: { children: ReactNode; href: unknown }) => (
        <a href={typeof href === 'string' ? href : (href as { url: string }).url}>{children}</a>
    ),
    Form: ({
        children,
        ...rest
    }: {
        children: (state: { processing: boolean; errors: Record<string, string> }) => ReactNode;
        [key: string]: unknown;
    }) => <form id={rest.id as string}>{children({ processing: false, errors: {} })}</form>,
    router: { post: vi.fn(), get: vi.fn() },
    usePage: () => ({
        props: {
            auth: shared.auth,
            currentPeriod: { academic_year: '2026-2027', term: 'first_term', label: '1st Term, 2026-2027' },
        },
    }),
}));

function link(name: string | RegExp) {
    return screen.queryByRole('link', { name });
}

describe('BlockedState', () => {
    afterEach(cleanup);

    it('shows the icon, one heading, the body with bold values, and both actions in order', () => {
        render(
            <BlockedState
                icon={CalendarClock}
                title="Renewal not open yet"
                body={
                    <>
                        You can renew <strong>CA Org</strong> during <strong>3rd Term</strong>.
                    </>
                }
                secondary={{ label: 'See my renewals', href: '/renewals' }}
                primary={{ label: 'Go to My Organization', href: '/organizations/mine' }}
            />,
        );

        expect(screen.getByRole('heading', { level: 1, name: 'Renewal not open yet' })).toBeInTheDocument();
        expect(screen.getByText('CA Org').tagName).toBe('STRONG');
        const links = screen.getAllByRole('link').map((a) => a.textContent);
        expect(links).toEqual(['See my renewals', 'Go to My Organization']);
        expect(link('Go to My Organization')).toHaveAttribute('href', '/organizations/mine');
    });

    it('renders no buttons when no action is given', () => {
        render(<BlockedState icon={CalendarClock} title="Nothing here" body="Plain reason." />);

        expect(screen.queryByRole('link')).not.toBeInTheDocument();
    });
});

describe('blocked pages', () => {
    beforeEach(() => {
        shared.auth.user.account_status = 'verified';
        shared.auth.roles = [];
        shared.auth.canProposeOrganization = true;
    });
    afterEach(cleanup);

    const org = { id: 1, name: 'CA Org', college: 'SHS', program: null };
    const membership = { id: 1, position: 'president', position_label: 'President', organization: org };
    const baseRegistration = { canPropose: true, blocked: null, schools: [], organizationTypes: [], attachmentSlots: [] };

    describe('registration', () => {
        it('is "in review" with a link to that exact document', () => {
            render(
                <CreateRegistration
                    {...baseRegistration}
                    canPropose
                    blocked={{ reason: 'in_review', organization: 'CA Org', document_id: 7, href: '/registrations/7' }}
                />,
            );

            expect(screen.getByRole('heading', { name: 'Registration in review' })).toBeInTheDocument();
            expect(screen.getByText('CA Org').tagName).toBe('STRONG');
            expect(link('View my registration')).toHaveAttribute('href', '/registrations/7');
        });

        it('is "not available" for an officer, with the registered year and two next steps', () => {
            render(
                <CreateRegistration
                    {...baseRegistration}
                    canPropose={false}
                    blocked={{ reason: 'officer', organization: 'CA Org', covered_year: '2026-2027' }}
                />,
            );

            expect(screen.getByRole('heading', { name: 'Registration not available' })).toBeInTheDocument();
            expect(screen.getByText(/and it’s registered for 2026-2027/)).toBeInTheDocument();
            expect(link('See my registrations')).toBeInTheDocument();
            expect(link('Go to My Organization')).toBeInTheDocument();
        });

        it('says the account is waiting for verification, not that an organization exists', () => {
            render(<CreateRegistration {...baseRegistration} canPropose={false} blocked={{ reason: 'unverified' }} />);

            expect(screen.getByRole('heading', { name: 'Waiting for SDAO to verify your account' })).toBeInTheDocument();
            expect(link('Back to Home')).toBeInTheDocument();
            expect(link('Go to My Organization')).not.toBeInTheDocument();
        });

        it('still shows the form when nothing blocks it', () => {
            render(<CreateRegistration {...baseRegistration} />);

            expect(screen.getByRole('heading', { name: 'Register a New Organization' })).toBeInTheDocument();
            expect(screen.queryByRole('heading', { name: 'Registration not available' })).not.toBeInTheDocument();
        });
    });

    describe('renewal', () => {
        const base = {
            membership,
            priorRecord: {
                organization_type: 'co_curricular',
                purpose_of_organization: 'p',
                contact_person: 'c',
                contact_no: '1',
                email_address: 'e@x.com',
                date_organized: '2024-01-01',
            },
            currentPeriod: { academic_year: '2026-2027', term: 'first_term', label: '1st Term, 2026-2027' },
            organizationTypes: [],
            attachmentSlots: [],
        };

        it('is "not open yet" outside 3rd term, naming the organization and the term', () => {
            render(<CreateRenewal {...base} eligibility={{ status: 'season_closed', message: 'closed' }} />);

            expect(screen.getByRole('heading', { name: 'Renewal not open yet' })).toBeInTheDocument();
            expect(screen.getByText(/during/)).toHaveTextContent('You can renew CA Org during 3rd Term. SDAO will notify you once renewal opens.');
            expect(link('Go to My Organization')).toBeInTheDocument();
        });

        it('is "already filed" with a link to the renewals list', () => {
            render(<CreateRenewal {...base} eligibility={{ status: 'already_filed', message: 'filed' }} />);

            expect(screen.getByRole('heading', { name: 'Renewal already filed' })).toBeInTheDocument();
            expect(screen.getByText(/already has a renewal for/)).toHaveTextContent('2027-2028');
            expect(link('See my renewals')).toBeInTheDocument();
        });

        it('says there is nothing to renew when there is no approved record', () => {
            render(<CreateRenewal {...base} priorRecord={null} eligibility={{ status: 'no_prior_record', message: 'none' }} />);

            expect(screen.getByRole('heading', { name: 'Nothing to renew yet' })).toBeInTheDocument();
        });

        it('says "not an officer" with no organization, and offers only pages the student can open', () => {
            render(
                <CreateRenewal {...base} membership={null} priorRecord={null} eligibility={{ status: null, message: null }} />,
            );

            expect(screen.getByRole('heading', { name: 'You’re not an officer yet' })).toBeInTheDocument();
            expect(link('Join an organization')).toBeInTheDocument();
            expect(link('Register a new organization')).toBeInTheDocument();
            expect(link('Go to My Organization')).not.toBeInTheDocument();
        });

        it('still shows the form when renewal is open', () => {
            render(<CreateRenewal {...base} eligibility={{ status: 'eligible', message: null }} />);

            expect(screen.getByRole('heading', { name: 'Renew Your Organization' })).toBeInTheDocument();
            expect(screen.queryByRole('heading', { name: 'Renewal not open yet' })).not.toBeInTheDocument();
        });
    });

    describe('pages for officers, opened by someone who is not one', () => {
        it('proposal, report, calendar and officer change all say "not an officer yet"', () => {
            const pages: ReactNode[] = [
                <CreateActivityProposal
                    key="p"
                    membership={null}
                    current_term_label="1st Term"
                    calendarModes={[]}
                    activityNatures={[]}
                    activityTypes={[]}
                    sdgs={[]}
                    budgetSources={[]}
                    attachmentSlots={[]}
                />,
                <CreateReport key="r" membership={null} eligibleProposals={[]} attachmentSlots={[]} />,
                <CreateActivityCalendar key="c" membership={null} current_term_label="1st Term" eligibility={null} sdgs={[]} />,
                <RequestOfficerChange
                    key="o"
                    organization={null}
                    currentOfficers={[]}
                    pendingRequest={null}
                    positions={[]}
                />,
            ];

            for (const page of pages) {
                const { unmount } = render(page);
                expect(screen.getByRole('heading', { name: 'You’re not an officer yet' })).toBeInTheDocument();
                unmount();
            }
        });

        it('tells an unverified account that it is waiting on SDAO instead', () => {
            shared.auth.user.account_status = 'unverified';
            render(<CreateReport membership={null} eligibleProposals={[]} attachmentSlots={[]} />);

            expect(screen.getByRole('heading', { name: 'Waiting for SDAO to verify your account' })).toBeInTheDocument();
        });

        it('does not send an approver on a student page to student-only pages', () => {
            shared.auth.roles = [{ role: 'adviser' }];
            render(<CreateReport membership={null} eligibleProposals={[]} attachmentSlots={[]} />);

            expect(link('Join an organization')).not.toBeInTheDocument();
            expect(link('Back to Home')).toBeInTheDocument();
        });

        it('says the organization has no approved activities when a report has nothing to report on', () => {
            render(
                <CreateReport
                    membership={{ id: 1, position: 'president', position_label: 'President', organization: { ...org, school: null } }}
                    eligibleProposals={[]}
                    approvedActivities={[]}
                    attachmentSlots={[]}
                />,
            );

            expect(screen.getByRole('heading', { name: 'No approved activities yet' })).toBeInTheDocument();
            expect(link('View activity proposals')).toBeInTheDocument();
        });
    });

    describe('activity calendar already filed', () => {
        it('links to the filed calendar', () => {
            render(
                <CreateActivityCalendar
                    membership={membership}
                    current_term_label="1st Term"
                    sdgs={[]}
                    eligibility={{
                        status: 'already_filed',
                        message: 'CA Org already has a calendar for this term.',
                        existingDocument: { id: 3, status: 'returned', href: '/activity-calendars/3/edit' },
                    }}
                />,
            );

            expect(screen.getByRole('heading', { name: 'Activity calendar already filed' })).toBeInTheDocument();
            expect(link('Edit calendar')).toHaveAttribute('href', '/activity-calendars/3/edit');
        });
    });

    describe('join an organization', () => {
        it('is "pending" while a request is waiting, naming the organization', () => {
            render(<JoinOrganization alreadyAffiliated={false} pendingRequest={{ organization: { name: 'CODECS' } }} />);

            expect(screen.getByRole('heading', { name: 'Join request pending' })).toBeInTheDocument();
            expect(screen.getByText('CODECS').tagName).toBe('STRONG');
            expect(link('Back to Home')).toBeInTheDocument();
        });

        it('is "already an officer" for an officer, linking to their organization', () => {
            render(<JoinOrganization alreadyAffiliated pendingRequest={null} />);

            expect(screen.getByRole('heading', { name: 'You’re already an officer' })).toBeInTheDocument();
            expect(link('Go to My Organization')).toBeInTheDocument();
        });

        it('shows the search when nothing blocks it', () => {
            render(<JoinOrganization alreadyAffiliated={false} pendingRequest={null} />);

            expect(screen.getByRole('heading', { name: 'Join an Organization' })).toBeInTheDocument();
        });
    });

    describe('pending verification', () => {
        it('has no primary button, only a quiet Back to Home, and notes a waiting join request', () => {
            render(
                <PendingVerificationBlocked
                    extra={
                        <>
                            Your request to join <strong>CODECS</strong> is waiting on this too.
                        </>
                    }
                />,
            );

            expect(screen.getByText(/nothing else to do right now/)).toBeInTheDocument();
            expect(screen.getByText(/Your request to join/)).toBeInTheDocument();
            expect(screen.getAllByRole('link')).toHaveLength(1);
            expect(link('Back to Home')).toBeInTheDocument();
        });

        it('shows no link at all on Home, where the page already is', () => {
            render(<PendingVerificationBlocked showHome={false} />);

            expect(screen.queryByRole('link')).not.toBeInTheDocument();
        });
    });
});
