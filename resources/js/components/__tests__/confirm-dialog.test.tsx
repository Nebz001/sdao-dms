import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import ConfirmDialog from '@/components/confirm-dialog';
import type { ConfirmActions } from '@/components/confirm-dialog';

/**
 * These specs pin down the dismissal contract that was the root cause of
 * the "modal doesn't auto-dismiss" QA report: closing must be driven by the
 * request's own outcome, not by the click that starts it. `onConfirm` is
 * exercised directly with a manually-controlled `close`/`stopProcessing`
 * pair rather than mocking @inertiajs/react — the dialog doesn't know or
 * care that the real callers wire these to router.post's onSuccess/onFinish,
 * so testing the contract itself is what makes the previously
 * intermittent-looking behaviour deterministic here.
 */
describe('ConfirmDialog', () => {
    it('stays open while the action is in flight and closes only once it reports success', async () => {
        const user = userEvent.setup();
        let resolve: () => void = () => {
            throw new Error('onConfirm was not called');
        };
        const onConfirm = vi.fn(({ close, stopProcessing }: ConfirmActions) => {
            resolve = () => {
                close();
                stopProcessing();
            };
        });

        render(
            <ConfirmDialog
                trigger={<button>Approve</button>}
                title="Approve this registration?"
                description="This action is irreversible."
                confirmLabel="Confirm Approval"
                onConfirm={onConfirm}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Approve' }));
        await user.click(screen.getByRole('button', { name: 'Confirm Approval' }));

        expect(onConfirm).toHaveBeenCalledTimes(1);
        // The request hasn't resolved yet — the dialog must still be open.
        // This is the exact case the old sync `setOpen(false)` got wrong.
        expect(screen.getByRole('dialog')).toBeInTheDocument();

        resolve();

        await waitFor(() => {
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });
    });

    it('stays open and re-enables the confirm button when the action fails', async () => {
        const user = userEvent.setup();
        const onConfirm = vi.fn(({ stopProcessing }: ConfirmActions) => {
            // Simulates onError/onFinish firing without onSuccess — a 422
            // or a re-check failure. `close` is deliberately never called.
            stopProcessing();
        });

        render(
            <ConfirmDialog
                trigger={<button>Reject</button>}
                title="Reject this document?"
                description="This is permanent."
                confirmLabel="Confirm Rejection"
                confirmVariant="destructive"
                onConfirm={onConfirm}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Reject' }));
        await user.click(screen.getByRole('button', { name: 'Confirm Rejection' }));

        await waitFor(() => {
            expect(screen.getByRole('button', { name: 'Confirm Rejection' })).toBeEnabled();
        });
        // Not just re-enabled — never dismissed, unlike the old unconditional close.
        expect(screen.getByRole('dialog')).toBeInTheDocument();
    });

    it('disables the confirm button while processing, so a second click cannot fire a duplicate request', async () => {
        const user = userEvent.setup();
        const onConfirm = vi.fn();

        render(
            <ConfirmDialog
                trigger={<button>Deactivate</button>}
                title="Deactivate this officer?"
                description="This removes their access."
                confirmLabel="Deactivate"
                onConfirm={onConfirm}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Deactivate' }));
        const confirmButton = await screen.findByRole('button', { name: 'Deactivate' });
        await user.click(confirmButton);

        expect(onConfirm).toHaveBeenCalledTimes(1);
        expect(confirmButton).toBeDisabled();

        // A disabled button does not dispatch click handlers; confirms the
        // trigger behind the overlay can't be double-fired either.
        await user.click(confirmButton);
        expect(onConfirm).toHaveBeenCalledTimes(1);
    });

    it('cancel closes the dialog without invoking the confirm action', async () => {
        const user = userEvent.setup();
        const onConfirm = vi.fn();

        render(
            <ConfirmDialog
                trigger={<button>Approve</button>}
                title="Approve this?"
                description="Are you sure?"
                confirmLabel="Confirm Approval"
                onConfirm={onConfirm}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Approve' }));
        await user.click(screen.getByRole('button', { name: 'Cancel' }));

        await waitFor(() => {
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });
        expect(onConfirm).not.toHaveBeenCalled();
    });

    it('supports a fully custom body (e.g. a form) and closes when the caller calls close', async () => {
        const user = userEvent.setup();

        render(
            <ConfirmDialog
                trigger={<button>Reject</button>}
                title="Reject this document?"
                description="This is permanent."
            >
                {(close) => (
                    <div>
                        <label htmlFor="comment">Reason</label>
                        <textarea id="comment" />
                        <button type="button" onClick={close}>
                            Submit rejection
                        </button>
                    </div>
                )}
            </ConfirmDialog>,
        );

        await user.click(screen.getByRole('button', { name: 'Reject' }));

        const dialog = screen.getByRole('dialog');
        expect(within(dialog).getByLabelText('Reason')).toBeInTheDocument();

        await user.click(within(dialog).getByRole('button', { name: 'Submit rejection' }));

        await waitFor(() => {
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });
    });

    it('supports being opened externally with no trigger element (e.g. from a DropdownMenuItem)', async () => {
        const user = userEvent.setup();
        const onConfirm = vi.fn(({ close, stopProcessing }: ConfirmActions) => {
            close();
            stopProcessing();
        });
        const onOpenChange = vi.fn();

        const { rerender } = render(
            <ConfirmDialog
                open={false}
                onOpenChange={onOpenChange}
                title="Log out?"
                description="You'll need to sign in again."
                confirmLabel="Log out"
                onConfirm={onConfirm}
            />,
        );

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();

        rerender(
            <ConfirmDialog
                open={true}
                onOpenChange={onOpenChange}
                title="Log out?"
                description="You'll need to sign in again."
                confirmLabel="Log out"
                onConfirm={onConfirm}
            />,
        );

        expect(screen.getByRole('dialog')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Log out' }));

        expect(onConfirm).toHaveBeenCalledTimes(1);
        // `close()` (called by onConfirm above) must delegate to the
        // caller's onOpenChange rather than any internal state, since this
        // instance was never given internal state to fall back on.
        expect(onOpenChange).toHaveBeenCalledWith(false);
    });
});
