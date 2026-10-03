import { Link } from '@inertiajs/react';
import DocumentView from '@/components/document-view';
import CalendarDetails from '@/components/document-view/calendar-details';
import type { CalendarData } from '@/components/document-view/calendar-details';
import type { DocumentViewData } from '@/components/document-view/types';
import { Button } from '@/components/ui/button';
import { useDocumentUpdates } from '@/hooks/use-document-updates';

type Props = {
    document: {
        id: number;
        title: string;
        status: string;
        current_step_position: number | null;
        submitted_by: number | null;
        organization: { id: number; name: string };
    };
    calendar: CalendarData;
    view: DocumentViewData;
};

export default function ShowActivityCalendar({ document, calendar, view }: Props) {
    useDocumentUpdates(['document', 'calendar', 'view']);

    return (
        <DocumentView
            view={view}
            documentId={document.id}
            status={document.status}
            noun="activity calendar"
            audience="student"
            trail={[{ title: 'Activity calendars', href: '/activity-calendars' }]}
            actions={
                document.status === 'returned' && (
                    <Button asChild size="sm">
                        <Link href={`/activity-calendars/${document.id}/edit`}>Edit & resubmit</Link>
                    </Button>
                )
            }
        >
            <CalendarDetails calendar={calendar} />
        </DocumentView>
    );
}
