import { cleanup, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ComponentProps, ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { TrackCard, nextOpenStep } from '@/components/track-documents';
import { hereLabel, movingLocations, stepCaption } from '@/lib/track';
import type { TrackRow, TrackStep } from '@/lib/track';
import TrackDocuments from '@/pages/track';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href, ...rest }: { children: ReactNode; href: unknown; [key: string]: unknown }) => (
        <a href={typeof href === 'string' ? href : (href as { url: string }).url} {...rest}>
            {children}
        </a>
    ),
    router: { get: vi.fn() },
    usePage: () => ({
        props: { auth: { user: { id: 1, name: 'Nathaniel', account_status: 'verified' }, roles: [], canProposeOrganization: false } },
    }),
}));

type PopoverData = NonNullable<TrackStep['popover']>;

const popover = (overrides: Partial<PopoverData> = {}): PopoverData => ({
    status: 'Waiting',
    roleText: 'College Dean, SACE',
    receivedAt: null,
    decision: null,
    waitingDays: null,
    people: [],
    ...overrides,
});

const step = (overrides: Partial<TrackStep> & Pick<TrackStep, 'key' | 'label' | 'state'>): TrackStep => ({
    date: null,
    hereDays: null,
    isNext: false,
    muted: false,
    popover: null,
    ...overrides,
});

const ramon = { name: 'Dr. Ramon Santos', role: 'College Dean, SACE', email: null, approved: false };
const ana = { name: 'Ana Reyes', role: 'Adviser, CODECS', email: 'ana.reyes@nu-lipa.edu.ph', approved: false };

const adviserDone = step({
    key: 'step-1',
    label: 'Adviser',
    state: 'done',
    date: '2026-10-09T02:00:00Z',
    popover: popover({
        status: 'Approved',
        roleText: 'Adviser, CODECS',
        receivedAt: '2026-10-08T02:00:00Z',
        decision: { label: 'Approved', at: '2026-10-09T02:00:00Z' },
        people: [ana],
    }),
});

function row(overrides: Partial<TrackRow> = {}): TrackRow {
    return {
        id: 1,
        formType: 'activity_proposal',
        title: 'Bridge Design Challenge',
        showFormType: true,
        period: '1st Term, 2026-2027',
        formTypeLabel: 'Activity Proposal',
        submittedAt: '2026-10-08T02:00:00Z',
        status: 'in_review',
        action: { label: 'View', href: '/activity-proposals/1', kind: 'view' },
        note: null,
        steps: [
            step({ key: 'submitted', label: 'Submitted', state: 'done', date: '2026-10-08T02:00:00Z' }),
            adviserDone,
            step({
                key: 'step-2',
                label: 'College Dean',
                state: 'current',
                hereDays: 1,
                popover: popover({
                    status: 'Reviewing now',
                    receivedAt: '2026-10-09T06:15:00Z', // 2:15 PM in Manila
                    waitingDays: 1,
                    people: [ramon],
                }),
            }),
            step({ key: 'step-3', label: 'SDAO', state: 'upcoming', isNext: true, popover: popover({ roleText: 'SDAO' }) }),
            step({ key: 'approved', label: 'Approved', state: 'upcoming' }),
        ],
        ...overrides,
    };
}

function renderCard(r: TrackRow) {
    return render(<TrackCard row={r} />);
}

/** The connector segments of a row, one per gap between steps, in order. */
function segments(container: HTMLElement): string[] {
    return Array.from(container.querySelectorAll('ol > li')).flatMap((li) => {
        const segment = li.querySelector('[data-segment]');

        return segment ? [segment.getAttribute('data-segment') as string] : [];
    });
}

describe('TrackCard', () => {
    afterEach(cleanup);

    it('names the document by its activity, with the form type and period as chips and no org or dash', () => {
        renderCard(row());

        expect(screen.getByRole('heading', { name: 'Bridge Design Challenge' })).toBeInTheDocument();
        expect(screen.getByText('Activity Proposal')).toBeInTheDocument();
        expect(screen.getByText('1st Term, 2026-2027')).toBeInTheDocument();
        expect(screen.getByText(/Submitted Oct 8, 2026/)).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /View: Bridge Design Challenge/ })).toHaveAttribute('href', '/activity-proposals/1');
    });

    it('drops the form type chip for a form with no name of its own', () => {
        renderCard(row({ title: 'Activity Calendar', showFormType: false, formType: 'activity_calendar' }));

        expect(screen.queryByText('Activity Proposal')).not.toBeInTheDocument();
    });

    it('draws every step with its state and caption: done date, here now, next and waiting', () => {
        renderCard(row());

        const steps = screen.getAllByRole('listitem');
        expect(steps).toHaveLength(5);
        expect(steps.map((li) => li.getAttribute('data-state'))).toEqual(['done', 'done', 'current', 'upcoming', 'upcoming']);
        expect(within(steps[0]).getByText('Oct 8')).toBeInTheDocument();
        expect(within(steps[1]).getByText('Approved Oct 9')).toBeInTheDocument();
        expect(within(steps[2]).getByText('Here now · 1 day')).toBeInTheDocument();
        expect(within(steps[3]).getByText('Next')).toBeInTheDocument();
        expect(within(steps[4]).getByText('Waiting')).toBeInTheDocument();
    });

    it('shows a returned step in amber with the approver\'s note and a Fix and resend action', () => {
        renderCard(
            row({
                status: 'returned',
                action: { label: 'Fix and resend', href: '/activity-proposals/1/edit', kind: 'fix' },
                note: { role: 'Dean', comment: 'Please attach the signed attendance sheet.', remarks: [] },
                steps: [
                    step({ key: 'submitted', label: 'Submitted', state: 'done', date: '2026-10-06T02:00:00Z' }),
                    step({ key: 'step-1', label: 'College Dean', state: 'returned', date: '2026-10-09T02:00:00Z' }),
                    step({ key: 'step-2', label: 'SDAO', state: 'upcoming' }),
                    step({ key: 'approved', label: 'Approved', state: 'upcoming' }),
                ],
            }),
        );

        expect(screen.getByText('Returned Oct 9')).toBeInTheDocument();
        expect(screen.getByText('Dean’s note:')).toBeInTheDocument();
        expect(screen.getByText(/Please attach the signed attendance sheet/)).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Fix and resend/ })).toHaveAttribute('href', '/activity-proposals/1/edit');
        expect(screen.getAllByText('Waiting')).toHaveLength(2);
    });

    it('shows a draft with no stepper and its own action', () => {
        renderCard(
            row({
                status: 'draft',
                steps: null,
                action: { label: 'Continue draft', href: '/activity-proposals/1/continue', kind: 'continue' },
            }),
        );

        expect(screen.queryByRole('list', { name: /Approval steps/ })).not.toBeInTheDocument();
        expect(screen.getByText(/Not sent yet/)).toBeInTheDocument();
        expect(screen.getByText(/Started Oct 8, 2026/)).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Continue draft/ })).toBeInTheDocument();
    });
});

describe('the connector between steps', () => {
    afterEach(cleanup);

    const chain = (states: Array<TrackStep['state']>) =>
        row({
            steps: states.map((state, i) =>
                step({ key: `s${i}`, label: i === 0 ? 'Submitted' : i === states.length - 1 ? 'Approved' : `Step ${i}`, state }),
            ),
        });

    it('is green from Submitted into the current step, and gray after it', () => {
        const { container } = renderCard(chain(['done', 'done', 'current', 'upcoming', 'upcoming']));

        expect(segments(container)).toEqual(['done', 'done', 'track', 'track']);
    });

    it('is green into a returned step and gray after it', () => {
        const { container } = renderCard(chain(['done', 'done', 'returned', 'upcoming']));

        expect(segments(container)).toEqual(['done', 'done', 'track']);
    });

    it('is green into a rejected step and gray after it', () => {
        const { container } = renderCard(chain(['done', 'done', 'rejected', 'upcoming']));

        expect(segments(container)).toEqual(['done', 'done', 'track']);
    });

    it('is green all the way along a finished chain', () => {
        const { container } = renderCard(chain(['done', 'done', 'done', 'done']));

        expect(segments(container)).toEqual(['done', 'done', 'done']);
    });

    it('has one segment fewer than steps, for a short chain and a long one', () => {
        const short = renderCard(chain(['done', 'done', 'done']));
        expect(segments(short.container)).toHaveLength(2);
        short.unmount();

        const long = renderCard(chain(Array.from({ length: 9 }, () => 'done' as const)));
        expect(segments(long.container)).toHaveLength(8);
    });

    it('starts at the circle\'s edge and ends at the next circle\'s edge, never behind a circle', () => {
        const { container } = renderCard(chain(['done', 'current', 'upcoming']));
        const horizontal = container.querySelector('li [data-segment].sm\\:block') as HTMLElement;

        // From half a column plus the circle's radius, across one column less a diameter.
        expect(horizontal.className).toContain('left-[calc(50%+1rem)]');
        expect(horizontal.className).toContain('w-[calc(100%-2rem)]');
    });

    it('gives every circle a solid fill: a tint mixed into the card color, never a see-through one', () => {
        const { container } = renderCard(chain(['done', 'returned', 'rejected', 'upcoming']));
        const circle = (state: string) => (container.querySelector(`[data-circle="${state}"]`) as HTMLElement).className;

        expect(circle('done')).toContain('bg-success');
        expect(circle('returned')).toContain('var(--card)');
        expect(circle('rejected')).toContain('var(--card)');
        expect(circle('upcoming')).toContain('bg-muted');

        for (const state of ['done', 'returned', 'rejected', 'upcoming']) {
            expect(circle(state)).not.toMatch(/ring-|bg-[a-z-]+\/\d/);
        }
    });

    it('gives the current, returned and rejected circles a halo, and the done and upcoming ones none', () => {
        const { container } = renderCard(chain(['done', 'current', 'returned', 'rejected', 'upcoming']));
        const circle = (state: string) => (container.querySelector(`[data-circle="${state}"]`) as HTMLElement).className;

        for (const state of ['current', 'returned', 'rejected']) {
            expect(circle(state)).toContain('shadow-[0_0_0_4px_color-mix');
        }

        expect(circle('done')).not.toContain('shadow-');
        expect(circle('upcoming')).not.toContain('shadow-');
    });
});

describe('the step detail card', () => {
    afterEach(cleanup);

    const open = async (name: RegExp) => {
        const user = userEvent.setup();
        await user.click(screen.getByRole('button', { name }));

        return { user, card: document.querySelector('[data-slot="popover-content"]') as HTMLElement };
    };

    it('opens on a click, with the person, status, when it arrived (Manila time), how long it has waited, and no email', async () => {
        renderCard(row());
        const { card } = await open(/College Dean: Reviewing now/);

        expect(within(card).getByText('Dr. Ramon Santos')).toBeInTheDocument();
        expect(within(card).getByText('College Dean, SACE')).toBeInTheDocument();
        expect(within(card).getByText('Reviewing now')).toBeInTheDocument();
        expect(within(card).getByText('Received')).toBeInTheDocument();
        expect(within(card).getByText('Oct 9, 2026, 2:15 PM')).toBeInTheDocument();
        expect(within(card).getByText('Waiting for')).toBeInTheDocument();
        expect(within(card).getByText('1 day')).toBeInTheDocument();
        expect(within(card).queryByText('Email')).not.toBeInTheDocument();
    });

    it('shows the decision row with the full date and time for an approved step, and the adviser\'s email', async () => {
        renderCard(row());
        const { card } = await open(/Adviser: Approved/);

        expect(within(card).getByText('Approved', { selector: 'dt' })).toBeInTheDocument();
        expect(within(card).getByText('Oct 9, 2026, 10:00 AM')).toBeInTheDocument();
        expect(within(card).getByText('Received')).toBeInTheDocument();
        expect(within(card).getByText('ana.reyes@nu-lipa.edu.ph')).toBeInTheDocument();
        expect(within(card).queryByText('Waiting for')).not.toBeInTheDocument();
    });

    it('shows Returned and Rejected decision rows with their date and time', async () => {
        const stopped = (state: 'returned' | 'rejected', label: 'Returned' | 'Rejected') =>
            row({
                steps: [
                    step({ key: 'submitted', label: 'Submitted', state: 'done' }),
                    step({
                        key: 'step-1',
                        label: 'College Dean',
                        state,
                        date: '2026-10-09T02:00:00Z',
                        popover: popover({
                            status: label,
                            receivedAt: '2026-10-08T02:00:00Z',
                            decision: { label, at: '2026-10-09T02:00:00Z' },
                            people: [ramon],
                        }),
                    }),
                    step({ key: 'approved', label: 'Approved', state: 'upcoming' }),
                ],
            });

        for (const [state, label] of [
            ['returned', 'Returned'],
            ['rejected', 'Rejected'],
        ] as const) {
            const { unmount } = renderCard(stopped(state, label));
            const { card } = await open(new RegExp(`College Dean: ${label}`));

            expect(within(card).getByText(label, { selector: 'dt' })).toBeInTheDocument();
            expect(within(card).getByText('Oct 9, 2026, 10:00 AM')).toBeInTheDocument();
            unmount();
        }
    });

    it('shows a future step as just its role and Waiting, with no dates', async () => {
        renderCard(row());
        const { card } = await open(/SDAO: Waiting/);

        expect(within(card).getByText('SDAO')).toBeInTheDocument();
        expect(within(card).getByText('Waiting', { selector: 'dd' })).toBeInTheDocument();
        expect(within(card).queryByText('Received')).not.toBeInTheDocument();
        expect(within(card).queryByText('Approved', { selector: 'dt' })).not.toBeInTheDocument();
        expect(within(card).queryByText('Waiting for')).not.toBeInTheDocument();
    });

    it('opens on hover with an arrow, stays open while the pointer crosses into the card, and closes once it leaves both', async () => {
        const user = userEvent.setup();
        renderCard(row());
        const step = screen.getByRole('button', { name: /College Dean: Reviewing now/ });

        await user.hover(step);
        const card = (await screen.findByText('Dr. Ramon Santos')).closest('[data-slot="popover-content"]') as HTMLElement;
        expect(card.querySelector('[data-slot="popover-arrow"]')).not.toBeNull();

        await user.unhover(step);
        await user.hover(card);
        await new Promise((resolve) => setTimeout(resolve, 300));
        expect(screen.getByText('Dr. Ramon Santos')).toBeInTheDocument();

        await user.unhover(card);
        await waitFor(() => expect(screen.queryByText('Dr. Ramon Santos')).not.toBeInTheDocument());
    });

    it('opens on keyboard focus and closes on Escape', async () => {
        const user = userEvent.setup();
        renderCard(row());

        await user.tab(); // the View link
        await user.tab(); // the Adviser step (Submitted has no card)

        expect(screen.getByRole('button', { name: /Adviser: Approved/ })).toHaveFocus();
        expect(await screen.findByText('Ana Reyes')).toBeInTheDocument();

        await user.keyboard('{Escape}');
        expect(screen.queryByText('Ana Reyes')).not.toBeInTheDocument();
    });

    it('opens on a tap and closes on a second tap', async () => {
        const user = userEvent.setup();
        renderCard(row());
        const step = screen.getByRole('button', { name: /College Dean: Reviewing now/ });

        await user.pointer({ keys: '[TouchA]', target: step });
        expect(await screen.findByText('Dr. Ramon Santos')).toBeInTheDocument();

        await user.pointer({ keys: '[TouchA]', target: step });
        expect(screen.queryByText('Dr. Ramon Santos')).not.toBeInTheDocument();
    });

    it('closes on Escape, a click outside, or opening another step', async () => {
        renderCard(row());
        const { user } = await open(/College Dean: Reviewing now/);

        await user.keyboard('{Escape}');
        expect(screen.queryByText('Dr. Ramon Santos')).not.toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: /College Dean: Reviewing now/ }));
        expect(screen.getByText('Dr. Ramon Santos')).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: /Adviser: Approved/ }));
        expect(screen.queryByText('Dr. Ramon Santos')).not.toBeInTheDocument();
        expect(screen.getByText('Ana Reyes')).toBeInTheDocument();

        await user.click(document.body);
        expect(screen.queryByText('Ana Reyes')).not.toBeInTheDocument();
    });

    it('opens from the keyboard with Enter', async () => {
        const user = userEvent.setup();
        renderCard(row());

        await user.tab(); // the View link
        await user.tab(); // the Adviser step: focus opens its card
        await user.keyboard('{Escape}');
        expect(screen.queryByText('Ana Reyes')).not.toBeInTheDocument();
        await user.keyboard('{Enter}');

        expect(await screen.findByText('Ana Reyes')).toBeInTheDocument();
    });

    it('says when nobody is assigned to the current step', async () => {
        renderCard(
            row({
                steps: [
                    step({ key: 'submitted', label: 'Submitted', state: 'done' }),
                    step({
                        key: 'step-1',
                        label: 'Adviser',
                        state: 'current',
                        popover: popover({ status: 'Reviewing now', roleText: 'Adviser, CODECS' }),
                    }),
                    step({ key: 'approved', label: 'Approved', state: 'upcoming' }),
                ],
            }),
        );
        await open(/Adviser: Reviewing now/);

        expect(screen.getByText('No one is assigned to this step yet.')).toBeInTheDocument();
    });
});

describe('Track Documents page', () => {
    afterEach(cleanup);

    const base: ComponentProps<typeof TrackDocuments> = {
        organization: { id: 1, name: 'PICE' },
        period: '2026-2027',
        search: '',
        track: {
            rows: [row()],
            counts: { all: 3, action: 1, moving: 1, approved: 1, stopped: 0 },
            tab: 'all',
            meta: { current_page: 1, last_page: 1, from: 1, to: 1, total: 3 },
            links: { prev: null, next: null },
        },
    };

    const page = (props: ComponentProps<typeof TrackDocuments>) => <TrackDocuments {...props} />;

    it('keeps an open detail card open when a background refresh hands the page brand-new data', async () => {
        const user = userEvent.setup();
        const { rerender } = render(page(base));

        await user.click(screen.getByRole('button', { name: /College Dean: Reviewing now/ }));
        expect(screen.getByText('Dr. Ramon Santos')).toBeInTheDocument();

        // A poll lands: every row and step is a new object, as after a real reload.
        const refreshed = { ...base, track: { ...base.track, rows: [row({ title: 'Bridge Design Challenge' })] } };
        rerender(page(refreshed));
        rerender(page({ ...refreshed, track: { ...refreshed.track, rows: [row()] } }));

        expect(screen.getByText('Dr. Ramon Santos')).toBeInTheDocument();
    });

    it('shows the tabs with their counts, marks the tab from the URL as selected, and hides Stopped when empty', () => {
        render(page({ ...base, track: { ...base.track, tab: 'moving' } }));

        expect(screen.getByRole('radio', { name: 'Needs your action, 1' })).toBeInTheDocument();
        expect(screen.getByRole('radio', { name: 'Moving, 1' })).toBeChecked();
        expect(screen.queryByRole('radio', { name: /Stopped/ })).not.toBeInTheDocument();
        expect(screen.getByText('Showing 2026-2027 and anything still open.')).toBeInTheDocument();
    });

    it('adds a Stopped tab only when something was stopped', () => {
        render(page({ ...base, track: { ...base.track, counts: { ...base.track.counts, stopped: 2 } } }));

        expect(screen.getByRole('radio', { name: 'Stopped, 2' })).toBeInTheDocument();
    });

    it('has a clear empty state for the tab', () => {
        render(
            page({
                ...base,
                track: { ...base.track, rows: [], tab: 'action', counts: { all: 0, action: 0, moving: 0, approved: 0, stopped: 0 } },
            }),
        );

        expect(screen.getByText('Nothing needs your action')).toBeInTheDocument();
    });

    it('is a blocked state for someone with no organization', () => {
        render(page({ ...base, organization: null }));

        expect(screen.getByRole('heading', { name: 'You’re not an officer yet' })).toBeInTheDocument();
    });
});

describe('track wording helpers', () => {
    it('words where the moving documents are, with singular and plural right', () => {
        expect(
            movingLocations([
                { label: 'College Dean', count: 1 },
                { label: 'SDAO', count: 2 },
            ]),
        ).toBe('1 is with the College Dean, 2 are with SDAO');
        expect(hereLabel(0)).toBe('Here now · today');
        expect(hereLabel(1)).toBe('Here now · 1 day');
        expect(hereLabel(3)).toBe('Here now · 3 days');
    });

    it('writes dates in Asia/Manila, not the viewer\'s zone', () => {
        // 11:30 PM UTC on Oct 8 is already Oct 9 in Manila.
        expect(stepCaption(step({ key: 'step-1', label: 'Adviser', state: 'done', date: '2026-10-08T23:30:00Z' }))).toBe('Approved Oct 9');
    });
});

describe('which card is open', () => {
    it('ignores a late close of a card that is no longer the open one', () => {
        // Clicking another step can deliver its "open" before the old card's "close".
        let open = nextOpenStep(null, '1:b', true);
        open = nextOpenStep(open, '1:a', true);
        open = nextOpenStep(open, '1:b', false);

        expect(open).toBe('1:a');
        expect(nextOpenStep(open, '1:a', false)).toBeNull();
    });
});
