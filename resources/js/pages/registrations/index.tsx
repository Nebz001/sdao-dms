import { Head, Link, router } from '@inertiajs/react';
import { Files } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import QueueStatStrip from '@/components/queue-stat-strip';
import { StatusBadge } from '@/components/status-badge';
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
import * as registrations from '@/routes/registrations';

type StatusOption = { value: string };

type Registration = {
    id: number;
    title: string;
    status: string;
    organization: { id: number; name: string };
    created_at: string;
    href: string;
};

type Props = {
    registrations: {
        data: Registration[];
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
        inProgress: number;
        approved: number;
        rejected: number;
    };
};

const ALL_STATUSES = 'all';

export default function RegistrationsIndex({
    registrations: items,
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
        router.get(registrations.index().url, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['registrations', 'filters', 'stats'],
            onFinish: () => setLoading(false),
        });
    }

    // A single debounced effect covers both filters (select change and
    // keystrokes alike) so clearing/combining filters triggers exactly one
    // reload instead of racing separate effects per field — same pattern as
    // the Document Archive / Document History pages.
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
                only: ['registrations', 'filters', 'stats'],
                onFinish: () => setLoading(false),
            },
        );
    }

    return (
        <>
            <Head title="Registrations" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-balance">
                            Registrations
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Every organization registration you've submitted,
                            and its status in SDAO review.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={registrations.create().url}>
                            New Registration
                        </Link>
                    </Button>
                </div>

                <QueueStatStrip
                    stats={[
                        {
                            label: 'Total',
                            value: String(stats.total),
                            count: stats.total,
                        },
                        {
                            label: 'In Progress',
                            value: String(stats.inProgress),
                            count: stats.inProgress,
                        },
                        {
                            label: 'Approved',
                            value: String(stats.approved),
                            count: stats.approved,
                        },
                        {
                            label: 'Rejected',
                            value: String(stats.rejected),
                            count: stats.rejected,
                        },
                    ]}
                />

                <Card>
                    <CardContent className="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                        <div className="grid gap-2">
                            <Label htmlFor="registrations-status">Status</Label>
                            <Select value={status} onValueChange={setStatus}>
                                <SelectTrigger
                                    id="registrations-status"
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
                            <Label htmlFor="registrations-search">Search</Label>
                            <Input
                                id="registrations-search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search by title or organization…"
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
                            My Registrations
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {loading ? (
                            <div className="space-y-3">
                                {Array.from({ length: 5 }).map((_, i) => (
                                    <Skeleton key={i} className="h-12 w-full" />
                                ))}
                            </div>
                        ) : items.data.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <Files />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        {hasFilters
                                            ? 'No registrations match these filters'
                                            : 'No registrations yet'}
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        {hasFilters
                                            ? 'Try a different status or search term.'
                                            : "Once you submit a registration, it'll show up here."}
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
                                {items.data.map((r) => (
                                    <div
                                        key={r.id}
                                        className="relative flex items-center justify-between gap-4 py-3"
                                    >
                                        <div className="min-w-0">
                                            {/* after:absolute after:inset-0 stretches this link's hit target to
                                                the whole row (the parent is `relative`), so the row is fully
                                                clickable without wrapping the trailing actions in an anchor —
                                                that would nest a <button> inside an <a>, which is invalid HTML
                                                with ambiguous click bubbling. Same title-as-link idiom as the
                                                Activity Log page. */}
                                            <Link
                                                href={r.href}
                                                className="font-medium after:absolute after:inset-0"
                                            >
                                                <p className="truncate">
                                                    {r.title}
                                                </p>
                                            </Link>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {r.organization.name} ·{' '}
                                                {new Date(
                                                    r.created_at,
                                                ).toLocaleDateString()}
                                            </p>
                                        </div>
                                        <div className="relative z-10 flex shrink-0 items-center gap-2">
                                            <StatusBadge status={r.status} />
                                            {r.status === 'returned' && (
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link
                                                        href={
                                                            registrations.edit({
                                                                document: r.id,
                                                            }).url
                                                        }
                                                    >
                                                        Revise
                                                    </Link>
                                                </Button>
                                            )}
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

RegistrationsIndex.layout = {
    breadcrumbs: [{ title: 'Registrations' }],
};
