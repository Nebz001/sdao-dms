import { Form, Head } from '@inertiajs/react';
import { ArrowRight, UserRoundPlus } from 'lucide-react';
import { useState } from 'react';
import JoinRequestReviewController from '@/actions/App/Http/Controllers/JoinRequestReviewController';
import AccountName from '@/components/account-name';
import ConfirmDialog from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import DataTable from '@/components/review-queue/data-table';
import type { DataColumn } from '@/components/review-queue/data-table';
import { SectionCard } from '@/components/review-queue/queue-tables';
import { OldestWaitingCard } from '@/components/review-queue/stat-cards';
import { formatDate } from '@/components/review-queue/types';
import type { WaitTier } from '@/components/review-queue/types';
import WaitPill from '@/components/review-queue/wait-pill';
import WaitingBucketsCard from '@/components/review-queue/waiting-buckets-card';
import type { WaitBuckets } from '@/components/review-queue/waiting-buckets-card';
import { RequestStatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { DialogClose, DialogFooter } from '@/components/ui/dialog';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useDocumentUpdates } from '@/hooks/use-document-updates';
import { useRowJump } from '@/hooks/use-row-jump';
import { splitAccountName } from '@/lib/account-name';

type PositionOption = { value: string; label: string };

type JoinRequestQueueItem = {
    id: number;
    student: { id: number; name: string; email: string; id_number: string | null };
    organization: { id: number; name: string };
    created_at: string;
    open_positions: string[];
    days_waiting: number;
    /** Bucketed in PHP by ReviewQueueData::tierFor(); the thresholds live only there. */
    tier: WaitTier;
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
    buckets: WaitBuckets;
    oldest: JoinRequestQueueItem | null;
    closed: ClosedJoinRequest[];
    positions: PositionOption[];
};

export default function JoinRequestsIndex({ queue, buckets, oldest, closed, positions }: Props) {
    // 5s poll, same convention as every other review queue: a decision
    // made from another tab or reviewer should not leave a stale row on screen.
    useDocumentUpdates(['queue', 'buckets', 'oldest', 'closed']);

    const { highlightedId, jumpToRow } = useRowJump();

    // The officer position picked per request; until one is picked, the first open seat.
    const [picked, setPicked] = useState<Record<number, string>>({});
    const positionFor = (request: JoinRequestQueueItem) => picked[request.id] ?? request.open_positions[0] ?? '';

    // An adviser normally has one organization, so the column only appears when the list spans several.
    const showOrganization = new Set(queue.map((r) => r.organization.id)).size > 1;

    const columns: DataColumn<JoinRequestQueueItem>[] = [
        {
            key: 'student',
            header: 'Student',
            slot: 'title',
            cell: (r) => <AccountName name={r.student.name} />,
        },
        {
            key: 'email',
            header: 'Email',
            cell: (r) => <span className="break-all">{r.student.email}</span>,
        },
        {
            key: 'id_number',
            header: 'Student ID',
            cell: (r) => <span className="tabular-nums">{r.student.id_number ?? '—'}</span>,
        },
        ...(showOrganization
            ? [
                  {
                      key: 'organization',
                      header: 'Organization',
                      cell: (r: JoinRequestQueueItem) => r.organization.name,
                  },
              ]
            : []),
        {
            key: 'requested',
            header: 'Requested',
            cell: (r) => <span className="tabular-nums">{formatDate(r.created_at)}</span>,
        },
        {
            key: 'position',
            header: 'Position',
            cell: (r) =>
                r.open_positions.length === 0 ? (
                    <span className="text-sm text-muted-foreground">Both positions are filled</span>
                ) : (
                    <Select value={positionFor(r)} onValueChange={(value) => setPicked((p) => ({ ...p, [r.id]: value }))}>
                        <SelectTrigger size="sm" className="w-36" aria-label={`Position for ${r.student.name}`}>
                            <SelectValue placeholder="Position" />
                        </SelectTrigger>
                        <SelectContent>
                            {positions
                                .filter((p) => r.open_positions.includes(p.value))
                                .map((p) => (
                                    <SelectItem key={p.value} value={p.value}>
                                        {p.label}
                                    </SelectItem>
                                ))}
                        </SelectContent>
                    </Select>
                ),
        },
        {
            key: 'waiting',
            header: 'Waiting',
            slot: 'badge',
            cell: (r) => <WaitPill days={r.days_waiting} tier={r.tier} />,
        },
        {
            key: 'actions',
            header: 'Action',
            slot: 'action',
            align: 'right',
            cell: (r) => (
                <div className="flex items-center gap-2 md:justify-end">
                    <ApproveAction request={r} positions={positions} position={positionFor(r)} />
                    <DeclineAction request={r} />
                </div>
            ),
        },
    ];

    const closedColumns: DataColumn<ClosedJoinRequest>[] = [
        {
            key: 'student',
            header: 'Student',
            slot: 'title',
            cell: (r) => <AccountName name={r.student.name} />,
        },
        {
            key: 'status',
            header: 'Status',
            slot: 'badge',
            cell: () => <RequestStatusBadge status="withdrawn" className="text-xs tracking-normal normal-case" />,
        },
        {
            key: 'reason',
            header: 'Reason',
            cell: (r) => r.reason ?? '—',
        },
        {
            key: 'closed',
            header: 'Closed on',
            className: 'tabular-nums',
            cell: (r) => formatDate(r.closed_at),
        },
    ];

    return (
        <>
            <Head title="Join Requests" />

            <div className="flex flex-col gap-6">
                <div className="max-w-3xl">
                    <PageHeader
                        title="Join Requests"
                        subtitle="Students asking to join your organization. Approving adds them as an officer right away. A declined student has to send a new request."
                    />
                </div>

                <section aria-label="Join request figures" className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <WaitingBucketsCard title="Waiting for your decision" buckets={buckets} total={queue.length} />
                    <OldestWaitingCard
                        headline={oldest ? splitAccountName(oldest.student.name).name : ''}
                        headlineDetail={oldest ? splitAccountName(oldest.student.name).detail : null}
                        emptyText="No request is waiting for your decision."
                        waiting={oldest ? { tier: oldest.tier, days: oldest.days_waiting, suffix: 'waiting' } : null}
                        action={(className) =>
                            oldest && (
                                <a
                                    href={`#request-${oldest.id}`}
                                    onClick={(event) => {
                                        event.preventDefault();
                                        jumpToRow(oldest.id);
                                    }}
                                    className={className}
                                >
                                    Review request
                                    <ArrowRight className="size-3.5" aria-hidden />
                                </a>
                            )
                        }
                    >
                        {oldest && (
                            <dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1.5 text-sm">
                                <dt className="text-muted-foreground">Organization</dt>
                                <dd className="min-w-0 font-medium break-words">{oldest.organization.name}</dd>
                                <dt className="text-muted-foreground">Email</dt>
                                <dd className="min-w-0 font-medium break-words">{oldest.student.email}</dd>
                                <dt className="text-muted-foreground">Requested</dt>
                                <dd className="font-medium tabular-nums">{formatDate(oldest.created_at)}</dd>
                            </dl>
                        )}
                    </OldestWaitingCard>
                </section>

                <SectionCard title="Awaiting your decision" count={queue.length} aside="Oldest first">
                    {queue.length === 0 ? (
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <UserRoundPlus />
                                </EmptyMedia>
                                <EmptyTitle>No pending requests</EmptyTitle>
                                <EmptyDescription>
                                    When a student asks to join your organization, the request will show up here for you to
                                    approve or decline.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <DataTable
                            rows={queue}
                            columns={columns}
                            rowKey={(r) => r.id}
                            rowId={(r) => r.id}
                            rowClassName={(r) => (r.id === highlightedId ? 'bg-warning/10 transition-colors' : 'transition-colors')}
                            roomy
                        />
                    )}
                </SectionCard>

                {closed.length > 0 && (
                    <SectionCard title="Closed automatically" aside="Last 14 days">
                        <DataTable rows={closed} columns={closedColumns} rowKey={(r) => r.id} roomy />
                    </SectionCard>
                )}
            </div>
        </>
    );
}

function ApproveAction({
    request,
    positions,
    position,
}: {
    request: JoinRequestQueueItem;
    positions: PositionOption[];
    position: string;
}) {
    const positionLabel = positions.find((p) => p.value === position)?.label;

    return (
        <ConfirmDialog
            trigger={
                <Button type="button" size="sm" disabled={request.open_positions.length === 0}>
                    Approve<span className="sr-only"> {request.student.name}</span>
                </Button>
            }
            title={`Approve ${request.student.name}'s request?`}
            description={
                <>
                    {request.student.name} will be added as {positionLabel ?? 'an officer'} of {request.organization.name} right
                    away.
                </>
            }
        >
            {(close) => (
                <Form
                    {...JoinRequestReviewController.approve.form(request.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={close}
                >
                    {({ processing, errors }) => (
                        <>
                            <input type="hidden" name="position" value={position} />
                            <InputError message={errors.position || errors.join_request} />
                            <DialogFooter className="mt-4 gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary" disabled={processing}>
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" loading={processing}>
                                    Approve
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            )}
        </ConfirmDialog>
    );
}

function DeclineAction({ request }: { request: JoinRequestQueueItem }) {
    return (
        <ConfirmDialog
            trigger={
                <Button type="button" size="sm" variant="destructive">
                    Decline<span className="sr-only"> {request.student.name}</span>
                </Button>
            }
            title={`Decline ${request.student.name}'s request?`}
            description="This is final. They would need to send a new request to try again."
        >
            {(close) => (
                <Form
                    {...JoinRequestReviewController.decline.form(request.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={close}
                >
                    {({ processing, errors }) => (
                        <>
                            <Label htmlFor={`decline-comment-${request.id}`}>Reason (optional)</Label>
                            <Textarea
                                id={`decline-comment-${request.id}`}
                                name="comment"
                                placeholder="Let them know why, if you'd like"
                                rows={3}
                            />
                            <InputError message={errors.comment || errors.join_request} />
                            <DialogFooter className="mt-4 gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary" disabled={processing}>
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" variant="destructive" loading={processing}>
                                    Decline
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            )}
        </ConfirmDialog>
    );
}

JoinRequestsIndex.layout = {
    breadcrumbs: [{ title: 'Review' }, { title: 'Join Requests' }],
};
