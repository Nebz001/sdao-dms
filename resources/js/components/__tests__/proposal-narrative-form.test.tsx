import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ComponentProps, ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import StepTwo from '@/pages/activity-proposals/step-two';

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

describe('Activity Proposal step 2', () => {
    afterEach(cleanup);

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
