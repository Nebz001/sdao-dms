import { Head, Link } from '@inertiajs/react';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import * as reports from '@/routes/reports';

type ReportEntry = {
    id: number;
    title: string;
    status: string;
    organization: { id: number; name: string };
    created_at: string;
};

type Props = {
    reports: ReportEntry[];
};

export default function ReportsIndex({ reports: items }: Props) {
    return (
        <>
            <Head title="After-Activity Reports" />

            <div className="mx-auto max-w-3xl space-y-6 p-8">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">After-Activity Reports</h1>
                    <Button asChild>
                        <Link href={reports.create().url}>New Report</Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">My Reports</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {items.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No reports yet.</p>
                        ) : (
                            <div className="divide-y">
                                {items.map((r) => (
                                    <div key={r.id} className="flex items-center justify-between py-3">
                                        <div>
                                            <p className="font-medium">{r.title}</p>
                                            <p className="text-sm text-muted-foreground">{r.organization.name}</p>
                                            <p className="text-xs text-muted-foreground">
                                                Date Submitted: {new Date(r.created_at).toLocaleDateString()}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <StatusBadge status={r.status} />
                                            {r.status === 'returned' ? (
                                                <Button asChild size="sm" variant="outline">
                                                    <Link href={reports.edit({ document: r.id }).url}>
                                                        Revise
                                                    </Link>
                                                </Button>
                                            ) : (
                                                <Button asChild size="sm" variant="ghost">
                                                    <Link href={reports.show({ document: r.id }).url}>
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

ReportsIndex.layout = {
    breadcrumbs: [{ title: 'Reports' }],
};
