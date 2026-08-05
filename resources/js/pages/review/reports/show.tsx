import { Form, Head, router } from '@inertiajs/react';
import AfterActivityReportReviewController from '@/actions/App/Http/Controllers/AfterActivityReportReviewController';
import type { AttachmentSlotDef, ExistingAttachment } from '@/components/attachment-slot-field';
import AttachmentsCard from '@/components/attachments-card';
import ConfirmDialog from '@/components/confirm-dialog';
import type { ConfirmActions } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import PrintFormButton from '@/components/print-form-button';
import SectionFlagFields from '@/components/section-flag-fields';
import type {SectionFlagDef} from '@/components/section-flag-fields';
import { StatusBadge, statusBorderClass } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DialogClose, DialogFooter } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import * as reviewReports from '@/routes/review/reports';
import type { FlaggedSectionLabels, TransitionEntry } from '@/types';

type Organization = { id: number; name: string };

type DocumentData = {
    id: number;
    title: string;
    status: string;
    current_step_position: number | null;
    organization: Organization;
    date_submitted: string;
};

type ReportData = {
    summary: string;
    outcomes: string | null;
    participant_count: number | null;
    activity_chairs: string[] | null;
    prepared_by: string | null;
    event_program: string | null;
    target_participants_percentage: number | null;
    activity: {
        title: string;
        venue: string | null;
        activity_date: string | null;
        start_time: string | null;
        end_time: string | null;
    } | null;
} | null;

type StepApproval = { user_id: number; name: string };

type Props = {
    document: DocumentData;
    report: ReportData;
    attachmentSlots: AttachmentSlotDef[];
    attachments: Record<string, ExistingAttachment[]>;
    history: TransitionEntry[];
    flaggedSectionLabels: FlaggedSectionLabels;
    sectionFlags: SectionFlagDef[];
    currentStepApprovals: StepApproval[];
    hasApproved: boolean;
};

function actionLabel(action: string): string {
    return action.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

/**
 * Remediation-phase fix: approvers now retain read access to a report
 * after it leaves In Review (rejected, approved, or returned) instead of
 * hitting a 403 — see DocumentPolicy::hasActedOn(). This explains why no
 * review actions appear, mirroring the existing "already approved, waiting"
 * note below rather than leaving the page silent once status changes.
 */
function reviewOnlyStatusNote(status: string): string {
    switch (status) {
        case 'approved':
            return 'This report has been approved. No further action is available.';
        case 'rejected':
            return 'This report was rejected and is now closed. The organization must submit a new report to proceed.';
        case 'returned':
            return 'This report was returned for revision. It will reappear here once the student resubmits.';
        default:
            return 'This report is no longer awaiting your review.';
    }
}

export default function ReviewReportShow({
    document,
    report,
    attachmentSlots,
    attachments,
    history,
    flaggedSectionLabels,
    sectionFlags,
    currentStepApprovals,
    hasApproved,
}: Props) {
    useDocumentUpdates(['document', 'report', 'attachments', 'history', 'currentStepApprovals', 'hasApproved']);

    const isInReview = document.status === 'in_review';

    function handleApprove({ close, stopProcessing }: ConfirmActions) {
        router.post(reviewReports.approve.url(document.id), {}, {
            preserveScroll: true,
            onSuccess: close,
            onFinish: stopProcessing,
        });
    }

    return (
        <>
            <Head title={`Review: ${document.title}`} />

            <div className="max-w-3xl space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-balance">{document.title}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {document.organization.name}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <StatusBadge status={document.status} />
                        <PrintFormButton documentId={document.id} />
                    </div>
                </div>

                {/* Date Submitted (Phase 2 item 7 slice 3) — derived. */}
                <p className="text-sm text-muted-foreground">
                    <span className="font-medium text-foreground">Date Submitted:</span>{' '}
                    {new Date(document.date_submitted).toLocaleDateString()}
                </p>

                {/* Dual-SDAO quorum state */}
                {isInReview && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Quorum Status</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            {currentStepApprovals.length === 0 ? (
                                <p className="text-muted-foreground">
                                    Neither SDAO member has approved yet.
                                </p>
                            ) : (
                                <p>
                                    Approved by:{' '}
                                    {currentStepApprovals.map((a) => a.name).join(', ')}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Report card */}
                {report && (
                    <Card className={`border-l-4 ${statusBorderClass(document.status)}`}>
                        <CardHeader>
                            <CardTitle className="text-base">Report Details</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3 text-sm">
                            {report.activity && (
                                <Row label="Name of Event" value={report.activity.title} />
                            )}
                            {report.activity?.venue && (
                                <Row label="Venue" value={report.activity.venue} />
                            )}
                            {report.activity?.activity_date && (
                                <Row
                                    label="Date and Time of Event"
                                    value={`${report.activity.activity_date} · ${report.activity.start_time}–${report.activity.end_time}`}
                                />
                            )}
                            {report.prepared_by && (
                                <Row label="Prepared By" value={report.prepared_by} />
                            )}
                            {report.activity_chairs && report.activity_chairs.length > 0 && (
                                <div className="grid gap-1">
                                    <span className="font-medium text-muted-foreground">Activity Chair/s</span>
                                    <ul className="list-disc pl-4">
                                        {report.activity_chairs.map((chair, i) => (
                                            <li key={i}>{chair}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                            {report.participant_count !== null && (
                                <Row label="Participants" value={String(report.participant_count)} />
                            )}
                            {report.target_participants_percentage !== null && (
                                <Row
                                    label="Activity Evaluation Report — % Target Participants"
                                    value={`${report.target_participants_percentage}%`}
                                />
                            )}
                            <div className="grid gap-1">
                                <span className="font-medium text-muted-foreground">Summary</span>
                                <p className="whitespace-pre-wrap">{report.summary}</p>
                            </div>
                            {report.event_program && (
                                <div className="grid gap-1">
                                    <span className="font-medium text-muted-foreground">Program</span>
                                    <p className="whitespace-pre-wrap">{report.event_program}</p>
                                </div>
                            )}
                            {report.outcomes && (
                                <div className="grid gap-1">
                                    <span className="font-medium text-muted-foreground">Outcomes</span>
                                    <p className="whitespace-pre-wrap">{report.outcomes}</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                <AttachmentsCard slots={attachmentSlots} files={attachments} />

                {/* Review actions */}
                {isInReview && !hasApproved && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Review Actions</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Approve */}
                            <ConfirmDialog
                                trigger={<Button className="w-full sm:w-auto">Approve</Button>}
                                title="Approve this report?"
                                description="This action is irreversible once the SDAO quorum is met."
                                confirmLabel="Confirm Approval"
                                onConfirm={handleApprove}
                            />

                            {/* Return for revision */}
                            <Form
                                {...AfterActivityReportReviewController.return.form({ document: document.id })}
                                className="space-y-2 border-t pt-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <p className="text-sm font-medium">Return for Revision</p>
                                        <Textarea
                                            name="comment"
                                            placeholder="Explain what the student needs to revise…"
                                            rows={3}
                                            required
                                        />
                                        <InputError message={errors.comment} />
                                        <SectionFlagFields sections={sectionFlags} />
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            Return
                                        </Button>
                                    </>
                                )}
                            </Form>

                            {/* Reject — the reason field lives inside the dialog (not
                                crossing the Radix portal via a form id) so a blank-comment
                                validation error is visible while the dialog is still open,
                                instead of rendering behind it. */}
                            <div className="border-t pt-4">
                                <p className="mb-2 text-sm font-medium text-destructive">Reject (permanent)</p>
                                <ConfirmDialog
                                    trigger={
                                        <Button type="button" variant="destructive">
                                            Reject
                                        </Button>
                                    }
                                    title="Reject this report?"
                                    description="This is permanent — the student cannot revive this document. They must file a brand-new report."
                                >
                                    {(close) => (
                                        <Form
                                            {...AfterActivityReportReviewController.reject.form({ document: document.id })}
                                            options={{ preserveScroll: true }}
                                            onSuccess={close}
                                        >
                                            {({ processing, errors }) => (
                                                <>
                                                    <Textarea
                                                        name="comment"
                                                        placeholder="Reason for rejection…"
                                                        rows={3}
                                                        required
                                                    />
                                                    <InputError message={errors.comment} />
                                                    <DialogFooter className="mt-4 gap-2">
                                                        <DialogClose asChild>
                                                            <Button type="button" variant="secondary" disabled={processing}>
                                                                Cancel
                                                            </Button>
                                                        </DialogClose>
                                                        <Button type="submit" variant="destructive" loading={processing}>
                                                            Reject
                                                        </Button>
                                                    </DialogFooter>
                                                </>
                                            )}
                                        </Form>
                                    )}
                                </ConfirmDialog>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {isInReview && hasApproved && (
                    <p className="text-sm text-muted-foreground">
                        You have already approved this step. Waiting for the other SDAO member.
                    </p>
                )}

                {!isInReview && (
                    <p className="text-sm text-muted-foreground">{reviewOnlyStatusNote(document.status)}</p>
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
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            "{entry.comment}"
                                        </p>
                                    )}
                                    {entry.flagged_sections && entry.flagged_sections.length > 0 && (
                                        <p className="mt-1 text-xs text-destructive">
                                            Flagged: {entry.flagged_sections.map((key) => flaggedSectionLabels[key] ?? key).join(', ')}
                                        </p>
                                    )}
                                    {entry.section_comments && Object.keys(entry.section_comments).length > 0 && (
                                        <ul className="mt-1 space-y-0.5 text-xs text-destructive">
                                            {Object.entries(entry.section_comments).map(([key, note]) => (
                                                <li key={key}>
                                                    <span className="font-medium">{flaggedSectionLabels[key] ?? key}:</span> {note}
                                                </li>
                                            ))}
                                        </ul>
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

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="grid grid-cols-3 gap-2">
            <span className="font-medium text-muted-foreground">{label}</span>
            <span className="col-span-2">{value}</span>
        </div>
    );
}

ReviewReportShow.layout = {
    breadcrumbs: [
        { title: 'Review' },
        { title: 'Reports', href: reviewReports.index() },
        { title: 'Review' },
    ],
};
