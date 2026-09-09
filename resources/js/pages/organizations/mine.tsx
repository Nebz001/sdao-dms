import { Head, Link } from '@inertiajs/react';
import { Check, UserCircle, Users, X } from 'lucide-react';
import { OrganizationStatusBadge } from '@/components/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { cn } from '@/lib/utils';
import * as renewals from '@/routes/renewals';

type RequirementItem = { key: string; label: string; met: boolean };

type OfficerEntry = {
    id: number;
    user: { id: number; name: string; email: string };
    position: string;
    position_label: string;
};

type Adviser = { id: number; name: string; email: string };

type Props = {
    organization: {
        id: number;
        name: string;
        school: string | null;
        program: string | null;
    };
    status: string;
    renewalDue: boolean;
    coversThroughAcademicYear: string | null;
    requirements: RequirementItem[];
    officers: OfficerEntry[];
    adviser: Adviser | null;
};

export default function MyOrganization({
    organization,
    status,
    renewalDue,
    coversThroughAcademicYear,
    requirements,
    officers,
    adviser,
}: Props) {
    return (
        <>
            <Head title={organization.name} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold tracking-tight text-balance">
                                {organization.name}
                            </h1>
                            <OrganizationStatusBadge status={status} />
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {[organization.school, organization.program]
                                .filter(Boolean)
                                .join(' · ') || 'No college'}
                        </p>
                    </div>
                </div>

                {renewalDue && (
                    <Alert>
                        <AlertTitle>Renewal is open</AlertTitle>
                        <AlertDescription>
                            <p>
                                It&apos;s renewal season, and{' '}
                                {organization.name} hasn&apos;t filed for next
                                year yet.
                            </p>
                            <Button asChild size="sm" className="mt-2">
                                <Link href={renewals.create().url}>
                                    Start renewal
                                </Link>
                            </Button>
                        </AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Requirements
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="divide-y">
                        {requirements.map((item) => (
                            <div
                                key={item.key}
                                className="flex items-center gap-3 py-2.5"
                            >
                                <span
                                    className={cn(
                                        'flex size-5 shrink-0 items-center justify-center rounded-full',
                                        item.met
                                            ? 'bg-success text-background'
                                            : 'bg-muted text-muted-foreground',
                                    )}
                                    aria-hidden
                                >
                                    {item.met ? (
                                        <Check className="size-3.5" />
                                    ) : (
                                        <X className="size-3.5" />
                                    )}
                                </span>
                                <span
                                    className={cn(
                                        'text-sm',
                                        !item.met && 'text-muted-foreground',
                                    )}
                                >
                                    {item.label}
                                </span>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <div className="grid gap-6 sm:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Officers
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {officers.length === 0 ? (
                                <Empty>
                                    <EmptyHeader>
                                        <EmptyMedia variant="icon">
                                            <Users />
                                        </EmptyMedia>
                                        <EmptyTitle>
                                            No active officers
                                        </EmptyTitle>
                                        <EmptyDescription>
                                            Ask your adviser to bind a president
                                            or secretary.
                                        </EmptyDescription>
                                    </EmptyHeader>
                                </Empty>
                            ) : (
                                <div className="divide-y">
                                    {officers.map((o) => (
                                        <div key={o.id} className="py-2.5">
                                            <p className="truncate font-medium">
                                                {o.user.name}
                                            </p>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {o.position_label} ·{' '}
                                                {o.user.email}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Adviser</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {adviser === null ? (
                                <Empty>
                                    <EmptyHeader>
                                        <EmptyMedia variant="icon">
                                            <UserCircle />
                                        </EmptyMedia>
                                        <EmptyTitle>
                                            No adviser bound
                                        </EmptyTitle>
                                        <EmptyDescription>
                                            SDAO binds an adviser when your
                                            registration is approved.
                                        </EmptyDescription>
                                    </EmptyHeader>
                                </Empty>
                            ) : (
                                <div>
                                    <p className="truncate font-medium">
                                        {adviser.name}
                                    </p>
                                    <p className="truncate text-sm text-muted-foreground">
                                        {adviser.email}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Coverage</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {coversThroughAcademicYear === null ? (
                            <p className="text-sm text-muted-foreground">
                                No approved coverage yet — your registration or
                                renewal is still in progress.
                            </p>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Covered through{' '}
                                <span className="font-medium text-foreground">
                                    {coversThroughAcademicYear}
                                </span>
                                .
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

MyOrganization.layout = {
    breadcrumbs: [{ title: 'My Organization' }],
};
