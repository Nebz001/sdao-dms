import { Head, Link } from '@inertiajs/react';
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

type Props = {
    document: DocumentData;
    report: ReportData;
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

export default function ShowReport({ document, report, history }: Props) {
    useDocumentUpdates(['document', 'report', 'history']);

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
