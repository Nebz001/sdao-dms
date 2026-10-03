import { Link } from '@inertiajs/react';
import type { AttachmentSlotDef, ExistingAttachment } from '@/components/attachment-slot-field';
import DocumentView from '@/components/document-view';
import ReportDetails from '@/components/document-view/report-details';
import type { ReportData } from '@/components/document-view/report-details';
import type { DocumentViewData } from '@/components/document-view/types';
import { Button } from '@/components/ui/button';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import * as reports from '@/routes/reports';

type Props = {
    document: {
        id: number;
        title: string;
        status: string;
        current_step_position: number | null;
        submitted_by: number | null;
        organization: { id: number; name: string };
    };
    report: ReportData;
    attachmentSlots: AttachmentSlotDef[];
    attachments: Record<string, ExistingAttachment[]>;
    view: DocumentViewData;
};

export default function ShowReport({ document, report, attachmentSlots, attachments, view }: Props) {
    useDocumentUpdates(['document', 'report', 'attachments', 'view']);

    return (
        <DocumentView
            view={view}
            documentId={document.id}
            status={document.status}
            noun="report"
            audience="student"
            trail={[{ title: 'Reports', href: reports.index() }]}
            attachmentSlots={attachmentSlots}
            attachments={attachments}
            actions={
                document.status === 'returned' && (
                    <Button asChild size="sm">
                        <Link href={reports.edit(document.id)}>Edit & resubmit</Link>
                    </Button>
                )
            }
        >
            <ReportDetails report={report} />
        </DocumentView>
    );
}
