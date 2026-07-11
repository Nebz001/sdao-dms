import { Form, Head } from '@inertiajs/react';
import ActivityProposalReviewController from '@/actions/App/Http/Controllers/ActivityProposalReviewController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { useDocumentUpdates } from '@/hooks/use-document-updates';

type Organization = { id: number; name: string };

type DocumentData = {
    id: number;
    title: string;
    status: string;
    current_step_position: number | null;
    organization: Organization;
};

type ProposalData = {
    calendar_mode: string;
    title: string;
    objectives: string | null;
    narrative: string | null;
    criteria_mechanics: string | null;
    program_flow: string | null;
    source_of_funding: string | null;
    expenses: string | null;
    proposed_budget: string | null;
    activity_nature_label: string | null;
    activity_type_label: string | null;
    partner_organizations: string[] | null;
    target_sdg_label: string | null;
    budget_source: string | null;
} | null;

type ActivityData = {
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
    actor: { name: string } | null;
    created_at: string;
};

type StepApproval = { user_id: number; name: string };

type ConflictInfo = { confirmed: { name: string; organization: string }[] } | null;

type Props = {
    document: DocumentData;
    proposal: ProposalData;
    activity: ActivityData;
    history: TransitionEntry[];
    currentStepApprovals: StepApproval[];
    hasApproved: boolean;
    currentStepRole: string | null;
    requiredApprovals: number;
    activityConflict: ConflictInfo;
    hasConfirmedConflict: boolean;
    errors?: Record<string, string>;
};

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    draft: 'outline',
    in_review: 'secondary',
    returned: 'outline',
    approved: 'default',
    rejected: 'destructive',
};

function statusLabel(s: string): string {
    return s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function actionLabel(a: string): string {
    return a.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function roleLabel(role: string | null): string {
    if (!role) {
return 'Approver';
}

    return role.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export default function ReviewActivityProposalShow({
    document: doc,
    proposal,
    activity,
    history,
    currentStepApprovals,
    hasApproved,
    currentStepRole,
    requiredApprovals,
    activityConflict,
    hasConfirmedConflict,
    errors = {},
}: Props) {
    useDocumentUpdates(['document', 'proposal', 'activity', 'history', 'currentStepApprovals', 'hasApproved', 'currentStepRole', 'requiredApprovals', 'activityConflict', 'hasConfirmedConflict']);

    const isInReview = doc.status === 'in_review';
    const isSdaoStep = currentStepRole === 'sdao_member';

    return (
        <>
            <Head title={`Review — ${doc.title}`} />

            <div className="mx-auto max-w-3xl space-y-6 p-8">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">{doc.title}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            <span className="font-medium">Name of RSO:</span> {doc.organization.name}
                        </p>
                    </div>
                    <Badge variant={statusVariant[doc.status] ?? 'outline'}>{statusLabel(doc.status)}</Badge>
                </div>

                {/* Off-calendar conflict warning */}
                {activityConflict && activityConflict.confirmed.length > 0 && (
                    <Card className="border-destructive bg-destructive/5">
                        <CardContent className="pt-4">
                            <p className="text-sm font-medium text-destructive">
                                Venue conflict — this activity overlaps an already-approved booking:
                            </p>
                            {activityConflict.confirmed.map((c, i) => (
                                <p key={i} className="text-sm text-destructive">
                                    {c.name} ({c.organization})
                                </p>
                            ))}
                            <p className="mt-2 text-sm text-destructive">
                                Approval is blocked. Return this proposal to the submitter to resolve the conflict.
                            </p>
                        </CardContent>
                    </Card>
                )}

                {/* Activity */}
                {activity && proposal && (
                    <Card>
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
                            {proposal.target_sdg_label && <Row label="Target SDG" value={proposal.target_sdg_label} />}
                            {proposal.proposed_budget && (
                                <Row label="Proposed Budget" value={`₱${proposal.proposed_budget}`} />
                            )}
                            {proposal.budget_source && <Row label="Budget Source" value={proposal.budget_source} />}
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

                {/* Approver actions */}
                {isInReview && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {isSdaoStep ? 'SDAO Approval' : `${roleLabel(currentStepRole)} Approval`}
                                {isSdaoStep && (
                                    <span className="ml-2 text-xs font-normal text-muted-foreground">
                                        ({currentStepApprovals.length}/{requiredApprovals} approved)
                                    </span>
                                )}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {isSdaoStep && currentStepApprovals.length > 0 && (
                                <p className="text-sm text-muted-foreground">
                                    Approved by: {currentStepApprovals.map((a) => a.name).join(', ')}
                                </p>
                            )}

                            <InputError message={errors.approve} />

                            <div className="flex flex-wrap gap-3">
                                {/* Approve */}
                                <Form
                                    action={ActivityProposalReviewController.approve({ document: doc.id }).url}
                                    method="post"
                                >
                                    <Button type="submit" disabled={hasApproved || hasConfirmedConflict}>
                                        {hasApproved ? 'Already Approved' : 'Approve'}
                                    </Button>
                                </Form>

                                {/* Return */}
                                <Form
                                    action={ActivityProposalReviewController.return({ document: doc.id }).url}
                                    method="post"
                                    className="flex gap-2"
                                >
                                    <Textarea name="comment" placeholder="Return comment (required)…" rows={2} className="w-64" />
                                    <Button type="submit" variant="outline">
                                        Return for Revision
                                    </Button>
                                </Form>

                                {/* Reject */}
                                <Form
                                    action={ActivityProposalReviewController.reject({ document: doc.id }).url}
                                    method="post"
                                    className="flex gap-2"
                                >
                                    <Textarea name="comment" placeholder="Rejection reason (required)…" rows={2} className="w-64" />
                                    <Button type="submit" variant="destructive">
                                        Reject
                                    </Button>
                                </Form>
                            </div>
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

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="grid grid-cols-3 gap-2">
            <span className="font-medium text-muted-foreground">{label}</span>
            <span className="col-span-2">{value}</span>
        </div>
    );
}

ReviewActivityProposalShow.layout = {
    breadcrumbs: [
        { title: 'Review Activity Proposals', href: '/review/activity-proposals' },
        { title: 'Review' },
    ],
};
