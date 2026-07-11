import { Form, Head, router } from '@inertiajs/react';
import CalendarSectionFlagFields from '@/components/calendar-section-flag-fields';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { useDocumentUpdates } from '@/hooks/use-document-updates';

type DocumentData = {
    id: number;
    title: string;
    status: string;
    current_step_position: number | null;
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
    comment: string | null;
    flagged_sections: string[] | null;
    actor: { name: string } | null;
    created_at: string;
};

type ConflictState = { confirmed: { name: string; organization: string }[] };

type StepApproval = { user_id: number; name: string };

type Props = {
    document: DocumentData;
    calendar: CalendarData;
    history: TransitionEntry[];
    currentStepApprovals: StepApproval[];
    hasApproved: boolean;
    activityConflicts: Record<number, ConflictState>;
    hasConfirmedConflict: boolean;
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

export default function ReviewActivityCalendarShow({
    document,
    calendar,
    history,
    currentStepApprovals,
    hasApproved,
    activityConflicts,
    hasConfirmedConflict,
}: Props) {
    useDocumentUpdates(['document', 'calendar', 'history', 'currentStepApprovals', 'hasApproved', 'activityConflicts', 'hasConfirmedConflict']);

    const isInReview = document.status === 'in_review';

    function handleApprove() {
        router.post(`/review/activity-calendars/${document.id}/approve`);
    }

    return (
        <>
            <Head title={`Review: ${document.title}`} />

            <div className="mx-auto max-w-3xl space-y-6 p-8">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">{document.title}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">{document.organization.name}</p>
                    </div>
                    <Badge variant="secondary">
                        {document.status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())}
                    </Badge>
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

                {/* Dual-SDAO quorum */}
                {isInReview && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Quorum Status</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            {currentStepApprovals.length === 0 ? (
                                <p className="text-muted-foreground">Neither SDAO member has approved yet.</p>
                            ) : (
                                <p>Approved by: {currentStepApprovals.map((a) => a.name).join(', ')}</p>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Activities with per-row conflict state */}
                {calendar && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {calendar.term_label} {calendar.academic_year} — Activities
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="divide-y">
                            {calendar.activities.map((a) => {
                                const conflict = activityConflicts[a.id];

                                return (
                                    <div key={a.id} className="py-3 space-y-1">
                                        <p className="font-medium">{a.name}</p>
                                        <p className="text-sm text-muted-foreground">
                                            {a.venue} · {a.activity_date} · {a.start_time}–{a.end_time}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {a.sdg_label && <>SDG: {a.sdg_label} · </>}
                                            {a.participant_program_assigned && (
                                                <>Participant/Program Assigned: {a.participant_program_assigned} · </>
                                            )}
                                            {a.budget && <>Budget: ₱{a.budget}</>}
                                        </p>
                                        {a.description && <p className="text-sm">{a.description}</p>}
                                        {conflict?.confirmed.length > 0 && (
                                            <div className="rounded-md bg-destructive/10 p-2 text-sm text-destructive">
                                                ⛔ Confirmed conflict:{' '}
                                                {conflict.confirmed.map((c) => `"${c.name}" (${c.organization})`).join(', ')}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>
                )}

                {/* Review actions */}
                {isInReview && !hasApproved && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Review Actions</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Approve */}
                            {hasConfirmedConflict ? (
                                <div className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                                    ⛔ Cannot approve: one or more activities conflict with an already-approved
                                    booking. Return the document to the submitter to resolve.
                                </div>
                            ) : (
                                <Button onClick={handleApprove} className="w-full sm:w-auto">
                                    Approve
                                </Button>
                            )}

                            {/* Return for revision */}
                            <Form
                                action={`/review/activity-calendars/${document.id}/return`}
                                method="post"
                                className="space-y-2 border-t pt-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <p className="text-sm font-medium">Return for Revision</p>
                                        <Textarea
                                            name="comment"
                                            placeholder="Explain what the submitter needs to revise…"
                                            rows={3}
                                            required
                                        />
                                        <InputError message={errors.comment} />
                                        <CalendarSectionFlagFields activities={calendar?.activities ?? []} />
                                        <Button type="submit" variant="outline" disabled={processing}>
                                            Return
                                        </Button>
                                    </>
                                )}
                            </Form>

                            {/* Reject */}
                            <Form
                                action={`/review/activity-calendars/${document.id}/reject`}
                                method="post"
                                className="space-y-2 border-t pt-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <p className="text-sm font-medium text-destructive">Reject (permanent)</p>
                                        <Textarea
                                            name="comment"
                                            placeholder="Reason for rejection…"
                                            rows={3}
                                            required
                                        />
                                        <InputError message={errors.comment} />
                                        <Button type="submit" variant="destructive" disabled={processing}>
                                            Reject
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}

                {isInReview && hasApproved && (
                    <p className="text-sm text-muted-foreground">
                        You have already approved this step. Waiting for the other SDAO member.
                    </p>
                )}

                {/* History */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Transition History</CardTitle>
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

ReviewActivityCalendarShow.layout = {
    breadcrumbs: [
        { title: 'Review' },
        { title: 'Activity Calendars', href: '/review/activity-calendars' },
        { title: 'Review' },
    ],
};
