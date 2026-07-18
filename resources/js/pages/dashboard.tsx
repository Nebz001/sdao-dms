import { Head, Link, usePage } from '@inertiajs/react';
import { Ban, FilePlus2, Hourglass, UserCog } from 'lucide-react';
import DashboardStatCard from '@/components/dashboard-stat-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import * as calendar from '@/routes/calendar';
import * as registrations from '@/routes/registrations';

type OrgDocItem = { id: number; title: string; status: string; href: string };
type QueueCountRow = { label: string; count: number; href: string };
type ProposalItem = { id: number; title: string; href: string };

type Props = {
    myOrganization: { id: number; name: string; count: number; items: OrgDocItem[] } | null;
    sdaoQueueCounts: QueueCountRow[] | null;
    proposalsAtMyStep: { count: number; items: ProposalItem[]; href: string } | null;
};

export default function Dashboard({ myOrganization, sdaoQueueCounts, proposalsAtMyStep }: Props) {
    const { auth } = usePage().props;
    const accountStatus = auth.user.account_status;

    if (accountStatus === 'unverified') {
        return (
            <>
                <Head title="Dashboard" />
                <div className="mx-auto w-full max-w-2xl">
                    <Alert>
                        <Hourglass />
                        <AlertTitle>Pending SDAO verification</AlertTitle>
                        <AlertDescription>
                            <p>
                                Your account is awaiting review. Once SDAO verifies it, you&apos;ll be
                                able to be bound as an organization officer and submit documents.
                            </p>
                            <p>There&apos;s nothing else to do right now — check back later.</p>
                        </AlertDescription>
                    </Alert>
                </div>
            </>
        );
    }

    if (accountStatus === 'rejected') {
        return (
            <>
                <Head title="Dashboard" />
                <div className="mx-auto w-full max-w-2xl">
                    <Alert variant="destructive">
                        <Ban />
                        <AlertTitle>Account not approved</AlertTitle>
                        <AlertDescription>
                            <p>SDAO reviewed your registration and it was not approved.</p>
                            <p>Contact SDAO directly if you believe this was a mistake.</p>
                        </AlertDescription>
                    </Alert>
                </div>
            </>
        );
    }

    const hasAnyCard = Boolean(myOrganization || sdaoQueueCounts || proposalsAtMyStep);

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4">
                {!hasAnyCard ? (
                    <div className="mx-auto w-full max-w-2xl">
                        {auth.canProposeOrganization ? (
                            <Alert>
                                <FilePlus2 />
                                <AlertTitle>Ready to register your organization?</AlertTitle>
                                <AlertDescription>
                                    <p>
                                        Your account is verified and you aren&apos;t affiliated with an
                                        organization yet. If your organization isn&apos;t registered in
                                        the system, you can submit its registration now — SDAO will review
                                        it, and you&apos;ll be bound as its president once it&apos;s
                                        approved.
                                    </p>
                                    <p>
                                        Already part of an existing organization? Ask its adviser to add
                                        you as an officer instead.
                                    </p>
                                    <Button asChild className="mt-2">
                                        <Link href={registrations.create()}>Submit Registration</Link>
                                    </Button>
                                </AlertDescription>
                            </Alert>
                        ) : (
                            <Alert>
                                <UserCog />
                                <AlertTitle>Nothing to do yet</AlertTitle>
                                <AlertDescription>
                                    <p>
                                        Your account is verified, but you aren&apos;t bound to an
                                        organization or an approval role yet.
                                    </p>
                                    <p>
                                        Ask your organization&apos;s adviser to add you as an officer, or
                                        check the{' '}
                                        <Link href={calendar.index()} className="underline">
                                            Venue Calendar
                                        </Link>{' '}
                                        in the meantime.
                                    </p>
                                </AlertDescription>
                            </Alert>
                        )}
                    </div>
                ) : (
                    <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                        {myOrganization && (
                            <DashboardStatCard
                                title={`Your Organization — ${myOrganization.name}`}
                                headlineCount={myOrganization.count}
                                emptyLabel="Nothing needs your attention right now."
                                rows={myOrganization.items.map((d) => ({
                                    key: d.id,
                                    label: d.title,
                                    href: d.href,
                                    status: d.status,
                                }))}
                            />
                        )}

                        {sdaoQueueCounts && (
                            <DashboardStatCard
                                title="Awaiting Your Review"
                                headlineCount={sdaoQueueCounts.reduce((sum, r) => sum + r.count, 0)}
                                emptyLabel="Nothing is awaiting SDAO review."
                                rows={sdaoQueueCounts.map((r) => ({
                                    key: r.label,
                                    label: r.label,
                                    href: r.href,
                                    count: r.count,
                                }))}
                            />
                        )}

                        {proposalsAtMyStep && (
                            <DashboardStatCard
                                title="Proposals At Your Step"
                                headlineCount={proposalsAtMyStep.count}
                                emptyLabel="No proposals are waiting on you."
                                viewAllHref={proposalsAtMyStep.count > 0 ? proposalsAtMyStep.href : undefined}
                                rows={proposalsAtMyStep.items.map((p) => ({
                                    key: p.id,
                                    label: p.title,
                                    href: p.href,
                                }))}
                            />
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
