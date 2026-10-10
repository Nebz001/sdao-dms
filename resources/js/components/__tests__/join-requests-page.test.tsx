import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { TooltipProvider } from '@/components/ui/tooltip';
import { askedExact, askedLabel } from '@/lib/asked-time';
import JoinRequestsIndex from '@/pages/review/join-requests/index';

const inertia = vi.hoisted(() => ({
    post: vi.fn(),
    reload: vi.fn(),
    organization: { name: 'CODECS' } as { name: string } | null,
}));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { post: inertia.post, reload: inertia.reload },
    usePage: () => ({ props: { auth: { organization: inertia.organization } } }),
}));

vi.mock('@/hooks/use-document-updates', () => ({ useDocumentUpdates: () => undefined }));

const positions = [
    { value: 'president', label: 'President' },
    { value: 'secretary', label: 'Secretary' },
];

function request(id: number, name: string, createdAt: string, open = ['secretary']) {
    return {
        id,
        student: { id: id + 100, name, email: `${name.split(' ')[0].toLowerCase()}@students.nu-lipa.edu.ph`, id_number: null },
        organization: { id: 1, name: 'CODECS' },
        created_at: createdAt,
        open_positions: open,
    };
}

const jasmine = request(1, 'Jasmine Reyes', '2026-10-01T08:00:00Z');
const paolo = request(2, 'Paolo Dimaculangan', '2026-10-05T08:00:00Z');

function renderPage(queue = [jasmine, paolo], closed: never[] = []) {
    return render(
        <TooltipProvider>
            <JoinRequestsIndex queue={queue} closed={closed} positions={positions} />
        </TooltipProvider>,
    );
}

describe('Join Requests page', () => {
    beforeEach(() => {
        inertia.post.mockReset();
        inertia.reload.mockReset();
        inertia.organization = { name: 'CODECS' };
    });
    afterEach(cleanup);

    it('shows the empty state, a quiet 0 count and no "Oldest first" when nothing is waiting', () => {
        renderPage([]);

        expect(screen.getByRole('heading', { name: /Waiting for you/ })).toHaveTextContent('0');
        expect(screen.getByText('No requests right now')).toBeInTheDocument();
        expect(
            screen.getByText('When a student asks to join CODECS, they’ll show up here for you to approve or decline.'),
        ).toBeInTheDocument();
        expect(screen.queryByText('Oldest first')).not.toBeInTheDocument();
        expect(screen.getByText(/Approving adds them as an officer right away/)).toBeInTheDocument();
    });

    it('lists the requests in the order the server sends them (oldest first), with the count', () => {
        renderPage();

        expect(screen.getByRole('heading', { name: /Waiting for you/ })).toHaveTextContent('2');
        expect(screen.getByText('Oldest first')).toBeInTheDocument();

        const names = screen.getAllByRole('listitem').map((li) => li.textContent ?? '');
        expect(names[0]).toContain('Jasmine Reyes');
        expect(names[1]).toContain('Paolo Dimaculangan');
        expect(screen.getByText('jasmine@students.nu-lipa.edu.ph')).toBeInTheDocument();
    });

    it('approving asks first, names the student and the organization, and only then posts the chosen seat', async () => {
        const user = userEvent.setup();
        renderPage([jasmine]);

        await user.click(screen.getByRole('button', { name: /Approve Jasmine Reyes/ }));

        const dialog = await screen.findByRole('dialog');
        expect(within(dialog).getByText('Add Jasmine Reyes as an officer of CODECS?')).toBeInTheDocument();
        expect(inertia.post).not.toHaveBeenCalled();

        await user.click(within(dialog).getByRole('button', { name: 'Approve' }));

        expect(inertia.post).toHaveBeenCalledTimes(1);
        expect(inertia.post.mock.calls[0][1]).toEqual({ position: 'secretary' });
        expect(inertia.post.mock.calls[0][0]).toContain('/review/join-requests/1/approve');
    });

    it('declining asks first, and the optional reason reaches the server as the comment', async () => {
        const user = userEvent.setup();
        renderPage([jasmine]);

        await user.click(screen.getByRole('button', { name: /Decline Jasmine Reyes/ }));

        const dialog = await screen.findByRole('dialog');
        expect(inertia.post).not.toHaveBeenCalled();

        await user.type(within(dialog).getByLabelText(/Reason/), 'Not a CODECS student');
        await user.click(within(dialog).getByRole('button', { name: 'Decline' }));

        expect(inertia.post.mock.calls[0][0]).toContain('/review/join-requests/1/decline');
        expect(inertia.post.mock.calls[0][1]).toEqual({ comment: 'Not a CODECS student' });
    });

    it('closes the dialog on success, and the row and count go once the list refreshes', async () => {
        const user = userEvent.setup();
        inertia.post.mockImplementation((_url, _data, options) => {
            options.onSuccess?.();
            options.onFinish?.();
        });
        const { rerender } = renderPage();

        await user.click(screen.getByRole('button', { name: /Approve Jasmine Reyes/ }));
        await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Approve' }));

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();

        // The server redirects back with the row gone.
        rerender(
            <TooltipProvider>
                <JoinRequestsIndex queue={[paolo]} closed={[]} positions={positions} />
            </TooltipProvider>,
        );

        expect(screen.queryByText('Jasmine Reyes')).not.toBeInTheDocument();
        expect(screen.getByRole('heading', { name: /Waiting for you/ })).toHaveTextContent('1');
    });

    it('shows the server message on a collision, keeps the dialog open, and refreshes the list', async () => {
        const user = userEvent.setup();
        inertia.post.mockImplementation((_url, _data, options) => {
            options.onError?.({ join_request: 'Secretary is already filled for this organization.' });
            options.onFinish?.();
        });
        renderPage([jasmine]);

        await user.click(screen.getByRole('button', { name: /Approve Jasmine Reyes/ }));
        const dialog = await screen.findByRole('dialog');
        await user.click(within(dialog).getByRole('button', { name: 'Approve' }));

        expect(within(dialog).getByText('Secretary is already filled for this organization.')).toBeInTheDocument();
        expect(inertia.reload).toHaveBeenCalledWith({ only: ['queue', 'closed'] });
        expect(screen.getByRole('dialog')).toBeInTheDocument();
    });

    it('disables Approve when both seats are filled and says why, but still allows Decline', () => {
        renderPage([request(3, 'Rina Santos', '2026-10-05T08:00:00Z', [])]);

        expect(screen.getByRole('button', { name: /Approve Rina Santos/ })).toBeDisabled();
        expect(screen.getByRole('button', { name: /Decline Rina Santos/ })).toBeEnabled();
        expect(screen.getByText(/Both officer positions are filled/)).toBeInTheDocument();
    });

    it('uses a plain phrase for an adviser, whose shared props carry no organization', () => {
        inertia.organization = null;
        renderPage([]);

        expect(screen.getByText(/When a student asks to join your organization/)).toBeInTheDocument();
    });
});

describe('asked time', () => {
    const now = new Date('2026-10-08T10:00:00Z'); // 6:00 PM in Manila

    it('counts calendar days in Manila time', () => {
        expect(askedLabel('2026-10-08T08:00:00Z', now)).toBe('Asked today');
        expect(askedLabel('2026-10-07T08:00:00Z', now)).toBe('Asked yesterday');
        expect(askedLabel('2026-10-05T08:00:00Z', now)).toBe('Asked 3 days ago');
        // 1:00 AM Manila on the 8th is still the 7th in UTC, but it is today in Manila.
        expect(askedLabel('2026-10-07T17:00:00Z', now)).toBe('Asked today');
        expect(askedLabel('2026-10-07T15:30:00Z', now)).toBe('Asked yesterday');
    });

    it('gives the exact date and time in Manila for the tooltip', () => {
        expect(askedExact('2026-10-07T15:42:00Z')).toBe('Oct 7, 2026, 11:42 PM');
    });
});
