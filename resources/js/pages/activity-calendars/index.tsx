import { Head, Link } from '@inertiajs/react';
import { Files } from 'lucide-react';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import * as activityCalendars from '@/routes/activity-calendars';

type ActivityCalendarEntry = {
    id: number;
    title: string;
    status: string;
    organization: { id: number; name: string };
    created_at: string;
};

type Props = {
    calendars: ActivityCalendarEntry[];
};

export default function ActivityCalendarsIndex({ calendars }: Props) {
    return (
        <>
            <Head title="Activity Calendars" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold tracking-tight text-balance">
                        Activity Calendars
                    </h1>
                    <Button asChild>
                        <Link href={activityCalendars.create().url}>New Activity Calendar</Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">My Calendars</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {calendars.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <Files />
                                    </EmptyMedia>
                                    <EmptyTitle>No activity calendars yet</EmptyTitle>
                                    <EmptyDescription>
                                        Once you submit an activity calendar, it'll show up here.
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <div className="divide-y">
                                {calendars.map((c) => (
                                    <div key={c.id} className="flex items-center justify-between gap-4 py-3">
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">{c.title}</p>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {c.organization.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Date Received: {new Date(c.created_at).toLocaleDateString()}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-2">
                                            <StatusBadge status={c.status} />
                                            {c.status === 'returned' ? (
                                                <Button asChild size="sm" variant="outline">
                                                    <Link href={activityCalendars.edit({ document: c.id }).url}>
                                                        Revise
                                                    </Link>
                                                </Button>
                                            ) : (
                                                <Button asChild size="sm" variant="ghost">
                                                    <Link href={activityCalendars.show({ document: c.id }).url}>
                                                        View
                                                    </Link>
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ActivityCalendarsIndex.layout = {
    breadcrumbs: [{ title: 'Activity Calendars' }],
};
