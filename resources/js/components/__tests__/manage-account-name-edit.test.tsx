import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import ManageAccountDialog from '@/components/approver-accounts/manage-account-dialog';
import type { ApproverAccount } from '@/components/approver-accounts/types';

vi.mock('@inertiajs/react', () => ({
    router: { post: vi.fn() },
    Form: ({
        children,
        action,
    }: {
        children: (state: { processing: boolean; errors: Record<string, string> }) => React.ReactNode;
        action?: string;
    }) => (
        <form data-testid="form" data-action={action}>
            {children({ processing: false, errors: {} })}
        </form>
    ),
}));

const account: ApproverAccount = {
    id: 7,
    name: 'Dr. Alice Lacorte',
    first_name: 'Alice',
    last_name: 'Lacorte',
    email: 'lacorte@nu-lipa.edu.ph',
    is_self: false,
    deactivated_at: null,
    deactivated_reason: null,
    deactivated_by: null,
    group: 'dean',
    role_label: 'Dean',
    approves_for: null,
    scope_key: null,
    roles: [],
};

// Lives outside resources/js/pages/ on purpose (see register-page.test.tsx).

describe('Manage account — edit name', () => {
    it('opens a first and last name form prefilled from the account, and can be cancelled', async () => {
        const user = userEvent.setup();
        render(<ManageAccountDialog account={account} />);

        await user.click(screen.getByRole('button', { name: /Manage/ }));
        await user.click(screen.getByRole('button', { name: /Edit name/ }));

        expect(screen.getByLabelText('First name')).toHaveValue('Alice');
        expect(screen.getByLabelText('Last name')).toHaveValue('Lacorte');
        expect(screen.getByLabelText('First name')).toBeRequired();
        expect(screen.getByTestId('form').getAttribute('data-action')).toContain('/admin/accounts/7/name');

        await user.click(screen.getByRole('button', { name: 'Cancel' }));
        expect(screen.queryByLabelText('First name')).not.toBeInTheDocument();
    });
});
