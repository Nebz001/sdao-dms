import { Head, router } from '@inertiajs/react';
import ActivityCalendarReviewController from '@/actions/App/Http/Controllers/ActivityCalendarReviewController';
import ApprovalActionsCard from '@/components/approval-actions-card';
import CalendarSectionFlagFields from '@/components/calendar-section-flag-fields';
import type { ConfirmActions } from '@/components/confirm-dialog';
import { FieldChangeDiff } from '@/components/field-change-diff';
import PrintFormButton from '@/components/print-form-button';
import { StatusBadge, statusBorderClass } from '@/components/status-badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import { formatCalendarDate, formatTimeRange } from '@/lib/utils';
import * as reviewActivityCalendars from '@/routes/review/activity-calendars';
import type { FieldChanges } from '@/types/document-transitions';

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
    // Unlike section_comments (omitted here — no stable row identity across
    // a delete+recreate resubmit), field_changes IS safe: it's captured
    // fresh from the rows as they existed at the moment of resubmission,
    // never read back through a stale row id.
    field_changes: FieldChanges | null;
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
    errors?: Record<string, string>;
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

/**
 * Remediation-phase fix: approvers now retain read access to a calendar
 * after it leaves In Review (rejected, approved, or returned) instead of
 * hitting a 403 — see DocumentPolicy::hasActedOn(). This explains why no
 * review actions appear, mirroring the existing "already approved, waiting"
 * note below rather than leaving the page silent once status changes.
 */
function reviewOnlyStatusNote(status: string): string {
    switch (status) {
        case 'approved':
            return 'This activity calendar has been approved. No further action is available.';
        case 'rejected':
            return 'This activity calendar was rejected and is now closed. The organization must submit a new calendar to proceed.';
        case 'returned':
            return 'This activity calendar was returned for revision. It will reappear here once the student resubmits.';
        default:
            return 'This activity calendar is no longer awaiting your review.';
    }
}

export default function ReviewActivityCalendarShow({
    document,
    calendar,
    history,
    currentStepApprovals,
    hasApproved,
    activityConflicts,
    hasConfirmedConflict,
    errors = {},
}: Props) {
    useDocumentUpdates([
        'document',
        'calendar',
        'history',
        'currentStepApprovals',
        'hasApproved',
        'activityConflicts',
        'hasConfirmedConflict',
    ]);

    const isInReview = document.status === 'in_review';

    function handleApprove({ close, stopProcessing }: ConfirmActions) {
        router.post(
            reviewActivityCalendars.approve.url(document.id),
            {},
            {
                preserveScroll: true,
                onSuccess: close,
                onFinish: stopProcessing,
            },
        );
    }

    return (
        <>
            <Head title={`Review: ${document.title}`} />

            <div className="max-w-3xl space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-balance">
                            {document.title}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {document.organization.name}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <StatusBadge status={document.status} />
                        <PrintFormButton documentId={document.id} />
                    </div>
                </div>

                {/* RSO Name / Date Received (Phase 2 item 7 slice 1) — derived,
                    document-level values, shown once rather than per activity row. */}
                <div className="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm text-muted-foreground">
                    <span>
                        <span className="font-medium text-foreground">
                            RSO Name:
                        </span>{' '}
                        {document.rso_name}
                    </span>
                    <span>
                        <span className="font-medium text-foreground">
                            Date Received:
                        </span>{' '}
                        {new Date(document.date_received).toLocaleDateString()}
                    </span>
                </div>

                {/* Dual-SDAO quorum */}
                {isInReview && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Quorum Status
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            {currentStepApprovals.length === 0 ? (
                                <p className="text-muted-foreground">
                                    Neither SDAO member has approved yet.
                                </p>
                            ) : (
                                <p>
                                    Approved by:{' '}
                                    {currentStepApprovals
                                        .map((a) => a.name)
                                        .join(', ')}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Activities with per-row conflict state */}
                {calendar && (
                    <Card
                        className={`border-l-4 ${statusBorderClass(document.status)}`}
                    >
                        <CardHeader>
                            <CardTitle className="text-base">
                                {calendar.term_label} {calendar.academic_year} —
                                Activities
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="divide-y">
                            {calendar.activities.map((a) => {
                                const conflict = activityConflicts[a.id];

                                return (
                                    <div key={a.id} className="space-y-1 py-3">
                                        <p className="font-medium">{a.name}</p>
                                        <p className="text-sm text-muted-foreground">
                                            {a.venue} ·{' '}
                                            {formatCalendarDate(
                                                a.activity_date,
                                            )}{' '}
                                            ·{' '}
                                            {formatTimeRange(
                                                a.start_time,
                                                a.end_time,
                                            )}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {a.sdg_label && (
                                                <>SDG: {a.sdg_label} · </>
                                            )}
                                            {a.participant_program_assigned && (
                                                <>
                                                    Participant/Program
                                                    Assigned:{' '}
                                                    {
                                                        a.participant_program_assigned
                                                    }{' '}
                                                    ·{' '}
                                                </>
                                            )}
                                            {a.budget && (
                                                <>Budget: ₱{a.budget}</>
                                            )}
                                        </p>
                                        {a.description && (
                                            <p className="text-sm">
                                                {a.description}
                                            </p>
                                        )}
                                        {conflict?.confirmed.length > 0 && (
                                            <div className="rounded-md bg-destructive/10 p-2 text-sm text-destructive">
                                                ⛔ Confirmed conflict:{' '}
                                                {conflict.confirmed
                                                    .map(
                                                        (c) =>
                                                            `"${c.name}" (${c.organization})`,
                                                    )
                                                    .join(', ')}
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
                    <ApprovalActionsCard
                        title="Review Actions"
                        approve={{
                            blocked: hasConfirmedConflict ? (
                                <div className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                                    ⛔ Cannot approve: one or more activities
                                    conflict with an already-approved booking.
                                    Return the document to the submitter to
                                    resolve.
                                </div>
                            ) : undefined,
                            confirmTitle: 'Approve this activity calendar?',
                            confirmDescription: (
                                <>
                                    This action is irreversible once the SDAO
                                    quorum is met — every listed activity
                                    becomes an approved, venue-blocking booking.
                                    {errors.approve && (
                                        <span className="mt-2 block text-destructive">
                                            {errors.approve}
                                        </span>
                                    )}
                                </>
                            ),
                            onConfirm: handleApprove,
                        }}
                        return={{
                            formProps:
                                ActivityCalendarReviewController.return.form({
                                    document: document.id,
                                }),
                            placeholder:
                                'Explain what the submitter needs to revise…',
                            flagFields: (
                                <CalendarSectionFlagFields
                                    activities={calendar?.activities ?? []}
                                />
                            ),
                        }}
                        reject={{
                            formProps:
                                ActivityCalendarReviewController.reject.form({
                                    document: document.id,
                                }),
                            confirmTitle: 'Reject this activity calendar?',
                            confirmDescription:
                                'This is permanent — the submitter cannot revive this document. They must file a brand-new calendar submission.',
                        }}
                    />
                )}

                {isInReview && hasApproved && (
                    <p className="text-sm text-muted-foreground">
                        You have already approved this step. Waiting for the
                        other SDAO member.
                    </p>
                )}

                {!isInReview && (
                    <p className="text-sm text-muted-foreground">
                        {reviewOnlyStatusNote(document.status)}
                    </p>
                )}

                {/* History */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Transition History
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ol className="relative border-l border-border pl-4">
                            {history.map((entry) => (
                                <li key={entry.id} className="mb-4 ml-2">
                                    <div className="flex items-center gap-2">
                                        <span className="font-medium">
                                            {actionLabel(entry.action)}
                                        </span>
                                        {entry.actor && (
                                            <span className="text-sm text-muted-foreground">
                                                — {entry.actor.name}
                                            </span>
                                        )}
                                    </div>
                                    {entry.comment && (
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            "{entry.comment}"
                                        </p>
                                    )}
                                    {entry.flagged_sections &&
                                        entry.flagged_sections.length > 0 && (
                                            <p className="mt-1 text-xs text-destructive">
                                                Flagged:{' '}
                                                {entry.flagged_sections
                                                    .map(calendarFlagLabel)
                                                    .join(', ')}
                                            </p>
                                        )}
                                    {entry.action === 'resubmitted' &&
                                        entry.field_changes && (
                                            <FieldChangeDiff
                                                changes={
                                                    entry.field_changes
                                                }
                                            />
                                        )}
                                    <time className="text-xs text-muted-foreground">
                                        {new Date(
                                            entry.created_at,
                                        ).toLocaleString()}
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
        { title: 'Activity Calendars', href: reviewActivityCalendars.index() },
        { title: 'Review' },
    ],
};
