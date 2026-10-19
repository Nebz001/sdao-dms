import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { progressText, sameValue } from '@/lib/fix-resend';
import type { FixProps } from '@/lib/fix-resend';
import EditCalendar from '@/pages/activity-calendars/edit';
import EditProposal from '@/pages/activity-proposals/edit';
import EditRegistration from '@/pages/registrations/edit';
import EditRenewal from '@/pages/renewals/edit';
import EditReport from '@/pages/reports/edit';
import { fixItem as item, makeFix } from './fix-fixtures';

const put = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href }: { children: ReactNode; href: unknown }) => (
        <a
            href={
                typeof href === 'string' ? href : (href as { url: string }).url
            }
        >
            {children}
        </a>
    ),
    router: { put: (...args: unknown[]) => put(...args), post: vi.fn() },
    setLayoutProps: vi.fn(),
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
                user: { id: 1, name: 'Nathaniel' },
                organization: { name: 'PICE', school: { name: 'SACE' } },
            },
        },
    }),
}));

beforeEach(() => {
    put.mockReset();
    // Not implemented in jsdom; the scroll-to-item chips call it.
    Element.prototype.scrollIntoView = vi.fn();
});
afterEach(cleanup);

const slots = [
    {
        key: 'letter_of_intent',
        label: 'Letter of Intent',
        required: true,
        multiple: false,
        accept: '.pdf',
        max_kb: 2048,
    },
    {
        key: 'by_laws',
        label: 'By-Laws',
        required: true,
        multiple: false,
        accept: '.pdf',
        max_kb: 2048,
    },
];
const existing = {
    letter_of_intent: [
        {
            id: 1,
            original_filename: 'letter_v1.pdf',
            download_url: '/d1',
            preview_url: '/p1',
            size: 100,
        },
    ],
    by_laws: [
        {
            id: 2,
            original_filename: 'bylaws_v1.pdf',
            download_url: '/d2',
            preview_url: '/p2',
            size: 100,
        },
    ],
};
const org = { name: 'PICE', college: 'SACE', program: 'BS Civil Engineering' };
const detail = {
    organization_type_label: 'Co-Curricular',
    purpose_of_organization: 'We renew.',
    contact_person: 'PICE Officers',
    contact_no: '09170000000',
    email_address: 'pice@students.nu-lipa.edu.ph',
    date_organized: '2024-08-15',
};

function renewal(fix: FixProps) {
    return render(
        <EditRenewal
            document={{ id: 1, title: 'Renewal', organization: org }}
            detail={detail}
            attachmentSlots={slots}
            attachments={existing}
            fix={fix}
        />,
    );
}

describe('the comparison behind "fixed"', () => {
    it('trims text and reads numbers by value', () => {
        expect(sameValue('  hello ', 'hello')).toBe(true);
        expect(sameValue('5000', '5000.00')).toBe(true);
        expect(sameValue('a', 'b')).toBe(false);
        expect(sameValue(null, '')).toBe(true);
        expect(sameValue(['a', ' b '], ['a', 'b'])).toBe(true);
    });

    it('says how many are left, and why the button is off', () => {
        expect(progressText(2, 3)).toBe('2 of 3 fixed. Fix 1 more to resend.');
        expect(progressText(3, 3)).toBe('3 of 3 fixed. Ready to resend.');
    });
});

describe('Fix and Resend: a returned renewal', () => {
    it('locks everything that was not flagged: no inputs, just labelled values and a Locked badge', () => {
        renewal(
            makeFix([
                item(
                    'organization_details',
                    'Organization Details',
                    'Describe what the organization does.',
                ),
            ]),
        );

        // Contact was not flagged: its values show, its inputs do not exist.
        expect(
            screen.queryByLabelText('Contact person'),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByLabelText('Organization email'),
        ).not.toBeInTheDocument();
        expect(screen.getByText('PICE Officers')).toBeInTheDocument();
        const contact = screen.getByRole('region', { name: 'Contact' });
        expect(within(contact).getByText('Locked')).toBeInTheDocument();

        // The flagged section is editable, with the approver's comment on top.
        expect(screen.getByLabelText('Purpose')).toHaveValue('We renew.');
        expect(
            screen.getByText('Describe what the organization does.'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: '1 item needs your changes' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/Returned by Zaira Joy Enayo, SDAO/),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                'Only the items SDAO flagged can be changed. Everything else stays as you sent it.',
            ),
        ).toBeInTheDocument();
    });

    it('keeps Resend off, with the reason on the page, until every flagged item is changed', async () => {
        const user = userEvent.setup({ delay: null });
        renewal(
            makeFix([
                item('organization_details', 'Organization Details'),
                item('contact_information', 'Contact Information'),
            ]),
        );

        const resend = screen.getByRole('button', { name: 'Resend to SDAO' });
        expect(resend).toBeDisabled();
        expect(resend).toHaveAccessibleDescription(
            '0 of 2 fixed. Fix 2 more to resend.',
        );

        await user.clear(screen.getByLabelText('Purpose'));
        await user.type(
            screen.getByLabelText('Purpose'),
            'We run site visits and contests.',
        );
        expect(
            screen.getByText('1 of 2 fixed. Fix 1 more to resend.'),
        ).toBeInTheDocument();
        expect(resend).toBeDisabled();

        // A trimmed-equal edit is not a change.
        await user.type(screen.getByLabelText('Contact person'), '   ');
        expect(resend).toBeDisabled();

        await user.type(screen.getByLabelText('Contact person'), ' Jr.');
        expect(
            screen.getByText('2 of 2 fixed. Ready to resend.'),
        ).toBeInTheDocument();
        expect(resend).toBeEnabled();
    });

    it('turns a chip green once its item is fixed', async () => {
        const user = userEvent.setup({ delay: null });
        renewal(
            makeFix([item('organization_details', 'Organization Details')]),
        );

        const chips = screen.getByRole('list', { name: 'Items to fix' });
        expect(within(chips).queryByText('(fixed)')).not.toBeInTheDocument();

        await user.type(screen.getByLabelText('Purpose'), ' More.');

        expect(within(chips).getByText('(fixed)')).toBeInTheDocument();
        expect(screen.getByText('Changed')).toBeInTheDocument();
    });

    it('needs a real new file for a flagged requirement, and keeps the old file name while it can', async () => {
        const user = userEvent.setup({ delay: null });
        const { container } = renewal(
            makeFix([item('by_laws', 'By-Laws', 'Wrong version.', 'file')]),
        );

        expect(screen.getByText('Needs new file')).toBeInTheDocument();
        expect(screen.getByText('bylaws_v1.pdf')).not.toHaveClass(
            'line-through',
        );
        expect(
            screen.getByRole('button', { name: 'Resend to SDAO' }),
        ).toBeDisabled();

        // The other requirement is a locked row with a View link, and has no file input.
        const locked = screen.getByText('Letter of Intent').closest('li')!;
        expect(within(locked).getByText('letter_v1.pdf')).toBeInTheDocument();
        expect(
            within(locked).getByRole('button', { name: /View/ }),
        ).toBeInTheDocument();
        expect(
            container.querySelector(
                'input[name="attachments[letter_of_intent]"]',
            ),
        ).toBeNull();
        expect(screen.getByText('1 of 2 locked')).toBeInTheDocument();

        await user.upload(
            container.querySelector(
                'input[name="attachments[by_laws]"]',
            ) as HTMLInputElement,
            new File(['x'], 'bylaws_v2.pdf', { type: 'application/pdf' }),
        );

        expect(screen.getByText('New file added')).toBeInTheDocument();
        expect(screen.getByText('bylaws_v2.pdf')).toBeInTheDocument();
        expect(screen.getByText('bylaws_v1.pdf')).toHaveClass('line-through');
        expect(
            screen.getByRole('button', { name: 'Resend to SDAO' }),
        ).toBeEnabled();
    });

    it('refuses a file of the wrong type on the spot', async () => {
        const user = userEvent.setup({ applyAccept: false, delay: null });
        const { container } = renewal(
            makeFix([item('by_laws', 'By-Laws', null, 'file')]),
        );

        await user.upload(
            container.querySelector(
                'input[name="attachments[by_laws]"]',
            ) as HTMLInputElement,
            new File(['x'], 'notes.txt', { type: 'text/plain' }),
        );

        expect(
            screen.getByText(/is not an accepted file type/),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Resend to SDAO' }),
        ).toBeDisabled();
    });

    it('stays a fully editable form, with the return note, when nothing fixable was flagged', () => {
        renewal(
            makeFix([], {
                notes: [
                    { label: 'General', comment: 'Please be more careful.' },
                ],
            }),
        );

        expect(screen.getByLabelText('Purpose')).toBeInTheDocument();
        expect(screen.getByLabelText('Contact person')).toBeInTheDocument();
        expect(screen.getByLabelText('Date organized')).toBeInTheDocument();
        expect(screen.getByText('Please be more careful.')).toBeInTheDocument();
        expect(
            screen.getByText(/the whole form is open for changes/),
        ).toBeInTheDocument();
        expect(screen.queryByText('Locked')).not.toBeInTheDocument();
        // No flags means no progress rule: the button is on.
        expect(
            screen.getByRole('button', { name: 'Resend to SDAO' }),
        ).toBeEnabled();
    });

    it('names the real next step on the button', () => {
        renewal(
            makeFix([item('organization_details', 'Organization Details')], {
                role: 'College Dean',
                resendLabel: 'Resend to College Dean',
            }),
        );

        expect(
            screen.getByRole('button', { name: 'Resend to College Dean' }),
        ).toBeInTheDocument();
    });
});

describe('Fix and Resend: a returned registration', () => {
    it('shows the adviser picker only when the adviser was flagged', () => {
        const registrationDetail = {
            ...detail,
            adviser: { id: 2, name: 'Old Adviser' },
        };
        const { rerender } = render(
            <EditRegistration
                document={{ id: 1, title: 'Reg', organization: org }}
                detail={registrationDetail}
                attachmentSlots={slots}
                attachments={existing}
                fix={makeFix(
                    [item('contact_information', 'Contact Information')],
                    { formLabel: 'Registration' },
                )}
            />,
        );

        // Adviser locked: its name is a value, there is no picker or hidden field.
        expect(
            screen.getByRole('region', { name: 'Adviser' }),
        ).toHaveTextContent('Old Adviser');
        expect(
            screen.queryByLabelText('Faculty adviser'),
        ).not.toBeInTheDocument();
        expect(screen.getByLabelText('Contact person')).toBeInTheDocument();

        rerender(
            <EditRegistration
                document={{ id: 1, title: 'Reg', organization: org }}
                detail={registrationDetail}
                attachmentSlots={slots}
                attachments={existing}
                fix={makeFix(
                    [
                        item(
                            'adviser_selection',
                            'Adviser Selection',
                            'Pick someone else.',
                        ),
                    ],
                    { formLabel: 'Registration' },
                )}
            />,
        );

        expect(screen.getByText('Pick someone else.')).toBeInTheDocument();
        expect(
            screen.queryByLabelText('Contact person'),
        ).not.toBeInTheDocument();
    });
});

describe('Fix and Resend: a returned after-activity report', () => {
    const reportDetail = {
        summary: 'We held it.',
        outcomes: 'Good.',
        participant_count: 20,
        activity_chairs: ['Ana Reyes'],
        prepared_by: 'Ana Reyes',
        event_program: '9am start.',
        target_participants_percentage: 80,
        activity: null,
    };
    const reportSlots = [
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

    it('locks the summary when only the evaluation was flagged, and takes new photos as more files', async () => {
        const user = userEvent.setup({ delay: null });
        const { container } = render(
            <EditReport
                document={{ id: 9, title: 'Report' }}
                detail={reportDetail}
                attachmentSlots={reportSlots}
                attachments={{
                    photos: [
                        {
                            id: 3,
                            original_filename: 'p1.jpg',
                            download_url: '/d',
                            preview_url: '/p',
                            size: 1,
                        },
                    ],
                }}
                fix={makeFix(
                    [
                        item('evaluation', 'Evaluation'),
                        item('photos', 'Photos', null, 'file'),
                    ],
                    { formLabel: 'After-Activity Report' },
                )}
            />,
        );

        expect(screen.queryByLabelText('Summary')).not.toBeInTheDocument();
        expect(screen.getByText('We held it.')).toBeInTheDocument();
        expect(screen.getByLabelText(/Outcomes/)).toHaveValue('Good.');
        expect(screen.getByText('Already on file:')).toBeInTheDocument();

        const photos = container.querySelector(
            'input[name="attachments[photos][]"]',
        ) as HTMLInputElement;
        expect(photos).toHaveAttribute('multiple');
        await user.upload(photos, [
            new File(['x'], 'a.jpg', { type: 'image/jpeg' }),
            new File(['x'], 'b.jpg', { type: 'image/jpeg' }),
        ]);
        expect(screen.getByText('2 files chosen')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Resend to SDAO' }),
        ).toBeDisabled();
    });
});

describe('Fix and Resend: a returned activity calendar', () => {
    const calendar = {
        term_label: '1st Term',
        activities: [1, 2].map((n) => ({
            id: n,
            name: `Activity ${n} name`,
            venue: `Venue ${n}`,
            activity_date: '2027-03-0' + n,
            start_time: '09:00',
            end_time: '11:00',
            description: null,
            sdg: ['sdg_4'],
            participant_program_assigned: 'All',
            budget: '100.00',
        })),
    };

    it('opens only the flagged row, and sends only that row, by its position', async () => {
        const user = userEvent.setup({ delay: null });
        render(
            <EditCalendar
                document={{ id: 5, title: 'Calendar' }}
                calendar={calendar}
                sdgs={[{ value: 'sdg_4', label: '4. Quality Education' }]}
                fix={makeFix(
                    [item('activity_1', 'Activity 2: Activity 2 name')],
                    { formLabel: 'Activity Calendar' },
                )}
            />,
        );

        // Row 1 is locked (no pencil); row 2 is flagged and opens in the form.
        expect(screen.getByText('Venue 1')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Edit Activity 1 name' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /^Delete/ }),
        ).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Venue')).not.toBeInTheDocument();

        await user.click(
            screen.getByRole('button', { name: 'Edit Activity 2 name' }),
        );
        await user.clear(screen.getByLabelText('Venue'));
        await user.type(screen.getByLabelText('Venue'), 'Gymnasium');
        await user.click(screen.getByRole('button', { name: 'Save changes' }));
        await user.click(
            screen.getByRole('button', { name: 'Resend to SDAO' }),
        );
        await user.click(
            await screen
                .findByRole('button', { name: 'Resend to SDAO', hidden: false })
                .then(
                    () =>
                        screen
                            .getAllByRole('button', { name: 'Resend to SDAO' })
                            .at(-1)!,
                ),
        );

        expect(put).toHaveBeenCalledTimes(1);
        const [, payload] = put.mock.calls[0];
        expect(Object.keys(payload.activities)).toEqual(['1']);
        expect(payload.activities[1].venue).toBe('Gymnasium');
    });
});

describe('Fix and Resend: a returned activity proposal', () => {
    const proposal = {
        calendar_mode: 'off_calendar',
        title: 'Coding Night',
        objectives: 'Learn.',
        activity_description: 'Describe.',
        criteria_mechanics: 'Criteria.',
        program_flow: 'Flow.',
        expenses: null,
        expense_items: [
            { material: 'Tarp', quantity: '1', unit_price: '100.00' },
        ],
        responsible_persons: ['Ana'],
        proposed_budget: '5000.00',
        activity_nature: 'co_curricular',
        activity_nature_other: null,
        activity_type: 'seminar_workshop',
        activity_type_other: null,
        partner_organizations: null,
        target_sdg: ['sdg_4'],
        budget_source: 'rso_fund',
    };
    const activity = {
        id: 4,
        name: 'Coding Night',
        venue: 'Hall',
        activity_date: '2027-03-01',
        start_time: '09:00',
        end_time: '11:00',
    };

    function renderProposal(fix: FixProps) {
        return render(
            <EditProposal
                document={{ id: 7, title: 'Coding Night' }}
                proposal={proposal}
                activity={activity}
                activityNatures={[
                    { value: 'co_curricular', label: 'Co-Curricular' },
                ]}
                activityTypes={[
                    { value: 'seminar_workshop', label: 'Seminar/Workshop' },
                ]}
                sdgs={[{ value: 'sdg_4', label: '4. Quality Education' }]}
                budgetSources={[{ value: 'rso_fund', label: 'RSO Fund' }]}
                attachmentSlots={[
                    {
                        key: 'request_letter',
                        label: 'Request Letter',
                        required: true,
                        multiple: false,
                        accept: '.pdf',
                        max_kb: 2048,
                    },
                ]}
                attachments={{
                    request_letter: [
                        {
                            id: 8,
                            original_filename: 'letter.pdf',
                            download_url: '/d',
                            preview_url: '/p',
                            size: 1,
                        },
                    ],
                }}
                fix={fix}
            />,
        );
    }

    it('shows step 1 and the narrative on one page, with only the flagged section open', () => {
        renderProposal(
            makeFix([item('budget', 'Budget', 'Totals do not add up.')], {
                formLabel: 'Activity Proposal',
            }),
        );

        expect(
            screen.getByRole('region', { name: 'Activity request' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('region', { name: 'Proposal narrative' }),
        ).toBeInTheDocument();

        // Budget is open.
        expect(screen.getByText('Totals do not add up.')).toBeInTheDocument();
        expect(screen.getByLabelText('Proposed Budget')).toHaveValue(5000);

        // The calendar choice is a value, never an input; the rest is locked.
        expect(screen.getByText('Off-calendar activity')).toBeInTheDocument();
        expect(screen.queryByLabelText('Objectives')).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Venue')).not.toBeInTheDocument();
        expect(
            screen.queryByLabelText('Title of Activity'),
        ).not.toBeInTheDocument();
        expect(screen.getByText('Learn.')).toBeInTheDocument();
    });

    it('opens the narrative sections that were flagged and nothing else', async () => {
        const user = userEvent.setup({ delay: null });
        renderProposal(
            makeFix(
                [
                    item('objectives', 'Objectives'),
                    item('responsible_persons', 'Responsible Persons'),
                ],
                { formLabel: 'Activity Proposal' },
            ),
        );

        expect(screen.getByLabelText('Objectives')).toHaveValue('Learn.');
        expect(screen.getByLabelText('Responsible person 1')).toHaveValue(
            'Ana',
        );
        expect(
            screen.queryByLabelText('Activity Description'),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByLabelText('Proposed Budget'),
        ).not.toBeInTheDocument();

        await user.type(screen.getByLabelText('Objectives'), ' More.');
        expect(
            screen.getByText('1 of 2 fixed. Fix 1 more to resend.'),
        ).toBeInTheDocument();
    });

    it('is the whole form again when the return flagged nothing fixable', () => {
        renderProposal(makeFix([], { formLabel: 'Activity Proposal' }));

        expect(screen.getByLabelText('Objectives')).toBeInTheDocument();
        expect(screen.getByLabelText('Proposed Budget')).toBeInTheDocument();
        expect(screen.getByLabelText('Venue')).toBeInTheDocument();
    });
});
