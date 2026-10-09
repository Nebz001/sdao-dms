import { router } from '@inertiajs/react';
import AfterActivityReportReviewController from '@/actions/App/Http/Controllers/AfterActivityReportReviewController';
import ApprovalActionsCard from '@/components/approval-actions-card';
import type { AttachmentSlotDef, ExistingAttachment } from '@/components/attachment-slot-field';
import type { ConfirmActions } from '@/components/confirm-dialog';
import DocumentView from '@/components/document-view';
import ReportDetails from '@/components/document-view/report-details';
import type { ReportData } from '@/components/document-view/report-details';
import type { DocumentViewData } from '@/components/document-view/types';
import SectionFlagFields from '@/components/section-flag-fields';
import type { SectionFlagDef } from '@/components/section-flag-fields';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import * as reviewReports from '@/routes/review/reports';

type Props = {
    document: {
        id: number;
        title: string;
        status: string;
        current_step_position: number | null;
        organization: { id: number; name: string };
    };
    report: ReportData;
    attachmentSlots: AttachmentSlotDef[];
    attachments: Record<string, ExistingAttachment[]>;
    view: DocumentViewData;
    sectionFlags: SectionFlagDef[];
    hasApproved: boolean;
    canAct: boolean;
};

export default function ReviewReportShow({
    document,
    report,
    attachmentSlots,
    attachments,
    view,
    sectionFlags,
    hasApproved,
    canAct,
}: Props) {
    useDocumentUpdates(['document', 'report', 'attachments', 'view', 'hasApproved', 'canAct']);

    function handleApprove({ close, stopProcessing }: ConfirmActions) {
        router.post(
            reviewReports.approve.url(document.id),
            {},
            { preserveScroll: true, onSuccess: close, onFinish: stopProcessing },
        );
    }

    return (
        <DocumentView
            view={view}
            documentId={document.id}
            status={document.status}
            noun="report"
            audience="approver"
            trail={[{ title: 'Review' }, { title: 'Reports', href: reviewReports.index() }]}
            canAct={canAct && !hasApproved}
            hasApproved={canAct && hasApproved}
            attachmentSlots={attachmentSlots}
            attachments={attachments}
            decision={
                <ApprovalActionsCard
                    approve={{
                        confirmTitle: 'Approve this report?',
                        confirmDescription: 'Your approval cannot be undone. The report moves on once every required approver at this step has approved.',
                        onConfirm: handleApprove,
                    }}
                    return={{
                        formProps: AfterActivityReportReviewController.return.form({ document: document.id }),
                        placeholder: 'Explain what the student needs to revise…',
                        flagFields: <SectionFlagFields sections={sectionFlags} />,
                    }}
                    reject={{
                        formProps: AfterActivityReportReviewController.reject.form({ document: document.id }),
                        confirmTitle: 'Reject this report?',
                        confirmDescription:
                            'This is permanent — the student cannot revive this document. They must file a brand-new report.',
                    }}
                />
            }
        >
            <ReportDetails report={report} />
        </DocumentView>
    );
}
