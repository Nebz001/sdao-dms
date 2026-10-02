import { Head, router } from '@inertiajs/react';
import { UserRoundCog } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import CenteredContainer from '@/components/centered-container';
import ConfirmDialog from '@/components/confirm-dialog';
import PageHeader from '@/components/page-header';
import PageNotice from '@/components/page-notice';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import * as officerChange from '@/routes/organizations/officer-change';

type PositionOption = { value: string; label: string };

type StudentResult = { id: number; name: string; email: string };

type Props = {
    organization: { id: number; name: string } | null;
    currentOfficers: Array<{
        position: string;
        position_label: string;
        user: { id: number; name: string };
    }>;
    pendingRequest: {
        position_label: string;
        nominee: { name: string };
    } | null;
    positions: PositionOption[];
};

export default function RequestOfficerChange({
    organization,
    currentOfficers,
    pendingRequest,
    positions,
}: Props) {
    const [position, setPosition] = useState(positions[0]?.value ?? '');
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<StudentResult[]>([]);
    const [selected, setSelected] = useState<StudentResult | null>(null);
    const [status, setStatus] = useState<'idle' | 'searching' | 'done'>(
        'idle',
    );
    const [searchFailed, setSearchFailed] = useState(false);
    const debounceTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const latestQuery = useRef('');

    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);
    const [submitError, setSubmitError] = useState<string | null>(null);

    // Mirrors organizations/join/create.tsx's typeahead pattern exactly:
    // 600ms debounce, stale-response guard so a slow reply for a query the
    // officer has since changed or cleared never overwrites fresher results.
    const search = useCallback((q: string) => {
        if (q.trim() === '') {
            setResults([]);
            setStatus('idle');
            setSearchFailed(false);

            return;
        }

        setStatus('searching');
        setSearchFailed(false);

        fetch(officerChange.search.url({ query: { q } }), {
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

                setResults(data.students ?? []);
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

    function selectStudent(student: StudentResult) {
        setSelected(student);
        setResults([]);
        setStatus('idle');
        setQuery(student.name);
    }

    if (organization === null) {
        return (
            <>
                <Head title="Request Officer Change" />
                <CenteredContainer maxWidth="2xl" className="space-y-6">
                    <PageHeader title="Request Officer Change" subtitle="Ask SDAO to change your organization's officers" />

                    <PageNotice tone="info">
                        Only an active president or secretary of an organization
                        can request an officer change.
                    </PageNotice>
                </CenteredContainer>
            </>
        );
    }

    if (pendingRequest) {
        return (
            <>
                <Head title="Request Officer Change" />
                <CenteredContainer maxWidth="2xl" className="space-y-6">
                    <PageHeader title="Request Officer Change" subtitle="Ask SDAO to change your organization's officers" />

                    <PageNotice tone="info">
                        Your request is already on its way.
                    </PageNotice>
                    <p className="text-sm text-muted-foreground">
                        You have a pending request to change{' '}
                        <strong className="text-foreground">
                            {organization.name}
                        </strong>
                        &apos;s {pendingRequest.position_label} to{' '}
                        <strong className="text-foreground">
                            {pendingRequest.nominee.name}
                        </strong>
                        . An SDAO admin will approve or decline it — you&apos;ll
                        be notified either way.
                    </p>
                </CenteredContainer>
            </>
        );
    }

    return (
        <>
            <Head title="Request Officer Change" />

            <CenteredContainer maxWidth="2xl" className="space-y-6">
                <PageHeader title="Request Officer Change" subtitle="Ask SDAO to change your organization's officers" />

                <PageNotice tone="info">
                    Request a change to {organization.name}&apos;s roster. SDAO must
                    approve it first.
                </PageNotice>

                {currentOfficers.length > 0 && (
                    <div className="rounded-md border p-3 text-sm">
                        <p className="mb-1 font-medium">Current officers</p>
                        {currentOfficers.map((officer) => (
                            <p
                                key={officer.position}
                                className="text-muted-foreground"
                            >
                                {officer.position_label}: {officer.user.name}
                            </p>
                        ))}
                    </div>
                )}

                <div className="grid gap-2">
                    <Label htmlFor="position">Position to change</Label>
                    <Select value={position} onValueChange={setPosition}>
                        <SelectTrigger id="position" className="w-full">
                            <SelectValue placeholder="Position…" />
                        </SelectTrigger>
                        <SelectContent>
                            {positions.map((p) => (
                                <SelectItem key={p.value} value={p.value}>
                                    {p.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="nominee-search">Nominate</Label>
                    <Input
                        id="nominee-search"
                        placeholder="Search by name or email…"
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
                            students…
                        </p>
                    )}
                    {status === 'done' && searchFailed && (
                        <PageNotice tone="destructive" urgent title="Couldn't search students just now.">
                            Try again.
                        </PageNotice>
                    )}
                    {results.length > 0 && (
                        <div className="divide-y rounded-md border">
                            {results.map((student) => (
                                <button
                                    key={student.id}
                                    type="button"
                                    onClick={() => selectStudent(student)}
                                    className="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left text-sm hover:bg-accent"
                                >
                                    <span>{student.name}</span>
                                    <span className="text-xs text-muted-foreground">
                                        {student.email}
                                    </span>
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="reason">Reason (optional)</Label>
                    <Textarea
                        id="reason"
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        placeholder="Let the admin know why, if you'd like…"
                        rows={3}
                    />
                </div>

                {submitError && (
                    <PageNotice tone="destructive" urgent title={submitError} />
                )}

                <ConfirmDialog
                    trigger={
                        <Button
                            disabled={!selected || !position}
                            data-icon="inline-start"
                        >
                            <UserRoundCog />
                            Send Request
                        </Button>
                    }
                    title="Send this officer change request?"
                    description={
                        <>
                            This goes to an SDAO admin for review.{' '}
                            {selected?.name} will not become an officer until
                            it&apos;s approved.
                        </>
                    }
                    confirmLabel="Send Request"
                    onConfirm={({ close, stopProcessing }) => {
                        setProcessing(true);
                        setSubmitError(null);

                        router.post(
                            officerChange.store().url,
                            {
                                position,
                                nominee_id: selected?.id,
                                reason: reason.trim() || undefined,
                            },
                            {
                                preserveScroll: true,
                                onSuccess: close,
                                onError: (errors) => {
                                    setSubmitError(
                                        errors.nominee_id ??
                                            errors.position ??
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
            </CenteredContainer>
        </>
    );
}

RequestOfficerChange.layout = {
    breadcrumbs: [
        { title: 'My Organization' },
        { title: 'Request Officer Change' },
    ],
};
