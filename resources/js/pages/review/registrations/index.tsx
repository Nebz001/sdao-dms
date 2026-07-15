import { Head, Link } from '@inertiajs/react';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import * as reviewRegistrations from '@/routes/review/registrations';

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

export default function ReviewRegistrationsIndex({ queue }: Props) {
    useDocumentUpdates(['queue']);

    return (
        <>
            <Head title="Review: Registrations" />

            <div className="mx-auto max-w-3xl space-y-6 p-8">
                <div>
                    <h1 className="text-xl font-semibold">Registration Review Queue</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {queue.length === 0
                            ? 'No registrations pending review.'
                            : `${queue.length} registration${queue.length !== 1 ? 's' : ''} awaiting review.`}
                    </p>
                </div>

                {queue.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Pending</CardTitle>
                        </CardHeader>
                        <CardContent className="divide-y">
                            {queue.map((doc) => (
                                <div
                                    key={doc.id}
                                    className="flex items-center justify-between py-3"
                                >
                                    <div>
                                        <p className="font-medium">{doc.title}</p>
                                        <p className="text-sm text-muted-foreground">
                                            {doc.organization.name} ·{' '}
                                            {new Date(doc.created_at).toLocaleDateString()}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <StatusBadge status="in_review" />
                                        <Button asChild size="sm">
                                            <Link href={reviewRegistrations.show(doc.id)}>
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

ReviewRegistrationsIndex.layout = {
    breadcrumbs: [{ title: 'Review' }, { title: 'Registrations' }],
};
