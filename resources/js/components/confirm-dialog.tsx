import type { VariantProps } from 'class-variance-authority';
import { useState } from 'react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
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
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type ButtonVariant = VariantProps<typeof buttonVariants>['variant'];

/**
 * Handed to `onConfirm` so the caller can drive the dialog from the actual
 * request lifecycle instead of the click event. Call `close` from the
 * request's onSuccess (closes the dialog and clears the in-flight state).
 * Call `stopProcessing` from onFinish (or onError, if there's no separate
 * onFinish) so the confirm button re-enables and the dialog stays open to
 * show the error — without this, a failed request would leave the button
 * disabled forever. `remarks` is the trimmed text of the optional remarks
 * field (see `remarks` below), or an empty string when it is blank or the
 * dialog has no such field.
 */
export type ConfirmActions = {
    close: () => void;
    stopProcessing: () => void;
    remarks: string;
};

/** Opts a dialog into an optional "Remarks" textarea above the buttons. */
export type RemarksField = {
    maxLength: number;
    /** A server validation message for the field, e.g. `errors.comment`. */
    error?: string;
    label?: string;
    placeholder?: string;
};

type ConfirmDialogProps = {
    /**
     * The element that opens the dialog, e.g. a <Button>Approve</Button>.
     * Omit when the caller drives `open` externally instead (e.g. a
     * DropdownMenuItem, which can't safely nest a DialogTrigger — opening it
     * from an onSelect handler and controlling `open`/`onOpenChange` avoids
     * the Radix focus/close race between the two overlays).
     */
    trigger?: ReactNode;
    title: string;
    description: ReactNode;
    /**
     * Inline messages shown under the description: a caution (PageNotice
     * warning) or the error from a failed request (PageNotice destructive,
     * urgent). It is a sibling of the description, not inside it, because the
     * description renders a paragraph and a notice is a block.
     */
    notice?: ReactNode;
    /**
     * Adds an optional, labelled remarks textarea (used by Approve). Its text
     * is handed to `onConfirm` and cleared whenever the dialog closes.
     */
    remarks?: RemarksField;
    /** Disables the trigger itself (e.g. while a related mutation is in flight). */
    triggerDisabled?: boolean;
    /** Externally controlled open state — pairs with `onOpenChange`. Omit both to manage state internally (the default, used by every trigger-based caller). */
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    confirmLabel?: string;
    confirmVariant?: ButtonVariant;
    /** Disables the confirm button itself (e.g. a required field is invalid). */
    confirmDisabled?: boolean;
    /**
     * Programmatic confirm path (e.g. router.post/router.delete). The dialog
     * only closes when the caller calls `close` — normally from the
     * request's onSuccess — so a failed request leaves the dialog open with
     * its error visible instead of closing as if it had worked. Mutually
     * exclusive with `children` — provide exactly one.
     */
    onConfirm?: (actions: ConfirmActions) => void;
    /**
     * Fully custom body — e.g. an Inertia <Form> with its own fields and
     * footer (used for actions that need input, like a rejection reason).
     * Receives `close`, to be passed to the form's onSuccess. Mutually
     * exclusive with `onConfirm`/`confirmLabel` — provide exactly one.
     */
    children?: (close: () => void) => ReactNode;
};

/**
 * Confirmation modal for destructive or hard-to-reverse actions (approve,
 * reject, deactivate). Standard per CLAUDE.md — every such action must
 * confirm before firing.
 *
 * Dismissal is tied to the request lifecycle, not to the click that starts
 * it: closing happens only once the caller reports success. This is
 * deliberate — a synchronous close on click made the dialog lie about
 * whether the action actually happened.
 */
export default function ConfirmDialog({
    trigger,
    title,
    description,
    notice,
    remarks,
    triggerDisabled = false,
    open: openProp,
    onOpenChange: onOpenChangeProp,
    confirmLabel,
    confirmVariant = 'default',
    confirmDisabled = false,
    onConfirm,
    children,
}: ConfirmDialogProps) {
    const [internalOpen, setInternalOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [remarksText, setRemarksText] = useState('');

    const open = openProp ?? internalOpen;
    const setOpen = onOpenChangeProp ?? setInternalOpen;

    const close = () => {
        setProcessing(false);
        setRemarksText('');
        setOpen(false);
    };

    const stopProcessing = () => setProcessing(false);

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                // Ignore dismiss attempts (Escape, outside click) while a
                // request from the built-in confirm button is in flight —
                // `close`/`stopProcessing` are the only way out until it
                // resolves. Custom `children` content manages its own
                // processing state and isn't affected by this guard.
                if (processing && !next) {
                    return;
                }

                setOpen(next);
            }}
        >
            {trigger !== undefined && (
                <DialogTrigger asChild disabled={triggerDisabled}>
                    {trigger}
                </DialogTrigger>
            )}
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>
                {notice}
                {remarks && !children && (
                    <div className="flex flex-col gap-2">
                        <div className="flex items-baseline justify-between gap-3">
                            <Label htmlFor="confirm-remarks">
                                {remarks.label ?? 'Remarks'}{' '}
                                <span className="font-normal text-muted-foreground">(optional)</span>
                            </Label>
                            <span className="text-xs text-muted-foreground tabular-nums" aria-hidden>
                                {remarksText.length}/{remarks.maxLength}
                            </span>
                        </div>
                        <Textarea
                            id="confirm-remarks"
                            rows={3}
                            value={remarksText}
                            maxLength={remarks.maxLength}
                            disabled={processing}
                            placeholder={remarks.placeholder}
                            aria-invalid={remarks.error ? true : undefined}
                            onChange={(event) => setRemarksText(event.target.value)}
                        />
                        <InputError message={remarks.error} />
                    </div>
                )}
                {children ? (
                    children(close)
                ) : (
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary" disabled={processing}>
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            type="button"
                            variant={confirmVariant}
                            disabled={confirmDisabled}
                            loading={processing}
                            onClick={() => {
                                setProcessing(true);
                                onConfirm?.({
                                    close,
                                    stopProcessing,
                                    remarks: remarksText.trim(),
                                });
                            }}
                        >
                            {confirmLabel}
                        </Button>
                    </DialogFooter>
                )}
            </DialogContent>
        </Dialog>
    );
}
