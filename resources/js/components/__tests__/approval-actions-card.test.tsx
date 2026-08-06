import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import ApprovalActionsCard from '@/components/approval-actions-card';

const returnFormProps = { action: '/fake/return', method: 'post' as const };
const rejectFormProps = { action: '/fake/reject', method: 'post' as const };

function baseProps(
    overrides: Partial<React.ComponentProps<typeof ApprovalActionsCard>> = {},
) {
    return {
        title: 'Review Actions',
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
    it('renders the title, an approve trigger, a return form, and a reject trigger', () => {
        render(<ApprovalActionsCard {...baseProps()} />);

        expect(screen.getByText('Review Actions')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Approve' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Return for Revision' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Reject' }),
        ).toBeInTheDocument();
    });

    it('renders the given note above the actions', () => {
        render(
            <ApprovalActionsCard
                {...baseProps({ note: <p>Approved by: Alice, Bob</p> })}
            />,
        );

        expect(screen.getByText('Approved by: Alice, Bob')).toBeInTheDocument();
    });

    it('renders a blocked banner instead of the approve trigger when approve.blocked is set', () => {
        render(
            <ApprovalActionsCard
                {...baseProps({
                    approve: {
                        ...baseProps().approve,
                        blocked: <p>Cannot approve: conflict detected.</p>,
                    },
                })}
            />,
        );

        expect(
            screen.getByText('Cannot approve: conflict detected.'),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Approve' }),
        ).not.toBeInTheDocument();
    });

    it('uses a custom approve label when given (e.g. "Already Approved")', () => {
        render(
            <ApprovalActionsCard
                {...baseProps({
                    approve: {
                        ...baseProps().approve,
                        label: 'Already Approved',
                        disabled: true,
                    },
                })}
            />,
        );

        const trigger = screen.getByRole('button', {
            name: 'Already Approved',
        });
        expect(trigger).toBeDisabled();
    });

    it('opens the approve confirmation dialog and calls onConfirm only after the confirm button is clicked', async () => {
        const user = userEvent.setup();
        const onConfirm = vi.fn();
        render(
            <ApprovalActionsCard
                {...baseProps({
                    approve: { ...baseProps().approve, onConfirm },
                })}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Approve' }));
        expect(onConfirm).not.toHaveBeenCalled();

        const dialog = screen.getByRole('dialog');
        expect(
            within(dialog).getByText('Approve this document?'),
        ).toBeInTheDocument();

        await user.click(
            within(dialog).getByRole('button', { name: 'Confirm Approval' }),
        );
        expect(onConfirm).toHaveBeenCalledTimes(1);
    });

    it('renders the flagFields slot inside the return form', () => {
        render(<ApprovalActionsCard {...baseProps()} />);

        expect(screen.getByTestId('flag-fields-marker')).toBeInTheDocument();
    });

    it('renders the return comment textarea as required, with the given placeholder', () => {
        render(<ApprovalActionsCard {...baseProps()} />);

        const textarea = screen.getByPlaceholderText(
            'Explain what needs to change…',
        );
        expect(textarea).toBeRequired();
        expect(textarea).toHaveAttribute('rows', '3');
    });

    it('opens the reject dialog with a required reason field, and cancel closes it without submitting', async () => {
        const user = userEvent.setup();
        render(<ApprovalActionsCard {...baseProps()} />);

        await user.click(screen.getByRole('button', { name: 'Reject' }));

        const dialog = screen.getByRole('dialog');
        expect(
            within(dialog).getByText('Reject this document?'),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByPlaceholderText('Reason for rejection…'),
        ).toBeRequired();

        await user.click(
            within(dialog).getByRole('button', { name: 'Cancel' }),
        );
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('uses a custom reject placeholder when given', async () => {
        const user = userEvent.setup();
        render(
            <ApprovalActionsCard
                {...baseProps({
                    reject: {
                        ...baseProps().reject,
                        placeholder: 'Why is this being rejected?',
                    },
                })}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Reject' }));

        expect(
            screen.getByPlaceholderText('Why is this being rejected?'),
        ).toBeInTheDocument();
    });

    it('gives approve, return, and reject their expected button variants', () => {
        render(<ApprovalActionsCard {...baseProps()} />);

        expect(screen.getByRole('button', { name: 'Approve' })).toHaveClass(
            'bg-primary',
        );
        expect(
            screen.getByRole('button', { name: 'Return for Revision' }),
        ).toHaveClass('border-input');
        expect(screen.getByRole('button', { name: 'Reject' })).toHaveClass(
            'bg-destructive',
        );
    });
});
