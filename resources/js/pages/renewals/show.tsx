import { Head, Link } from '@inertiajs/react';
import type { AttachmentSlotDef, ExistingAttachment } from '@/components/attachment-slot-field';
import AttachmentsCard from '@/components/attachments-card';
import { StatusBadge, statusBorderClass } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import * as renewals from '@/routes/renewals';

type Organization = { id: number; name: string; college: string | null; program: string | null };

type DocumentData = {
    id: number;
    title: string;
    status: string;
    current_step_position: number | null;
    submitted_by: number | null;
    organization: Organization;
};

type DetailData = {
    organization_type: string;
    organization_type_label: string;
    purpose_of_organization: string;
    contact_person: string;
    contact_no: string;
    email_address: string;
    date_organized: string;
    adviser: { name: string } | null;
    academic_year: string | null;
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
    detail: DetailData;
    attachmentSlots: AttachmentSlotDef[];
    attachments: Record<string, ExistingAttachment[]>;
    history: TransitionEntry[];
    flaggedSectionLabels: Record<string, string>;
};

function actionLabel(action: string): string {
    return action.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export default function ShowRenewal({ document, detail, attachmentSlots, attachments, history, flaggedSectionLabels }: Props) {
    useDocumentUpdates(['document', 'detail', 'attachments', 'history']);

    const isReturned = document.status === 'returned';

    return (
        <>
            <Head title={document.title} />

            <div className="mx-auto max-w-3xl space-y-6 p-8">
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
                        {isReturned && (
                            <Button asChild size="sm">
                                <Link href={renewals.edit(document.id)}>
                                    Edit & Resubmit
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {/* Detail card */}
                {detail && (
                    <Card className={`border-l-4 ${statusBorderClass(document.status)}`}>
                        <CardHeader>
                            <CardTitle className="text-base">Renewal Details</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3 text-sm">
                            {/* Field-presence parity (Phase 2 item 7 slice 2) */}
                            <Row label="Organization Name" value={document.organization.name} />
                            <Row label="College" value={document.organization.college ?? '—'} />
                            {document.organization.program && (
                                <Row label="Program" value={document.organization.program} />
                            )}
                            {detail.academic_year && (
                                <Row label="Academic Year" value={detail.academic_year} />
                            )}
                            <Row label="Type of Organization" value={detail.organization_type_label} />
                            <Row label="Contact Person" value={detail.contact_person} />
                            <Row label="Contact No." value={detail.contact_no} />
                            <Row label="Email Address" value={detail.email_address} />
                            <Row label="Date Organized" value={detail.date_organized} />
                            {detail.adviser && (
                                <Row label="Adviser" value={detail.adviser.name} />
                            )}
                            <div className="grid gap-1">
                                <span className="font-medium text-muted-foreground">Purpose of Organization</span>
                                <p className="whitespace-pre-wrap">{detail.purpose_of_organization}</p>
                            </div>
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
                                    {entry.flagged_sections && entry.flagged_sections.length > 0 && (
                                        <p className="mt-1 text-xs text-destructive">
                                            Flagged: {entry.flagged_sections.map((key) => flaggedSectionLabels[key] ?? key).join(', ')}
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

ShowRenewal.layout = {
    breadcrumbs: [{ title: 'Renewals' }, { title: 'View' }],
};
