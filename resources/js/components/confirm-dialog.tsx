import type { VariantProps } from 'class-variance-authority';
import { useState } from 'react';
import type { ReactNode } from 'react';
import type { buttonVariants } from '@/components/ui/button';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type ButtonVariant = VariantProps<typeof buttonVariants>['variant'];

type ConfirmDialogProps = {
    /** The element that opens the dialog, e.g. a <Button>Approve</Button>. */
    trigger: ReactNode;
    title: string;
    description: ReactNode;
    confirmLabel: string;
    confirmVariant?: ButtonVariant;
    /** Disables the trigger itself (e.g. while a related mutation is in flight). */
    triggerDisabled?: boolean;
    /**
     * Programmatic confirm path (e.g. router.post/router.delete). Mutually
     * exclusive with confirmForm — provide exactly one.
     */
    onConfirm?: () => void;
    /**
     * Confirm by submitting an existing <form id="…"> elsewhere on the page
     * (e.g. an Inertia <Form>). The HTML `form` attribute on the submit
     * button works across the Radix dialog portal since it targets by id,
     * not DOM nesting.
     */
    confirmForm?: string;
    /** Disables the confirm button itself (e.g. a required field is invalid). */
    confirmDisabled?: boolean;
};

/**
 * Confirmation modal for destructive or hard-to-reverse actions (approve,
 * reject, deactivate). Standard per CLAUDE.md — every such action must
 * confirm before firing.
 */
export default function ConfirmDialog({
    trigger,
    title,
    description,
    confirmLabel,
    confirmVariant = 'default',
    triggerDisabled = false,
    onConfirm,
    confirmForm,
    confirmDisabled = false,
}: ConfirmDialogProps) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild disabled={triggerDisabled}>
                {trigger}
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            Cancel
                        </Button>
                    </DialogClose>
                    {onConfirm ? (
                        <Button
                            type="button"
                            variant={confirmVariant}
                            disabled={confirmDisabled}
                            onClick={() => {
                                onConfirm();
                                setOpen(false);
                            }}
                        >
                            {confirmLabel}
                        </Button>
                    ) : (
                        <Button
                            type="submit"
                            form={confirmForm}
                            variant={confirmVariant}
                            disabled={confirmDisabled}
                        >
                            {confirmLabel}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
