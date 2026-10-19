import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import CreateReport from '@/pages/reports/create';
import EditReport from '@/pages/reports/edit';
import { fixItem, makeFix } from './fix-fixtures';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    setLayoutProps: vi.fn(),
    Link: ({ children, href }: { children: ReactNode; href: unknown }) => (
        <a
            href={
                typeof href === 'string' ? href : (href as { url: string }).url
            }
        >
            {children}
        </a>
    ),
    Form: ({
        children,
        ...rest
    }: {
        children: (state: {
            processing: boolean;
            errors: Record<string, string>;
        }) => ReactNode;
        [key: string]: unknown;
    }) => (
        <form id={rest.id as string}>
            {children({ processing: false, errors: {} })}
        </form>
    ),
    usePage: () => ({
        props: {
            auth: {
                user: {
                    id: 1,
                    name: 'Carl Andrew Mangobos',
                    first_name: 'Carl Andrew',
                    last_name: 'Mangobos',
                },
                organization: {
                    id: 1,
                    name: 'CA Org',
                    logoUrl: null,
                    school: { id: 1, name: 'Senior High School' },
                },
            },
        },
    }),
}));

const slots = [
    {
        key: 'photos',
        label: 'Photos',
        required: true,
        multiple: true,
        accept: '.jpg,.png',
        max_kb: 2048,
    },
    {
        key: 'attendance_sheet',
        label: 'Attendance Sheet',
        required: true,
        multiple: false,
        accept: '.pdf',
        max_kb: 2048,
    },
];

const detail = {
    summary: 'We held it.',
    outcomes: null,
    participant_count: 40,
    activity_chairs: ['Ana Cruz', 'Ben Lim'],
    prepared_by: 'Carl Andrew Mangobos',
    event_program: '9am opening',
    target_participants_percentage: 85,
    activity: {
        title: 'Hack Night',
        venue: 'Gym',
        activity_date: '2026-09-20',
        start_time: '13:00',
        end_time: '15:00',
    },
};

const membership = {
    id: 1,
    position: 'president',
    position_label: 'President',
    organization: { id: 1, name: 'CA Org', school: null },
};

const approved = [
    {
        activity_proposal_id: 3,
        title: 'Hack Night',
        venue: 'Gym',
        activity_date: '2026-09-20',
        start_time: '13:00',
        end_time: '15:00',
        term_label: '1st Term, 2026-2027',
        approved_on: '2026-09-06',
        report_filed: false,
    },
    {
        activity_proposal_id: 4,
        title: 'Robotics Fair',
        venue: 'Lab',
        activity_date: '2026-09-25',
        start_time: '09:00',
        end_time: '12:00',
        term_label: '1st Term, 2026-2027',
        approved_on: '2026-09-07',
        report_filed: true,
    },
];

describe('After-Activity Report form', () => {
    afterEach(cleanup);

    it('fills Prepared by with the first and last name and lets the officer add and remove chairs', async () => {
        const user = userEvent.setup();
        render(
            <CreateReport
                membership={membership}
                eligibleProposals={[
                    {
                        activity_proposal_id: 3,
                        title: 'Hack Night',
                        approved_on: '2026-09-06',
                        activity: {
                            name: 'Hack Night',
                            venue: 'Gym',
                            activity_date: '2026-09-20',
                        },
                    },
                ]}
                approvedActivities={approved}
                attachmentSlots={slots}
            />,
        );

        expect(screen.getByLabelText('Prepared by')).toHaveValue(
            'Carl Andrew Mangobos',
        );
        expect(screen.getByText(/Held Sep 20, 2026/)).toBeInTheDocument();
        expect(screen.getByText('Gym')).toBeInTheDocument();
        expect(
            screen.getByText(
                'Only approved activities without a report can be picked',
            ),
        ).toBeInTheDocument();

        expect(
            screen.getByRole('button', { name: 'Remove activity chair 1' }),
        ).toBeDisabled();
        await user.click(
            screen.getByRole('button', { name: 'Add another chair' }),
        );
        expect(screen.getByLabelText('Activity chair 2')).toBeInTheDocument();
        await user.click(
            screen.getByRole('button', { name: 'Remove activity chair 2' }),
        );
        expect(
            screen.queryByLabelText('Activity chair 2'),
        ).not.toBeInTheDocument();
    });

    it('lists every approved activity, with the ones that have a report disabled', async () => {
        const user = userEvent.setup();
        render(
            <CreateReport
                membership={membership}
                eligibleProposals={[]}
                approvedActivities={approved}
                attachmentSlots={slots}
            />,
        );

        await user.click(
            screen.getByRole('combobox', { name: 'Approved activity' }),
        );

        const filed = screen
            .getByText('Robotics Fair')
            .closest('[cmdk-item]') as HTMLElement;
        expect(filed).toHaveAttribute('aria-disabled', 'true');
        expect(filed).toHaveAccessibleDescription('Report filed');
        expect(screen.getByText(/already has a report/)).toBeInTheDocument();
    });

    it('picking an approved activity fills activity_proposal_id', async () => {
        const user = userEvent.setup();
        const { container } = render(
            <CreateReport
                membership={membership}
                eligibleProposals={[
                    {
                        activity_proposal_id: 3,
                        title: 'Hack Night',
                        approved_on: null,
                        activity: null,
                    },
                    {
                        activity_proposal_id: 5,
                        title: 'Other',
                        approved_on: null,
                        activity: null,
                    },
                ]}
                approvedActivities={[
                    ...approved,
                    { ...approved[0], activity_proposal_id: 5, title: 'Other' },
                ]}
                attachmentSlots={slots}
            />,
        );

        await user.click(
            screen.getByRole('combobox', { name: 'Approved activity' }),
        );
        await user.click(screen.getByText('Other'));

        expect(
            container.querySelector<HTMLInputElement>(
                'input[name="activity_proposal_id"]',
            ),
        ).toHaveValue('5');
    });

    it('explains when the organization has no approved activities at all', () => {
        render(
            <CreateReport
                membership={membership}
                eligibleProposals={[]}
                approvedActivities={[]}
                attachmentSlots={slots}
            />,
        );

        expect(
            screen.getByRole('heading', { name: 'No approved activities yet' }),
        ).toBeInTheDocument();
        expect(screen.queryByRole('combobox')).not.toBeInTheDocument();
    });

    it('opens the flagged sections, with their comments, when a returned report is edited, and locks the rest', () => {
        render(
            <EditReport
                document={{ id: 9, title: 'Report' }}
                detail={detail}
                attachmentSlots={slots}
                attachments={{}}
                fix={makeFix(
                    [
                        fixItem(
                            'summary_program',
                            'Summary/Program',
                            'Summary is too thin.',
                        ),
                        fixItem(
                            'evaluation',
                            'Evaluation',
                            'Percent looks wrong.',
                        ),
                        fixItem(
                            'attendance_sheet',
                            'Attendance Sheet',
                            'Sheet is unreadable.',
                            'file',
                        ),
                    ],
                    { formLabel: 'After-Activity Report' },
                )}
            />,
        );

        expect(screen.getByText('Summary is too thin.')).toBeInTheDocument();
        expect(screen.getByText('Percent looks wrong.')).toBeInTheDocument();
        expect(screen.getByText('Sheet is unreadable.')).toBeInTheDocument();

        const summary = screen.getByRole('region', {
            name: 'Summary and program',
        });
        expect(within(summary).getByLabelText('Summary')).toHaveValue(
            'We held it.',
        );
        const attachments = screen.getByRole('region', { name: 'Attachments' });
        expect(attachments).toHaveTextContent('Sheet is unreadable.');
        expect(
            screen.getByRole('heading', { name: '3 items need your changes' }),
        ).toBeInTheDocument();
    });

    it('stays fully editable, with no highlights, when nothing was flagged', () => {
        render(
            <EditReport
                document={{ id: 9, title: 'Report' }}
                detail={detail}
                attachmentSlots={slots}
                attachments={{}}
                fix={makeFix([], { comment: 'Redo it.' })}
            />,
        );

        expect(screen.queryByText('Needs a change')).not.toBeInTheDocument();
        expect(screen.getByLabelText('Summary')).toHaveValue('We held it.');
        expect(screen.getByText('Redo it.')).toBeInTheDocument();
    });
});
