import { router } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useEffect, useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import * as stuckDocuments from '@/routes/admin/stuck-documents';

/** Same wait as App\Approval\StuckDocumentReminders::COOLDOWN_HOURS; the server enforces it, this only words it. */
const COOLDOWN_HOURS = 12;

/** "5h", or "40m" under an hour. Rounded up, so the button never says 0. */
export function timeLeftLabel(msLeft: number): string {
    const minutes = Math.max(1, Math.ceil(msLeft / 60_000));

    return minutes >= 60 ? `${Math.ceil(minutes / 60)}h` : `${minutes}m`;
}

const KIND_WORDS: Record<string, string> = {
    manual: 'sent by an admin',
    automatic: 'sent automatically',
    overdue: 'sent automatically as an overdue alert',
};

/** Re-renders every `everyMs`, so a countdown stays honest on a page left open. */
function useNow(everyMs: number): number {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), everyMs);

        return () => window.clearInterval(timer);
    }, [everyMs]);

    return now;
}

/**
 * "Remind" for one stuck document: asks first (saying who will get it), then
 * posts to the server, which sends the email and the in-app notification and
 * shows the success toast. Any reminder to that step's approver, manual or
 * automatic, locks the button for 12 hours for every admin. While locked it is
 * disabled and reads "Remind again in 5h"; the tooltip says when the last
 * reminder went out. The server refuses a request made during the lock too, so
 * this is wording, never the guard.
 */
export default function RemindDocumentButton({
    documentId,
    documentTitle,
    remindTo,
    availableAt,
    lastSentLabel,
    lastSentKind,
}: {
    documentId: number;
    documentTitle: string;
    /** Who gets it, in words: "Marvin Atanacio", "the president and secretary of PICE". */
    remindTo: string;
    /** ISO time the button unlocks, or null when a reminder can go now. */
    availableAt: string | null;
    /** When the reminder that locked it was sent, in display time. */
    lastSentLabel: string | null;
    lastSentKind: string | null;
}) {
    const now = useNow(30_000);
    const msLeft = availableAt ? new Date(availableAt).getTime() - now : 0;
    const locked = msLeft > 0;
    const noteId = `remind-note-${documentId}`;

    if (locked) {
        const lastSent = lastSentLabel
            ? `The last reminder was ${KIND_WORDS[lastSentKind ?? ''] ?? 'sent'} on ${lastSentLabel}.`
            : 'A reminder was sent recently.';

        return (
            <div className="flex flex-col items-end gap-1 max-md:items-stretch">
                <Tooltip>
                    <TooltipTrigger asChild>
                        {/* A disabled button gets no pointer or focus events, so the span carries the tooltip and the focus stop. */}
                        <span tabIndex={0} className="inline-flex rounded-md focus-visible:focus-ring max-md:w-full" aria-describedby={noteId}>
                            <Button type="button" size="sm" variant="outline" disabled className="pointer-events-none max-md:w-full">
                                <Bell aria-hidden />
                                Remind again in {timeLeftLabel(msLeft)}
                                <span className="sr-only"> about {documentTitle}</span>
                            </Button>
                        </span>
                    </TooltipTrigger>
                    <TooltipContent>
                        {lastSent} Everyone is locked out for {COOLDOWN_HOURS} hours after any reminder.
                    </TooltipContent>
                </Tooltip>
                <span id={noteId} className="sr-only">
                    {lastSent}
                </span>
            </div>
        );
    }

    return (
        <ConfirmDialog
            trigger={
                <Button type="button" size="sm" variant="outline">
                    <Bell aria-hidden />
                    Remind<span className="sr-only"> about {documentTitle}</span>
                </Button>
            }
            title="Send a reminder?"
            description={`We will email ${remindTo} and add a notification in the app about "${documentTitle}". After this, no admin can send another reminder for this step for ${COOLDOWN_HOURS} hours. The automatic daily reminders are not affected.`}
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
    );
}
