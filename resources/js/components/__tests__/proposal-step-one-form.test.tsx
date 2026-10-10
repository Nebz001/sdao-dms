import { cleanup, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CreateActivityProposal from '@/pages/activity-proposals/create';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Form: ({
        children,
        ...rest
    }: {
        children: (state: { processing: boolean; errors: Record<string, string> }) => ReactNode;
        [key: string]: unknown;
    }) => (
        <form data-testid="form" action={rest.action as string}>
            {children({ processing: false, errors: {} })}
        </form>
    ),
}));

const activity = {
    id: 7,
    name: 'Hack Night',
    venue: 'Gym',
    activity_date: '2026-11-20',
    start_time: '13:00',
    end_time: '15:00',
    term_label: '1st Term, 2026-2027',
    locked: false,
};

const lockedActivity = { ...activity, id: 8, name: 'Already Planned', locked: true };

const props = {
    membership: {
        id: 1,
        position: 'president',
        position_label: 'President',
        organization: { id: 1, name: 'CA Org', school: 'Senior High School' },
    },
    current_term_label: '1st Term, 2026-2027',
    calendarModes: [
        { value: 'on_calendar', label: 'On calendar' },
        { value: 'off_calendar', label: 'Off calendar' },
    ],
    activityNatures: [{ value: 'co_curricular', label: 'Co-Curricular' }],
    activityTypes: [{ value: 'seminar_workshop', label: 'Seminar/Workshop' }],
    sdgs: [
        { value: 'no_poverty', label: '1. No Poverty' },
        { value: 'zero_hunger', label: '2. Zero Hunger' },
        { value: 'good_health', label: '3. Good Health' },
    ],
    budgetSources: [{ value: 'rso_fund', label: 'RSO Fund' }],
    attachmentSlots: [
        {
            key: 'request_letter',
            label: 'Request Letter (must include Rationale, Objectives, and Program)',
            required: true,
            multiple: false,
            accept: '.pdf',
            max_kb: 10240,
        },
        {
            key: 'resume_of_resource_person',
            label: 'Resume of Resource Person(s)',
            required: false,
            multiple: false,
            accept: '.pdf',
            max_kb: 10240,
        },
    ],
};

function mockActivities(activities: unknown[]) {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue({ json: () => Promise.resolve({ activities }) }),
    );
}

describe('New Activity Proposal, step 1', () => {
    beforeEach(() => mockActivities([]));
    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('opens with "Yes, on calendar" selected and the whole form visible', async () => {
        mockActivities([activity]);
        render(<CreateActivityProposal {...props} />);

        expect(screen.getByRole('radio', { name: /Yes, on calendar/ })).toBeChecked();
        expect(screen.getByRole('radio', { name: /No, off calendar/ })).not.toBeChecked();

        // No gate: the other sections are on screen straight away.
        expect(screen.getByText('Calendar activity')).toBeInTheDocument();
        expect(screen.getByText('Nature of activity')).toBeInTheDocument();
        expect(screen.getByRole('group', { name: /Target SDGs/ })).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Budget' })).toBeInTheDocument();
        expect(screen.getByRole('heading', { name: 'Attachments' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Continue to Narrative/ })).toBeInTheDocument();
        expect(screen.getByText('Senior High School')).toBeInTheDocument();
        await waitFor(() =>
            expect(screen.getByRole('combobox', { name: 'Calendar activity' })).toBeInTheDocument(),
        );
    });

    it('picking a calendar activity fills calendar_activity_id and shows its card; activities with a proposal are disabled', async () => {
        const user = userEvent.setup();
        mockActivities([activity, lockedActivity]);
        const { container } = render(<CreateActivityProposal {...props} />);

        await user.click(await screen.findByRole('combobox', { name: 'Calendar activity' }));

        const locked = screen.getByText('Already Planned').closest('[cmdk-item]') as HTMLElement;
        expect(locked).toHaveAttribute('aria-disabled', 'true');
        expect(within(locked).getByText('Already proposed')).toBeInTheDocument();

        await user.click(screen.getByText('Hack Night'));

        expect(
            container.querySelector<HTMLInputElement>('input[name="calendar_activity_id"]'),
        ).toHaveValue('7');
        expect(screen.getByText('1:00 PM to 3:00 PM')).toBeInTheDocument();
        expect(
            screen.getByText('The date, time, and venue come from your approved activity calendar'),
        ).toBeInTheDocument();

        // Change reopens the list.
        await user.click(screen.getByRole('button', { name: /Change activity/ }));
        expect(screen.getByPlaceholderText('Search activities')).toBeInTheDocument();
    });

    it('shows the amber message and a link that switches to off calendar when there are no approved activities', async () => {
        const user = userEvent.setup();
        render(<CreateActivityProposal {...props} />);

        expect(await screen.findByText(/No approved calendar activities/)).toHaveTextContent(
            'No approved calendar activities found for CA Org.',
        );

        await user.click(screen.getByRole('button', { name: 'Choose off calendar instead' }));

        expect(screen.getByRole('radio', { name: /No, off calendar/ })).toBeChecked();
        expect(screen.getByLabelText('Title of activity')).toBeInTheDocument();
        expect(screen.getByText('Set by SDAO')).toBeInTheDocument();
    });

    it('keeps what was typed when switching between on and off calendar', async () => {
        const user = userEvent.setup();
        render(<CreateActivityProposal {...props} />);
        await screen.findByText(/No approved calendar activities/);

        await user.click(screen.getByRole('radio', { name: /No, off calendar/ }));
        await user.type(screen.getByLabelText('Title of activity'), 'Leadership Summit');
        await user.type(screen.getByLabelText('Venue'), 'NU Lipa Gym');
        await user.type(screen.getByLabelText('Proposed budget'), '1500');
        await user.click(screen.getByRole('button', { name: /Zero Hunger/ }));

        await user.click(screen.getByRole('radio', { name: /Yes, on calendar/ }));
        await user.click(screen.getByRole('radio', { name: /No, off calendar/ }));

        expect(screen.getByLabelText('Title of activity')).toHaveValue('Leadership Summit');
        expect(screen.getByLabelText('Venue')).toHaveValue('NU Lipa Gym');
        expect(screen.getByLabelText('Proposed budget')).toHaveValue(1500);
        expect(screen.getByRole('button', { name: /Zero Hunger/ })).toHaveAttribute(
            'aria-pressed',
            'true',
        );
    }, 20000);

    it('counts the selected SDGs live and submits each one', async () => {
        const user = userEvent.setup();
        const { container } = render(<CreateActivityProposal {...props} />);
        await screen.findByText(/No approved calendar activities/);

        expect(screen.getByText(/0 selected/)).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: /No Poverty/ }));
        await user.click(screen.getByRole('button', { name: /Good Health/ }));

        expect(screen.getByText(/2 selected/)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /No Poverty/ })).toHaveAttribute(
            'aria-pressed',
            'true',
        );
        expect(screen.getByRole('button', { name: /Zero Hunger/ })).toHaveAttribute(
            'aria-pressed',
            'false',
        );

        const submitted = Array.from(
            container.querySelectorAll<HTMLInputElement>('input[name="target_sdg[]"]'),
        ).map((input) => input.value);
        expect(submitted).toEqual(['no_poverty', 'good_health']);

        await user.click(screen.getByRole('button', { name: /No Poverty/ }));
        expect(screen.getByText(/1 selected/)).toBeInTheDocument();
    });

    it('labels the request letter with its guidance and marks the resume optional', async () => {
        render(<CreateActivityProposal {...props} />);
        await screen.findByText(/No approved calendar activities/);

        const attachments = screen
            .getByRole('heading', { name: 'Attachments' })
            .closest('section')!;
        expect(within(attachments).getByText('Request letter')).toBeInTheDocument();
        expect(
            within(attachments).getByText('Must include the rationale, objectives, and program'),
        ).toBeInTheDocument();
        expect(within(attachments).getByText('optional')).toBeInTheDocument();
    });
});
