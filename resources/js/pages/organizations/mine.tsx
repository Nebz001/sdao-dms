import { Head, Link, usePage } from '@inertiajs/react';
import {
    Check,
    ChevronRight,
    GraduationCap,
    Mail,
    Plus,
    RefreshCw,
    ShieldCheck,
    UserPlus,
    Users,
} from 'lucide-react';
import AccountName from '@/components/account-name';
import { orgMonogram } from '@/components/org-branding';
import PageHeader from '@/components/page-header';
import { OrganizationStatusBadge, ToneBadge } from '@/components/status-badge';
import TagBadge from '@/components/tag-badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useInitials } from '@/hooks/use-initials';
import { buildRequirementRows, peopleSummary } from '@/lib/org-requirements';
import type {
    RequirementAction,
    RequirementItem,
} from '@/lib/org-requirements';
import { NO_SCHOOL_LABEL } from '@/lib/school';
import * as officerChange from '@/routes/organizations/officer-change';
import * as registrationRoutes from '@/routes/registrations';
import * as renewalRoutes from '@/routes/renewals';

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

const SEATS = [
    { position: 'president', label: 'President' },
    { position: 'secretary', label: 'Secretary' },
];

function actionHref(target: RequirementAction['target']): string {
    switch (target) {
        case 'registrations':
            return registrationRoutes.index().url;
        case 'officer-change':
            return officerChange.create().url;
        case 'renewal':
            return renewalRoutes.create().url;
    }
}

export default function MyOrganization({
    organization,
    status,
    coversThroughAcademicYear,
    renewalDue,
    requirements,
    officers,
    adviser,
}: Props) {
    const { auth } = usePage().props;
    const getInitials = useInitials();
    const rows = buildRequirementRows(
        requirements,
        renewalDue,
        organization.name,
        coversThroughAcademicYear,
    );
    const percent = rows.total === 0 ? 0 : (rows.doneCount / rows.total) * 100;
    const showSchool =
        organization.school !== null && organization.school !== NO_SCHOOL_LABEL;
    const emptySeats = SEATS.filter(
        (seat) => !officers.some((o) => o.position === seat.position),
    );

    return (
        <>
            <Head title={organization.name} />

            <div className="flex flex-col gap-6">
                <Card>
                    <CardContent className="flex flex-wrap items-center gap-5">
                        <Avatar className="size-20 rounded-xl">
                            <AvatarImage
                                src={auth?.organization?.logoUrl ?? undefined}
                                alt=""
                            />
                            <AvatarFallback className="rounded-xl bg-violet-500/10 text-2xl font-semibold text-violet-700 dark:bg-violet-400/15 dark:text-violet-300">
                                {orgMonogram(organization.name)}
                            </AvatarFallback>
                        </Avatar>
                        <div className="flex min-w-0 flex-1 basis-64 flex-col gap-3">
                            <PageHeader
                                title={organization.name}
                                subtitle="Your organization's profile"
                                badge={<OrganizationStatusBadge status={status} />}
                            />
                            <ul className="flex flex-wrap items-center gap-2">
                                {showSchool && (
                                    <li>
                                        <TagBadge className="h-7 gap-1.5 px-3 text-sm">
                                            <GraduationCap aria-hidden />
                                            {[
                                                organization.school,
                                                organization.program,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </TagBadge>
                                    </li>
                                )}
                                {coversThroughAcademicYear !== null && (
                                    <li>
                                        <TagBadge className="h-7 gap-1.5 px-3 text-sm">
                                            <ShieldCheck aria-hidden />
                                            Covered through{' '}
                                            <span className="font-medium">
                                                {coversThroughAcademicYear}
                                            </span>
                                        </TagBadge>
                                    </li>
                                )}
                                <li>
                                    <TagBadge className="h-7 gap-1.5 px-3 text-sm">
                                        <Users aria-hidden />
                                        {peopleSummary(
                                            officers.length,
                                            adviser ? 1 : 0,
                                        )}
                                    </TagBadge>
                                </li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
                        <div>
                            <CardTitle className="text-lg">
                                Requirements
                            </CardTitle>
                            <CardDescription>
                                What {organization.name} needs to stay active
                            </CardDescription>
                        </div>
                        <div className="flex items-center gap-3 text-sm">
                            <span>
                                <span className="font-semibold">
                                    {rows.doneCount} of {rows.total}
                                </span>{' '}
                                <span className="text-muted-foreground">
                                    done
                                </span>
                            </span>
                            <div
                                role="progressbar"
                                aria-label="Requirements done"
                                aria-valuemin={0}
                                aria-valuemax={rows.total}
                                aria-valuenow={rows.doneCount}
                                className="h-2 w-32 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    className="h-full rounded-full bg-success transition-[width] motion-reduce:transition-none"
                                    style={{ width: `${percent}%` }}
                                />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {rows.done.length > 0 && (
                            <ul className="flex flex-wrap gap-2">
                                {rows.done.map((item) => (
                                    <li key={item.key}>
                                        <ToneBadge
                                            tone="success"
                                            className="h-8 gap-2 px-3 text-sm tracking-normal normal-case"
                                        >
                                            <Check aria-hidden />
                                            {item.label}
                                        </ToneBadge>
                                    </li>
                                ))}
                            </ul>
                        )}
                        {rows.open.map((item) => (
                            <div
                                key={item.key}
                                className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-warning/40 bg-warning/5 p-4"
                            >
                                <div className="flex min-w-0 items-center gap-3">
                                    <span
                                        aria-hidden
                                        className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-warning/15 text-warning-foreground"
                                    >
                                        {item.key === 'renewal_filed' ? (
                                            <RefreshCw className="size-4" />
                                        ) : (
                                            <UserPlus className="size-4" />
                                        )}
                                    </span>
                                    <div className="min-w-0">
                                        <p className="font-semibold">
                                            {item.title}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {item.description}
                                        </p>
                                    </div>
                                </div>
                                {item.action && (
                                    <Button
                                        asChild
                                        variant="secondary"
                                        size="sm"
                                    >
                                        <Link
                                            href={actionHref(
                                                item.action.target,
                                            )}
                                        >
                                            {item.action.label}
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        ))}
                        {rows.open.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Everything is in place. Nothing needs your
                                action here.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <div className="grid gap-6 md:grid-cols-2 [&>*]:min-w-0">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">Officers</CardTitle>
                            <CardDescription>
                                People who can file documents for{' '}
                                {organization.name}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="divide-y">
                                {officers.map((o) => (
                                    <li
                                        key={o.id}
                                        className="flex items-center gap-3 py-3 first:pt-0"
                                    >
                                        <Avatar className="size-10">
                                            <AvatarFallback className="bg-indigo-500/10 text-sm font-medium text-indigo-700 dark:bg-indigo-400/15 dark:text-indigo-300">
                                                {getInitials(o.user.name)}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <AccountName
                                                    name={o.user.name}
                                                    nameClassName="font-semibold"
                                                />
                                                <TagBadge>
                                                    {o.position_label}
                                                </TagBadge>
                                                {o.user.id ===
                                                    auth?.user?.id && (
                                                    <ToneBadge
                                                        tone="info"
                                                        className="tracking-normal normal-case"
                                                    >
                                                        You
                                                    </ToneBadge>
                                                )}
                                            </div>
                                            <p className="mt-0.5 flex items-center gap-1.5 text-sm text-muted-foreground">
                                                <Mail
                                                    aria-hidden
                                                    className="size-3.5 shrink-0"
                                                />
                                                <span className="max-sm:break-all sm:truncate">
                                                    {o.user.email}
                                                </span>
                                            </p>
                                        </div>
                                    </li>
                                ))}
                                {emptySeats.map((seat) => (
                                    <li
                                        key={seat.position}
                                        className="flex items-center gap-3 py-3 last:pb-0"
                                    >
                                        <span
                                            aria-hidden
                                            className="flex size-10 shrink-0 items-center justify-center rounded-full border border-dashed text-muted-foreground"
                                        >
                                            <Plus className="size-4" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium text-muted-foreground">
                                                    No{' '}
                                                    {seat.label.toLowerCase()}{' '}
                                                    yet
                                                </span>
                                                <TagBadge>
                                                    {seat.label}
                                                </TagBadge>
                                            </div>
                                            <p className="text-sm text-muted-foreground">
                                                Add one through an officer
                                                change request
                                            </p>
                                        </div>
                                        <Link
                                            href={officerChange.create().url}
                                            aria-label={`Add a ${seat.label.toLowerCase()}`}
                                            className="inline-flex shrink-0 items-center gap-1 rounded-sm text-sm font-medium text-primary-text hover:underline focus-visible:focus-ring"
                                        >
                                            Add
                                            <ChevronRight
                                                aria-hidden
                                                className="size-4"
                                            />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">Adviser</CardTitle>
                            <CardDescription>
                                Signs off on your documents first
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {adviser === null ? (
                                <div className="flex items-center gap-3">
                                    <span
                                        aria-hidden
                                        className="flex size-10 shrink-0 items-center justify-center rounded-full border border-dashed text-muted-foreground"
                                    >
                                        <Plus className="size-4" />
                                    </span>
                                    <div>
                                        <p className="font-medium text-muted-foreground">
                                            No adviser yet
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            SDAO assigns an adviser to your
                                            organization.
                                        </p>
                                    </div>
                                </div>
                            ) : (
                                <div className="flex items-center gap-3">
                                    <Avatar className="size-10">
                                        <AvatarFallback className="bg-teal-500/10 text-sm font-medium text-teal-700 dark:bg-teal-400/15 dark:text-teal-300">
                                            {getInitials(adviser.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <AccountName
                                                name={adviser.name}
                                                nameClassName="font-semibold"
                                            />
                                            <TagBadge>Adviser</TagBadge>
                                        </div>
                                        <p className="mt-0.5 flex items-center gap-1.5 text-sm text-muted-foreground">
                                            <Mail
                                                aria-hidden
                                                className="size-3.5 shrink-0"
                                            />
                                            <span className="max-sm:break-all sm:truncate">
                                                {adviser.email}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
