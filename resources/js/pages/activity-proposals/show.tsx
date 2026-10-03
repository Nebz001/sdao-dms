import { Link } from '@inertiajs/react';
import type { AttachmentSlotDef, ExistingAttachment } from '@/components/attachment-slot-field';
import DocumentView from '@/components/document-view';
import ProposalDetails from '@/components/document-view/proposal-details';
import type { ProposalActivity, ProposalData } from '@/components/document-view/proposal-details';
import type { DocumentViewData } from '@/components/document-view/types';
import PageNotice from '@/components/page-notice';
import { Button } from '@/components/ui/button';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import * as activityProposals from '@/routes/activity-proposals';

type Props = {
    document: {
        id: number;
        title: string;
        status: string;
        current_step_position: number | null;
        submitted_by: number | null;
        organization: { id: number; name: string };
    };
    proposal: ProposalData;
    activity: ProposalActivity;
    attachmentSlots: AttachmentSlotDef[];
    attachments: Record<string, ExistingAttachment[]>;
    view: DocumentViewData;
    flash?: { message?: string; warnings?: { conflicts: object[] }[] };
};

export default function ShowActivityProposal({
    document: doc,
    proposal,
    activity,
    attachmentSlots,
    attachments,
    view,
    flash,
}: Props) {
    useDocumentUpdates(['document', 'proposal', 'activity', 'attachments', 'view']);

    return (
        <DocumentView
            view={view}
            documentId={doc.id}
            status={doc.status}
            noun="proposal"
            audience="student"
            trail={[{ title: 'Activity proposals', href: '/activity-proposals' }]}
            attachmentSlots={attachmentSlots}
            attachments={attachments}
            actions={
                <>
                    {doc.status === 'draft' && (
                        <Button asChild size="sm">
                            <Link href={activityProposals.continueMethod({ document: doc.id }).url}>Continue narrative</Link>
                        </Button>
                    )}
                    {doc.status === 'returned' && (
                        <Button asChild size="sm">
                            <Link href={activityProposals.edit({ document: doc.id }).url}>Edit & resubmit</Link>
                        </Button>
                    )}
                </>
            }
            notices={
                flash?.warnings && flash.warnings.length > 0 ? (
                    <PageNotice tone="warning" title="Submitted, but a possible venue conflict was detected." />
                ) : undefined
            }
        >
            <ProposalDetails organizationName={doc.organization.name} proposal={proposal} activity={activity} />
        </DocumentView>
    );
}
