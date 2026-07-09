import { Head, usePage } from '@inertiajs/react';
import { Ban, Hourglass } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { dashboard } from '@/routes';

export default function Dashboard() {
    const { auth } = usePage().props;
    const accountStatus = auth.user.account_status;

    if (accountStatus === 'unverified') {
        return (
            <>
                <Head title="Dashboard" />
                <div className="mx-auto max-w-2xl p-8">
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
                <div className="mx-auto max-w-2xl p-8">
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

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                </div>
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
