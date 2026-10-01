import { Head, Link } from '@inertiajs/react';
import { Files } from 'lucide-react';
import PageHeader from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
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

            <div className="space-y-6">
                <PageHeader title="After-Activity Reports" subtitle="Reports for your organization's approved activities" actions={
<Button asChild>
                        <Link href={reports.create().url}>New Report</Link>
                    </Button>
} />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">My Reports</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {items.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <Files />
                                    </EmptyMedia>
                                    <EmptyTitle>No reports yet</EmptyTitle>
                                    <EmptyDescription>
                                        Once you submit an after-activity report, it'll show up here.
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <div className="divide-y">
                                {items.map((r) => (
                                    <div key={r.id} className="flex items-center justify-between gap-4 py-3">
                                        <div className="min-w-0">
                                            <p className="sm:truncate max-sm:break-words font-medium">{r.title}</p>
                                            <p className="sm:truncate max-sm:break-words text-sm text-muted-foreground">
                                                {r.organization.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Date Submitted: {new Date(r.created_at).toLocaleDateString()}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-2">
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
