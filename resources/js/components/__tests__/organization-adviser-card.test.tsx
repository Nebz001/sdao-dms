import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import OrganizationAdviserCard from '@/components/organization-adviser-card';
import type { AdviserData } from '@/components/organization-adviser-card';

vi.mock('@inertiajs/react', () => ({
    Link: ({ children, href }: { children: ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    Form: ({
        children,
    }: {
        children: (state: {
            processing: boolean;
            errors: Record<string, string>;
        }) => ReactNode;
    }) => <form>{children({ processing: false, errors: {} })}</form>,
}));

const ORGANIZATION = { id: 7, name: 'Debate Society' };

const CURRENT = {
    id: 3,
    name: 'Adviser One',
    email: 'one@nu-lipa.edu.ph',
    since: '2026-01-05T00:00:00Z',
    assigned_by: 'Carl Justin Magpantay',
};

function renderCard(data: Partial<AdviserData>) {
    render(
        <OrganizationAdviserCard
            organization={ORGANIZATION}
            data={{ current: CURRENT, history: [], pool: [], ...data }}
        />,
    );
}

async function openDialog(name: string) {
    await userEvent.setup().click(screen.getByRole('button', { name }));

    return screen.findByRole('dialog');
}

describe('Organization adviser card (SDAO, organization page)', () => {
    it('shows the current adviser and who assigned them', () => {
        renderCard({});

        expect(screen.getByText('Adviser One')).toBeInTheDocument();
        expect(screen.getByText(/assigned by Carl Justin Magpantay/)).toBeInTheDocument();
    });

    it('lists past advisers with how each term ended', () => {
        renderCard({
            history: [
                {
                    id: 1,
                    name: 'Former Adviser',
                    started_at: '2025-01-01T00:00:00Z',
                    ended_at: '2026-01-05T00:00:00Z',
                    assigned_by: null,
                    ended_by: 'Carl Justin Magpantay',
                    outcome: 'Account deactivated',
                },
            ],
        });

        expect(screen.getAllByText('Former Adviser').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Account deactivated').length).toBeGreaterThan(0);
    });

    it('offers to assign an adviser when the organization has none', () => {
        renderCard({ current: null });

        expect(screen.getByText('No adviser assigned')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Assign adviser' })).toBeInTheDocument();
    });

    it('warns what the swap does to the outgoing adviser, and defaults to returning them to the pool', async () => {
        renderCard({
            pool: [{ id: 9, name: 'Pool Adviser', email: 'pool@nu-lipa.edu.ph' }],
        });
        const dialog = await openDialog('Change adviser');

        expect(
            within(dialog).getByText(
                'Adviser One stops being the adviser of Debate Society.',
            ),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByText(/return to the pool of unassigned advisers/),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByRole('checkbox', {
                name: /Also deactivate Adviser One/,
            }),
        ).not.toBeChecked();
        // Nothing can be submitted until an adviser is chosen.
        expect(
            within(dialog).getByRole('button', { name: 'Change adviser' }),
        ).toBeDisabled();
        expect(
            within(dialog).getByDisplayValue('returned_to_pool'),
        ).toBeInTheDocument();
    });

    it('sends deactivated when the deactivate choice is ticked, and says so', async () => {
        renderCard({
            pool: [{ id: 9, name: 'Pool Adviser', email: 'pool@nu-lipa.edu.ph' }],
        });
        const dialog = await openDialog('Change adviser');

        await userEvent
            .setup()
            .click(within(dialog).getByRole('checkbox', { name: /Also deactivate/ }));

        expect(within(dialog).getByDisplayValue('deactivated')).toBeInTheDocument();
        expect(
            within(dialog).getByText(/signed out everywhere/),
        ).toBeInTheDocument();
    });

    it('sends the adviser the page showed, so a stale page is refused', async () => {
        renderCard({
            pool: [{ id: 9, name: 'Pool Adviser', email: 'pool@nu-lipa.edu.ph' }],
        });
        const dialog = await openDialog('Change adviser');

        expect(within(dialog).getByDisplayValue('3')).toHaveAttribute(
            'name',
            'current_adviser_id',
        );
    });

    it('points at the create-account path when the pool is empty', async () => {
        renderCard({ pool: [] });
        const dialog = await openDialog('Change adviser');

        expect(within(dialog).getByText('No unassigned advisers.')).toBeInTheDocument();
        expect(
            within(dialog).getByRole('link', { name: 'Create adviser account' }),
        ).toHaveAttribute('href', expect.stringContaining('organization_id=7'));
    });
});
