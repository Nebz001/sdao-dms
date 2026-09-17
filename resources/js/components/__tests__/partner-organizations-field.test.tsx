import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PartnerOrganizationsField from '@/components/partner-organizations-field';

/**
 * Covers the interaction contract that makes typing itself the free-text
 * fallback (no separate mode toggle), and specifically the case flagged
 * during review: does editing an already-linked entry correctly drop the
 * stale organization_id, not just visually but in the actual hidden input
 * that gets submitted.
 *
 * Uses real timers throughout (not vi.useFakeTimers()) — mixing fake timers
 * with userEvent's own internal delay simulation reliably deadlocks/times
 * out here. The component's debounce is a real 600ms setTimeout, so tests
 * that need it to fire just poll for the result with a generous findBy
 * timeout instead of manually advancing a mocked clock.
 */
const { fetchMock } = vi.hoisted(() => ({
    fetchMock: vi.fn(),
}));

function mockSearchResults(organizations: Array<{ id: number; name: string; school: string | null; program: string | null }>) {
    fetchMock.mockResolvedValueOnce({
        json: () => Promise.resolve({ organizations }),
    });
}

function hiddenOrganizationIdInput(container: HTMLElement, index = 0): HTMLInputElement {
    return container.querySelectorAll<HTMLInputElement>('input[type="hidden"]')[index];
}

describe('PartnerOrganizationsField', () => {
    beforeEach(() => {
        fetchMock.mockClear();
        vi.stubGlobal('fetch', fetchMock);
    });

    it('typing without picking a result submits it as free text — organization_id stays empty', async () => {
        const user = userEvent.setup();
        const { container } = render(<PartnerOrganizationsField errors={{}} />);

        await user.type(screen.getByPlaceholderText('Search organizations, or type a name…'), 'An Unregistered RSO');

        expect(screen.getByDisplayValue('An Unregistered RSO')).toBeInTheDocument();
        expect(hiddenOrganizationIdInput(container).value).toBe('');
    });

    it('picking a search result sets both the visible name and the hidden organization_id', async () => {
        mockSearchResults([{ id: 42, name: 'Computing Society', school: 'School of Computing and IT', program: 'BS Computer Science' }]);
        const user = userEvent.setup();
        const { container } = render(<PartnerOrganizationsField errors={{}} />);

        await user.type(screen.getByPlaceholderText('Search organizations, or type a name…'), 'Computing');

        const result = await screen.findByRole('button', { name: /Computing Society/ }, { timeout: 3000 });
        await user.click(result);

        expect(screen.getByDisplayValue('Computing Society')).toBeInTheDocument();
        expect(hiddenOrganizationIdInput(container).value).toBe('42');
    });

    it('editing an already-linked entry clears the stale organization_id — no lingering link survives a keystroke', async () => {
        mockSearchResults([{ id: 42, name: 'Computing Society', school: null, program: null }]);
        const user = userEvent.setup();
        const { container } = render(<PartnerOrganizationsField errors={{}} />);

        const input = screen.getByPlaceholderText('Search organizations, or type a name…');
        await user.type(input, 'Computing');
        const result = await screen.findByRole('button', { name: /Computing Society/ }, { timeout: 3000 });
        await user.click(result);

        expect(hiddenOrganizationIdInput(container).value).toBe('42');

        // Now edit the linked entry's text — this must drop the link
        // immediately, on the very next keystroke, not just eventually.
        await user.type(input, ' Renamed');

        expect(hiddenOrganizationIdInput(container).value).toBe('');
        expect(screen.getByDisplayValue('Computing Society Renamed')).toBeInTheDocument();
    });

    it('the "+ Add" button adds another independent row, each with its own hidden organization_id input', async () => {
        const user = userEvent.setup();
        const { container } = render(<PartnerOrganizationsField errors={{}} />);

        await user.click(screen.getByRole('button', { name: '+ Add' }));

        const inputs = screen.getAllByPlaceholderText('Search organizations, or type a name…');
        expect(inputs).toHaveLength(2);
        expect(container.querySelectorAll('input[type="hidden"]')).toHaveLength(2);
    });

    it('shows a row-level error for a missing/invalid name', () => {
        render(<PartnerOrganizationsField errors={{ 'partner_organizations.0.name': 'The name field is required.' }} />);

        expect(screen.getByText('The name field is required.')).toBeInTheDocument();
    });

    it('also shows a row-level error for a rejected organization_id (the exists rule) — not silently swallowed', () => {
        render(
            <PartnerOrganizationsField
                errors={{ 'partner_organizations.0.organization_id': 'That organization no longer exists. Clear the entry and search again, or type the name as free text.' }}
            />,
        );

        expect(
            screen.getByText('That organization no longer exists. Clear the entry and search again, or type the name as free text.'),
        ).toBeInTheDocument();
    });
});
