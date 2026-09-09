import { Head, router } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import QueueStatStrip from '@/components/queue-stat-strip';
import { OrganizationStatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { statusLabel } from '@/lib/utils';
import * as organizations from '@/routes/admin/organizations';

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
    stats: {
        total: number;
        active: number;
        needsRenewal: number;
        pendingReview: number;
        inactive: number;
    };
};

const ALL_STATUSES = 'all';

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
            only: ['organizations', 'filters', 'stats'],
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
                only: ['organizations', 'filters', 'stats'],
                onFinish: () => setLoading(false),
            },
        );
    }

    return (
        <>
            <Head title="Organizations" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-balance">
                        Organizations
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Every organization, its derived standing, and what it
                        still needs.
                    </p>
                </div>

                <QueueStatStrip
                    stats={[
                        {
                            label: 'Total',
                            value: String(stats.total),
                        },
                        {
                            label: 'Active',
                            value: String(stats.active),
                        },
                        {
                            label: 'Needs Renewal',
                            value: String(stats.needsRenewal),
                            count: stats.needsRenewal,
                        },
                        {
                            label: 'Pending Review',
                            value: String(stats.pendingReview),
                            count: stats.pendingReview,
                        },
                        {
                            label: 'Inactive',
                            value: String(stats.inactive),
                        },
                    ]}
                />

                <Card>
                    <CardContent className="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                        <div className="grid gap-2">
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

                        <div className="grid flex-1 gap-2">
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

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Organizations
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {loading ? (
                            <div className="space-y-3">
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
                                            ? 'Try a different status or search term.'
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
                            <div className="divide-y">
                                {items.data.map((org) => (
                                    <div
                                        key={org.id}
                                        className="flex items-center justify-between gap-4 py-3"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {org.name}
                                            </p>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {[org.school, org.program]
                                                    .filter(Boolean)
                                                    .join(' · ') ||
                                                    'No college'}
                                                {' · '}
                                                {org.requirementsMet} of{' '}
                                                {org.requirementsTotal}{' '}
                                                requirements met
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-2">
                                            {org.renewalDue && (
                                                <span className="rounded-full bg-warning/15 px-2.5 py-0.5 text-xs font-medium text-warning">
                                                    Renewal due
                                                </span>
                                            )}
                                            <OrganizationStatusBadge
                                                status={org.status}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {!loading && items.data.length > 0 && (
                    <Card>
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
