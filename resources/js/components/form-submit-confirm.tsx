import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import type { ConfirmActions } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';

type Props = {
    /** Id of the <form> the confirmed submit goes to. */
    formId: string;
    /** The form's `processing` flag, so the dialog stays up until the request settles. */
    processing: boolean;
    title: string;
    description: ReactNode;
    confirmLabel: string;
    /** Content of the button that opens the dialog. */
    children: ReactNode;
    triggerDisabled?: boolean;
};

/**
 * "Submit for review" asks first (CLAUDE.md: confirm before a hard-to-reverse
 * action). Confirming submits the real form natively, so the server's
 * validation, the file uploads and the redirect work exactly as before; the
 * dialog closes when the request settles, success or validation failure, so
 * the inline errors are visible behind it.
 */
export default function FormSubmitConfirm({
    formId,
    processing,
    title,
    description,
    confirmLabel,
    children,
    triggerDisabled,
}: Props) {
    const [open, setOpen] = useState(false);
    const actions = useRef<ConfirmActions | null>(null);
    const awaitingResult = useRef(false);

    // Runs when the form's request settles (processing flips back to false).
    useEffect(() => {
        if (!processing && awaitingResult.current) {
            awaitingResult.current = false;
            actions.current?.close();
        }
    }, [processing]);

    return (
        <ConfirmDialog
            open={open}
            onOpenChange={setOpen}
            trigger={
                <Button type="button" disabled={triggerDisabled}>
                    {children}
                </Button>
            }
            title={title}
            description={description}
            confirmLabel={confirmLabel}
            onConfirm={(confirmActions) => {
                actions.current = confirmActions;
                awaitingResult.current = true;
                (document.getElementById(formId) as HTMLFormElement | null)?.requestSubmit();
            }}
        />
    );
}
