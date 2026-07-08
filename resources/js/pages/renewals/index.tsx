import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

export default function RenewalsIndex({ renewals: items }: Props) {
    return (
        <>
            <Head title="Renewals" />

            <div className="mx-auto max-w-3xl space-y-6 p-8">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Organization Renewals</h1>
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
                            <p className="text-sm text-muted-foreground">No renewals yet.</p>
                        ) : (
                            <div className="divide-y">
                                {items.map((r) => (
                                    <div key={r.id} className="flex items-center justify-between py-3">
                                        <div>
                                            <p className="font-medium">{r.title}</p>
                                            <p className="text-sm text-muted-foreground">{r.organization.name}</p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Badge variant={statusVariant[r.status] ?? 'outline'}>
                                                {statusLabel(r.status)}
                                            </Badge>
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
