import { Deferred, Head } from '@inertiajs/react';
import { ArrowRight, BarChart3, CircleCheck, Inbox, UserRoundCog, UserRoundSearch } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import OfficerChangeReviewDialog from '@/components/officer-change-review-dialog';
import type {
    DecidedOfficerChange,
    OfficerChangeRow,
    PendingOfficerChange,
} from '@/components/officer-change-review-dialog';
import PageHeader from '@/components/page-header';
import DataTable from '@/components/review-queue/data-table';
import type { DataColumn } from '@/components/review-queue/data-table';
import { SectionCard } from '@/components/review-queue/queue-tables';
import SegmentedBar from '@/components/review-queue/segmented-bar';
import Sparkline from '@/components/review-queue/sparkline';
import StatCard, { StatCardSkeleton, StatValue } from '@/components/review-queue/stat-card';
import { OldestWaitingCard } from '@/components/review-queue/stat-cards';
import { formatDate } from '@/components/review-queue/types';
import type { WaitTier } from '@/components/review-queue/types';
import WaitPill from '@/components/review-queue/wait-pill';
import { RequestStatusBadge, ToneBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { useDocumentUpdates } from '@/hooks/use-document-updates';

type Buckets = Record<WaitTier, number>;

type TermActivity = {
    termLabel: string;
    submitted: { total: number; thisWeek: number; weeks: number[] };
    decided: { total: number; approved: number; declined: number };
};

type Props = {
    requests: PendingOfficerChange[];
    buckets: Buckets;
    oldest: PendingOfficerChange | null;
    recentDecisions: DecidedOfficerChange[];
    /** Deferred: scans every request filed or decided in the term. */
    termActivity?: TermActivity;
};

const BUCKET_LABEL: Record<WaitTier, string> = {
    fresh: '0 to 2 days',
    aging: '3 to 7 days',
    overdue: '8+ days',
};

/** A name in bold with a quiet second line under it, the shape of every two line cell here. */
function TwoLine({ primary, secondary, strong = false }: { primary: string; secondary: ReactNode; strong?: boolean }) {
    return (
        <div className="flex flex-col">
            <span className={strong ? 'font-medium' : undefined}>{primary}</span>
            <span className="text-sm text-muted-foreground">{secondary}</span>
        </div>
    );
}

function WaitingCard({ buckets, total }: { buckets: Buckets; total: number }) {
    // Anything past the 0 to 2 day band has been waiting 3 days or more.
    const slow = buckets.aging + buckets.overdue;

    return (
        <StatCard icon={UserRoundSearch} title="Waiting for review">
            <div className="flex items-center justify-between gap-3">
                <StatValue>{total}</StatValue>
                {slow > 0 && (
                    <ToneBadge tone="warning" className="text-xs tracking-normal normal-case tabular-nums">
                        {slow === total ? 'All' : slow} waiting 3+ days
                    </ToneBadge>
                )}
            </div>
            <SegmentedBar
                ariaLabel={`Waiting time: ${buckets.fresh} at ${BUCKET_LABEL.fresh}, ${buckets.aging} at ${BUCKET_LABEL.aging}, ${buckets.overdue} at ${BUCKET_LABEL.overdue}`}
                segments={[
                    { label: BUCKET_LABEL.fresh, count: buckets.fresh, className: 'bg-info' },
                    { label: BUCKET_LABEL.aging, count: buckets.aging, className: 'bg-warning' },
                    { label: BUCKET_LABEL.overdue, count: buckets.overdue, className: 'bg-destructive' },
                ]}
            />
        </StatCard>
    );
}

function OldestCard({ oldest, onReview }: { oldest: PendingOfficerChange | null; onReview: (row: PendingOfficerChange) => void }) {
    return (
        <OldestWaitingCard
            headline={oldest?.change_type ?? ''}
            emptyText="No request is waiting for review."
            waiting={oldest ? { tier: oldest.tier, days: oldest.days_waiting, suffix: 'waiting' } : null}
            action={(className) =>
                oldest && (
                    <button type="button" onClick={() => onReview(oldest)} className={className}>
                        Review request
                        <ArrowRight className="size-3.5" aria-hidden />
                    </button>
                )
            }
        >
            {oldest && (
                <dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1.5 text-sm">
                    <dt className="text-muted-foreground">Organization</dt>
                    <dd className="min-w-0 font-medium break-words">{oldest.organization.name}</dd>
                    <dt className="text-muted-foreground">Requested by</dt>
                    <dd className="font-medium">{oldest.requester_position ?? oldest.requester.name}</dd>
                    <dt className="text-muted-foreground">Submitted</dt>
                    <dd className="font-medium tabular-nums">{formatDate(oldest.created_at)}</dd>
                </dl>
            )}
        </OldestWaitingCard>
    );
}

function SubmittedCard({ data }: { data: TermActivity['submitted'] }) {
    return (
        <StatCard icon={BarChart3} title="Submitted this term">
            <StatValue>{data.total}</StatValue>
            {data.weeks.length > 0 ? (
                <Sparkline
                    values={data.weeks}
                    label={`Requests per week this term, ${data.weeks.length} weeks: ${data.weeks.join(', ')}`}
                />
            ) : (
                <p className="flex h-10 items-end text-sm text-muted-foreground">The term has not started yet.</p>
            )}
            <p className="text-sm text-muted-foreground">
                <strong className="font-semibold text-foreground tabular-nums">{data.thisWeek}</strong> this week
            </p>
        </StatCard>
    );
}

function DecidedCard({ data }: { data: TermActivity['decided'] }) {
    return (
        <StatCard icon={CircleCheck} title="Decided this term">
            <StatValue>{data.total}</StatValue>
            {data.total > 0 ? (
                <SegmentedBar
                    ariaLabel={`Decided this term: ${data.approved} approved, ${data.declined} declined`}
                    segments={[
                        { label: 'Approved', count: data.approved, className: 'bg-success' },
                        { label: 'Declined', count: data.declined, className: 'bg-destructive' },
                    ]}
                />
            ) : (
                <p className="flex h-10 items-end text-sm text-muted-foreground">No decisions yet this term.</p>
            )}
        </StatCard>
    );
}

function TermCards({ data }: { data: TermActivity }) {
    return (
        <>
            <SubmittedCard data={data.submitted} />
            <DecidedCard data={data.decided} />
        </>
    );
}

function TermCardsSkeleton() {
    return (
        <>
            <StatCardSkeleton icon={BarChart3} title="Submitted this term" />
            <StatCardSkeleton icon={CircleCheck} title="Decided this term" />
        </>
    );
}

export default function OfficerChangeRequestsIndex({ requests, buckets, oldest, recentDecisions, termActivity }: Props) {
    // 5s poll, same convention as every other review queue.
    useDocumentUpdates(['requests', 'buckets', 'oldest', 'recentDecisions', 'termActivity']);

    // Which row's dialog is open. Only the id is kept: the row itself is looked
    // up in the live lists, so the dialog closes on its own when the request
    // is decided and leaves the waiting list.
    const [openRow, setOpenRow] = useState<{ id: number; kind: 'review' | 'view' } | null>(null);

    const selected: OfficerChangeRow | null = openRow
        ? ((openRow.kind === 'review' ? requests : recentDecisions).find((r) => r.id === openRow.id) ?? null)
        : null;

    const changeColumn = {
        key: 'change',
        header: 'Change',
        cell: (r: OfficerChangeRow) => <TwoLine primary={r.change_type} secondary={r.change_detail} />,
    };

    const organizationColumn = {
        key: 'organization',
        header: 'Organization',
        slot: 'title' as const,
        cell: (r: OfficerChangeRow) => <TwoLine strong primary={r.organization.name} secondary={r.college} />,
    };

    const waitingColumns: DataColumn<PendingOfficerChange>[] = [
        organizationColumn,
        changeColumn,
        {
            key: 'requested_by',
            header: 'Requested by',
            cell: (r) => r.requester_position ?? r.requester.name,
        },
        {
            key: 'submitted',
            header: 'Submitted',
            className: 'tabular-nums',
            cell: (r) => formatDate(r.created_at),
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
                <Button type="button" size="sm" onClick={() => setOpenRow({ id: r.id, kind: 'review' })}>
                    Review<span className="sr-only"> {r.change_type} for {r.organization.name}</span>
                </Button>
            ),
        },
    ];

    const decidedColumns: DataColumn<DecidedOfficerChange>[] = [
        organizationColumn,
        changeColumn,
        {
            key: 'result',
            header: 'Result',
            slot: 'badge',
            cell: (r) => <RequestStatusBadge status={r.result} className="text-xs tracking-normal normal-case" />,
        },
        {
            key: 'decided',
            header: 'Decided on',
            className: 'tabular-nums',
            cell: (r) => formatDate(r.decided_at),
        },
        {
            key: 'actions',
            header: 'Action',
            slot: 'action',
            align: 'right',
            cell: (r) => (
                <Button type="button" size="sm" variant="secondary" onClick={() => setOpenRow({ id: r.id, kind: 'view' })}>
                    View<span className="sr-only"> {r.change_type} for {r.organization.name}</span>
                </Button>
            ),
        },
    ];

    return (
        <>
            <Head title="Officer Change Requests" />

            <div className="flex flex-col gap-6">
                <div className="max-w-3xl">
                    <PageHeader
                        title="Officer Change Requests"
                        subtitle="Requests from a current president or secretary to change their organization's roster. Approving makes the change right away. Declining is final, so they would need to file a new request."
                    />
                </div>

                <section aria-label="Officer change request figures" className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <WaitingCard buckets={buckets} total={requests.length} />
                    <OldestCard oldest={oldest} onReview={(row) => setOpenRow({ id: row.id, kind: 'review' })} />
                    <Deferred data="termActivity" fallback={<TermCardsSkeleton />}>
                        {termActivity && <TermCards data={termActivity} />}
                    </Deferred>
                </section>

                <SectionCard title="Awaiting review" count={requests.length} aside="Oldest first">
                    {requests.length === 0 ? (
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <UserRoundCog />
                                </EmptyMedia>
                                <EmptyTitle>No pending requests</EmptyTitle>
                                <EmptyDescription>Officer change requests will show up here.</EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <DataTable rows={requests} columns={waitingColumns} rowKey={(r) => r.id} roomy />
                    )}
                </SectionCard>

                <SectionCard title="Recently decided" aside="Last 30 days">
                    {recentDecisions.length === 0 ? (
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Inbox />
                                </EmptyMedia>
                                <EmptyTitle>No decisions to show</EmptyTitle>
                                <EmptyDescription>Requests approved or declined in the last 30 days will show up here.</EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <DataTable rows={recentDecisions} columns={decidedColumns} rowKey={(r) => r.id} roomy />
                    )}
                </SectionCard>
            </div>

            <OfficerChangeReviewDialog request={selected} onClose={() => setOpenRow(null)} />
        </>
    );
}

OfficerChangeRequestsIndex.layout = {
    breadcrumbs: [{ title: 'Admin' }, { title: 'Officer Change Requests' }],
};
