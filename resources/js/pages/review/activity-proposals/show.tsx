import { router } from '@inertiajs/react';
import ActivityProposalReviewController from '@/actions/App/Http/Controllers/ActivityProposalReviewController';
import ApprovalActionsCard from '@/components/approval-actions-card';
import type { AttachmentSlotDef, ExistingAttachment } from '@/components/attachment-slot-field';
import type { ConfirmActions } from '@/components/confirm-dialog';
import DocumentView from '@/components/document-view';
import ProposalDetails from '@/components/document-view/proposal-details';
import type { ProposalActivity, ProposalData } from '@/components/document-view/proposal-details';
import type { DocumentViewData } from '@/components/document-view/types';
import PageNotice from '@/components/page-notice';
import SectionFlagFields from '@/components/section-flag-fields';
import type { SectionFlagDef } from '@/components/section-flag-fields';
import { useDocumentUpdates } from '@/hooks/use-document-updates';

type Props = {
    document: {
        id: number;
        title: string;
        status: string;
        current_step_position: number | null;
        organization: { id: number; name: string };
    };
    proposal: ProposalData;
    activity: ProposalActivity;
    attachmentSlots: AttachmentSlotDef[];
    attachments: Record<string, ExistingAttachment[]>;
    view: DocumentViewData;
    sectionFlags: SectionFlagDef[];
    hasApproved: boolean;
    canAct: boolean;
    activityConflict: { confirmed: { name: string; organization: string }[] } | null;
    hasConfirmedConflict: boolean;
    errors?: Record<string, string>;
};

export default function ReviewActivityProposalShow({
    document: doc,
    proposal,
    activity,
    attachmentSlots,
    attachments,
    view,
    sectionFlags,
    hasApproved,
    canAct,
    activityConflict,
    hasConfirmedConflict,
    errors = {},
}: Props) {
    useDocumentUpdates([
        'document',
        'proposal',
        'activity',
        'attachments',
        'view',
        'hasApproved',
        'canAct',
        'activityConflict',
        'hasConfirmedConflict',
    ]);

    return (
        <DocumentView
            view={view}
            documentId={doc.id}
            status={doc.status}
            noun="proposal"
            audience="approver"
            trail={[{ title: 'Review' }, { title: 'Activity proposals', href: '/review/activity-proposals' }]}
            canAct={canAct}
            hasApproved={canAct && hasApproved}
            attachmentSlots={attachmentSlots}
            attachments={attachments}
            notices={
                activityConflict && activityConflict.confirmed.length > 0 ? (
                    <PageNotice
                        tone="destructive"
                        urgent
                        title="Venue conflict. This activity overlaps an already-approved booking."
                    >
                        {activityConflict.confirmed.map((c, i) => (
                            <p key={i}>
                                {c.name} ({c.organization})
                            </p>
                        ))}
                        <p className="mt-2">Approval is blocked. Return this proposal to the submitter to resolve the conflict.</p>
                    </PageNotice>
                ) : undefined
            }
            decision={
                <ApprovalActionsCard
                    approve={{
                        label: hasApproved ? 'Already approved' : 'Approve',
                        disabled: hasApproved || hasConfirmedConflict,
                        confirmTitle: 'Approve this proposal?',
                        confirmDescription: 'This action is irreversible once all required approvals are met.',
                        confirmNotice: errors.approve ? (
                            <PageNotice tone="destructive" urgent title={errors.approve} />
                        ) : undefined,
                        confirmDisabled: hasApproved || hasConfirmedConflict,
                        onConfirm: ({ close, stopProcessing }: ConfirmActions) =>
                            router.post(
                                ActivityProposalReviewController.approve({ document: doc.id }).url,
                                {},
                                { preserveScroll: true, onSuccess: close, onFinish: stopProcessing },
                            ),
                    }}
                    return={{
                        formProps: ActivityProposalReviewController.return.form({ document: doc.id }),
                        placeholder: 'Explain what the student needs to revise…',
                        flagFields: <SectionFlagFields sections={sectionFlags} />,
                    }}
                    reject={{
                        formProps: ActivityProposalReviewController.reject.form({ document: doc.id }),
                        confirmTitle: 'Reject this proposal?',
                        confirmDescription:
                            'This is permanent — the submitter cannot revive this document. They must file a brand-new proposal.',
                    }}
                />
            }
        >
            <ProposalDetails organizationName={doc.organization.name} proposal={proposal} activity={activity} />
        </DocumentView>
    );
}
