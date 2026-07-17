import { Head, Link } from '@inertiajs/react';
import { StatusBadge, statusBorderClass } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useDocumentUpdates } from '@/hooks/use-document-updates';

type DocumentData = {
    id: number;
    title: string;
    status: string;
    current_step_position: number | null;
    submitted_by: number | null;
    organization: { id: number; name: string };
    rso_name: string;
    date_received: string;
};

type ActivityData = {
    id: number;
    name: string;
    venue: string;
    activity_date: string;
    start_time: string;
    end_time: string;
    description: string | null;
    sdg_label: string | null;
    participant_program_assigned: string | null;
    budget: string | null;
};

type CalendarData = {
    academic_year: string;
    term: string;
    term_label: string;
    activities: ActivityData[];
} | null;

type TransitionEntry = {
    id: number;
    action: string;
    from_status: string | null;
    to_status: string;
    step_position: number | null;
    comment: string | null;
    flagged_sections: string[] | null;
    actor: { name: string } | null;
    created_at: string;
};

type Props = {
    document: DocumentData;
    calendar: CalendarData;
    history: TransitionEntry[];
};

function actionLabel(action: string): string {
    return action.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

/**
 * Activity Calendar has no static section registry (see App\Approval\
 * SectionFlags) — raw "activity_N" keys are resolved to "Activity N+1"
 * directly here instead of a server-side label lookup.
 */
function calendarFlagLabel(key: string): string {
    const match = key.match(/^activity_(\d+)$/);

    return match ? `Activity ${Number(match[1]) + 1}` : key;
}

export default function ShowActivityCalendar({ document, calendar, history }: Props) {
    useDocumentUpdates(['document', 'calendar', 'history']);

    const isReturned = document.status === 'returned';

    return (
        <>
            <Head title={document.title} />

            <div className="mx-auto max-w-3xl space-y-6 p-8">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-balance">{document.title}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">{document.organization.name}</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <StatusBadge status={document.status} />
                        {isReturned && (
                            <Button asChild size="sm">
                                <Link href={`/activity-calendars/${document.id}/edit`}>
                                    Edit & Resubmit
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {/* RSO Name / Date Received (Phase 2 item 7 slice 1) — derived,
                    document-level values, shown once rather than per activity row. */}
                <div className="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm text-muted-foreground">
                    <span>
                        <span className="font-medium text-foreground">RSO Name:</span> {document.rso_name}
                    </span>
                    <span>
                        <span className="font-medium text-foreground">Date Received:</span>{' '}
                        {new Date(document.date_received).toLocaleDateString()}
                    </span>
                </div>

                {/* Activities table */}
                {calendar && (
                    <Card className={`border-l-4 ${statusBorderClass(document.status)}`}>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {calendar.term_label} {calendar.academic_year} — Activities
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {calendar.activities.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No activities.</p>
                            ) : (
                                <div className="divide-y">
                                    {calendar.activities.map((a) => (
                                        <div key={a.id} className="py-3">
                                            <p className="font-medium">{a.name}</p>
                                            <p className="text-sm text-muted-foreground">
                                                {a.venue} · {a.activity_date} · {a.start_time}–{a.end_time}
                                            </p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {a.sdg_label && <>SDG: {a.sdg_label} · </>}
                                                {a.participant_program_assigned && (
                                                    <>Participant/Program Assigned: {a.participant_program_assigned} · </>
                                                )}
                                                {a.budget && <>Budget: ₱{a.budget}</>}
                                            </p>
                                            {a.description && (
                                                <p className="mt-1 text-sm">{a.description}</p>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Revision history */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Revision History</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ol className="relative border-l border-border pl-4">
                            {history.map((entry) => (
                                <li key={entry.id} className="mb-4 ml-2">
                                    <div className="flex items-center gap-2">
                                        <span className="font-medium">{actionLabel(entry.action)}</span>
                                        {entry.actor && (
                                            <span className="text-sm text-muted-foreground">
                                                — {entry.actor.name}
                                            </span>
                                        )}
                                    </div>
                                    {entry.comment && (
                                        <p className="mt-1 text-sm text-muted-foreground">"{entry.comment}"</p>
                                    )}
                                    {entry.flagged_sections && entry.flagged_sections.length > 0 && (
                                        <p className="mt-1 text-xs text-destructive">
                                            Flagged: {entry.flagged_sections.map(calendarFlagLabel).join(', ')}
                                        </p>
                                    )}
                                    <time className="text-xs text-muted-foreground">
                                        {new Date(entry.created_at).toLocaleString()}
                                    </time>
                                </li>
                            ))}
                        </ol>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ShowActivityCalendar.layout = {
    breadcrumbs: [{ title: 'Activity Calendars' }, { title: 'View' }],
};
