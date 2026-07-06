import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
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
};

type ActivityData = {
    id: number;
    name: string;
    venue: string;
    activity_date: string;
    start_time: string;
    end_time: string;
    description: string | null;
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
    actor: { name: string } | null;
    created_at: string;
};

type Props = {
    document: DocumentData;
    calendar: CalendarData;
    history: TransitionEntry[];
};

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    draft: 'outline',
    in_review: 'secondary',
    returned: 'outline',
    approved: 'default',
    rejected: 'destructive',
};

function statusLabel(status: string): string {
    return status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function actionLabel(action: string): string {
    return action.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
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
                        <h1 className="text-xl font-semibold">{document.title}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">{document.organization.name}</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant={statusVariant[document.status] ?? 'outline'}>
                            {statusLabel(document.status)}
                        </Badge>
                        {isReturned && (
                            <Button asChild size="sm">
                                <Link href={`/activity-calendars/${document.id}/edit`}>
                                    Edit &amp; Resubmit
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {/* Activities table */}
                {calendar && (
                    <Card>
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
