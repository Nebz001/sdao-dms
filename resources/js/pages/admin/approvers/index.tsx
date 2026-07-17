import { Head, Link } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import * as approvers from '@/routes/admin/approvers';

type RoleEntry = { role: string; label: string; scope: string };

type ApproverEntry = {
    id: number;
    name: string;
    email: string;
    roles: RoleEntry[];
};

type Props = {
    approvers: ApproverEntry[];
};

export default function AdminApproversIndex({ approvers: items }: Props) {
    return (
        <>
            <Head title="Approvers" />

            <div className="mx-auto max-w-3xl space-y-6 p-8">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold tracking-tight text-balance">Approver Accounts</h1>
                    <Button asChild>
                        <Link href={approvers.create().url}>Provision Approver</Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">All Approvers</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {items.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <ShieldCheck />
                                    </EmptyMedia>
                                    <EmptyTitle>No approvers provisioned yet</EmptyTitle>
                                    <EmptyDescription>
                                        Provisioned approver accounts will show up here.
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <div className="divide-y">
                                {items.map((a) => (
                                    <div key={a.id} className="flex items-center justify-between gap-4 py-3">
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">{a.name}</p>
                                            <p className="truncate text-sm text-muted-foreground">{a.email}</p>
                                        </div>
                                        <div className="flex shrink-0 flex-wrap justify-end gap-1">
                                            {a.roles.map((r, i) => (
                                                <Badge key={i} variant="secondary">
                                                    {r.label} · {r.scope}
                                                </Badge>
                                            ))}
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

AdminApproversIndex.layout = {
    breadcrumbs: [{ title: 'Admin' }, { title: 'Approvers' }],
};
