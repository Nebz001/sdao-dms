import { Head, router } from '@inertiajs/react';
import ActivityProposalReviewController from '@/actions/App/Http/Controllers/ActivityProposalReviewController';
import ApprovalActionsCard from '@/components/approval-actions-card';
import type {
    AttachmentSlotDef,
    ExistingAttachment,
} from '@/components/attachment-slot-field';
import AttachmentsCard from '@/components/attachments-card';
import type { ConfirmActions } from '@/components/confirm-dialog';
import ExpenseItemsTable from '@/components/expense-items-table';
import { FieldChangeDiff } from '@/components/field-change-diff';
import PrintFormButton from '@/components/print-form-button';
import SectionFlagFields from '@/components/section-flag-fields';
import type { SectionFlagDef } from '@/components/section-flag-fields';
import { StatusBadge, statusBorderClass } from '@/components/status-badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import type { FlaggedSectionLabels, TransitionEntry } from '@/types';

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
    expense_items: { label: string; amount: string }[] | null;
    expense_items_total: string | null;
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

type StepApproval = { user_id: number; name: string };

type ConflictInfo = {
    confirmed: { name: string; organization: string }[];
} | null;

type Props = {
    document: DocumentData;
    proposal: ProposalData;
    activity: ActivityData;
    attachmentSlots: AttachmentSlotDef[];
    attachments: Record<string, ExistingAttachment[]>;
    history: TransitionEntry[];
    flaggedSectionLabels: FlaggedSectionLabels;
    sectionFlags: SectionFlagDef[];
    currentStepApprovals: StepApproval[];
    hasApproved: boolean;
    currentStepRole: string | null;
    requiredApprovals: number;
    activityConflict: ConflictInfo;
    hasConfirmedConflict: boolean;
    errors?: Record<string, string>;
};

function actionLabel(a: string): string {
    return a.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function roleLabel(role: string | null): string {
    if (!role) {
        return 'Approver';
    }

    return role.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

/**
 * Remediation-phase fix: approvers now retain read access to a proposal
 * after it leaves In Review (rejected, approved, or after the chain
 * advances past their step) instead of hitting a 403 — see
 * DocumentPolicy::hasActedOn(). This explains why no review actions appear
 * once the document moves on.
 */
function reviewOnlyStatusNote(status: string): string {
    switch (status) {
        case 'approved':
            return 'This proposal has been approved. No further action is available.';
        case 'rejected':
            return 'This proposal was rejected and is now closed. The organization must submit a new proposal to proceed.';
        case 'returned':
            return 'This proposal was returned for revision. It will reappear here once the student resubmits.';
        default:
            return 'This proposal is no longer awaiting your review.';
    }
}

export default function ReviewActivityProposalShow({
    document: doc,
    proposal,
    activity,
    attachmentSlots,
    attachments,
    history,
    flaggedSectionLabels,
    sectionFlags,
    currentStepApprovals,
    hasApproved,
    currentStepRole,
    requiredApprovals,
    activityConflict,
    hasConfirmedConflict,
    errors = {},
}: Props) {
    useDocumentUpdates([
        'document',
        'proposal',
        'activity',
        'attachments',
        'history',
        'currentStepApprovals',
        'hasApproved',
        'currentStepRole',
        'requiredApprovals',
        'activityConflict',
        'hasConfirmedConflict',
    ]);

    const isInReview = doc.status === 'in_review';
    const isSdaoStep = currentStepRole === 'sdao_member';

    return (
        <>
            <Head title={`Review — ${doc.title}`} />

            <div className="max-w-3xl space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-balance">
                            {doc.title}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            <span className="font-medium">Name of RSO:</span>{' '}
                            {doc.organization.name}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <StatusBadge status={doc.status} />
                        <PrintFormButton documentId={doc.id} />
                    </div>
                </div>

                {/* Off-calendar conflict warning */}
                {activityConflict && activityConflict.confirmed.length > 0 && (
                    <Card className="border-destructive bg-destructive/5">
                        <CardContent>
                            <p className="text-sm font-medium text-destructive">
                                Venue conflict — this activity overlaps an
                                already-approved booking:
                            </p>
                            {activityConflict.confirmed.map((c, i) => (
                                <p key={i} className="text-sm text-destructive">
                                    {c.name} ({c.organization})
                                </p>
                            ))}
                            <p className="mt-2 text-sm text-destructive">
                                Approval is blocked. Return this proposal to the
                                submitter to resolve the conflict.
                            </p>
                        </CardContent>
                    </Card>
                )}

                {/* Activity */}
                {activity && proposal && (
                    <Card
                        className={`border-l-4 ${statusBorderClass(doc.status)}`}
                    >
                        <CardHeader>
                            <CardTitle className="text-base">
                                Activity{' '}
                                <span className="text-xs font-normal text-muted-foreground">
                                    (
                                    {proposal.calendar_mode === 'on_calendar'
                                        ? 'On Calendar'
                                        : 'Off Calendar'}
                                    )
                                </span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            <p className="font-medium">{activity.name}</p>
                            <p className="text-muted-foreground">
                                {activity.venue} · {activity.activity_date} ·{' '}
                                {activity.start_time}–{activity.end_time}
                            </p>
                        </CardContent>
                    </Card>
                )}

                {/* Activity Request Form fields (Phase 2 item 7 slice 4a) */}
                {proposal && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Activity Request Form
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3 text-sm">
                            {proposal.activity_nature_label && (
                                <Row
                                    label="Nature of Activity"
                                    value={proposal.activity_nature_label}
                                />
                            )}
                            {proposal.activity_type_label && (
                                <Row
                                    label="Type of Activity"
                                    value={proposal.activity_type_label}
                                />
                            )}
                            {proposal.partner_organizations &&
                                proposal.partner_organizations.length > 0 && (
                                    <div className="grid gap-1">
                                        <span className="font-medium text-muted-foreground">
                                            Partner
                                            Organization(s)/School(s)/RSO
                                        </span>
                                        <ul className="list-disc pl-4">
                                            {proposal.partner_organizations.map(
                                                (org, i) => (
                                                    <li key={i}>{org}</li>
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                )}
                            {proposal.target_sdg_label && (
                                <Row
                                    label="Target SDG"
                                    value={proposal.target_sdg_label}
                                />
                            )}
                            {proposal.proposed_budget && (
                                <Row
                                    label="Proposed Budget"
                                    value={`₱${proposal.proposed_budget}`}
                                />
                            )}
                            {proposal.budget_source && (
                                <Row
                                    label="Budget Source"
                                    value={proposal.budget_source}
                                />
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Narrative */}
                {proposal && (proposal.objectives || proposal.narrative) && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Proposal Narrative
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            {proposal.objectives && (
                                <div>
                                    <p className="mb-1 font-medium">
                                        Objectives
                                    </p>
                                    <p className="whitespace-pre-wrap text-muted-foreground">
                                        {proposal.objectives}
                                    </p>
                                </div>
                            )}
                            {proposal.narrative && (
                                <div>
                                    <p className="mb-1 font-medium">
                                        Narrative
                                    </p>
                                    <p className="whitespace-pre-wrap text-muted-foreground">
                                        {proposal.narrative}
                                    </p>
                                </div>
                            )}
                            {proposal.criteria_mechanics && (
                                <div>
                                    <p className="mb-1 font-medium">
                                        Criteria/Mechanics
                                    </p>
                                    <p className="whitespace-pre-wrap text-muted-foreground">
                                        {proposal.criteria_mechanics}
                                    </p>
                                </div>
                            )}
                            {proposal.program_flow && (
                                <div>
                                    <p className="mb-1 font-medium">
                                        Program Flow
                                    </p>
                                    <p className="whitespace-pre-wrap text-muted-foreground">
                                        {proposal.program_flow}
                                    </p>
                                </div>
                            )}
                            {proposal.source_of_funding && (
                                <div>
                                    <p className="mb-1 font-medium">
                                        Source of Funding
                                    </p>
                                    <p className="whitespace-pre-wrap text-muted-foreground">
                                        {proposal.source_of_funding}
                                    </p>
                                </div>
                            )}
                            <ExpenseItemsTable
                                items={proposal.expense_items}
                                total={proposal.expense_items_total}
                                legacyText={proposal.expenses}
                            />
                        </CardContent>
                    </Card>
                )}

                <AttachmentsCard slots={attachmentSlots} files={attachments} />

                {/* Approver actions */}
                {isInReview && (
                    <ApprovalActionsCard
                        title={
                            <>
                                {isSdaoStep
                                    ? 'SDAO Approval'
                                    : `${roleLabel(currentStepRole)} Approval`}
                                {isSdaoStep && (
                                    <span className="ml-2 text-xs font-normal text-muted-foreground">
                                        ({currentStepApprovals.length}/
                                        {requiredApprovals} approved)
                                    </span>
                                )}
                            </>
                        }
                        note={
                            isSdaoStep && currentStepApprovals.length > 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Approved by:{' '}
                                    {currentStepApprovals
                                        .map((a) => a.name)
                                        .join(', ')}
                                </p>
                            ) : undefined
                        }
                        approve={{
                            label: hasApproved ? 'Already Approved' : 'Approve',
                            disabled: hasApproved || hasConfirmedConflict,
                            confirmTitle: 'Approve this proposal?',
                            confirmDescription: (
                                <>
                                    This action is irreversible once all
                                    required approvals are met.
                                    {errors.approve && (
                                        <span className="mt-2 block text-destructive">
                                            {errors.approve}
                                        </span>
                                    )}
                                </>
                            ),
                            confirmDisabled:
                                hasApproved || hasConfirmedConflict,
                            onConfirm: ({
                                close,
                                stopProcessing,
                            }: ConfirmActions) =>
                                router.post(
                                    ActivityProposalReviewController.approve({
                                        document: doc.id,
                                    }).url,
                                    {},
                                    {
                                        preserveScroll: true,
                                        onSuccess: close,
                                        onFinish: stopProcessing,
                                    },
                                ),
                        }}
                        return={{
                            formProps:
                                ActivityProposalReviewController.return.form({
                                    document: doc.id,
                                }),
                            placeholder:
                                'Explain what the student needs to revise…',
                            flagFields: (
                                <SectionFlagFields sections={sectionFlags} />
                            ),
                        }}
                        reject={{
                            formProps:
                                ActivityProposalReviewController.reject.form({
                                    document: doc.id,
                                }),
                            confirmTitle: 'Reject this proposal?',
                            confirmDescription:
                                'This is permanent — the submitter cannot revive this document. They must file a brand-new proposal.',
                        }}
                    />
                )}

                {!isInReview && (
                    <p className="text-sm text-muted-foreground">
                        {reviewOnlyStatusNote(doc.status)}
                    </p>
                )}

                {/* Revision history */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Revision History
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
                                                    .map(
                                                        (key) =>
                                                            flaggedSectionLabels[
                                                                key
                                                            ] ?? key,
                                                    )
                                                    .join(', ')}
                                            </p>
                                        )}
                                    {entry.section_comments &&
                                        Object.keys(entry.section_comments)
                                            .length > 0 && (
                                            <ul className="mt-1 space-y-0.5 text-xs text-destructive">
                                                {Object.entries(
                                                    entry.section_comments,
                                                ).map(([key, note]) => (
                                                    <li key={key}>
                                                        <span className="font-medium">
                                                            {flaggedSectionLabels[
                                                                key
                                                            ] ?? key}
                                                            :
                                                        </span>{' '}
                                                        {note}
                                                    </li>
                                                ))}
                                            </ul>
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
        {
            title: 'Review Activity Proposals',
            href: '/review/activity-proposals',
        },
        { title: 'Review' },
    ],
};
