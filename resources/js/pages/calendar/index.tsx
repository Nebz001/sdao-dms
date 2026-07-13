import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import { formatCalendarDate, formatTimeRange } from '@/lib/utils';

type ActivityEntry = {
    id: number;
    name: string;
    venue: string;
    activity_date: string;
    start_time: string;
    end_time: string;
    status: string;
    organization: string;
    document_id: number;
};

type Props = {
    activities: ActivityEntry[];
};

/**
 * Shared venue calendar — confirmed (approved) and tentative (in_review) activities.
 * Groups by venue, then by date within each venue.
 */
export default function CalendarIndex({ activities }: Props) {
    useDocumentUpdates(['activities']);

    // Group: venue → date → activities
    const grouped: Record<string, Record<string, ActivityEntry[]>> = {};

    for (const activity of activities) {
        if (!grouped[activity.venue]) {
            grouped[activity.venue] = {};
        }

        if (!grouped[activity.venue][activity.activity_date]) {
            grouped[activity.venue][activity.activity_date] = [];
        }

        grouped[activity.venue][activity.activity_date].push(activity);
    }

    const venues = Object.keys(grouped).sort();

    return (
        <>
            <Head title="Venue Calendar" />

            <div className="mx-auto max-w-4xl space-y-6 p-8">
                <div>
                    <h1 className="text-xl font-semibold">Venue Calendar</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Confirmed (approved) and tentative (under review) activity bookings across all
                        organizations. Venue names are matched exactly — same spelling required to detect
                        conflicts.
                    </p>
                </div>

                {activities.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No activities on the calendar yet.</p>
                ) : (
                    venues.map((venue) => (
                        <Card key={venue}>
                            <CardHeader>
                                <CardTitle className="text-base">{venue}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {Object.keys(grouped[venue])
                                    .sort()
                                    .map((date) => (
                                        <div key={date}>
                                            <p className="mb-2 text-sm font-medium text-muted-foreground">
                                                {formatCalendarDate(date)}
                                            </p>
                                            <div className="space-y-2">
                                                {grouped[venue][date].map((a) => (
                                                    <div
                                                        key={a.id}
                                                        className="flex items-center justify-between rounded-md border px-3 py-2"
                                                    >
                                                        <div>
                                                            <p className="text-sm font-medium">{a.name}</p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {a.organization} · {formatTimeRange(a.start_time, a.end_time)}
                                                            </p>
                                                        </div>
                                                        <Badge
                                                            variant={a.status === 'approved' ? 'default' : 'secondary'}
                                                        >
                                                            {a.status === 'approved' ? 'Confirmed' : 'Tentative'}
                                                        </Badge>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                            </CardContent>
                        </Card>
                    ))
                )}
            </div>
        </>
    );
}

CalendarIndex.layout = {
    breadcrumbs: [{ title: 'Calendar' }],
};
