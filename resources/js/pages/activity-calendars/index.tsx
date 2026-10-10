import { Head } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import ClientListPage from '@/components/client-list-page';
import type { ListRow } from '@/components/client-list-page';
import type { CanStart } from '@/components/student-list';
import * as activityCalendars from '@/routes/activity-calendars';

type Props = {
    calendars: ListRow[];
    canStart: CanStart;
};

export default function ActivityCalendarsIndex({ calendars, canStart }: Props) {
    return (
        <>
            <Head title="Activity Calendars" />
            <ClientListPage
                kind="calendar"
                icon={CalendarDays}
                title="Activity Calendars"
                subtitle="Your plan of activities for each term"
                searchPlaceholder="Search calendars"
                startLabel="New Activity Calendar"
                startHref={activityCalendars.create().url}
                canStart={canStart}
                rows={calendars}
                dateLabel="Received"
                empty={{
                    title: 'No activity calendars yet',
                    description:
                        'Plan the activities your organization will hold this term, then send the plan to SDAO.',
                    steps: [
                        'List your activities',
                        'Set dates and venues',
                        'Send to SDAO',
                    ],
                }}
                rowAction={(row) =>
                    row.status === 'returned'
                        ? {
                              label: 'Revise',
                              href: activityCalendars.edit({ document: row.id })
                                  .url,
                          }
                        : {
                              label: 'View',
                              href: activityCalendars.show({ document: row.id })
                                  .url,
                          }
                }
            />
        </>
    );
}
