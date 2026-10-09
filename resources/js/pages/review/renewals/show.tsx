import { router } from '@inertiajs/react';
import RenewalReviewController from '@/actions/App/Http/Controllers/RenewalReviewController';
import ApprovalActionsCard from '@/components/approval-actions-card';
import type { AttachmentSlotDef, ExistingAttachment } from '@/components/attachment-slot-field';
import type { ConfirmActions } from '@/components/confirm-dialog';
import DocumentView from '@/components/document-view';
import OrganizationDetails from '@/components/document-view/organization-details';
import type { OrganizationDetail, OrganizationSummary } from '@/components/document-view/organization-details';
import type { DocumentViewData } from '@/components/document-view/types';
import SectionFlagFields from '@/components/section-flag-fields';
import type { SectionFlagDef } from '@/components/section-flag-fields';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import * as reviewRenewals from '@/routes/review/renewals';

type Props = {
    document: {
        id: number;
        title: string;
        status: string;
        current_step_position: number | null;
        organization: OrganizationSummary & { id: number };
    };
    detail: OrganizationDetail;
    attachmentSlots: AttachmentSlotDef[];
    attachments: Record<string, ExistingAttachment[]>;
    view: DocumentViewData;
    sectionFlags: SectionFlagDef[];
    hasApproved: boolean;
    canAct: boolean;
};

export default function ReviewRenewalShow({
    document,
    detail,
    attachmentSlots,
    attachments,
    view,
    sectionFlags,
    hasApproved,
    canAct,
}: Props) {
    useDocumentUpdates(['document', 'detail', 'attachments', 'view', 'hasApproved', 'canAct']);

    function handleApprove({ close, stopProcessing, remarks }: ConfirmActions) {
        router.post(
            reviewRenewals.approve.url(document.id),
            { comment: remarks },
            { preserveScroll: true, onSuccess: close, onFinish: stopProcessing },
        );
    }

    return (
        <DocumentView
            view={view}
            documentId={document.id}
            status={document.status}
            noun="renewal"
            audience="approver"
            trail={[{ title: 'Review' }, { title: 'Renewals', href: reviewRenewals.index() }]}
            canAct={canAct && !hasApproved}
            hasApproved={canAct && hasApproved}
            attachmentSlots={attachmentSlots}
            attachments={attachments}
            decision={
                <ApprovalActionsCard
                    approve={{
                        confirmTitle: 'Approve this renewal?',
                        confirmDescription:
                            'This action is irreversible once the SDAO quorum is met — the renewal becomes final for this academic year.',
                        onConfirm: handleApprove,
                    }}
                    return={{
                        formProps: RenewalReviewController.return.form({ document: document.id }),
                        placeholder: 'Explain what the student needs to revise…',
                        flagFields: <SectionFlagFields sections={sectionFlags} />,
                    }}
                    reject={{
                        formProps: RenewalReviewController.reject.form({ document: document.id }),
                        confirmTitle: 'Reject this renewal?',
                        confirmDescription:
                            'This is permanent — the student cannot revive this document. They must file a brand-new renewal.',
                    }}
                />
            }
        >
            <OrganizationDetails title="Renewal details" organization={document.organization} detail={detail} />
        </DocumentView>
    );
}
