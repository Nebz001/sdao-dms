import { Deferred, Head, router } from '@inertiajs/react';
import { Building2, Clock, ListChecks, RefreshCw } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import PageHeader from '@/components/page-header';
import DataTable, { RowViewButton } from '@/components/review-queue/data-table';
import type { DataColumn } from '@/components/review-queue/data-table';
import { SectionCard } from '@/components/review-queue/queue-tables';
import SegmentedBar from '@/components/review-queue/segmented-bar';
import StatCard, {
    StatCardSkeleton,
    StatValue,
} from '@/components/review-queue/stat-card';
import { OldestCard } from '@/components/review-queue/stat-cards';
import ThinProgress from '@/components/review-queue/thin-progress';
import type {
    QueueRow,
    ReviewQueueConfig,
} from '@/components/review-queue/types';
import {
    OrganizationStatusBadge,
    RenewalBadge,
} from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { NO_SCHOOL_LABEL } from '@/lib/school';
import { statusLabel } from '@/lib/utils';
import * as organizations from '@/routes/admin/organizations';
import * as reviewRegistrations from '@/routes/review/registrations';

type StatusOption = { value: string };

type OrganizationRow = {
    id: number;
    name: string;
    school: string | null;
    program: string | null;
    status: string;
    renewalDue: boolean;
    requirementsMet: number;
    requirementsTotal: number;
};

type OrganizationStats = {
    total: number;
    active: number;
    needsRenewal: number;
    pendingReview: number;
    inactive: number;
    missingRequirements: number;
    furthestBehind: { name: string; met: number; total: number } | null;
    /** The longest-waiting registration or renewal in SDAO's queue, if any. */
    oldestPending: QueueRow | null;
    /** Organizations that can file a renewal right now (3rd term only). */
    renewalDue: number;
    renewalWindow: { open: boolean; closes: string | null; nextOpens: string };
};

/** Row links and wording come from each row; these are the registration defaults. */
const oldestPendingConfig: ReviewQueueConfig = {
    headTitle: '',
    title: '',
    subtitle: '',
    noun: 'registration',
    typeLabel: 'Registration',
    emptyDescription: '',
    showRoute: (id) => reviewRegistrations.show(id).url,
};

type Props = {
    organizations: {
        data: OrganizationRow[];
        meta: {
            current_page: number;
            last_page: number;
            from: number | null;
            to: number | null;
            total: number;
        };
        links: { prev: string | null; next: string | null };
    };
    filters: {
        status: string | null;
        search: string;
    };
    statuses: StatusOption[];
    /** Deferred, and always across every organization: ignores the filters. */
    stats?: OrganizationStats;
};

const ALL_STATUSES = 'all';

/** Green when complete or nearly, amber part-way; zero has no fill to color. */
function requirementsFill(met: number, total: number): string {
    return total > 0 && met / total >= 0.6 ? 'bg-success' : 'bg-warning';
}

function RequirementsCell({ org }: { org: OrganizationRow }) {
    return (
        <div className="flex flex-col gap-1.5">
            <span className="text-sm tabular-nums">
                {org.requirementsMet} of {org.requirementsTotal} met
            </span>
            <ThinProgress
                value={org.requirementsMet}
                max={org.requirementsTotal}
                label={`${org.name} requirements met`}
                fillClassName={requirementsFill(
                    org.requirementsMet,
                    org.requirementsTotal,
                )}
            />
        </div>
    );
}

function StatusCell({ org }: { org: OrganizationRow }) {
    return (
        <div className="flex flex-wrap items-center justify-end gap-2">
            {org.renewalDue && <RenewalBadge status="due" />}
            <OrganizationStatusBadge status={org.status} />
        </div>
    );
}

function StatCards({ stats }: { stats: OrganizationStats }) {
    const { furthestBehind, renewalWindow } = stats;

    return (
        <>
            <StatCard icon={Building2} title="Total organizations">
                <StatValue>{stats.total}</StatValue>
                <SegmentedBar
                    ariaLabel={`Organizations by status: ${stats.active} active, ${stats.pendingReview} pending, ${stats.needsRenewal} renewal, ${stats.inactive} inactive`}
                    segments={[
                        {
                            label: 'Active',
                            count: stats.active,
                            className: 'bg-success',
                        },
                        {
                            label: 'Pending',
                            count: stats.pendingReview,
                            className: 'bg-info',
                        },
                        {
                            label: 'Renewal',
                            count: stats.needsRenewal,
                            className: 'bg-warning',
                        },
                        {
                            label: 'Inactive',
                            count: stats.inactive,
                            className: 'bg-muted-foreground',
                        },
                    ]}
                />
            </StatCard>
            <OldestCard
                rows={stats.oldestPending ? [stats.oldestPending] : []}
                config={oldestPendingConfig}
                title="Oldest pending review"
                detail="college"
                emptyText="No organization is waiting for review"
            />
            <StatCard icon={ListChecks} title="Missing requirements">
                <div className="flex items-baseline gap-2">
                    <StatValue>{stats.missingRequirements}</StatValue>
                    <span className="text-sm text-muted-foreground tabular-nums">
                        of {stats.total}
                    </span>
                </div>
                <p className="mt-auto text-sm text-muted-foreground">
                    {furthestBehind ? (
                        <>
                            <strong className="font-semibold text-foreground">
                                {furthestBehind.name}
                            </strong>{' '}
                            is furthest behind at{' '}
                            <span className="tabular-nums">
                                {furthestBehind.met} of {furthestBehind.total}
                            </span>
                        </>
                    ) : (
                        'Every organization has met all requirements.'
                    )}
                </p>
            </StatCard>
            <StatCard icon={RefreshCw} title="Next renewal">
                <StatValue>{stats.renewalDue}</StatValue>
                <p className="mt-auto text-sm text-muted-foreground">
                    {stats.renewalDue > 0 && renewalWindow.closes
                        ? `Renewal window runs through ${renewalWindow.closes}`
                        : `No org is due for renewal. Next renewal window opens ${renewalWindow.nextOpens}`}
                </p>
            </StatCard>
        </>
    );
}

function StatCardsSkeleton() {
    return (
        <>
            <StatCardSkeleton icon={Building2} title="Total organizations" />
            <StatCardSkeleton icon={Clock} title="Oldest pending review" />
            <StatCardSkeleton icon={ListChecks} title="Missing requirements" />
            <StatCardSkeleton icon={RefreshCw} title="Next renewal" />
        </>
    );
}

const COLUMNS: DataColumn<OrganizationRow>[] = [
    {
        key: 'name',
        header: 'Organization',
        slot: 'title',
        className: 'min-w-64',
        cell: (org) => (
            <div className="flex flex-col">
                <span className="font-semibold">{org.name}</span>
                <span className="text-sm font-normal text-muted-foreground">
                    {org.school ?? NO_SCHOOL_LABEL}
                </span>
            </div>
        ),
    },
    {
        key: 'program',
        header: 'Program',
        className: 'min-w-48',
        cell: (org) => org.program ?? 'No program',
    },
    {
        key: 'requirements',
        header: 'Requirements',
        className: 'min-w-40',
        cell: (org) => <RequirementsCell org={org} />,
    },
    {
        key: 'status',
        header: 'Status',
        slot: 'badge',
        align: 'right',
        cell: (org) => <StatusCell org={org} />,
    },
    {
        key: 'actions',
        header: 'Action',
        slot: 'action',
        align: 'right',
        cell: (org) => (
            <RowViewButton
                href={organizations.show(org.id).url}
                label={org.name}
            />
        ),
    },
];

export default function OrganizationsIndex({
    organizations: items,
    filters,
    statuses,
    stats,
}: Props) {
    const [status, setStatus] = useState(filters.status ?? ALL_STATUSES);
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const debounceTimer = useRef<ReturnType<typeof setTimeout>>(undefined);
    const isFirstRender = useRef(true);

    function reload(params: Record<string, string>) {
        setLoading(true);
        router.get(organizations.index().url, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['organizations', 'filters'],
            onFinish: () => setLoading(false),
        });
    }

    // Single debounced effect over both filters — same pattern as
    // Registrations / Document Archive, so clearing/combining filters
    // triggers exactly one reload.
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        if (debounceTimer.current) {
            clearTimeout(debounceTimer.current);
        }

        debounceTimer.current = setTimeout(() => {
            const params: Record<string, string> = {};

            if (status !== ALL_STATUSES) {
                params.status = status;
            }

            if (search.trim() !== '') {
                params.search = search.trim();
            }

            reload(params);
        }, 400);

        return () => {
            if (debounceTimer.current) {
                clearTimeout(debounceTimer.current);
            }
        };
    }, [status, search]);

    const hasFilters = status !== ALL_STATUSES || search.trim() !== '';

    function clearFilters() {
        setStatus(ALL_STATUSES);
        setSearch('');
    }

    function goToPage(url: string | null) {
        if (!url) {
            return;
        }

        setLoading(true);
        router.get(
            url,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['organizations', 'filters'],
                onFinish: () => setLoading(false),
            },
        );
    }

    return (
        <>
            <Head title="Organizations" />

            <div className="flex flex-col gap-6">
                <PageHeader
                    title="Organizations"
                    subtitle="Every organization, its standing, and what it still needs."
                />

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)]">
                    <Deferred data="stats" fallback={<StatCardsSkeleton />}>
                        {stats && <StatCards stats={stats} />}
                    </Deferred>
                </div>

                <Card className="shadow-none">
                    <CardContent className="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="organizations-status">Status</Label>
                            <Select value={status} onValueChange={setStatus}>
                                <SelectTrigger
                                    id="organizations-status"
                                    className="w-full sm:w-44"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL_STATUSES}>
                                        All statuses
                                    </SelectItem>
                                    {statuses.map((s) => (
                                        <SelectItem
                                            key={s.value}
                                            value={s.value}
                                        >
                                            {statusLabel(s.value)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="flex flex-1 flex-col gap-2">
                            <Label htmlFor="organizations-search">Search</Label>
                            <Input
                                id="organizations-search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search by organization name…"
                            />
                        </div>

                        {hasFilters && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={clearFilters}
                            >
                                Clear filters
                            </Button>
                        )}
                    </CardContent>
                </Card>

                <SectionCard title="Organizations">
                    {loading ? (
                        <div aria-busy="true" className="flex flex-col gap-3">
                            {Array.from({ length: 5 }).map((_, i) => (
                                <Skeleton key={i} className="h-14 w-full" />
                            ))}
                        </div>
                    ) : items.data.length === 0 ? (
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Building2 />
                                </EmptyMedia>
                                <EmptyTitle>
                                    {hasFilters
                                        ? 'No organizations match these filters'
                                        : 'No organizations yet'}
                                </EmptyTitle>
                                <EmptyDescription>
                                    {hasFilters
                                        ? 'Clear the filters to see every organization.'
                                        : 'Organizations appear here once a registration is submitted.'}
                                </EmptyDescription>
                                {hasFilters && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={clearFilters}
                                    >
                                        Clear filters
                                    </Button>
                                )}
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <DataTable
                            rows={items.data}
                            columns={COLUMNS}
                            rowKey={(org) => org.id}
                        />
                    )}
                </SectionCard>

                {!loading && items.data.length > 0 && (
                    <Card className="shadow-none">
                        <CardContent className="flex items-center justify-between gap-4">
                            <p className="text-sm text-muted-foreground">
                                Showing {items.meta.from}–{items.meta.to} of{' '}
                                {items.meta.total}
                            </p>
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={!items.links.prev}
                                    onClick={() => goToPage(items.links.prev)}
                                >
                                    Previous
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={!items.links.next}
                                    onClick={() => goToPage(items.links.next)}
                                >
                                    Next
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

OrganizationsIndex.layout = {
    breadcrumbs: [{ title: 'Admin' }, { title: 'Organizations' }],
};
