import { Head, Link, usePage } from '@inertiajs/react';
import type { AttachmentSlotDef, ExistingAttachment } from '@/components/attachment-slot-field';
import AttachmentsCard from '@/components/attachments-card';
import { StatusBadge, statusBorderClass } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import * as activityProposals from '@/routes/activity-proposals';

type DocumentData = {
    id: number;
    title: string;
    status: string;
    current_step_position: number | null;
    submitted_by: number | null;
    organization: { id: number; name: string };
};

type ProposalData = {
    id: number;
    calendar_mode: string;
    title: string;
    objectives: string | null;
    narrative: string | null;
    criteria_mechanics: string | null;
    program_flow: string | null;
    source_of_funding: string | null;
    expenses: string | null;
    proposed_budget: string | null;
    form_step: number;
    activity_nature_label: string | null;
    activity_type_label: string | null;
    partner_organizations: string[] | null;
    target_sdg_label: string | null;
    budget_source: string | null;
} | null;

type ActivityData = {
    id: number;
    name: string;
    venue: string;
    activity_date: string;
    start_time: string;
    end_time: string;
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
    proposal: ProposalData;
    activity: ActivityData;
    attachmentSlots: AttachmentSlotDef[];
    attachments: Record<string, ExistingAttachment[]>;
    history: TransitionEntry[];
    flaggedSectionLabels: Record<string, string>;
    flash?: { message?: string; warnings?: { conflicts: object[] }[] };
};

function actionLabel(action: string): string {
    return action.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export default function ShowActivityProposal({
    document: doc,
    proposal,
    activity,
    attachmentSlots,
    attachments,
    history,
    flaggedSectionLabels,
    flash,
}: Props) {
    const { auth } = usePage<{ auth: { user: { id: number } } }>().props;

    useDocumentUpdates(['document', 'proposal', 'activity', 'attachments', 'history']);

    const isDraft = doc.status === 'draft';
    const isReturned = doc.status === 'returned';
    const isOwnDoc = doc.submitted_by === auth?.user?.id;

    return (
        <>
            <Head title={doc.title} />

            <div className="mx-auto max-w-3xl space-y-6 p-8">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-balance">{doc.title}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            <span className="font-medium">Name of RSO:</span> {doc.organization.name}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <StatusBadge status={doc.status} />
                        {isDraft && isOwnDoc && (
                            <Button asChild size="sm">
                                <Link href={activityProposals.continueMethod({ document: doc.id }).url}>
                                    Continue Narrative
                                </Link>
                            </Button>
                        )}
                        {isReturned && isOwnDoc && (
                            <Button asChild size="sm">
                                <Link href={activityProposals.edit({ document: doc.id }).url}>
                                    Edit & Resubmit
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {/* Flash warnings */}
                {flash?.warnings && flash.warnings.length > 0 && (
                    <Card className="border-warning/40 bg-warning/10">
                        <CardContent className="pt-4">
                            <p className="text-sm font-medium text-warning-foreground">
                                Submitted, but a possible venue conflict was detected:
                            </p>
                        </CardContent>
                    </Card>
                )}

                {/* Activity summary */}
                {activity && proposal && (
                    <Card className={`border-l-4 ${statusBorderClass(doc.status)}`}>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Activity{' '}
                                <span className="text-xs font-normal text-muted-foreground">
                                    ({proposal.calendar_mode === 'on_calendar' ? 'On Calendar' : 'Off Calendar'})
                                </span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            <p className="font-medium">{activity.name}</p>
                            <p className="text-muted-foreground">
                                {activity.venue} · {activity.activity_date} · {activity.start_time}–{activity.end_time}
                            </p>
                        </CardContent>
                    </Card>
                )}

                {/* Activity Request Form fields (Phase 2 item 7 slice 4a) */}
                {proposal && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Activity Request Form</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3 text-sm">
                            {proposal.activity_nature_label && (
                                <Row label="Nature of Activity" value={proposal.activity_nature_label} />
                            )}
                            {proposal.activity_type_label && (
                                <Row label="Type of Activity" value={proposal.activity_type_label} />
                            )}
                            {proposal.partner_organizations && proposal.partner_organizations.length > 0 && (
                                <div className="grid gap-1">
                                    <span className="font-medium text-muted-foreground">
                                        Partner Organization(s)/School(s)/RSO
                                    </span>
                                    <ul className="list-disc pl-4">
                                        {proposal.partner_organizations.map((org, i) => (
                                            <li key={i}>{org}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                            {proposal.target_sdg_label && (
                                <Row label="Target SDG" value={proposal.target_sdg_label} />
                            )}
                            {proposal.proposed_budget && (
                                <Row label="Proposed Budget" value={`₱${proposal.proposed_budget}`} />
                            )}
                            {proposal.budget_source && (
                                <Row label="Budget Source" value={proposal.budget_source} />
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Narrative */}
                {proposal && (proposal.objectives || proposal.narrative) && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Proposal Narrative</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            {proposal.objectives && (
                                <div>
                                    <p className="mb-1 font-medium">Objectives</p>
                                    <p className="whitespace-pre-wrap text-muted-foreground">{proposal.objectives}</p>
                                </div>
                            )}
                            {proposal.narrative && (
                                <div>
                                    <p className="mb-1 font-medium">Narrative</p>
                                    <p className="whitespace-pre-wrap text-muted-foreground">{proposal.narrative}</p>
                                </div>
                            )}
                            {proposal.criteria_mechanics && (
                                <div>
                                    <p className="mb-1 font-medium">Criteria/Mechanics</p>
                                    <p className="whitespace-pre-wrap text-muted-foreground">{proposal.criteria_mechanics}</p>
                                </div>
                            )}
                            {proposal.program_flow && (
                                <div>
                                    <p className="mb-1 font-medium">Program Flow</p>
                                    <p className="whitespace-pre-wrap text-muted-foreground">{proposal.program_flow}</p>
                                </div>
                            )}
                            {proposal.source_of_funding && (
                                <div>
                                    <p className="mb-1 font-medium">Source of Funding</p>
                                    <p className="whitespace-pre-wrap text-muted-foreground">{proposal.source_of_funding}</p>
                                </div>
                            )}
                            {proposal.expenses && (
                                <div>
                                    <p className="mb-1 font-medium">Expenses</p>
                                    <p className="whitespace-pre-wrap text-muted-foreground">{proposal.expenses}</p>
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
                        {history.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No history yet.</p>
                        ) : (
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
                                                Flagged: {entry.flagged_sections.map((key) => flaggedSectionLabels[key] ?? key).join(', ')}
                                            </p>
                                        )}
                                        <time className="text-xs text-muted-foreground">
                                            {new Date(entry.created_at).toLocaleString()}
                                        </time>
                                    </li>
                                ))}
                            </ol>
                        )}
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

ShowActivityProposal.layout = {
    breadcrumbs: [{ title: 'Activity Proposals', href: '/activity-proposals' }, { title: 'View' }],
};
