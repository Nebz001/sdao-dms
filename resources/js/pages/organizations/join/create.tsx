import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import * as organizationsJoin from '@/routes/organizations/join';
import * as registrations from '@/routes/registrations';

type OrganizationResult = {
    id: number;
    name: string;
    school: string | null;
    program: string | null;
};

type Props = {
    alreadyAffiliated: boolean;
    pendingRequest: { organization: { name: string } } | null;
};

export default function JoinOrganization({
    alreadyAffiliated,
    pendingRequest,
}: Props) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<OrganizationResult[]>([]);
    const [selected, setSelected] = useState<OrganizationResult | null>(null);
    const [status, setStatus] = useState<'idle' | 'searching' | 'done'>(
        'idle',
    );
    const [searchFailed, setSearchFailed] = useState(false);
    const debounceTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const latestQuery = useRef('');

    const [processing, setProcessing] = useState(false);
    const [submitError, setSubmitError] = useState<string | null>(null);

    // Mirrors registrations/create.tsx's adviser-search pattern exactly:
    // 600ms debounce, stale-response guard so a slow reply for a query the
    // student has since changed or cleared never overwrites fresher results.
    const search = useCallback((q: string) => {
        if (q.trim() === '') {
            setResults([]);
            setStatus('idle');
            setSearchFailed(false);

            return;
        }

        setStatus('searching');
        setSearchFailed(false);

        fetch(organizationsJoin.search.url({ query: { q } }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((res) => res.json())
            .then((data) => {
                if (latestQuery.current !== q) {
                    return;
                }

                setResults(data.organizations ?? []);
                setStatus('done');
            })
            .catch(() => {
                if (latestQuery.current !== q) {
                    return;
                }

                setSearchFailed(true);
                setStatus('done');
            });
    }, []);

    useEffect(() => {
        latestQuery.current = query;

        if (debounceTimer.current) {
            clearTimeout(debounceTimer.current);
        }

        debounceTimer.current = setTimeout(() => search(query), 600);

        return () => {
            if (debounceTimer.current) {
                clearTimeout(debounceTimer.current);
            }
        };
    }, [query, search]);

    function selectOrganization(organization: OrganizationResult) {
        setSelected(organization);
        setResults([]);
        setStatus('idle');
        setQuery(organization.name);
    }

    if (pendingRequest) {
        return (
            <>
                <Head title="Join an Organization" />
                <div className="mx-auto w-full max-w-2xl">
                    <Heading
                        title="Join an Organization"
                        description="Your request is already on its way."
                    />
                    <p className="text-sm text-muted-foreground">
                        You have a pending request to join{' '}
                        <strong className="text-foreground">
                            {pendingRequest.organization.name}
                        </strong>
                        . Its adviser or an active officer will approve or
                        decline it — you&apos;ll be notified either way.
                    </p>
                </div>
            </>
        );
    }

    if (alreadyAffiliated) {
        return (
            <>
                <Head title="Join an Organization" />
                <div className="mx-auto w-full max-w-2xl">
                    <Heading title="Join an Organization" />
                    <p className="text-sm text-muted-foreground">
                        You&apos;re already an active officer of an
                        organization. A student can only belong to one
                        organization at a time.
                    </p>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Join an Organization" />

            <div className="mx-auto w-full max-w-2xl space-y-6">
                <Heading
                    title="Join an Organization"
                    description="Search for your organization below. Once you send a request, its adviser or an active officer will need to approve it before you gain access."
                />

                <div className="grid gap-2">
                    <Label htmlFor="organization-search">Organization</Label>
                    <Input
                        id="organization-search"
                        placeholder="Search by organization name…"
                        value={query}
                        onChange={(e) => {
                            setQuery(e.target.value);
                            setSelected(null);
                        }}
                        autoComplete="off"
                    />
                    {query.trim() !== '' && status === 'searching' && (
                        <p className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Spinner className="size-3.5" /> Searching
                            organizations…
                        </p>
                    )}
                    {status === 'done' && searchFailed && (
                        <p className="text-sm text-destructive">
                            Couldn&apos;t search organizations just now. Try
                            again.
                        </p>
                    )}
                    {results.length > 0 && (
                        <div className="divide-y rounded-md border">
                            {results.map((org) => (
                                <button
                                    key={org.id}
                                    type="button"
                                    onClick={() => selectOrganization(org)}
                                    className="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left text-sm hover:bg-accent"
                                >
                                    <span>{org.name}</span>
                                    {(org.school || org.program) && (
                                        <span className="text-xs text-muted-foreground">
                                            {[org.school, org.program]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </span>
                                    )}
                                </button>
                            ))}
                        </div>
                    )}

                    {/* Per the task: always available, emphasized once a
                        search has actually come back empty — the system can
                        only show organizations it already knows about. */}
                    <p
                        className={
                            status === 'done' &&
                            !searchFailed &&
                            results.length === 0 &&
                            !selected
                                ? 'text-sm text-foreground'
                                : 'text-sm text-muted-foreground'
                        }
                    >
                        Can&apos;t find your organization?{' '}
                        <Link
                            href={registrations.create()}
                            className="underline underline-offset-2 hover:text-primary"
                        >
                            Register it as new instead
                        </Link>
                        .
                    </p>

                    {submitError && (
                        <p className="text-sm text-destructive">
                            {submitError}
                        </p>
                    )}
                </div>

                <ConfirmDialog
                    trigger={
                        <Button disabled={!selected} data-icon="inline-start">
                            <Search />
                            Request to Join
                        </Button>
                    }
                    title="Send this join request?"
                    description={
                        <>
                            SDAO membership requests go to{' '}
                            <strong>{selected?.name}</strong>&apos;s adviser
                            and active officers for approval. You won&apos;t
                            have access to the organization until one of them
                            approves it.
                        </>
                    }
                    confirmLabel="Send Request"
                    onConfirm={({ close, stopProcessing }) => {
                        setProcessing(true);
                        setSubmitError(null);

                        router.post(
                            organizationsJoin.store().url,
                            { organization_id: selected?.id },
                            {
                                preserveScroll: true,
                                onSuccess: close,
                                onError: (errors) => {
                                    setSubmitError(
                                        errors.organization ??
                                            'Something went wrong. Please try again.',
                                    );
                                    stopProcessing();
                                },
                                onFinish: () => setProcessing(false),
                            },
                        );
                    }}
                    confirmDisabled={processing}
                />
            </div>
        </>
    );
}

JoinOrganization.layout = {
    breadcrumbs: [{ title: 'Join an Organization' }],
};
