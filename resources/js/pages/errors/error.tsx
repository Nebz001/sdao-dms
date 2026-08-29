import { Head, Link, usePage } from '@inertiajs/react';
import { Home, LogIn } from 'lucide-react';
import AppLogoLockup from '@/components/app-logo-lockup';
import { Button } from '@/components/ui/button';
import { home, login } from '@/routes';

type Props = {
    status: number;
};

type ErrorCopy = {
    title: string;
    description: string;
};

// 400 and 405 are both "the request itself was malformed" — same generic,
// non-actionable copy (see CLAUDE.md: both are described as generic
// client-side request errors, unlike the other codes below which each have
// a distinct next step for the user).
const REQUEST_ERROR: ErrorCopy = {
    title: "There was a problem with that request",
    description:
        "We couldn't process that request. Please try again, or go back to where you were.",
};

// 500/502/503/504 are all "our servers, not you" — one shared message
// rather than four near-identical ones.
const SERVER_ERROR: ErrorCopy = {
    title: "We're having trouble on our end",
    description:
        'Something went wrong while handling your request. Please try again in a few minutes.',
};

const ERROR_COPY: Record<number, ErrorCopy> = {
    400: REQUEST_ERROR,
    401: {
        title: 'Please log in to continue',
        description: 'You need to be logged in to view this page.',
    },
    403: {
        title: "You don't have access to this page",
        description:
            "Your account doesn't have permission to view this. If you think this is a mistake, contact SDAO.",
    },
    404: {
        title: 'Page not found',
        description:
            "We couldn't find the page you were looking for. It may have been moved, or the link might be incorrect.",
    },
    405: REQUEST_ERROR,
    408: {
        title: 'That took too long',
        description: 'Your request timed out before it could finish. Please try again.',
    },
    409: {
        title: "That couldn't be completed",
        description:
            'Something changed before your request could go through. Please refresh the page and try again.',
    },
    419: {
        title: 'Your session has expired',
        description:
            'For your security, you were signed out after a period of inactivity. Please log in again to continue.',
    },
    422: {
        title: "We couldn't save that",
        description:
            "Some of the information you entered isn't valid. Please review the form and try again.",
    },
    429: {
        title: 'Slow down a little',
        description: "You're sending requests a bit too quickly. Please wait a moment and try again.",
    },
    500: SERVER_ERROR,
    502: SERVER_ERROR,
    503: SERVER_ERROR,
    504: SERVER_ERROR,
};

const DEFAULT_COPY: ErrorCopy = {
    title: 'Something went wrong',
    description: 'An unexpected error occurred. Please try again.',
};

// 401 and 419 both mean "you're not signed in (anymore)" — logging back in
// is the actual next step, not going home.
const LOGIN_STATUSES = new Set([401, 419]);

export default function ErrorPage({ status }: Props) {
    const { auth } = usePage().props;
    const copy = ERROR_COPY[status] ?? DEFAULT_COPY;
    const offerLogin = LOGIN_STATUSES.has(status);

    return (
        <>
            <Head title={`${status} — ${copy.title}`} />

            <div className="flex min-h-svh flex-col bg-background">
                <header className="border-b">
                    <div className="mx-auto flex h-16 w-full max-w-7xl items-center px-6 sm:h-20">
                        <Link
                            href={home()}
                            className="inline-flex items-center rounded-md outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <AppLogoLockup className="h-10" />
                        </Link>
                    </div>
                </header>

                <main className="flex flex-1 items-center justify-center px-6 py-16">
                    <div className="flex max-w-md flex-col items-center gap-6 text-center">
                        <p
                            aria-hidden="true"
                            className="text-7xl font-bold tracking-tight text-brand sm:text-8xl"
                        >
                            {status}
                        </p>

                        <div className="flex flex-col gap-2">
                            <h1 className="text-lg font-medium tracking-tight">
                                {copy.title}
                            </h1>
                            <p className="text-sm/relaxed text-muted-foreground">
                                {copy.description}
                            </p>
                        </div>

                        {offerLogin ? (
                            <Button variant="brand" asChild>
                                <Link href={login()}>
                                    <LogIn data-icon="inline-start" />
                                    Log in again
                                </Link>
                            </Button>
                        ) : (
                            <Button variant="brand" asChild>
                                <Link href={home()}>
                                    <Home data-icon="inline-start" />
                                    {auth.user ? 'Back to dashboard' : 'Back to home'}
                                </Link>
                            </Button>
                        )}
                    </div>
                </main>
            </div>
        </>
    );
}
