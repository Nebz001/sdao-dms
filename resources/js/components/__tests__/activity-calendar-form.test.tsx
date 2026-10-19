import {
    cleanup,
    fireEvent,
    render,
    screen,
    within,
} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CreateActivityCalendar from '@/pages/activity-calendars/create';
import EditActivityCalendar from '@/pages/activity-calendars/edit';

const post = vi.hoisted(() => vi.fn());
const put = vi.hoisted(() => vi.fn());

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
    router: { post, put, get: vi.fn() },
    setLayoutProps: vi.fn(),
    usePage: () => ({
        props: {
            auth: { user: { id: 1 }, roles: [], organization: null },
            currentPeriod: {
                academic_year: '2026-2027',
                term_label: '1st Term',
            },
        },
    }),
}));

const sdgs = [
    { value: 'quality_education', label: '4. Quality Education' },
    { value: 'climate_action', label: '13. Climate Action' },
];

const membership = {
    id: 1,
    position: 'president',
    position_label: 'President',
    organization: { id: 9, name: 'INF231' },
};

function renderCreate() {
    return render(
        <CreateActivityCalendar
            membership={membership}
            current_term_label="1st Term"
            eligibility={{
                status: 'eligible',
                message: null,
                existingDocument: null,
            }}
            sdgs={sdgs}
        />,
    );
}

async function fillForm(
    user: ReturnType<typeof userEvent.setup>,
    name: string,
    date: string,
) {
    const set = (label: string, value: string) =>
        fireEvent.change(screen.getByLabelText(label), { target: { value } });

    set('Activity name', name);
    set('Venue', 'Function Hall');
    set('Date', date);
    set('Start time', '13:00');
    set('End time', '16:00');
    await user.click(
        screen.getByRole('checkbox', { name: 'SDG 4: Quality Education' }),
    );
    set('Participants or program', 'All members');
    set('Budget', '3000');
}

vi.setConfig({ testTimeout: 30000 });

beforeEach(() => {
    post.mockReset();
    put.mockReset();
    window.localStorage.clear();
});

afterEach(cleanup);

describe('Activity calendar form', () => {
    it('starts empty, validates inline on Add to list, and blocks submit with no activities', async () => {
        const user = userEvent.setup({ delay: null });
        renderCreate();

        expect(screen.getByText('No activities yet')).toBeInTheDocument();
        expect(screen.getByText(/Submitting for/)).toBeInTheDocument();
        expect(screen.getByText(/Set by SDAO/)).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Submit for Review' }),
        ).toBeDisabled();

        await user.click(screen.getByRole('button', { name: 'Add to list' }));

        expect(
            screen.getByText('The Activity Name field is required.'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('The SDG field is required.'),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Activity name')).toHaveAttribute(
            'aria-invalid',
            'true',
        );
    });

    it('adds activities sorted by date, then edits one in the form and keeps its identity', async () => {
        const user = userEvent.setup({ delay: null });
        renderCreate();

        await fillForm(user, 'Later Event', '2099-11-08');
        await user.click(screen.getByRole('button', { name: 'Add to list' }));
        await fillForm(user, 'Earlier Event', '2099-09-12');
        await user.click(screen.getByRole('button', { name: 'Add to list' }));

        const list = screen.getByRole('list', {
            name: 'Activities in this calendar',
        });
        const names = within(list)
            .getAllByRole('button', { name: /^Edit / })
            .map((b) => b.getAttribute('aria-label'));
        expect(names).toEqual(['Edit Earlier Event', 'Edit Later Event']);
        expect(screen.getByText('2 activities')).toBeInTheDocument();
        expect(screen.getByText('₱6,000.00 total')).toBeInTheDocument();

        await user.click(
            screen.getByRole('button', { name: 'Edit Later Event' }),
        );
        expect(screen.getByText('Edit activity')).toBeInTheDocument();
        expect(screen.getByText('Editing')).toBeInTheDocument();
        expect(screen.getByLabelText('Activity name')).toHaveValue(
            'Later Event',
        );

        await user.clear(screen.getByLabelText('Activity name'));
        await user.type(screen.getByLabelText('Activity name'), 'Renamed');
        await user.click(screen.getByRole('button', { name: 'Save changes' }));

        expect(
            screen.getByRole('button', { name: 'Edit Renamed' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Add an activity')).toBeInTheDocument();
    });

    it('asks before discarding unsaved changes when another pencil is clicked', async () => {
        const user = userEvent.setup({ delay: null });
        renderCreate();

        await fillForm(user, 'First', '2099-09-12');
        await user.click(screen.getByRole('button', { name: 'Add to list' }));
        await user.type(screen.getByLabelText('Activity name'), 'Half typed');
        await user.click(screen.getByRole('button', { name: 'Edit First' }));

        expect(screen.getByText('Discard your changes?')).toBeInTheDocument();
        await user.click(
            screen.getByRole('button', { name: 'Discard changes' }),
        );

        expect(screen.getByLabelText('Activity name')).toHaveValue('First');
    });

    it('warns at submit when the form has unadded data, and sends rows without client ids', async () => {
        const user = userEvent.setup({ delay: null });
        renderCreate();

        await fillForm(user, 'First', '2099-09-12');
        await user.click(screen.getByRole('button', { name: 'Add to list' }));
        await user.type(screen.getByLabelText('Activity name'), 'Not added');
        await user.click(
            screen.getByRole('button', { name: 'Submit for Review' }),
        );

        expect(
            screen.getByText('The form isn’t added to the list yet.'),
        ).toBeInTheDocument();

        const dialog = screen.getByRole('dialog');
        await user.click(
            within(dialog).getByRole('button', { name: 'Submit for Review' }),
        );

        expect(post).toHaveBeenCalledTimes(1);
        const [url, payload] = post.mock.calls[0];
        expect(url).toBe('/activity-calendars');
        expect(payload.activities).toHaveLength(1);
        expect(Object.keys(payload.activities[0]).sort()).toEqual(
            [
                'activity_date',
                'budget',
                'description',
                'end_time',
                'name',
                'participant_program_assigned',
                'sdg',
                'start_time',
                'venue',
            ].sort(),
        );
    });

    it('loads H:i:s times as H:i when editing a returned calendar', async () => {
        const user = userEvent.setup({ delay: null });
        render(
            <EditActivityCalendar
                document={{ id: 5, title: 'Calendar' }}
                calendar={{
                    term_label: '1st Term',
                    activities: [
                        {
                            id: 1,
                            name: 'Bridge Challenge',
                            venue: 'Room 402',
                            activity_date: '2099-10-15',
                            start_time: '08:00:00',
                            end_time: '17:00:00',
                            description: null,
                            sdg: ['quality_education'],
                            participant_program_assigned: 'BSCE',
                            budget: '12500.00',
                        },
                    ],
                }}
                sdgs={sdgs}
                fix={{
                    active: false,
                    formLabel: 'Activity Calendar',
                    organization: 'INF231',
                    returnedAt: null,
                    returnedBy: null,
                    role: 'SDAO',
                    comment: null,
                    items: [],
                    notes: [],
                    resendLabel: 'Resend to SDAO',
                    back: { href: '/x', title: 'Back' },
                    draft: null,
                    draftUrl: '/draft',
                }}
            />,
        );

        await user.click(
            screen.getByRole('button', { name: 'Edit Bridge Challenge' }),
        );
        expect(screen.getByLabelText('Start time')).toHaveValue('08:00');
        expect(screen.getByLabelText('End time')).toHaveValue('17:00');
    });
});
