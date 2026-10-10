import { Head, router, usePage } from '@inertiajs/react';
import { Info, UserRoundCheck } from 'lucide-react';
import { useState } from 'react';
import JoinRequestReviewController from '@/actions/App/Http/Controllers/JoinRequestReviewController';
import CenteredContainer from '@/components/centered-container';
import ConfirmDialog from '@/components/confirm-dialog';
import CountBadge from '@/components/count-badge';
import IconTile from '@/components/icon-tile';
import PageHeader from '@/components/page-header';
import PageNotice from '@/components/page-notice';
import { formatDate } from '@/components/review-queue/types';
import { RequestStatusBadge } from '@/components/status-badge';
import TagBadge from '@/components/tag-badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import { useInitials } from '@/hooks/use-initials';
import { askedExact, askedLabel } from '@/lib/asked-time';
import { notify } from '@/lib/toast';
import { cn } from '@/lib/utils';

type PositionOption = { value: string; label: string };

type JoinRequestQueueItem = {
    id: number;
    student: { id: number; name: string; email: string; id_number: string | null };
    organization: { id: number; name: string };
    created_at: string;
    open_positions: string[];
};

type ClosedJoinRequest = {
    id: number;
    student: { id: number; name: string; email: string };
    organization: { id: number; name: string };
    closed_at: string;
    reason: string | null;
};

type Props = {
    queue: JoinRequestQueueItem[];
    closed: ClosedJoinRequest[];
    positions: PositionOption[];
};

type Busy = { id: number; action: 'approve' | 'decline' } | null;

const REFRESH_ONLY = ['queue', 'closed'];

export default function JoinRequestsIndex({ queue, closed, positions }: Props) {
    const { auth } = usePage().props;
    // 5s poll, same convention as every other review queue: a decision
    // made from another tab or reviewer should not leave a stale row on screen.
    useDocumentUpdates(REFRESH_ONLY);

    const [busy, setBusy] = useState<Busy>(null);

    // An officer's page is about their own organization; an adviser's has no
    // organization in the shared props, so it falls back to a plain phrase.
    const organizationName = auth.organization?.name ?? queue[0]?.organization.name ?? 'your organization';
    const showOrganization = new Set(queue.map((r) => r.organization.id)).size > 1;

    return (
        <>
            <Head title="Join Requests" />

            <CenteredContainer maxWidth="3xl" className="space-y-6">
                <PageHeader
                    title="Join Requests"
                    subtitle="Students who want to join your organization as an officer"
                />

                <ListCard
                    title="Waiting for you"
                    count={queue.length}
                    countTone="blue"
                    aside={queue.length > 0 ? 'Oldest first' : undefined}
                    footer={
                        <p className="flex items-start gap-2 text-xs text-muted-foreground">
                            <Info aria-hidden className="mt-0.5 size-3.5 shrink-0" />
                            Approving adds them as an officer right away. If you decline, they have to send a new request.
                        </p>
                    }
                >
                    {queue.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 px-6 py-12 text-center">
                            <IconTile icon={UserRoundCheck} tone="green" size="md" />
                            <div className="space-y-1">
                                <h2 className="text-lg font-bold">No requests right now</h2>
                                <p className="mx-auto max-w-sm text-sm text-muted-foreground">
                                    When a student asks to join {organizationName}, they’ll show up here for you to approve or
                                    decline.
                                </p>
                            </div>
                        </div>
                    ) : (
                        <ul className="divide-y">
                            {queue.map((request, index) => (
                                <RequestRow
                                    key={request.id}
                                    request={request}
                                    positions={positions}
                                    tone={index % 2 === 0 ? 'blue' : 'teal'}
                                    showOrganization={showOrganization}
                                    busy={busy}
                                    setBusy={setBusy}
                                />
                            ))}
                        </ul>
                    )}
                </ListCard>

                {closed.length > 0 && (
                    <ListCard title="Closed automatically" aside="Last 14 days">
                        <ul className="divide-y">
                            {closed.map((r) => (
                                <li key={r.id} className="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3 text-sm">
                                    <span className="min-w-0 flex-1 basis-48">
                                        <span className="block truncate font-medium">{r.student.name}</span>
                                        <span className="block truncate text-xs text-muted-foreground">{r.reason ?? r.student.email}</span>
                                    </span>
                                    <RequestStatusBadge status="withdrawn" className="text-xs tracking-normal normal-case" />
                                    <span className="text-xs text-muted-foreground tabular-nums">{formatDate(r.closed_at)}</span>
                                </li>
                            ))}
                        </ul>
                    </ListCard>
                )}
            </CenteredContainer>
        </>
    );
}

/** One card: a header with a title, count and quiet aside, the list, and an optional footer strip. */
function ListCard({
    title,
    count,
    countTone,
    aside,
    footer,
    children,
}: {
    title: string;
    count?: number;
    countTone?: 'blue';
    aside?: string;
    footer?: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <Card className="gap-0 overflow-hidden py-0 shadow-none">
            <div className="flex items-center justify-between gap-3 border-b px-5 py-4">
                <h2 className="flex items-center gap-2 text-base font-bold">
                    {title}
                    {count !== undefined && <CountBadge count={count} variant={countTone ?? 'secondary'} quietWhenZero />}
                </h2>
                {aside && <p className="text-sm text-muted-foreground">{aside}</p>}
            </div>
            {children}
            {footer && <div className="border-t bg-muted/40 px-5 py-3">{footer}</div>}
        </Card>
    );
}

function RequestRow({
    request,
    positions,
    tone,
    showOrganization,
    busy,
    setBusy,
}: {
    request: JoinRequestQueueItem;
    positions: PositionOption[];
    tone: 'blue' | 'teal';
    showOrganization: boolean;
    busy: Busy;
    setBusy: (busy: Busy) => void;
}) {
    const getInitials = useInitials();
    const rowBusy = busy?.id === request.id;
    const noSeat = request.open_positions.length === 0;

    return (
        <li className="flex flex-wrap items-center gap-x-4 gap-y-3 px-5 py-4">
            <Avatar className="size-10">
                <AvatarFallback
                    className={cn(
                        'text-xs font-semibold',
                        tone === 'blue'
                            ? 'bg-blue-500/10 text-blue-700 dark:bg-blue-400/15 dark:text-blue-300'
                            : 'bg-teal-500/10 text-teal-700 dark:bg-teal-400/15 dark:text-teal-300',
                    )}
                >
                    {getInitials(request.student.name)}
                </AvatarFallback>
            </Avatar>

            <div className="min-w-0 flex-1 basis-56">
                <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-base font-bold">
                    <span className="min-w-0 break-words">{request.student.name}</span>
                    {showOrganization && <TagBadge>{request.organization.name}</TagBadge>}
                </p>
                <p className="text-sm break-words text-muted-foreground">{request.student.email}</p>
                {noSeat && (
                    <p className="mt-1 text-xs text-warning-foreground">
                        Both officer positions are filled, so this can only be declined.
                    </p>
                )}
            </div>

            <div className="flex w-full flex-wrap items-center gap-x-4 gap-y-3 sm:w-auto sm:flex-nowrap">
                <Tooltip>
                    <TooltipTrigger asChild>
                        <span tabIndex={0} className="w-full text-sm text-muted-foreground sm:w-auto">
                            {askedLabel(request.created_at)}
                        </span>
                    </TooltipTrigger>
                    <TooltipContent>{askedExact(request.created_at)} (Manila time)</TooltipContent>
                </Tooltip>

                <div className="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto">
                    <DeclineAction request={request} busy={busy} setBusy={setBusy} rowBusy={rowBusy} />
                    <ApproveAction
                        request={request}
                        positions={positions}
                        busy={busy}
                        setBusy={setBusy}
                        rowBusy={rowBusy}
                        disabled={noSeat}
                    />
                </div>
            </div>
        </li>
    );
}

type ActionProps = {
    request: JoinRequestQueueItem;
    busy: Busy;
    setBusy: (busy: Busy) => void;
    rowBusy: boolean;
};

/** Reload only what the page shows, so a row another reviewer already decided leaves the list. */
function refreshList() {
    router.reload({ only: REFRESH_ONLY });
}

function ApproveAction({
    request,
    positions,
    busy,
    setBusy,
    rowBusy,
    disabled,
}: ActionProps & { positions: PositionOption[]; disabled: boolean }) {
    const open = positions.filter((p) => request.open_positions.includes(p.value));
    const [picked, setPicked] = useState('');
    const [error, setError] = useState<string | null>(null);
    // Until one is picked, the first open seat.
    const position = picked || open[0]?.value || '';
    const positionLabel = open.find((p) => p.value === position)?.label;

    return (
        <ConfirmDialog
            trigger={
                <Button
                    type="button"
                    disabled={disabled || rowBusy}
                    aria-label={`Approve ${request.student.name}`}
                    onClick={() => setError(null)}
                    loading={rowBusy && busy?.action === 'approve'}
                    loadingText="Approving…"
                >
                    Approve
                </Button>
            }
            title={`Add ${request.student.name} as an officer of ${request.organization.name}?`}
            description={
                <>
                    {request.student.name} will be added as {positionLabel ?? 'an officer'} right away.
                </>
            }
            notice={
                <>
                    {open.length > 1 && (
                        <div className="grid gap-1.5">
                            <Label htmlFor={`position-${request.id}`}>Position</Label>
                            <Select value={position} onValueChange={setPicked}>
                                <SelectTrigger id={`position-${request.id}`} className="w-full">
                                    <SelectValue placeholder="Position" />
                                </SelectTrigger>
                                <SelectContent>
                                    {open.map((p) => (
                                        <SelectItem key={p.value} value={p.value}>
                                            {p.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}
                    {error && <PageNotice tone="destructive" urgent title={error} />}
                </>
            }
            confirmLabel="Approve"
            onConfirm={({ close, stopProcessing }) => {
                setBusy({ id: request.id, action: 'approve' });
                setError(null);

                router.post(
                    JoinRequestReviewController.approve.url(request.id),
                    { position },
                    {
                        preserveScroll: true,
                        // The server's own toast ("Officer added") confirms it.
                        onSuccess: close,
                        onError: (errors) => {
                            const message =
                                errors.join_request ?? errors.position ?? 'Something went wrong. Please try again.';

                            setError(message);
                            // A toast too: if this row has already left the
                            // list, the dialog goes with it.
                            notify.error({ title: 'Couldn’t approve this request', message });
                            stopProcessing();
                            refreshList();
                        },
                        onFinish: () => setBusy(null),
                    },
                );
            }}
        />
    );
}

function DeclineAction({ request, busy, setBusy, rowBusy }: ActionProps) {
    const [error, setError] = useState<string | null>(null);

    return (
        <ConfirmDialog
            trigger={
                <Button
                    type="button"
                    variant="secondary"
                    disabled={rowBusy}
                    aria-label={`Decline ${request.student.name}`}
                    onClick={() => setError(null)}
                    loading={rowBusy && busy?.action === 'decline'}
                    loadingText="Declining…"
                >
                    Decline
                </Button>
            }
            title={`Decline ${request.student.name}’s request?`}
            description="This is final. They would need to send a new request to try again."
            notice={error ? <PageNotice tone="destructive" urgent title={error} /> : undefined}
            remarks={{
                maxLength: 2000,
                label: 'Reason',
                placeholder: 'Let them know why, if you’d like',
            }}
            confirmLabel="Decline"
            confirmVariant="destructive"
            onConfirm={({ close, stopProcessing, remarks }) => {
                setBusy({ id: request.id, action: 'decline' });
                setError(null);

                router.post(
                    JoinRequestReviewController.decline.url(request.id),
                    { comment: remarks || undefined },
                    {
                        preserveScroll: true,
                        onSuccess: close,
                        onError: (errors) => {
                            const message =
                                errors.join_request ?? errors.comment ?? 'Something went wrong. Please try again.';

                            setError(message);
                            notify.error({ title: 'Couldn’t decline this request', message });
                            stopProcessing();
                            refreshList();
                        },
                        onFinish: () => setBusy(null),
                    },
                );
            }}
        />
    );
}

JoinRequestsIndex.layout = {
    breadcrumbs: [{ title: 'Review' }, { title: 'Join Requests' }],
    columnWidth: '3xl',
};
