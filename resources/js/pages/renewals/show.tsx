import { Link } from '@inertiajs/react';
import type { AttachmentSlotDef, ExistingAttachment } from '@/components/attachment-slot-field';
import DocumentView from '@/components/document-view';
import OrganizationDetails from '@/components/document-view/organization-details';
import type { OrganizationDetail, OrganizationSummary } from '@/components/document-view/organization-details';
import type { DocumentViewData } from '@/components/document-view/types';
import { Button } from '@/components/ui/button';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import * as renewals from '@/routes/renewals';

type Props = {
    document: {
        id: number;
        title: string;
        status: string;
        current_step_position: number | null;
        submitted_by: number | null;
        organization: OrganizationSummary & { id: number };
    };
    detail: OrganizationDetail;
    attachmentSlots: AttachmentSlotDef[];
    attachments: Record<string, ExistingAttachment[]>;
    view: DocumentViewData;
};

export default function ShowRenewal({ document, detail, attachmentSlots, attachments, view }: Props) {
    useDocumentUpdates(['document', 'detail', 'attachments', 'view']);

    return (
        <DocumentView
            view={view}
            documentId={document.id}
            status={document.status}
            noun="renewal"
            audience="student"
            trail={[{ title: 'Renewals', href: renewals.index() }]}
            attachmentSlots={attachmentSlots}
            attachments={attachments}
            actions={
                document.status === 'returned' && (
                    <Button asChild size="sm">
                        <Link href={renewals.edit(document.id)}>Edit & resubmit</Link>
                    </Button>
                )
            }
        >
            <OrganizationDetails title="Renewal details" organization={document.organization} detail={detail} />
        </DocumentView>
    );
}
