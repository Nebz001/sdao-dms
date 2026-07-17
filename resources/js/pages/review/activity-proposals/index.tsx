import { Head, Link } from '@inertiajs/react';
import { Inbox } from 'lucide-react';
import QueueStatStrip from '@/components/queue-stat-strip';
import { StatusBadge, statusBorderClass } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import * as reviewActivityProposals from '@/routes/review/activity-proposals';

type QueueItem = {
    id: number;
    title: string;
    status: string;
    current_step_position: number | null;
    calendar_mode: string | null;
    organization: { id: number; name: string };
    created_at: string;
};

type Props = {
    queue: QueueItem[];
};

function modeLabel(mode: string | null): string {
    if (mode === 'on_calendar') {
return 'On Calendar';
}

    if (mode === 'off_calendar') {
return 'Off Calendar';
}

    return '';
}

export default function ReviewActivityProposalsIndex({ queue }: Props) {
    useDocumentUpdates(['queue']);

    const oldest = queue.length > 0
        ? new Date(Math.min(...queue.map((d) => new Date(d.created_at).getTime()))).toLocaleDateString()
        : '—';

    return (
        <>
            <Head title="Review Activity Proposals" />

            <div className="mx-auto max-w-3xl space-y-6 p-8">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-balance">
                        Activity Proposals — Review Queue
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {queue.length === 0
                            ? 'No proposals awaiting your review.'
                            : `${queue.length} proposal${queue.length !== 1 ? 's' : ''} awaiting your review.`}
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
                            <EmptyTitle>Nothing waiting on you</EmptyTitle>
                            <EmptyDescription>
                                Proposals will show up here once they reach a step routed to your role.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <Card className={`border-l-4 ${statusBorderClass('in_review')}`}>
                        <CardHeader>
                            <CardTitle className="text-base">Pending Your Action</CardTitle>
                        </CardHeader>
                        <CardContent className="divide-y">
                            {queue.map((item) => (
                                <div key={item.id} className="flex items-center justify-between gap-4 py-3">
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">{item.title}</p>
                                        <p className="truncate text-sm text-muted-foreground">
                                            {item.organization.name}
                                            {item.calendar_mode && ` · ${modeLabel(item.calendar_mode)}`}
                                            {item.current_step_position != null &&
                                                ` · Step ${item.current_step_position}`}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        <StatusBadge status="in_review" />
                                        <Button asChild size="sm">
                                            <Link href={reviewActivityProposals.show({ document: item.id }).url}>
                                                Review
                                            </Link>
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

ReviewActivityProposalsIndex.layout = {
    breadcrumbs: [{ title: 'Review Activity Proposals' }],
};
