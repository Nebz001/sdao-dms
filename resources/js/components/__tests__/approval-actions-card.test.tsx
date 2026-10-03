import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import ApprovalActionsCard from '@/components/approval-actions-card';
import PageNotice from '@/components/page-notice';

const returnFormProps = { action: '/fake/return', method: 'post' as const };
const rejectFormProps = { action: '/fake/reject', method: 'post' as const };

function baseProps(overrides: Partial<React.ComponentProps<typeof ApprovalActionsCard>> = {}) {
    return {
        approve: {
            confirmTitle: 'Approve this document?',
            confirmDescription: 'This is irreversible.',
            onConfirm: vi.fn(),
        },
        return: {
            formProps: returnFormProps,
            placeholder: 'Explain what needs to change…',
            flagFields: <div data-testid="flag-fields-marker">flag fields</div>,
        },
        reject: {
            formProps: rejectFormProps,
            confirmTitle: 'Reject this document?',
            confirmDescription: 'This is permanent.',
        },
        ...overrides,
    };
}

describe('ApprovalActionsCard', () => {
    it('renders the decision title and the three decisions', () => {
        render(<ApprovalActionsCard {...baseProps()} />);

        expect(screen.getByText('Record your decision')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Approve' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Return for revision' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Reject' })).toBeInTheDocument();
        expect(screen.getByText(/Approving sends this to the next step/)).toBeInTheDocument();
    });

    it('renders the given note above the actions', () => {
        render(<ApprovalActionsCard {...baseProps({ note: <p>Approved by: Alice, Bob</p> })} />);

        expect(screen.getByText('Approved by: Alice, Bob')).toBeInTheDocument();
    });

    it('renders a blocked banner instead of the approve trigger when approve.blocked is set', () => {
        render(
            <ApprovalActionsCard
                {...baseProps({
                    approve: { ...baseProps().approve, blocked: <p>Cannot approve: conflict detected.</p> },
                })}
            />,
        );

        expect(screen.getByText('Cannot approve: conflict detected.')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Approve' })).not.toBeInTheDocument();
    });

    it('uses a custom approve label when given (e.g. "Already approved")', () => {
        render(
            <ApprovalActionsCard
                {...baseProps({
                    approve: { ...baseProps().approve, label: 'Already approved', disabled: true },
                })}
            />,
        );

        expect(screen.getByRole('button', { name: 'Already approved' })).toBeDisabled();
    });

    it('opens the approve confirmation dialog and calls onConfirm only after the confirm button is clicked', async () => {
        const user = userEvent.setup();
        const onConfirm = vi.fn();
        render(<ApprovalActionsCard {...baseProps({ approve: { ...baseProps().approve, onConfirm } })} />);

        await user.click(screen.getByRole('button', { name: 'Approve' }));
        expect(onConfirm).not.toHaveBeenCalled();

        const dialog = screen.getByRole('dialog');
        expect(within(dialog).getByText('Approve this document?')).toBeInTheDocument();

        await user.click(within(dialog).getByRole('button', { name: 'Confirm Approval' }));
        expect(onConfirm).toHaveBeenCalledTimes(1);
    });

    it('swaps to the return form when Return for revision is chosen, with the section chips slot and a required message', async () => {
        const user = userEvent.setup();
        render(<ApprovalActionsCard {...baseProps()} />);

        expect(screen.queryByTestId('flag-fields-marker')).not.toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Return for revision' }));

        expect(screen.getByTestId('flag-fields-marker')).toBeInTheDocument();
        expect(screen.queryByText('Record your decision')).not.toBeInTheDocument();

        const message = screen.getByPlaceholderText('Explain what needs to change…');
        expect(message).toBeRequired();
        expect(message).toHaveAttribute('name', 'comment');
        expect(screen.getByRole('button', { name: 'Send back for revision' })).toBeInTheDocument();
    });

    it('Cancel returns from the return form to the decision buttons without submitting', async () => {
        const user = userEvent.setup();
        render(<ApprovalActionsCard {...baseProps()} />);

        await user.click(screen.getByRole('button', { name: 'Return for revision' }));
        await user.click(screen.getByRole('button', { name: 'Cancel' }));

        expect(screen.getByText('Record your decision')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Approve' })).toBeInTheDocument();
    });

    it('opens the reject dialog with a required reason field, and cancel closes it without submitting', async () => {
        const user = userEvent.setup();
        render(<ApprovalActionsCard {...baseProps()} />);

        await user.click(screen.getByRole('button', { name: 'Reject' }));

        const dialog = screen.getByRole('dialog');
        expect(within(dialog).getByText('Reject this document?')).toBeInTheDocument();
        expect(within(dialog).getByPlaceholderText('Reason for rejection…')).toBeRequired();

        await user.click(within(dialog).getByRole('button', { name: 'Cancel' }));
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('uses a custom reject placeholder when given', async () => {
        const user = userEvent.setup();
        render(
            <ApprovalActionsCard
                {...baseProps({ reject: { ...baseProps().reject, placeholder: 'Why is this being rejected?' } })}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Reject' }));

        expect(screen.getByPlaceholderText('Why is this being rejected?')).toBeInTheDocument();
    });

    it('gives approve, return, and reject their expected button treatments', () => {
        render(<ApprovalActionsCard {...baseProps()} />);

        expect(screen.getByRole('button', { name: 'Approve' })).toHaveClass('bg-primary');
        expect(screen.getByRole('button', { name: 'Return for revision' })).toHaveClass('border-input');
        expect(screen.getByRole('button', { name: 'Reject' })).toHaveClass('text-destructive-foreground');
    });
});

describe('ApprovalActionsCard confirmNotice', () => {
    it('shows a failed approval as an urgent notice inside the confirm dialog', async () => {
        const user = userEvent.setup();

        render(
            <ApprovalActionsCard
                {...baseProps({
                    approve: {
                        confirmTitle: 'Approve this document?',
                        confirmDescription: 'This is irreversible.',
                        confirmNotice: (
                            <PageNotice tone="destructive" urgent title="The adviser is assigned elsewhere." />
                        ),
                        onConfirm: vi.fn(),
                    },
                })}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Approve' }));

        const dialog = await screen.findByRole('dialog');
        expect(within(dialog).getByRole('alert')).toHaveTextContent('The adviser is assigned elsewhere.');
    });
});
