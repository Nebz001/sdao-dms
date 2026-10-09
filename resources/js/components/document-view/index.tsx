import { Head, setLayoutProps } from '@inertiajs/react';
import { CircleCheck, CircleX, FileEdit, Hourglass, Undo2 } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect } from 'react';
import type { ReactNode } from 'react';
import type { AttachmentSlotDef, ExistingAttachment } from '@/components/attachment-slot-field';
import AttachmentsCard from '@/components/attachments-card';
import PageHeader from '@/components/page-header';
import PrintFormButton from '@/components/print-form-button';
import { ToneBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { labelFor, toneFor } from '@/lib/status-tones';
import type { BreadcrumbItem } from '@/types';
import AddRemarkCard from './add-remark-card';
import FlowCard from './flow-card';
import HistoryCard from './history-card';
import RecordCard from './record-card';
import ReviewPanel from './review-panel';
import type { DocumentViewData } from './types';

const STATUS_ICONS: Record<string, LucideIcon> = {
    approved: CircleCheck,
    rejected: CircleX,
    returned: Undo2,
    in_review: Hourglass,
    draft: FileEdit,
};

function StatusPill({ status }: { status: string }) {
    const Icon = STATUS_ICONS[status];

    return (
        <ToneBadge tone={toneFor('document', status)} className="h-7 px-2.5">
            {Icon && <Icon aria-hidden />}
            {labelFor('document', status)}
        </ToneBadge>
    );
}

type Props = {
    view: DocumentViewData;
    documentId: number;
    status: string;
    /** "registration", "proposal": the noun used in the panel's sentences. */
    noun: string;
    audience: 'approver' | 'student';
    /** The trail before the subject, e.g. Review › Registrations. The subject is appended as the last crumb. */
    trail: { title: string; href?: BreadcrumbItem['href'] }[];
    /** Extra header buttons, e.g. a student's "Edit & resubmit". */
    actions?: ReactNode;
    canAct?: boolean;
    hasApproved?: boolean;
    /** The decision card, mounted only while the document is waiting on the viewer. */
    decision?: ReactNode;
    /** Page-specific warnings above the decision (venue conflict, adviser unavailable). */
    notices?: ReactNode;
    attachmentSlots?: AttachmentSlotDef[];
    attachments?: Record<string, ExistingAttachment[]>;
    /** The form's own details: one or more cards. The only part that differs per document type. */
    children: ReactNode;
};

/**
 * The one document page, shared by every document type and every audience.
 * It owns the structure (header, two columns, history, approval flow,
 * record) and the state-driven review panel; a form passes only its own
 * details as `children`, its meta chips and subject through `view`, and its
 * decision card through `decision`.
 *
 * On a narrow screen the columns stack in this order: the review panel (so
 * the decision stays near the top), the details, attachments and history,
 * then the approval flow and record.
 */
export default function DocumentView({
    view,
    documentId,
    status,
    noun,
    audience,
    trail,
    actions,
    canAct = false,
    hasApproved = false,
    decision,
    notices,
    attachmentSlots,
    attachments,
    children,
}: Props) {
    useEffect(() => {
        setLayoutProps({ breadcrumbs: [...trail, { title: view.subject }] });
        // The trail is a literal per page; re-run only when the subject changes.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [view.subject]);

    return (
        <>
            <Head title={`${view.subject} — ${view.typeLabel}`} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
                <PageHeader
                    eyebrow={
                        <Badge
                            variant="outline"
                            className="border-primary-text/40 bg-primary/15 text-[0.65rem] font-semibold tracking-wide text-primary-text uppercase"
                        >
                            {view.typeLabel}
                        </Badge>
                    }
                    title={view.subject}
                    subtitle={
                        view.chips.length > 0 ? (
                            <ul className="flex flex-wrap gap-2" aria-label="Document facts">
                                {view.chips.map((chip) => (
                                    <li
                                        key={chip.label}
                                        className="inline-flex max-w-full items-baseline gap-2 rounded-md border bg-card px-2.5 py-1 text-sm"
                                    >
                                        <span className="text-muted-foreground">{chip.label}</span>
                                        <span className="font-medium text-foreground">{chip.value}</span>
                                    </li>
                                ))}
                            </ul>
                        ) : null
                    }
                    actions={
                        <>
                            <StatusPill status={status} />
                            <PrintFormButton documentId={documentId} />
                            {actions}
                        </>
                    }
                />

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:grid-rows-[auto_auto_1fr]">
                    <div className="lg:col-start-2 lg:row-start-1">
                        <ReviewPanel
                            view={view}
                            status={status}
                            noun={noun}
                            audience={audience}
                            canAct={canAct}
                            hasApproved={hasApproved}
                            decision={decision}
                            notices={notices}
                        />
                    </div>

                    <div className="flex min-w-0 flex-col gap-6 lg:col-start-1 lg:row-span-3 lg:row-start-1">
                        {children}
                        <AttachmentsCard slots={attachmentSlots ?? []} files={attachments ?? {}} />
                        {view.remark.canAdd && <AddRemarkCard remark={view.remark} />}
                        <HistoryCard events={view.history} />
                    </div>

                    <div className="lg:col-start-2 lg:row-start-2">
                        <FlowCard flow={view.flow} />
                    </div>

                    <div className="lg:col-start-2 lg:row-start-3 lg:self-start">
                        <RecordCard record={view.record} />
                    </div>
                </div>
            </div>
        </>
    );
}
