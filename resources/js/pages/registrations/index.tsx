import { Head, Link } from '@inertiajs/react';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import * as registrations from '@/routes/registrations';

type Registration = {
    id: number;
    title: string;
    status: string;
    organization: { id: number; name: string };
    created_at: string;
};

type Props = {
    registrations: Registration[];
};

export default function RegistrationsIndex({ registrations: items }: Props) {
    return (
        <>
            <Head title="Registrations" />

            <div className="mx-auto max-w-3xl space-y-6 p-8">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Registrations</h1>
                    <Button asChild>
                        <Link href={registrations.create().url}>New Registration</Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">My Registrations</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {items.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No registrations yet.</p>
                        ) : (
                            <div className="divide-y">
                                {items.map((r) => (
                                    <div key={r.id} className="flex items-center justify-between py-3">
                                        <div>
                                            <p className="font-medium">{r.title}</p>
                                            <p className="text-sm text-muted-foreground">{r.organization.name}</p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <StatusBadge status={r.status} />
                                            {r.status === 'returned' ? (
                                                <Button asChild size="sm" variant="outline">
                                                    <Link href={registrations.edit({ document: r.id }).url}>
                                                        Revise
                                                    </Link>
                                                </Button>
                                            ) : (
                                                <Button asChild size="sm" variant="ghost">
                                                    <Link href={registrations.show({ document: r.id }).url}>
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

RegistrationsIndex.layout = {
    breadcrumbs: [{ title: 'Registrations' }],
};
