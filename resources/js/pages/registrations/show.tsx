import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import * as registrations from '@/routes/registrations';

type Organization = { id: number; name: string };

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
    description: string;
    contact_person: string;
    contact_number: string;
    contact_email: string;
    date_organized: string;
    adviser: { name: string } | null;
    roster: string[] | null;
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
    detail: DetailData;
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

export default function ShowRegistration({ document, detail, history }: Props) {
    useDocumentUpdates(['document', 'detail', 'history']);

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
                                <Link href={registrations.edit(document.id)}>
                                    Edit &amp; Resubmit
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {/* Detail card */}
                {detail && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Registration Details</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3 text-sm">
                            <Row label="Organization Type" value={detail.organization_type_label} />
                            <Row label="Contact Person" value={detail.contact_person} />
                            <Row label="Contact Number" value={detail.contact_number} />
                            <Row label="Contact Email" value={detail.contact_email} />
                            <Row label="Date Organized" value={detail.date_organized} />
                            {detail.adviser && (
                                <Row label="Adviser" value={detail.adviser.name} />
                            )}
                            <div className="grid gap-1">
                                <span className="font-medium text-muted-foreground">Description</span>
                                <p className="whitespace-pre-wrap">{detail.description}</p>
                            </div>
                            {detail.roster && detail.roster.length > 0 && (
                                <div className="grid gap-1">
                                    <span className="font-medium text-muted-foreground">Roster</span>
                                    <ul className="list-disc pl-4">
                                        {detail.roster.map((name, i) => (
                                            <li key={i}>{name}</li>
                                        ))}
                                    </ul>
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

ShowRegistration.layout = {
    breadcrumbs: [{ title: 'Registrations' }, { title: 'View' }],
};
