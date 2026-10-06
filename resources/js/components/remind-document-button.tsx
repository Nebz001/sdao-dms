import { router } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import ConfirmDialog from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import * as stuckDocuments from '@/routes/admin/stuck-documents';

/**
 * "Remind" for one stuck document: asks first (saying who will get it), then
 * posts to the server, which sends the email and the in-app notification and
 * shows the success toast. While a reminder is on its 24 hour cooldown the
 * button is disabled and says when the next one can go.
 */
export default function RemindDocumentButton({
    documentId,
    documentTitle,
    remindTo,
    availableLabel,
}: {
    documentId: number;
    documentTitle: string;
    /** Who gets it, in words: "Marvin Atanacio", "the president and secretary of PICE". */
    remindTo: string;
    /** When the next reminder can be sent, or null when one can go now. */
    availableLabel: string | null;
}) {
    const onCooldown = availableLabel !== null;
    const noteId = `remind-note-${documentId}`;

    return (
        <div className="flex flex-col items-end gap-1 max-md:items-stretch">
            <ConfirmDialog
                trigger={
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={onCooldown}
                        aria-describedby={onCooldown ? noteId : undefined}
                    >
                        <Bell aria-hidden />
                        Remind<span className="sr-only"> about {documentTitle}</span>
                    </Button>
                }
                title="Send a reminder?"
                description={`We will email ${remindTo} and add a notification in the app about "${documentTitle}". You can send one reminder per document every 24 hours.`}
                confirmLabel="Send reminder"
                onConfirm={({ close, stopProcessing }) =>
                    router.post(
                        stuckDocuments.remind(documentId).url,
                        {},
                        {
                            preserveScroll: true,
                            onSuccess: close,
                            onFinish: stopProcessing,
                        },
                    )
                }
            />
            {onCooldown && (
                <span id={noteId} className="text-xs text-muted-foreground">
                    Again after {availableLabel}
                </span>
            )}
        </div>
    );
}
