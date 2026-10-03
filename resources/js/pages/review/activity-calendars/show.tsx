import { router } from '@inertiajs/react';
import ActivityCalendarReviewController from '@/actions/App/Http/Controllers/ActivityCalendarReviewController';
import ApprovalActionsCard from '@/components/approval-actions-card';
import CalendarSectionFlagFields from '@/components/calendar-section-flag-fields';
import type { ConfirmActions } from '@/components/confirm-dialog';
import DocumentView from '@/components/document-view';
import CalendarDetails from '@/components/document-view/calendar-details';
import type { ActivityConflicts, CalendarData } from '@/components/document-view/calendar-details';
import type { DocumentViewData } from '@/components/document-view/types';
import PageNotice from '@/components/page-notice';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import * as reviewActivityCalendars from '@/routes/review/activity-calendars';

type Props = {
    document: {
        id: number;
        title: string;
        status: string;
        current_step_position: number | null;
        organization: { id: number; name: string };
    };
    calendar: CalendarData;
    view: DocumentViewData;
    hasApproved: boolean;
    canAct: boolean;
    activityConflicts: ActivityConflicts;
    hasConfirmedConflict: boolean;
    errors?: Record<string, string>;
};

export default function ReviewActivityCalendarShow({
    document,
    calendar,
    view,
    hasApproved,
    canAct,
    activityConflicts,
    hasConfirmedConflict,
    errors = {},
}: Props) {
    useDocumentUpdates([
        'document',
        'calendar',
        'view',
        'hasApproved',
        'canAct',
        'activityConflicts',
        'hasConfirmedConflict',
    ]);

    function handleApprove({ close, stopProcessing }: ConfirmActions) {
        router.post(
            reviewActivityCalendars.approve.url(document.id),
            {},
            { preserveScroll: true, onSuccess: close, onFinish: stopProcessing },
        );
    }

    return (
        <DocumentView
            view={view}
            documentId={document.id}
            status={document.status}
            noun="activity calendar"
            audience="approver"
            trail={[{ title: 'Review' }, { title: 'Activity calendars', href: reviewActivityCalendars.index() }]}
            canAct={canAct && !hasApproved}
            hasApproved={canAct && hasApproved}
            decision={
                <ApprovalActionsCard
                    approve={{
                        blocked: hasConfirmedConflict ? (
                            <PageNotice tone="destructive" urgent title="Cannot approve.">
                                One or more activities conflict with an already-approved booking. Return the document to the
                                submitter to resolve.
                            </PageNotice>
                        ) : undefined,
                        confirmTitle: 'Approve this activity calendar?',
                        confirmDescription:
                            'This action is irreversible once the SDAO quorum is met — every listed activity becomes an approved, venue-blocking booking.',
                        confirmNotice: errors.approve ? (
                            <PageNotice tone="destructive" urgent title={errors.approve} />
                        ) : undefined,
                        onConfirm: handleApprove,
                    }}
                    return={{
                        formProps: ActivityCalendarReviewController.return.form({ document: document.id }),
                        placeholder: 'Explain what the submitter needs to revise…',
                        flagFields: <CalendarSectionFlagFields activities={calendar?.activities ?? []} />,
                    }}
                    reject={{
                        formProps: ActivityCalendarReviewController.reject.form({ document: document.id }),
                        confirmTitle: 'Reject this activity calendar?',
                        confirmDescription:
                            'This is permanent — the submitter cannot revive this document. They must file a brand-new calendar submission.',
                    }}
                />
            }
        >
            <CalendarDetails calendar={calendar} conflicts={activityConflicts} />
        </DocumentView>
    );
}
