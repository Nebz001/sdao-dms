import { Head, Link } from '@inertiajs/react';
import type { AttachmentSlotDef, ExistingAttachment } from '@/components/attachment-slot-field';
import AttachmentsCard from '@/components/attachments-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import * as reports from '@/routes/reports';

type Organization = { id: number; name: string };

type DocumentData = {
    id: number;
    title: string;
    status: string;
    current_step_position: number | null;
    submitted_by: number | null;
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
    report: ReportData;
    attachmentSlots: AttachmentSlotDef[];
    attachments: Record<string, ExistingAttachment[]>;
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

export default function ShowReport({ document, report, attachmentSlots, attachments, history }: Props) {
    useDocumentUpdates(['document', 'report', 'attachments', 'history']);

    const isReturned = document.status === 'returned';

    return (
        <>
            <Head title={document.title} />

            <div className="mx-auto max-w-3xl space-y-6 p-8">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">{document.title}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {document.organization.name}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant={statusVariant[document.status] ?? 'outline'}>
                            {statusLabel(document.status)}
                        </Badge>
                        {isReturned && (
                            <Button asChild size="sm">
                                <Link href={reports.edit(document.id)}>
                                    Edit &amp; Resubmit
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {/* Date Submitted (Phase 2 item 7 slice 3) — derived,
                    document-level value, shown once near Status. */}
                <p className="text-sm text-muted-foreground">
                    <span className="font-medium text-foreground">Date Submitted:</span>{' '}
                    {new Date(document.date_submitted).toLocaleDateString()}
                </p>

                {/* Report card */}
                {report && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Report Details</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3 text-sm">
                            {/* Name of Event / Date and Time of Event — derived
                                from the linked proposal/activity (Phase 2 item 7
                                slice 3), not duplicated storage. */}
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

ShowReport.layout = {
    breadcrumbs: [{ title: 'Reports' }, { title: 'View' }],
};
