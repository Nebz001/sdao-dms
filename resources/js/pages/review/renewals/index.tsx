import { Head, Link } from '@inertiajs/react';
import { Inbox } from 'lucide-react';
import PageHeader from '@/components/page-header';
import PageNotice from '@/components/page-notice';
import QueueStatStrip from '@/components/queue-stat-strip';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import * as reviewRenewals from '@/routes/review/renewals';

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

export default function ReviewRenewalsIndex({ queue }: Props) {
    useDocumentUpdates(['queue']);

    const oldest =
        queue.length > 0
            ? new Date(
                  Math.min(
                      ...queue.map((d) => new Date(d.created_at).getTime()),
                  ),
              ).toLocaleDateString()
            : '—';

    return (
        <>
            <Head title="Review: Renewals" />

            <div className="space-y-6">
                <PageHeader title="Renewal Review Queue" subtitle="Organization renewals waiting for SDAO review" />

                {queue.length > 0 && (
                    <PageNotice tone="info" icon={Inbox}>
                        {queue.length} renewal
                        {queue.length !== 1 ? 's' : ''} awaiting review.
                    </PageNotice>
                )}

                <QueueStatStrip
                    stats={[
                        {
                            label: 'Pending',
                            value: String(queue.length),
                            count: queue.length,
                        },
                        { label: 'Oldest waiting', value: oldest },
                    ]}
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Pending</CardTitle>
                    </CardHeader>
                    <CardContent
                        className={queue.length > 0 ? 'divide-y' : undefined}
                    >
                        {queue.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <Inbox />
                                    </EmptyMedia>
                                    <EmptyTitle>Nothing to review</EmptyTitle>
                                    <EmptyDescription>
                                        Submitted renewals will show up here as
                                        soon as a student org sends one in.
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            queue.map((doc) => (
                                <div
                                    key={doc.id}
                                    className="flex items-center justify-between gap-4 py-3"
                                >
                                    <div className="min-w-0">
                                        <p className="sm:truncate max-sm:break-words font-medium">
                                            {doc.title}
                                        </p>
                                        <p className="sm:truncate max-sm:break-words text-sm text-muted-foreground">
                                            {doc.organization.name} ·{' '}
                                            {new Date(
                                                doc.created_at,
                                            ).toLocaleDateString()}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        <StatusBadge status="in_review" />
                                        <Button asChild size="sm">
                                            <Link
                                                href={reviewRenewals.show(
                                                    doc.id,
                                                )}
                                            >
                                                Review
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ReviewRenewalsIndex.layout = {
    breadcrumbs: [{ title: 'Review' }, { title: 'Renewals' }],
};
