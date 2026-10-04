import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import OfficersIndex from '@/pages/organizations/officers/index';

const deleteMock = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { delete: deleteMock, get: vi.fn() },
    Form: ({ children }: { children: (state: { processing: boolean; errors: Record<string, string> }) => ReactNode }) => (
        <form>{children({ processing: false, errors: {} })}</form>
    ),
}));

// Lives outside resources/js/pages/ on purpose: the @inertiajs/vite resolver globs ./pages/**/*.tsx with no test exclusion, so a test file there would be bundled as a page chunk.

/**
 * Guards the CALL SITE, not Wayfinder's route builder: Manage Officers once
 * passed officers.destroy two positional arguments, which threw before any
 * request was sent and left the Deactivate button inert. Reverting that
 * line makes this spec fail, because router.delete never gets called.
 */
describe('Manage Officers page — Deactivate', () => {
    beforeEach(() => {
        deleteMock.mockReset();
    });

    it('sends a DELETE to /organizations/{org}/officers/{membership} when an officer is deactivated', async () => {
        const user = userEvent.setup();

        render(
            <OfficersIndex
                organization={{ id: 7, name: 'Computing Society' }}
                memberships={[
                    {
                        id: 42,
                        user: { id: 3, name: 'Alpha Student', email: 'alpha@student.nu-lipa.edu.ph' },
                        position: 'president',
                        position_label: 'President',
                        academic_year: '2026-2027',
                    },
                ]}
                students={[]}
                search=""
                positions={[
                    { value: 'president', label: 'President' },
                    { value: 'secretary', label: 'Secretary' },
                ]}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Deactivate' }));

        const dialog = await screen.findByRole('dialog');
        expect(within(dialog).getByText(/Deactivate .*Alpha.*\?/)).toBeInTheDocument();

        await user.click(within(dialog).getByRole('button', { name: 'Deactivate' }));

        expect(deleteMock).toHaveBeenCalledTimes(1);
        expect(deleteMock.mock.calls[0][0]).toEqual({
            url: '/organizations/7/officers/42',
            method: 'delete',
        });
    });
});
