import { Head, Link } from '@inertiajs/react';
import { Files } from 'lucide-react';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import * as activityProposals from '@/routes/activity-proposals';

type Proposal = {
    id: number;
    title: string;
    status: string;
    calendar_mode: string | null;
    form_step: number | null;
    organization: { id: number; name: string };
    created_at: string;
};

type Props = {
    proposals: Proposal[];
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

export default function ActivityProposalsIndex({ proposals }: Props) {
    return (
        <>
            <Head title="Activity Proposals" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold tracking-tight text-balance">
                        Activity Proposals
                    </h1>
                    <Button asChild>
                        <Link href={activityProposals.create().url}>New Proposal</Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">My Proposals</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {proposals.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <Files />
                                    </EmptyMedia>
                                    <EmptyTitle>No proposals yet</EmptyTitle>
                                    <EmptyDescription>
                                        Once you submit an activity proposal, it'll show up here.
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <div className="divide-y">
                                {proposals.map((p) => (
                                    <div key={p.id} className="flex items-center justify-between gap-4 py-3">
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">{p.title}</p>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {p.organization.name}
                                                {p.calendar_mode && ` · ${modeLabel(p.calendar_mode)}`}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-2">
                                            <StatusBadge status={p.status} />
                                            {p.status === 'draft' ? (
                                                <Button asChild size="sm" variant="outline">
                                                    <Link href={activityProposals.continueMethod({ document: p.id }).url}>
                                                        Continue
                                                    </Link>
                                                </Button>
                                            ) : (
                                                <Button asChild size="sm" variant="ghost">
                                                    <Link href={activityProposals.show({ document: p.id }).url}>
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

ActivityProposalsIndex.layout = {
    breadcrumbs: [{ title: 'Activity Proposals' }],
};
