import { Head, Link } from '@inertiajs/react';
import { Files } from 'lucide-react';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import * as renewals from '@/routes/renewals';

type Renewal = {
    id: number;
    title: string;
    status: string;
    organization: { id: number; name: string };
    created_at: string;
};

type Props = {
    renewals: Renewal[];
};

export default function RenewalsIndex({ renewals: items }: Props) {
    return (
        <>
            <Head title="Renewals" />

            <div className="mx-auto max-w-3xl space-y-6 p-8">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold tracking-tight text-balance">
                        Organization Renewals
                    </h1>
                    <Button asChild>
                        <Link href={renewals.create().url}>New Renewal</Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">My Renewals</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {items.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <Files />
                                    </EmptyMedia>
                                    <EmptyTitle>No renewals yet</EmptyTitle>
                                    <EmptyDescription>
                                        Once you submit a renewal, it'll show up here.
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <div className="divide-y">
                                {items.map((r) => (
                                    <div key={r.id} className="flex items-center justify-between gap-4 py-3">
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">{r.title}</p>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {r.organization.name}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-2">
                                            <StatusBadge status={r.status} />
                                            {r.status === 'returned' ? (
                                                <Button asChild size="sm" variant="outline">
                                                    <Link href={renewals.edit({ document: r.id }).url}>
                                                        Revise
                                                    </Link>
                                                </Button>
                                            ) : (
                                                <Button asChild size="sm" variant="ghost">
                                                    <Link href={renewals.show({ document: r.id }).url}>
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

RenewalsIndex.layout = {
    breadcrumbs: [{ title: 'Renewals' }],
};
