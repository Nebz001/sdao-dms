import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    draft: 'outline',
    in_review: 'secondary',
    returned: 'outline',
    approved: 'default',
    rejected: 'destructive',
};

function statusLabel(status: string): string {
    return status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export default function ActivityCalendarsIndex({ calendars }: Props) {
    return (
        <>
            <Head title="Activity Calendars" />

            <div className="mx-auto max-w-3xl space-y-6 p-8">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Activity Calendars</h1>
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
                            <p className="text-sm text-muted-foreground">No activity calendars yet.</p>
                        ) : (
                            <div className="divide-y">
                                {calendars.map((c) => (
                                    <div key={c.id} className="flex items-center justify-between py-3">
                                        <div>
                                            <p className="font-medium">{c.title}</p>
                                            <p className="text-sm text-muted-foreground">{c.organization.name}</p>
                                            <p className="text-xs text-muted-foreground">
                                                Date Received: {new Date(c.created_at).toLocaleDateString()}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Badge variant={statusVariant[c.status] ?? 'outline'}>
                                                {statusLabel(c.status)}
                                            </Badge>
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
