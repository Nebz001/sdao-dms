import { Form, Head, router } from '@inertiajs/react';
import AfterActivityReportReviewController from '@/actions/App/Http/Controllers/AfterActivityReportReviewController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import * as reviewReports from '@/routes/review/reports';

type Organization = { id: number; name: string };

type DocumentData = {
    id: number;
    title: string;
    status: string;
    current_step_position: number | null;
    organization: Organization;
};

type ReportData = {
    narrative: string;
    outcomes: string | null;
    participant_count: number | null;
    activity: {
        title: string;
        venue: string | null;
        activity_date: string | null;
    } | null;
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

type StepApproval = { user_id: number; name: string };

type Props = {
    document: DocumentData;
    report: ReportData;
    history: TransitionEntry[];
    currentStepApprovals: StepApproval[];
    hasApproved: boolean;
};

function actionLabel(action: string): string {
    return action.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export default function ReviewReportShow({
    document,
    report,
    history,
    currentStepApprovals,
    hasApproved,
}: Props) {
    useDocumentUpdates(['document', 'report', 'history', 'currentStepApprovals', 'hasApproved']);

    const isInReview = document.status === 'in_review';

    function handleApprove() {
        router.post(reviewReports.approve.url(document.id));
    }

    return (
        <>
            <Head title={`Review: ${document.title}`} />

            <div className="mx-auto max-w-3xl space-y-6 p-8">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">{document.title}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {document.organization.name}
                        </p>
                    </div>
                    <Badge variant="secondary">
                        {document.status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())}
                    </Badge>
                </div>

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
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Report Details</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3 text-sm">
                            {report.activity && (
                                <Row label="Activity" value={report.activity.title} />
                            )}
                            {report.activity?.venue && (
                                <Row label="Venue" value={report.activity.venue} />
                            )}
                            {report.activity?.activity_date && (
                                <Row label="Activity Date" value={report.activity.activity_date} />
                            )}
                            {report.participant_count !== null && (
                                <Row label="Participants" value={String(report.participant_count)} />
                            )}
                            <div className="grid gap-1">
                                <span className="font-medium text-muted-foreground">Narrative</span>
                                <p className="whitespace-pre-wrap">{report.narrative}</p>
                            </div>
                            {report.outcomes && (
                                <div className="grid gap-1">
                                    <span className="font-medium text-muted-foreground">Outcomes</span>
                                    <p className="whitespace-pre-wrap">{report.outcomes}</p>
                                </div>
                            )}
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
                            <Button onClick={handleApprove} className="w-full sm:w-auto">
                                Approve
                            </Button>

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

                            {/* Reject */}
                            <Form
                                {...AfterActivityReportReviewController.reject.form({ document: document.id })}
                                className="space-y-2 border-t pt-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <p className="text-sm font-medium text-destructive">
                                            Reject (permanent)
                                        </p>
                                        <Textarea
                                            name="comment"
                                            placeholder="Reason for rejection…"
                                            rows={3}
                                            required
                                        />
                                        <InputError message={errors.comment} />
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing}
                                        >
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
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            "{entry.comment}"
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
