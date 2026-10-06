import { Head, Link, router } from '@inertiajs/react';
import { History } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import ActivityLogStats from '@/components/activity-log-stats';
import type { ActivityLogStatsData } from '@/components/activity-log-stats';
import PageHeader from '@/components/page-header';
import PageNotice from '@/components/page-notice';
import PaginationFooter from '@/components/pagination-footer';
import DataTable from '@/components/review-queue/data-table';
import type { DataColumn } from '@/components/review-queue/data-table';
import { SectionCard } from '@/components/review-queue/queue-tables';
import { ActionBadge } from '@/components/status-badge';
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
import { statusLabel } from '@/lib/utils';
import * as activityLog from '@/routes/admin/activity';

type FormTypeOption = { value: string; label: string };
type ActionOption = { value: string };

type ActivityEntry = {
    id: number;
    actorName: string;
    action: string;
    documentTitle: string;
    formTypeLabel: string;
    organizationName: string;
    college: string;
    createdAt: string;
    whenDate: string;
    whenTime: string;
    href: string | null;
};

const DATE_OPTIONS = [
    { value: 'term', label: 'This term' },
    { value: 'week', label: 'This week' },
    { value: 'last_30', label: 'Last 30 days' },
    { value: 'academic_year', label: 'This academic year' },
    { value: 'all', label: 'All time' },
];

const DEFAULT_DATE = 'term';
const CUSTOM_RANGE = 'custom';

type Props = {
    transitions: {
        data: ActivityEntry[];
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
        form_type: string | null;
        action: string | null;
        search: string;
        from?: string | null;
        to?: string | null;
        date?: string | null;
    };
    formTypes: FormTypeOption[];
    actions: ActionOption[];
    stats: ActivityLogStatsData;
};

const ALL_TYPES = 'all';
const ALL_ACTIONS = 'all';

export default function ActivityLogIndex({
    transitions,
    filters,
    formTypes,
    actions,
    stats,
}: Props) {
    const [formType, setFormType] = useState(filters.form_type ?? ALL_TYPES);
    const [action, setAction] = useState(filters.action ?? ALL_ACTIONS);
    const [search, setSearch] = useState(filters.search);
    const [date, setDate] = useState(filters.date ?? DEFAULT_DATE);
    const [dateRange, setDateRange] = useState<{ from: string; to: string } | null>(
        filters.from && filters.to ? { from: filters.from, to: filters.to } : null,
    );
    const [loading, setLoading] = useState(false);
    const debounceTimer = useRef<ReturnType<typeof setTimeout>>(undefined);
    const isFirstRender = useRef(true);

    function reload(params: Record<string, string>) {
        setLoading(true);
        router.get(activityLog.index().url, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['transitions', 'filters'],
            onFinish: () => setLoading(false),
        });
    }

    // A single debounced effect covers all three filters (select changes and
    // keystrokes alike) so clearing/combining filters triggers exactly one
    // reload instead of racing separate effects per field — same pattern as
    // the Document Archive page.
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

            if (formType !== ALL_TYPES) {
                params.form_type = formType;
            }

            if (action !== ALL_ACTIONS) {
                params.action = action;
            }

            if (search.trim() !== '') {
                params.search = search.trim();
            }

            if (dateRange) {
                params.from = dateRange.from;
                params.to = dateRange.to;
            } else if (date !== DEFAULT_DATE) {
                params.date = date;
            }

            reload(params);
        }, 400);

        return () => {
            if (debounceTimer.current) {
                clearTimeout(debounceTimer.current);
            }
        };
    }, [formType, action, search, date, dateRange]);

    const hasFilters =
        dateRange !== null ||
        date !== DEFAULT_DATE ||
        formType !== ALL_TYPES ||
        action !== ALL_ACTIONS ||
        search.trim() !== '';

    function clearFilters() {
        setDateRange(null);
        setDate(DEFAULT_DATE);
        setFormType(ALL_TYPES);
        setAction(ALL_ACTIONS);
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
                only: ['transitions', 'filters'],
                onFinish: () => setLoading(false),
            },
        );
    }

    return (
        <>
            <Head title="Activity Log" />

            <div className="space-y-6">
                <PageHeader title="Activity Log" subtitle="Every submission, approval, return, and rejection across every organization and form type." />

                <ActivityLogStats stats={stats} />

                <Card>
                    <CardContent className="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                        <div className="grid gap-2">
                            <Label htmlFor="activity-form-type">
                                Form type
                            </Label>
                            <Select
                                value={formType}
                                onValueChange={setFormType}
                            >
                                <SelectTrigger
                                    id="activity-form-type"
                                    className="w-full sm:w-56"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL_TYPES}>
                                        All types
                                    </SelectItem>
                                    {formTypes.map((t) => (
                                        <SelectItem
                                            key={t.value}
                                            value={t.value}
                                        >
                                            {t.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="activity-action">Action</Label>
                            <Select value={action} onValueChange={setAction}>
                                <SelectTrigger
                                    id="activity-action"
                                    className="w-full sm:w-48"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL_ACTIONS}>
                                        All actions
                                    </SelectItem>
                                    {actions.map((a) => (
                                        <SelectItem
                                            key={a.value}
                                            value={a.value}
                                        >
                                            {statusLabel(a.value)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="activity-date">Date</Label>
                            <Select
                                value={dateRange ? CUSTOM_RANGE : date}
                                onValueChange={(value) => {
                                    setDateRange(null);
                                    setDate(value);
                                }}
                            >
                                <SelectTrigger id="activity-date" className="w-full sm:w-48">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {dateRange && (
                                        <SelectItem value={CUSTOM_RANGE} disabled>
                                            Custom range
                                        </SelectItem>
                                    )}
                                    {DATE_OPTIONS.map((o) => (
                                        <SelectItem key={o.value} value={o.value}>
                                            {o.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid flex-1 gap-2">
                            <Label htmlFor="activity-search">Search</Label>
                            <Input
                                id="activity-search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Document, person, or organization"
                            />
                        </div>

                        {dateRange && (
                            <div className="sm:basis-full">
                                <PageNotice
                                    tone="info"
                                    action={
                                        <Button
                                            type="button"
                                            variant="link"
                                            className="h-auto p-0"
                                            onClick={() => {
                                                setDateRange(null);
                                                setDate('all');
                                            }}
                                        >
                                            Show all dates
                                        </Button>
                                    }
                                >
                                    Showing activity from {dateRange.from} to {dateRange.to}.
                                </PageNotice>
                            </div>
                        )}

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

                <SectionCard title="Events" count={transitions.meta.total} aside="Newest first">
                    {loading ? (
                        <div className="space-y-3" aria-busy="true">
                            {Array.from({ length: 5 }).map((_, i) => (
                                <Skeleton key={i} className="h-14 w-full" />
                            ))}
                        </div>
                    ) : transitions.data.length === 0 ? (
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <History />
                                </EmptyMedia>
                                <EmptyTitle>
                                    {hasFilters
                                        ? 'No activity matches these filters'
                                        : 'Nothing has happened yet'}
                                </EmptyTitle>
                                <EmptyDescription>
                                    {hasFilters
                                        ? 'Try a different form type, action, date, or search term.'
                                        : 'Submissions and approvals will show up here as they happen.'}
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
                        <div className="flex flex-col gap-4">
                            <DataTable rows={transitions.data} columns={columns} rowKey={(e) => e.id} roomy />
                            <PaginationFooter meta={transitions.meta} links={transitions.links} onNavigate={goToPage} />
                        </div>
                    )}
                </SectionCard>
            </div>
        </>
    );
}

const columns: DataColumn<ActivityEntry>[] = [
    {
        key: 'when',
        header: 'When',
        cell: (e) => (
            <div className="flex flex-col">
                <span className="tabular-nums">{e.whenDate}</span>
                <span className="text-sm text-muted-foreground tabular-nums">{e.whenTime}</span>
            </div>
        ),
    },
    {
        key: 'done_by',
        header: 'Done by',
        cell: (e) => <span className="font-semibold">{e.actorName}</span>,
    },
    {
        key: 'action',
        header: 'Action',
        slot: 'badge',
        cell: (e) => <ActionBadge action={e.action} className="text-xs tracking-normal normal-case" />,
    },
    {
        key: 'document',
        header: 'Document',
        slot: 'title',
        cell: (e) =>
            e.href ? (
                <Link href={e.href} className="font-semibold hover:underline">
                    {e.documentTitle}
                </Link>
            ) : (
                <span className="font-semibold">{e.documentTitle}</span>
            ),
    },
    {
        key: 'organization',
        header: 'Organization',
        cell: (e) => (
            <div className="flex flex-col">
                <span className="font-medium">{e.organizationName}</span>
                <span className="text-sm text-muted-foreground">{e.college}</span>
            </div>
        ),
    },
    {
        key: 'form_type',
        header: 'Form type',
        cell: (e) => e.formTypeLabel,
    },
];

ActivityLogIndex.layout = {
    breadcrumbs: [{ title: 'Admin' }, { title: 'Activity Log' }],
};
