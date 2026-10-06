import { Deferred, Form, Head, router } from '@inertiajs/react';
import {
    ArrowRight,
    BarChart3,
    CircleCheck,
    UserRoundCheck,
} from 'lucide-react';
import { useState } from 'react';
import PendingAccountController from '@/actions/App/Http/Controllers/Admin/PendingAccountController';
import AccountName from '@/components/account-name';
import ConfirmDialog from '@/components/confirm-dialog';
import PageHeader from '@/components/page-header';
import PageNotice from '@/components/page-notice';
import DataTable from '@/components/review-queue/data-table';
import type { DataColumn } from '@/components/review-queue/data-table';
import { SectionCard } from '@/components/review-queue/queue-tables';
import SegmentedBar from '@/components/review-queue/segmented-bar';
import Sparkline from '@/components/review-queue/sparkline';
import StatCard, {
    StatCardSkeleton,
    StatValue,
} from '@/components/review-queue/stat-card';
import { OldestWaitingCard } from '@/components/review-queue/stat-cards';
import { formatDate } from '@/components/review-queue/types';
import type { WaitTier } from '@/components/review-queue/types';
import WaitPill from '@/components/review-queue/wait-pill';
import WaitingBucketsCard from '@/components/review-queue/waiting-buckets-card';
import type { WaitBuckets } from '@/components/review-queue/waiting-buckets-card';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Spinner } from '@/components/ui/spinner';
import { useRowJump } from '@/hooks/use-row-jump';
import { splitAccountName } from '@/lib/account-name';

type PendingAccount = {
    id: number;
    name: string;
    email: string;
    id_number: string | null;
    created_at: string;
    days_waiting: number;
    /** Bucketed in PHP by ReviewQueueData::tierFor(); the thresholds live only there. */
    tier: WaitTier;
};

type TermActivity = {
    termLabel: string;
    signedUp: { total: number; thisWeek: number; weeks: number[] };
    decided: { total: number; verified: number; rejected: number };
};

type Props = {
    accounts: PendingAccount[];
    buckets: WaitBuckets;
    oldest: PendingAccount | null;
    /** Deferred: scans every self-registration in the term. */
    termActivity?: TermActivity;
};

function OldestCard({
    oldest,
    onReview,
}: {
    oldest: PendingAccount | null;
    onReview: (id: number) => void;
}) {
    return (
        <OldestWaitingCard
            headline={oldest ? splitAccountName(oldest.name).name : ''}
            headlineDetail={oldest ? splitAccountName(oldest.name).detail : null}
            emptyText="No account is waiting for review."
            waiting={
                oldest
                    ? {
                          tier: oldest.tier,
                          days: oldest.days_waiting,
                          suffix: 'waiting',
                      }
                    : null
            }
            action={(className) =>
                oldest && (
                    <a
                        href={`#account-${oldest.id}`}
                        onClick={(event) => {
                            event.preventDefault();
                            onReview(oldest.id);
                        }}
                        className={className}
                    >
                        Review account
                        <ArrowRight className="size-3.5" aria-hidden />
                    </a>
                )
            }
        >
            {oldest && (
                <dl className="flex flex-col gap-1.5 text-sm">
                    <div className="flex flex-wrap items-baseline gap-x-6">
                        <dt className="text-muted-foreground">Email</dt>
                        <dd className="min-w-0 font-medium break-words">
                            {oldest.email}
                        </dd>
                    </div>
                    <div className="flex flex-wrap items-baseline gap-x-6">
                        <dt className="text-muted-foreground">Registered</dt>
                        <dd className="font-medium tabular-nums">
                            {formatDate(oldest.created_at)}
                        </dd>
                    </div>
                </dl>
            )}
        </OldestWaitingCard>
    );
}

function SignedUpCard({ data }: { data: TermActivity['signedUp'] }) {
    return (
        <StatCard icon={BarChart3} title="Signed up this term">
            <StatValue>{data.total}</StatValue>
            {data.weeks.length > 0 ? (
                <Sparkline
                    values={data.weeks}
                    label={`Registrations per week this term, ${data.weeks.length} weeks: ${data.weeks.join(', ')}`}
                />
            ) : (
                <p className="flex h-10 items-end text-sm text-muted-foreground">
                    The term has not started yet.
                </p>
            )}
            <p className="text-sm text-muted-foreground">
                <strong className="font-semibold text-foreground tabular-nums">
                    {data.thisWeek}
                </strong>{' '}
                this week
            </p>
        </StatCard>
    );
}

function DecidedCard({ data }: { data: TermActivity['decided'] }) {
    // Only outcomes that happened get a segment; the legend carries both counts.
    const segments = [
        { label: 'Verified', count: data.verified, className: 'bg-success' },
        {
            label: 'Rejected',
            count: data.rejected,
            className: 'bg-destructive',
        },
    ];

    return (
        <StatCard icon={CircleCheck} title="Decided this term">
            <StatValue>{data.total}</StatValue>
            {data.total > 0 ? (
                <SegmentedBar
                    ariaLabel={`Decided this term: ${data.verified} verified, ${data.rejected} rejected`}
                    segments={segments}
                />
            ) : (
                <p className="flex h-10 items-end text-sm text-muted-foreground">
                    No decisions yet this term.
                </p>
            )}
        </StatCard>
    );
}

function TermCardsSkeleton() {
    return (
        <>
            <StatCardSkeleton icon={BarChart3} title="Signed up this term" />
            <StatCardSkeleton icon={CircleCheck} title="Decided this term" />
        </>
    );
}

export default function PendingAccountsIndex({
    accounts,
    buckets,
    oldest,
    termActivity,
}: Props) {
    // Scoped to the account that failed, not a page-wide flag — mirrors
    // organizations/officers/index.tsx's deactivateError. A flat flag would
    // leak a stale message into a different account's dialog on next open.
    const [rejectError, setRejectError] = useState<{
        accountId: number;
        message: string;
    } | null>(null);

    const { highlightedId, jumpToRow } = useRowJump();

    const columns: DataColumn<PendingAccount>[] = [
        {
            key: 'name',
            header: 'Name',
            slot: 'title',
            cell: (a) => <AccountName name={a.name} />,
        },
        {
            key: 'email',
            header: 'Email',
            cell: (a) => <span className="break-all">{a.email}</span>,
        },
        {
            key: 'id_number',
            header: 'ID number',
            cell: (a) => (
                <span className="tabular-nums">{a.id_number ?? '—'}</span>
            ),
        },
        {
            key: 'registered',
            header: 'Registered',
            cell: (a) => (
                <span className="tabular-nums">{formatDate(a.created_at)}</span>
            ),
        },
        {
            key: 'waiting',
            header: 'Waiting',
            slot: 'badge',
            cell: (a) => <WaitPill days={a.days_waiting} tier={a.tier} />,
        },
        {
            key: 'actions',
            header: 'Action',
            slot: 'action',
            align: 'right',
            cell: (account) => (
                <div className="flex items-center gap-2 md:justify-end">
                    <Form
                        {...PendingAccountController.verify.form(account.id)}
                        options={{ preserveScroll: true }}
                        className="max-md:flex-1"
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                size="sm"
                                disabled={processing}
                            >
                                {processing ? (
                                    <>
                                        <Spinner /> Verifying…
                                    </>
                                ) : (
                                    <>
                                        Verify
                                        <span className="sr-only">
                                            {' '}
                                            {account.name}
                                        </span>
                                    </>
                                )}
                            </Button>
                        )}
                    </Form>

                    <ConfirmDialog
                        trigger={
                            <Button
                                type="button"
                                size="sm"
                                variant="destructive"
                            >
                                Reject
                                <span className="sr-only"> {account.name}</span>
                            </Button>
                        }
                        title={`Reject ${account.name}'s account?`}
                        description={
                            <>
                                This is permanent. {account.name} will never be
                                able to submit documents or be bound as an
                                officer. Their account is not deleted.
                            </>
                        }
                        notice={
                            rejectError?.accountId === account.id && (
                                <PageNotice
                                    tone="destructive"
                                    urgent
                                    title={rejectError.message}
                                />
                            )
                        }
                        confirmLabel="Reject Account"
                        confirmVariant="destructive"
                        onConfirm={({ close, stopProcessing }) => {
                            setRejectError(null);
                            router.post(
                                PendingAccountController.reject.url(account.id),
                                {},
                                {
                                    preserveScroll: true,
                                    onSuccess: close,
                                    onError: (errors) =>
                                        setRejectError({
                                            accountId: account.id,
                                            message:
                                                errors.account ??
                                                'Could not reject this account. Please try again.',
                                        }),
                                    onFinish: stopProcessing,
                                },
                            );
                        }}
                    />
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Pending Accounts" />

            <div className="flex flex-col gap-6">
                <PageHeader
                    title="Pending Accounts"
                    subtitle="Self registered students waiting for SDAO review. Verified accounts can submit documents and be adviser bound as officers. Rejected accounts lose that ability but are never deleted."
                />

                <section
                    aria-label="Pending account figures"
                    className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4"
                >
                    <WaitingBucketsCard title="Waiting for verification" buckets={buckets} total={accounts.length} />
                    <OldestCard oldest={oldest} onReview={jumpToRow} />
                    <Deferred
                        data="termActivity"
                        fallback={<TermCardsSkeleton />}
                    >
                        {termActivity && <TermCards data={termActivity} />}
                    </Deferred>
                </section>

                <SectionCard
                    title="Awaiting review"
                    count={accounts.length}
                    aside="Oldest first"
                >
                    {accounts.length === 0 ? (
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <UserRoundCheck />
                                </EmptyMedia>
                                <EmptyTitle>No pending accounts</EmptyTitle>
                                <EmptyDescription>
                                    Self-registered students awaiting review
                                    will show up here.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <DataTable
                            rows={accounts}
                            columns={columns}
                            rowKey={(a) => a.id}
                            rowId={(a) => a.id}
                            rowClassName={(a) =>
                                a.id === highlightedId
                                    ? 'bg-warning/10 transition-colors'
                                    : 'transition-colors'
                            }
                            roomy
                        />
                    )}
                </SectionCard>
            </div>
        </>
    );
}

function TermCards({ data }: { data: TermActivity }) {
    return (
        <>
            <SignedUpCard data={data.signedUp} />
            <DecidedCard data={data.decided} />
        </>
    );
}

PendingAccountsIndex.layout = {
    breadcrumbs: [{ title: 'Admin' }, { title: 'Pending Accounts' }],
};
