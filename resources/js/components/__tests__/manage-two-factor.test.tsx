import type * as InertiaReact from '@inertiajs/react';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ManageTwoFactor from '@/components/manage-two-factor';
import { disable } from '@/routes/two-factor';

/**
 * confirm-disable-2fa — pins the one behavior this branch adds: disabling
 * 2FA now goes through the shared ConfirmDialog (see
 * components/confirm-dialog.tsx and its own dismissal-contract tests)
 * instead of firing on a bare submit click. Mocks only `router.delete`,
 * preserving every other real @inertiajs/react export via
 * `importOriginal()` — ManageTwoFactor's sibling children (recovery codes,
 * the setup modal) also depend on this module, and this component has no
 * Inertia page-context dependency to work around (confirmed by reading
 * @inertiajs/react's useHttp implementation: plain local React state).
 */
const { routerDeleteMock } = vi.hoisted(() => ({ routerDeleteMock: vi.fn() }));

vi.mock('@inertiajs/react', async (importOriginal) => {
    const actual = await importOriginal<typeof InertiaReact>();

    return {
        ...actual,
        router: { ...actual.router, delete: routerDeleteMock },
    };
});

describe('ManageTwoFactor — Disable 2FA', () => {
    beforeEach(() => {
        routerDeleteMock.mockClear();
    });

    it('does not disable immediately on trigger click — opens a confirmation dialog first', async () => {
        const user = userEvent.setup();
        render(<ManageTwoFactor canManageTwoFactor twoFactorEnabled />);

        await user.click(screen.getByRole('button', { name: 'Disable 2FA' }));

        expect(routerDeleteMock).not.toHaveBeenCalled();
        expect(
            screen.getByText('Disable two-factor authentication?'),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/no longer require a second factor to sign in/),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/go through setup again, including scanning a new QR code/),
        ).toBeInTheDocument();
    });

    it('disables only after the confirm button inside the dialog is clicked', async () => {
        const user = userEvent.setup();
        render(<ManageTwoFactor canManageTwoFactor twoFactorEnabled />);

        await user.click(screen.getByRole('button', { name: 'Disable 2FA' }));

        const dialog = screen.getByRole('dialog');
        await user.click(
            within(dialog).getByRole('button', { name: 'Disable 2FA' }),
        );

        expect(routerDeleteMock).toHaveBeenCalledTimes(1);
        expect(routerDeleteMock).toHaveBeenCalledWith(
            disable.url(),
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('cancel closes the dialog without disabling', async () => {
        const user = userEvent.setup();
        render(<ManageTwoFactor canManageTwoFactor twoFactorEnabled />);

        await user.click(screen.getByRole('button', { name: 'Disable 2FA' }));
        await user.click(screen.getByRole('button', { name: 'Cancel' }));

        expect(routerDeleteMock).not.toHaveBeenCalled();
        expect(
            screen.queryByText('Disable two-factor authentication?'),
        ).not.toBeInTheDocument();
    });
});
