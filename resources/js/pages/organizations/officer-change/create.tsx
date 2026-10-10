import { Head, router } from '@inertiajs/react';
import { ArrowRight, Search, Send, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import {
    FocusFirstError,
    FormCard,
    FormField,
    FormFooter,
    FormSection,
    FormShell,
    FormStrip,
} from '@/components/form-shell';
import InitialsAvatar from '@/components/initials-avatar';
import PageNotice from '@/components/page-notice';
import { NotAnOfficerBlocked } from '@/components/student-blocked';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { RadioGroup, RadioGroupOption } from '@/components/ui/radio-group';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import * as officerChange from '@/routes/organizations/officer-change';

type PositionOption = { value: string; label: string };

type StudentResult = { id: number; name: string; email: string };

type Officer = {
    position: string;
    position_label: string;
    user: { id: number; name: string };
};

type Props = {
    organization: { id: number; name: string } | null;
    currentOfficers: Officer[];
    pendingRequest: {
        position_label: string;
        nominee: { name: string };
    } | null;
    /** Seats that already have a pending request, from anyone in the organization. */
    pendingPositions?: string[];
    positions: PositionOption[];
};

/** The dashed stand-in for a seat nobody holds, or a person not picked yet. */
function EmptyAvatar({ mark = '?' }: { mark?: string }) {
    return (
        <span
            aria-hidden
            className="flex size-10 shrink-0 items-center justify-center rounded-full border border-dashed border-input text-sm text-muted-foreground"
        >
            {mark}
        </span>
    );
}

export default function RequestOfficerChange({
    organization,
    currentOfficers,
    pendingRequest,
    pendingPositions = [],
    positions,
}: Props) {
    const firstOpenSeat = positions.find(
        (p) => !pendingPositions.includes(p.value),
    );
    const [chosenPosition, setPosition] = useState(firstOpenSeat?.value ?? '');
    // A seat that turned pending (right after a request is filed) can no longer
    // be the one the form sits on; fall to the first open seat.
    const position = pendingPositions.includes(chosenPosition)
        ? (firstOpenSeat?.value ?? '')
        : chosenPosition;
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<StudentResult[]>([]);
    const [selected, setSelected] = useState<StudentResult | null>(null);
    const [status, setStatus] = useState<'idle' | 'searching' | 'done'>('idle');
    const [searchFailed, setSearchFailed] = useState(false);
    const debounceTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const latestQuery = useRef('');

    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

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
        setQuery('');
        setErrors((prev) => ({ ...prev, nominee_id: '' }));
    }

    if (organization === null) {
        return (
            <>
                <Head title="Request Officer Change" />
                <NotAnOfficerBlocked action="ask SDAO for an officer change" />
            </>
        );
    }

    const holderOf = (value: string) =>
        currentOfficers.find((officer) => officer.position === value);
    const seat = positions.find((p) => p.value === position);
    const currentHolder = holderOf(position);
    const noOpenSeat = firstOpenSeat === undefined;
    const alreadyHolds =
        selected !== null && currentHolder?.user.id === selected.id;
    const seatLabel = seat?.label.toLowerCase() ?? 'position';

    // The server stays the authority on every one of these rules; the page
    // shows its message under the field it belongs to.
    const fieldErrors = {
        position: errors.position || undefined,
        nominee_id:
            errors.nominee_id ||
            (alreadyHolds ? 'They already hold this position.' : undefined),
        reason: errors.reason || undefined,
    };

    return (
        <>
            <Head title="Request Officer Change" />

            <FormShell
                title="Request Officer Change"
                subtitle="Ask SDAO to change who holds an officer position."
            >
                {pendingRequest && (
                    <PageNotice tone="info" title="Your request is already on its way.">
                        You asked to change {organization.name}&apos;s{' '}
                        {pendingRequest.position_label} to{' '}
                        {pendingRequest.nominee.name}. An SDAO admin will
                        approve or decline it, and you&apos;ll be notified
                        either way.
                    </PageNotice>
                )}

                <form
                    noValidate
                    onSubmit={(event) => event.preventDefault()}
                    aria-label="Request an officer change"
                >
                    <FormCard>
                        <FocusFirstError errors={fieldErrors} />
                        <FormStrip
                            left={
                                <>
                                    Requesting for{' '}
                                    <strong className="font-semibold text-foreground">
                                        {organization.name}
                                    </strong>
                                </>
                            }
                            right="SDAO approves this first"
                        />

                        <FormSection title="Current officers">
                            <ul className="grid gap-3 sm:grid-cols-2">
                                {positions.map((p) => {
                                    const holder = holderOf(p.value);

                                    return (
                                        <li
                                            key={p.value}
                                            className={cn(
                                                'flex items-center gap-3 rounded-lg border px-3.5 py-3',
                                                !holder &&
                                                    'border-dashed bg-muted/20',
                                            )}
                                        >
                                            {holder ? (
                                                <InitialsAvatar name={holder.user.name} />
                                            ) : (
                                                <EmptyAvatar mark="–" />
                                            )}
                                            <div className="min-w-0">
                                                <p
                                                    className={cn(
                                                        'truncate text-sm font-medium',
                                                        !holder &&
                                                            'text-muted-foreground',
                                                    )}
                                                >
                                                    {holder
                                                        ? holder.user.name
                                                        : 'Vacant'}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {p.label}
                                                </p>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        </FormSection>

                        <FormSection title="The change">
                            <fieldset className="grid gap-1.5">
                                <legend className="mb-1.5 text-sm leading-snug font-medium">
                                    Which position?
                                </legend>
                                <RadioGroup className="grid-cols-1 sm:grid-cols-2">
                                    {positions.map((p) => {
                                        const holder = holderOf(p.value);
                                        const isPending = pendingPositions.includes(
                                            p.value,
                                        );

                                        return (
                                            <RadioGroupOption
                                                key={p.value}
                                                name="position"
                                                value={p.value}
                                                checked={position === p.value}
                                                disabled={isPending}
                                                onChange={() => {
                                                    setPosition(p.value);
                                                    setErrors((prev) => ({
                                                        ...prev,
                                                        position: '',
                                                        nominee_id: '',
                                                    }));
                                                }}
                                                title={p.label}
                                                description={
                                                    isPending
                                                        ? 'A change request is already pending for this position'
                                                        : holder
                                                          ? `Now held by ${holder.user.name}`
                                                          : 'Vacant'
                                                }
                                            />
                                        );
                                    })}
                                </RadioGroup>
                                {noOpenSeat && (
                                    <p className="text-sm text-muted-foreground">
                                        Every position already has a pending
                                        request. You can ask again once SDAO
                                        decides.
                                    </p>
                                )}
                                {fieldErrors.position && (
                                    <p
                                        aria-invalid
                                        tabIndex={-1}
                                        className="text-sm text-destructive"
                                    >
                                        {fieldErrors.position}
                                    </p>
                                )}
                            </fieldset>

                            <FormField
                                id="nominee-search"
                                label="Who should take this position?"
                                error={fieldErrors.nominee_id}
                            >
                                {(aria) => (
                                    <>
                                        {selected ? (
                                            <div className="flex items-center gap-3 rounded-lg border border-primary-text bg-primary/10 px-3.5 py-2.5">
                                                <InitialsAvatar name={selected.name} />
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-medium">
                                                        {selected.name}
                                                    </p>
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {selected.email}
                                                    </p>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => setSelected(null)}
                                                    {...aria}
                                                >
                                                    <X aria-hidden />
                                                    Change
                                                </Button>
                                            </div>
                                        ) : (
                                            <div className="relative">
                                                <Search
                                                    aria-hidden
                                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                                />
                                                <Input
                                                    id="nominee-search"
                                                    type="search"
                                                    placeholder="Search by name or email"
                                                    value={query}
                                                    onChange={(e) =>
                                                        setQuery(e.target.value)
                                                    }
                                                    autoComplete="off"
                                                    disabled={noOpenSeat}
                                                    className="pl-9"
                                                    {...aria}
                                                />
                                            </div>
                                        )}

                                        <div aria-live="polite" className="grid gap-2">
                                            {!selected &&
                                                query.trim() !== '' &&
                                                status === 'searching' && (
                                                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                                        <Spinner className="size-3.5" />
                                                        Searching students…
                                                    </p>
                                                )}
                                            {status === 'done' && searchFailed && (
                                                <PageNotice
                                                    tone="destructive"
                                                    urgent
                                                    title="Couldn't search students just now."
                                                >
                                                    Try again.
                                                </PageNotice>
                                            )}
                                            {status === 'done' &&
                                                !searchFailed &&
                                                results.length === 0 &&
                                                !selected &&
                                                query.trim() !== '' && (
                                                    <p className="rounded-md border border-dashed px-3.5 py-3 text-sm text-muted-foreground">
                                                        No student matches
                                                        &ldquo;{query.trim()}
                                                        &rdquo;. Students who
                                                        already hold a seat in
                                                        another organization,
                                                        accounts SDAO hasn&apos;t
                                                        verified, and approvers
                                                        can&apos;t be picked.
                                                    </p>
                                                )}
                                        </div>

                                        {!selected && results.length > 0 && (
                                            <ul
                                                aria-label="Matching students"
                                                className="divide-y overflow-hidden rounded-lg border"
                                            >
                                                {results.map((student) => (
                                                    <li key={student.id}>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                selectStudent(student)
                                                            }
                                                            className="flex w-full items-center gap-3 px-3.5 py-2.5 text-left hover:bg-accent focus-visible:focus-ring"
                                                        >
                                                            <InitialsAvatar name={student.name} />
                                                            <span className="min-w-0">
                                                                <span className="block truncate text-sm font-medium">
                                                                    {student.name}
                                                                </span>
                                                                <span className="block truncate text-xs text-muted-foreground">
                                                                    {student.email}
                                                                </span>
                                                            </span>
                                                        </button>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </>
                                )}
                            </FormField>

                            <div className="grid gap-1.5">
                                <h3 className="text-sm leading-snug font-medium">
                                    Preview
                                </h3>
                                <div className="flex flex-wrap items-center gap-x-4 gap-y-3 rounded-lg border border-dashed bg-muted/20 px-3.5 py-3">
                                    <div className="flex min-w-0 flex-1 basis-44 items-center gap-3">
                                        {currentHolder ? (
                                            <InitialsAvatar name={currentHolder.user.name} />
                                        ) : (
                                            <EmptyAvatar mark="–" />
                                        )}
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {currentHolder
                                                    ? currentHolder.user.name
                                                    : 'Vacant'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Current {seatLabel}
                                            </p>
                                        </div>
                                    </div>
                                    <ArrowRight
                                        aria-label="becomes"
                                        role="img"
                                        className="size-4 shrink-0 text-muted-foreground max-sm:rotate-90"
                                    />
                                    <div className="flex min-w-0 flex-1 basis-44 items-center gap-3">
                                        {selected ? (
                                            <InitialsAvatar name={selected.name} />
                                        ) : (
                                            <EmptyAvatar />
                                        )}
                                        <div className="min-w-0">
                                            <p
                                                className={cn(
                                                    'truncate text-sm font-medium',
                                                    !selected &&
                                                        'text-muted-foreground',
                                                )}
                                            >
                                                {selected
                                                    ? selected.name
                                                    : 'Not picked yet'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                New {seatLabel}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <FormField
                                id="reason"
                                label="Reason"
                                optional
                                error={fieldErrors.reason}
                            >
                                {(aria) => (
                                    <Textarea
                                        id="reason"
                                        value={reason}
                                        onChange={(e) => setReason(e.target.value)}
                                        placeholder="Let SDAO know why, if you'd like"
                                        rows={4}
                                        maxLength={2000}
                                        {...aria}
                                    />
                                )}
                            </FormField>
                        </FormSection>

                        <FormFooter status="You’ll get a notification once SDAO decides.">
                            <ConfirmDialog
                                trigger={
                                    <Button
                                        type="button"
                                        disabled={
                                            !selected ||
                                            !position ||
                                            alreadyHolds ||
                                            noOpenSeat
                                        }
                                        data-icon="inline-start"
                                    >
                                        <Send aria-hidden />
                                        Send Request
                                    </Button>
                                }
                                title="Send this officer change request?"
                                description={
                                    <>
                                        This goes to an SDAO admin for review.{' '}
                                        {selected?.name} will not become an
                                        officer until it&apos;s approved.
                                    </>
                                }
                                confirmLabel="Send Request"
                                onConfirm={({ close, stopProcessing }) => {
                                    setProcessing(true);
                                    setErrors({});

                                    router.post(
                                        officerChange.store().url,
                                        {
                                            position,
                                            nominee_id: selected?.id,
                                            reason: reason.trim() || undefined,
                                        },
                                        {
                                            preserveScroll: true,
                                            onSuccess: () => {
                                                setSelected(null);
                                                setQuery('');
                                                setReason('');
                                                close();
                                            },
                                            onError: (serverErrors) => {
                                                // Close the dialog so the message
                                                // shows under its own field, not
                                                // behind the modal.
                                                setErrors(serverErrors);
                                                stopProcessing();
                                                close();
                                            },
                                            onFinish: () => setProcessing(false),
                                        },
                                    );
                                }}
                                confirmDisabled={processing}
                            />
                        </FormFooter>
                    </FormCard>
                </form>
            </FormShell>
        </>
    );
}

RequestOfficerChange.layout = {
    breadcrumbs: [
        { title: 'My Organization' },
        { title: 'Request Officer Change' },
    ],
    columnWidth: '3xl',
};
