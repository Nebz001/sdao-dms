import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

    return (
        <>
            <Head title="Review Activity Proposals" />

            <div className="mx-auto max-w-3xl space-y-6 p-8">
                <div>
                    <h1 className="text-xl font-semibold">Activity Proposals — Review Queue</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {queue.length === 0
                            ? 'No proposals awaiting your review.'
                            : `${queue.length} proposal${queue.length !== 1 ? 's' : ''} awaiting your review.`}
                    </p>
                </div>

                {queue.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Pending Your Action</CardTitle>
                        </CardHeader>
                        <CardContent className="divide-y">
                            {queue.map((item) => (
                                <div key={item.id} className="flex items-center justify-between py-3">
                                    <div>
                                        <p className="font-medium">{item.title}</p>
                                        <p className="text-sm text-muted-foreground">
                                            {item.organization.name}
                                            {item.calendar_mode && ` · ${modeLabel(item.calendar_mode)}`}
                                            {item.current_step_position != null &&
                                                ` · Step ${item.current_step_position}`}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge variant="secondary">In Review</Badge>
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
