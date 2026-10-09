import { router, usePage } from '@inertiajs/react';
import RegistrationReviewController from '@/actions/App/Http/Controllers/RegistrationReviewController';
import ApprovalActionsCard from '@/components/approval-actions-card';
import type { AttachmentSlotDef, ExistingAttachment } from '@/components/attachment-slot-field';
import type { ConfirmActions } from '@/components/confirm-dialog';
import DocumentView from '@/components/document-view';
import OrganizationDetails from '@/components/document-view/organization-details';
import type { OrganizationDetail, OrganizationSummary } from '@/components/document-view/organization-details';
import type { DocumentViewData } from '@/components/document-view/types';
import PageNotice from '@/components/page-notice';
import SectionFlagFields from '@/components/section-flag-fields';
import type { SectionFlagDef } from '@/components/section-flag-fields';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import * as reviewRegistrations from '@/routes/review/registrations';

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
    adviserAvailable: boolean;
};

export default function ReviewRegistrationShow({
    document,
    detail,
    attachmentSlots,
    attachments,
    view,
    sectionFlags,
    hasApproved,
    canAct,
    adviserAvailable,
}: Props) {
    useDocumentUpdates(['document', 'detail', 'attachments', 'view', 'hasApproved', 'canAct', 'adviserAvailable']);

    const { errors } = usePage<{ errors: Record<string, string> }>().props;

    function handleApprove({ close, stopProcessing, remarks }: ConfirmActions) {
        router.post(
            reviewRegistrations.approve.url(document.id),
            { comment: remarks },
            { preserveScroll: true, onSuccess: close, onFinish: stopProcessing },
        );
    }

    return (
        <DocumentView
            view={view}
            documentId={document.id}
            status={document.status}
            noun="registration"
            audience="approver"
            trail={[{ title: 'Review' }, { title: 'Registrations', href: reviewRegistrations.index() }]}
            canAct={canAct && !hasApproved}
            hasApproved={canAct && hasApproved}
            attachmentSlots={attachmentSlots}
            attachments={attachments}
            decision={
                <ApprovalActionsCard
                    approve={{
                        // Blocked when the chosen adviser is no longer available
                        // (Phase 2 item 5 race-condition guard).
                        blocked:
                            !adviserAvailable || errors.approve ? (
                                <PageNotice
                                    tone="destructive"
                                    urgent
                                    title={
                                        errors.approve ??
                                        'Cannot approve: the chosen adviser is now assigned to a different organization.'
                                    }
                                >
                                    Return the document so the student can pick a different adviser.
                                </PageNotice>
                            ) : undefined,
                        confirmTitle: 'Approve this registration?',
                        confirmDescription:
                            'This action is irreversible once the SDAO quorum is met — the organization becomes real, the adviser is bound, and the founding student is locked to this organization going forward.',
                        onConfirm: handleApprove,
                    }}
                    return={{
                        formProps: RegistrationReviewController.return.form({ document: document.id }),
                        placeholder: 'Explain what the student needs to revise…',
                        flagFields: <SectionFlagFields sections={sectionFlags} />,
                    }}
                    reject={{
                        formProps: RegistrationReviewController.reject.form({ document: document.id }),
                        confirmTitle: 'Reject this registration?',
                        confirmDescription:
                            'This is permanent — the student cannot revive this document. They must file a brand-new registration.',
                    }}
                />
            }
        >
            <OrganizationDetails title="Registration details" organization={document.organization} detail={detail} />
        </DocumentView>
    );
}
