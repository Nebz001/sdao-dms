import { Head, Link } from '@inertiajs/react';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

            <div className="mx-auto max-w-3xl space-y-6 p-8">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Activity Proposals</h1>
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
                            <p className="text-sm text-muted-foreground">No proposals yet.</p>
                        ) : (
                            <div className="divide-y">
                                {proposals.map((p) => (
                                    <div key={p.id} className="flex items-center justify-between py-3">
                                        <div>
                                            <p className="font-medium">{p.title}</p>
                                            <p className="text-sm text-muted-foreground">
                                                {p.organization.name}
                                                {p.calendar_mode && ` · ${modeLabel(p.calendar_mode)}`}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
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
