import { cleanup, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ComponentProps, ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { notify } from '@/lib/toast';
import StepTwo from '@/pages/activity-proposals/step-two';

vi.mock('@/lib/toast', () => ({ notify: { success: vi.fn(), error: vi.fn() } }));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Form: ({
        children,
        ...rest
    }: {
        children: (state: { processing: boolean; errors: Record<string, string> }) => ReactNode;
        [key: string]: unknown;
    }) => <form id={rest.id as string}>{children({ processing: false, errors: {} })}</form>,
    usePage: () => ({
        props: {
            auth: {
                user: { id: 1, name: 'Carl Andrew' },
                organization: { id: 1, name: 'CA Org', logoUrl: null, school: { id: 1, name: 'Senior High School' } },
            },
        },
    }),
}));

const activity = {
    name: 'Leadership Summit',
    venue: 'NU Lipa Gymnasium',
    activity_date: '2026-10-17',
    start_time: '16:59',
    end_time: '18:00',
};

const proposal = {
    calendar_mode: 'off_calendar',
    title: 'Leadership Summit',
    objectives: null,
    specific_objectives: null,
    activity_description: null,
    criteria_mechanics: null,
    program_flow: null,
    expenses: null,
    expense_items: [
        { material: 'Tarpaulin', quantity: '2', unit_price: '500' },
        { material: 'Snacks', quantity: '50', unit_price: '80' },
    ],
    responsible_persons: null,
    proposed_budget: '90000.00',
    budget_source_label: 'RSO Savings',
    activity_nature_label: 'Non-curricular',
    activity_type_label: 'General Assembly',
    partner_organizations: [{ organization_id: null, name: 'Red Cross Youth' }],
    target_sdg_labels: ['No Poverty', 'Good Health'],
};

function renderStep(overrides: Record<string, unknown> = {}) {
    const merged = { ...proposal, ...overrides } as ComponentProps<typeof StepTwo>['proposal'];

    return render(<StepTwo document={{ id: 5, title: 'Proposal' }} proposal={merged} activity={activity} />);
}

/** What a failed autosave really looks like: a followed redirect to an ordinary page, status 200. */
function redirectedPage(): Response {
    const response = new Response('<html>Redirecting</html>', { status: 200, headers: { 'Content-Type': 'text/html' } });
    Object.defineProperty(response, 'redirected', { value: true });

    return response;
}

function savedReply(): Response {
    return new Response(JSON.stringify({ saved: true }), { status: 200, headers: { 'Content-Type': 'application/json' } });
}

describe('Activity Proposal step 2', () => {
    afterEach(() => {
        cleanup();
        vi.useRealTimers();
        vi.unstubAllGlobals();
        vi.clearAllMocks();
    });

    it('counts the Specific Objectives text against the 2,000 cap and stops there', async () => {
        const user = userEvent.setup();
        renderStep({ specific_objectives: 'First\nSecond' });

        const box = screen.getByLabelText(/Specific Objectives/);
        expect(screen.getByText('12 / 2,000')).toBeInTheDocument();

        await user.click(box);
        await user.paste('x'.repeat(3000));

        expect((box as HTMLTextAreaElement).value).toHaveLength(2000);
        expect(screen.getByText('2,000 / 2,000 · 0 left')).toHaveAttribute('data-tone', 'full');
    });

    it('caps the short text inputs at the server limit of 255', () => {
        renderStep();

        expect(screen.getByLabelText('Responsible person 1')).toHaveAttribute('maxlength', '255');
        expect(screen.getByLabelText('Item 1')).toHaveAttribute('maxlength', '255');
    });

    it('never reports a lost autosave as saved: the footer turns red and one toast says so per run of failures', async () => {
        // Real timers: the autosave debounce is 1.5 s. Earlier tests in this file
        // type into fields, and their pending autosaves (which rightly outlive
        // an unmounted page) fire into this one, so let them finish first.
        await new Promise((resolve) => setTimeout(resolve, 1700));
        vi.clearAllMocks();
        const wait = { timeout: 4000 };
        const fetchMock = vi.fn().mockImplementation(() => Promise.resolve(redirectedPage()));
        vi.stubGlobal('fetch', fetchMock);
        const user = userEvent.setup();
        renderStep();
        const objectives = screen.getByLabelText('Objectives');

        await user.type(objectives, 'a');
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1), wait);

        expect(await screen.findByText(/Not saved\./)).toHaveClass('text-destructive');
        expect(screen.queryByText(/Saved as draft/)).not.toBeInTheDocument();
        expect(notify.error).toHaveBeenCalledTimes(1);
        expect(notify.error).toHaveBeenCalledWith(expect.objectContaining({ title: 'Draft not saved' }));

        // A second failure in the same run does not toast again.
        await user.type(objectives, 'b');
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2), wait);
        expect(notify.error).toHaveBeenCalledTimes(1);

        // It recovers, and a later run of failures toasts again.
        fetchMock.mockImplementation(() => Promise.resolve(savedReply()));
        await user.type(objectives, 'c');
        expect(await screen.findByText(/Saved as draft/, undefined, wait)).toBeInTheDocument();

        fetchMock.mockImplementation(() => Promise.resolve(redirectedPage()));
        await user.type(objectives, 'd');
        await waitFor(() => expect(notify.error).toHaveBeenCalledTimes(2), wait);
    }, 20000);

    it('Save draft says it failed when the server answers with a redirect, and that it saved when it really did', async () => {
        const user = userEvent.setup();
        const fetchMock = vi.fn().mockImplementation(() => Promise.resolve(redirectedPage()));
        vi.stubGlobal('fetch', fetchMock);
        renderStep();

        await user.click(screen.getByRole('button', { name: 'Save draft' }));
        expect(await screen.findByText(/Not saved\./)).toBeInTheDocument();
        expect(notify.success).not.toHaveBeenCalled();

        fetchMock.mockImplementation(() => Promise.resolve(savedReply()));
        await user.click(screen.getByRole('button', { name: 'Save draft' }));
        expect(await screen.findByText(/Saved as draft/)).toBeInTheDocument();
        expect(notify.success).toHaveBeenCalledWith(expect.objectContaining({ title: 'Draft saved' }));
    });

    it('puts an optional Specific Objectives textarea directly under Objectives, hinting one objective per line', () => {
        renderStep({ specific_objectives: 'First\nSecond' });

        const objectives = screen.getByLabelText('Objectives');
        const specific = screen.getByLabelText(/Specific Objectives/);

        expect(objectives).toHaveAttribute('placeholder', 'Describe the overall goal of the activity.\nList specific measurable objectives.');
        expect(specific.tagName).toBe('TEXTAREA');
        expect(specific).toHaveAttribute('name', 'specific_objectives');
        expect(specific).toHaveAttribute('placeholder', 'Type one objective per line, press Enter to add the next one');
        expect(specific).toHaveValue('First\nSecond');
        expect(specific).not.toBeRequired();
        expect(specific).toHaveAttribute('maxlength', '2000');
        expect(objectives.compareDocumentPosition(specific) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
        // A plain textarea, not a repeatable row widget.
        expect(screen.queryByRole('button', { name: /add objective/i })).not.toBeInTheDocument();
    });

    it('shows the step 1 values in the activity summary, in Manila 12 hour time', () => {
        renderStep();

        const section = screen.getByRole('heading', { name: 'Your activity' }).closest('section')!;
        expect(within(section).getByText('Leadership Summit')).toBeInTheDocument();
        expect(within(section).getByText('NU Lipa Gymnasium')).toBeInTheDocument();
        expect(within(section).getByText('Oct 17, 2026')).toBeInTheDocument();
        expect(within(section).getByText('4:59 PM to 6:00 PM')).toBeInTheDocument();
        expect(within(section).getByText('Non-curricular')).toBeInTheDocument();
        expect(within(section).getByText('General Assembly')).toBeInTheDocument();
        expect(within(section).getByText('Red Cross Youth')).toBeInTheDocument();
        expect(within(section).getByText('No Poverty, Good Health')).toBeInTheDocument();
        expect(within(section).getByText('₱90,000.00')).toBeInTheDocument();
        expect(within(section).getByText('RSO Savings')).toBeInTheDocument();
    });

    it('writes "None" for a step 1 value that is empty, never a blank', () => {
        renderStep({ partner_organizations: [], target_sdg_labels: [], budget_source_label: null });

        const section = screen.getByRole('heading', { name: 'Your activity' }).closest('section')!;
        expect(within(section).getAllByText('None')).toHaveLength(3);
    });

    it('shows step 1 as done with a green check, step 2 as the current step, and the school in the strip', () => {
        renderStep();

        expect(screen.getByRole('listitem', { current: 'step' })).toHaveTextContent('Narrative');
        expect(screen.getByText('Senior High School')).toBeInTheDocument();
        expect(screen.getByText(/Filing for/)).toHaveTextContent('Filing for CA Org');
    });

    it('computes expense row totals and the grand total live, in pesos', async () => {
        const user = userEvent.setup();
        renderStep();

        expect(screen.getByText('₱1,000.00')).toBeInTheDocument();
        expect(screen.getByText('₱4,000.00')).toBeInTheDocument();
        expect(screen.getByText('₱5,000.00')).toBeInTheDocument();

        await user.clear(screen.getByLabelText('Quantity for item 1'));
        await user.type(screen.getByLabelText('Quantity for item 1'), '4');

        expect(screen.getByText('₱2,000.00')).toBeInTheDocument();
        expect(screen.getByText('₱6,000.00')).toBeInTheDocument();
    });

    it('says "Within budget" while the total fits, and "Over budget by" once it does not, without blocking anything', async () => {
        const user = userEvent.setup();
        renderStep({ proposed_budget: '5500.00' });

        expect(screen.getByText('Within budget.')).toBeInTheDocument();

        await user.clear(screen.getByLabelText('Quantity for item 2'));
        await user.type(screen.getByLabelText('Quantity for item 2'), '60');

        // 2 x 500 + 60 x 80 = 5,800, which is ₱300 over ₱5,500.
        expect(screen.getByText('Over budget by ₱300.00.')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Submit for Review/ })).toBeEnabled();
    });

    it('adds and removes expense rows and responsible persons', async () => {
        const user = userEvent.setup();
        renderStep();

        await user.click(screen.getByRole('button', { name: 'Add item' }));
        expect(screen.getByLabelText('Item 3')).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Remove item 3' }));
        expect(screen.queryByLabelText('Item 3')).not.toBeInTheDocument();

        expect(screen.getByRole('button', { name: 'Remove responsible person 1' })).toBeDisabled();
        await user.click(screen.getByRole('button', { name: 'Add another person' }));
        expect(screen.getByLabelText('Responsible person 2')).toBeInTheDocument();
    });

    it('keeps the narrative field names the server expects', () => {
        const { container } = renderStep();

        for (const name of [
            'objectives',
            'activity_description',
            'criteria_mechanics',
            'program_flow',
            'expense_items[0][material]',
            'expense_items[1][unit_price]',
            'responsible_persons[0]',
        ]) {
            expect(container.querySelector(`[name="${name}"]`)).not.toBeNull();
        }
    });
});
