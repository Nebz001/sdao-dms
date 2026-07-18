import { Head, Link } from '@inertiajs/react';
import { Inbox } from 'lucide-react';
import QueueStatStrip from '@/components/queue-stat-strip';
import { StatusBadge, statusBorderClass } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import * as reviewActivityCalendars from '@/routes/review/activity-calendars';

type QueueEntry = {
    id: number;
    title: string;
    status: string;
    current_step_position: number | null;
    organization: { id: number; name: string };
    created_at: string;
};

type Props = {
    queue: QueueEntry[];
};

export default function ReviewActivityCalendarsIndex({ queue }: Props) {
    useDocumentUpdates(['queue']);

    const oldest = queue.length > 0
        ? new Date(Math.min(...queue.map((d) => new Date(d.created_at).getTime()))).toLocaleDateString()
        : '—';

    return (
        <>
            <Head title="Review: Activity Calendars" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-balance">
                        Activity Calendar Review Queue
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {queue.length === 0
                            ? 'No activity calendars pending review.'
                            : `${queue.length} calendar${queue.length !== 1 ? 's' : ''} awaiting review.`}
                    </p>
                </div>

                <QueueStatStrip
                    stats={[
                        { label: 'Pending', value: String(queue.length) },
                        { label: 'Oldest waiting', value: oldest },
                    ]}
                />

                {queue.length === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <Inbox />
                            </EmptyMedia>
                            <EmptyTitle>Nothing to review</EmptyTitle>
                            <EmptyDescription>
                                Submitted activity calendars will show up here as soon as a student org sends
                                one in.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <Card className={`border-l-4 ${statusBorderClass('in_review')}`}>
                        <CardHeader>
                            <CardTitle className="text-base">Pending</CardTitle>
                        </CardHeader>
                        <CardContent className="divide-y">
                            {queue.map((doc) => (
                                <div key={doc.id} className="flex items-center justify-between gap-4 py-3">
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">{doc.title}</p>
                                        <p className="truncate text-sm text-muted-foreground">
                                            {doc.organization.name} ·{' '}
                                            {new Date(doc.created_at).toLocaleDateString()}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        <StatusBadge status="in_review" />
                                        <Button asChild size="sm">
                                            <Link href={reviewActivityCalendars.show(doc.id)}>Review</Link>
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

ReviewActivityCalendarsIndex.layout = {
    breadcrumbs: [{ title: 'Review' }, { title: 'Activity Calendars' }],
};
