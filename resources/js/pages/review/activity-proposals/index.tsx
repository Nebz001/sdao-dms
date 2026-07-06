import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    return (
        <>
            <Head title="Review Activity Proposals" />

            <div className="mx-auto max-w-3xl space-y-6 p-8">
                <h1 className="text-xl font-semibold">Activity Proposals — Review Queue</h1>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Pending Your Action</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {queue.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No proposals awaiting your review.</p>
                        ) : (
                            <div className="divide-y">
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
                                        <Button asChild size="sm" variant="outline">
                                            <Link href={reviewActivityProposals.show({ document: item.id }).url}>
                                                Review
                                            </Link>
                                        </Button>
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

ReviewActivityProposalsIndex.layout = {
    breadcrumbs: [{ title: 'Review Activity Proposals' }],
};
