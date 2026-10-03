import { ArrowUp, Ban, Check, FileText, Undo2, X } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { FieldChangeDiff } from '@/components/field-change-diff';
import { ToneBadge } from '@/components/status-badge';
import { Card, CardContent } from '@/components/ui/card';
import { toneFor } from '@/lib/status-tones';
import type { Tone } from '@/lib/status-tones';
import { cn } from '@/lib/utils';
import { CardHeading } from './details';
import { formatDateTime } from './format';
import type { HistoryEvent } from './types';

const ICONS: Record<string, LucideIcon> = {
    submitted: FileText,
    resubmitted: ArrowUp,
    approved: Check,
    advanced: Check,
    completed: Check,
    returned: Undo2,
    rejected: X,
    withdrawn: Ban,
};

const ACTION_NAMES: Record<string, string> = {
    submitted: 'Submitted',
    resubmitted: 'Resubmitted',
    approved: 'Approved',
    advanced: 'Advanced',
    completed: 'Completed',
    returned: 'Returned',
    rejected: 'Rejected',
    withdrawn: 'Withdrawn',
};

/** Same tint-and-border pairs the status badges use, so no new colors. */
const CIRCLE: Record<Tone, string> = {
    success: 'border-success/40 bg-success/10 text-success-foreground',
    info: 'border-info/40 bg-info/10 text-info-foreground',
    warning: 'border-warning/40 bg-warning/10 text-warning-foreground',
    destructive: 'border-destructive/40 bg-destructive/10 text-destructive-foreground',
    neutral: 'border-muted-foreground/40 bg-muted-foreground/10 text-foreground/75',
};

function actionName(action: string): string {
    return ACTION_NAMES[action] ?? action.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());
}

function EventRow({ event, last }: { event: HistoryEvent; last: boolean }) {
    const Icon = ICONS[event.action] ?? FileText;
    const tone = toneFor('action', event.action);
    const actor = event.actor ? [event.actor.name, event.actor.role].filter(Boolean).join(', ') : null;
    const when = formatDateTime(event.created_at);

    return (
        <li className="relative flex gap-4 pb-6 last:pb-0">
            {!last && <span aria-hidden className="absolute top-9 bottom-1 left-4 w-px -translate-x-1/2 bg-border" />}
            <span
                className={cn('z-10 flex size-8 shrink-0 items-center justify-center rounded-full border', CIRCLE[tone])}
            >
                <Icon className="size-4" aria-hidden />
            </span>
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <p className="flex flex-wrap items-baseline gap-x-2">
                    <span className="font-semibold">{actionName(event.action)}</span>
                    {actor && <span className="text-sm text-muted-foreground">{actor}</span>}
                </p>
                {when && (
                    <time dateTime={event.created_at ?? undefined} className="text-xs text-muted-foreground">
                        {when}
                    </time>
                )}

                {event.action === 'resubmitted' && <FieldChangeDiff changes={event.field_changes} />}

                {event.comment && (
                    <blockquote className="mt-2 rounded-md border-l-2 border-warning bg-muted/40 px-4 py-3 text-sm whitespace-pre-wrap">
                        {event.comment}
                    </blockquote>
                )}

                {event.flagged.length > 0 && (
                    <ul className="mt-1 flex flex-wrap gap-2" aria-label="Flagged sections">
                        {event.flagged.map((label) => (
                            <li key={label}>
                                <ToneBadge tone="warning" className="normal-case">
                                    Flagged: {label}
                                </ToneBadge>
                            </li>
                        ))}
                    </ul>
                )}

                {event.section_notes.length > 0 && (
                    <ul className="mt-1 flex flex-col gap-1 text-sm text-muted-foreground">
                        {event.section_notes.map((note) => (
                            <li key={note.label}>
                                <span className="font-medium text-foreground">{note.label}:</span> {note.note}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </li>
    );
}

/** One unified timeline of every transition on the document, newest first. */
export default function HistoryCard({ events }: { events: HistoryEvent[] }) {
    return (
        <Card>
            <CardHeading title="History" aside={`${events.length} ${events.length === 1 ? 'event' : 'events'}`} />
            <CardContent>
                {events.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Nothing has happened yet. Events appear here once the document is submitted.
                    </p>
                ) : (
                    <ol>
                        {events.map((event, index) => (
                            <EventRow key={event.id} event={event} last={index === events.length - 1} />
                        ))}
                    </ol>
                )}
            </CardContent>
        </Card>
    );
}
