import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import DeactivateOfficerAccountDialog, {
    remainingOfficersWarning,
} from '@/components/deactivate-officer-account-dialog';

vi.mock('@inertiajs/react', () => ({
    Form: ({
        children,
    }: {
        children: (state: {
            processing: boolean;
            errors: Record<string, string>;
        }) => ReactNode;
    }) => <form>{children({ processing: false, errors: {} })}</form>,
}));

function renderDialog(remaining: number) {
    render(
        <DeactivateOfficerAccountDialog
            officer={{
                user_id: 3,
                name: 'Alpha Student',
                position: 'President',
                remaining_officers: remaining,
            }}
            organizationName="Debate Society"
        />,
    );
}

async function openDialog() {
    const user = userEvent.setup();
    await user.click(
        screen.getByRole('button', { name: 'Deactivate account' }),
    );

    return screen.findByRole('dialog');
}

describe('remainingOfficersWarning', () => {
    it('only warns when the org would be left with none, or only one', () => {
        expect(remainingOfficersWarning(0, 'CS')?.title).toBe(
            'CS will have no active officers.',
        );
        expect(remainingOfficersWarning(1, 'CS')?.title).toBe(
            'Only one active officer will remain.',
        );
        expect(remainingOfficersWarning(2, 'CS')).toBeNull();
        expect(remainingOfficersWarning(5, 'CS')).toBeNull();
    });
});

describe('Deactivate account dialog (SDAO, organization page)', () => {
    it('warns that nobody can submit when the org would have no active officers', async () => {
        renderDialog(0);
        const dialog = await openDialog();

        expect(
            within(dialog).getByText(
                'Debate Society will have no active officers.',
            ),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByText(/Nobody can submit documents for it/),
        ).toBeInTheDocument();
    });

    it('warns when only one active officer would remain', async () => {
        renderDialog(1);
        const dialog = await openDialog();

        expect(
            within(dialog).getByText('Only one active officer will remain.'),
        ).toBeInTheDocument();
    });

    it('shows no warning when two or more officers remain', async () => {
        renderDialog(2);
        const dialog = await openDialog();

        expect(
            within(dialog).queryByText(/no active officers/),
        ).not.toBeInTheDocument();
        expect(
            within(dialog).queryByText(/Only one active officer/),
        ).not.toBeInTheDocument();
    });

    it('says plainly this closes the whole account, not just the seat, and points to End seat for the lighter action', async () => {
        renderDialog(2);
        const dialog = await openDialog();

        expect(
            within(dialog).getByText("Deactivate Alpha Student's account?"),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByText(
                /closes Alpha Student.s whole account, not just the officer seat/,
            ),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByText(
                /ends their President seat of Debate Society/,
            ),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByText(
                /the adviser uses End seat in Manage Officers/,
            ),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByRole('button', { name: 'Deactivate account' }),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByLabelText('Reason (optional)'),
        ).toBeInTheDocument();
    });
});
