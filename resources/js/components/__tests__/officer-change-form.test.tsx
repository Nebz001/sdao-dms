import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import RequestOfficerChange from '@/pages/organizations/officer-change/create';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { post: vi.fn() },
}));

const base = {
    organization: { id: 1, name: 'CODECS' },
    currentOfficers: [
        { position: 'president', position_label: 'President', user: { id: 1, name: 'Miguel Torres' } },
        { position: 'secretary', position_label: 'Secretary', user: { id: 2, name: 'Bianca Fernandez' } },
    ],
    pendingRequest: null,
    positions: [
        { value: 'president', label: 'President' },
        { value: 'secretary', label: 'Secretary' },
    ],
};

describe('Request Officer Change', () => {
    afterEach(cleanup);

    it('shows who holds each seat and starts on the first position', () => {
        render(<RequestOfficerChange {...base} pendingPositions={[]} />);

        expect(screen.getByRole('radio', { name: /President/ })).toBeChecked();
        expect(screen.getByText('Now held by Miguel Torres')).toBeInTheDocument();
        expect(screen.getByText('Now held by Bianca Fernandez')).toBeInTheDocument();
        expect(screen.getByText('Not picked yet')).toBeInTheDocument();
        expect(screen.getByText('SDAO approves this first')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Send Request/ })).toBeDisabled();
    });

    it('disables a seat that already has a pending request and says why', () => {
        render(<RequestOfficerChange {...base} pendingPositions={['president']} />);

        expect(screen.getByRole('radio', { name: /President/ })).toBeDisabled();
        expect(
            screen.getByText('A change request is already pending for this position'),
        ).toBeInTheDocument();

        // The form falls to the seat that is still open.
        expect(screen.getByRole('radio', { name: /Secretary/ })).toBeChecked();
    });

    it('shows a vacant seat as a dashed Vacant card and as a position option', () => {
        render(
            <RequestOfficerChange
                {...base}
                currentOfficers={[base.currentOfficers[0]]}
                pendingPositions={[]}
            />,
        );

        expect(screen.getAllByText('Vacant').length).toBeGreaterThanOrEqual(2);
    });

    it('cannot be sent while every seat has a pending request', async () => {
        const user = userEvent.setup();
        render(<RequestOfficerChange {...base} pendingPositions={['president', 'secretary']} />);

        expect(screen.getByText(/Every position already has a pending request/)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Send Request/ })).toBeDisabled();
        await user.click(screen.getByRole('button', { name: /Send Request/ }));
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
});
