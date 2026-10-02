import { Head, Link, router } from '@inertiajs/react';
import { CalendarClock, CircleCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import IdleBadge from '@/components/idle-badge';
import type { IdleTier } from '@/components/idle-badge';
import PageHeader from '@/components/page-header';
import QueueStatStrip from '@/components/queue-stat-strip';
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
import * as adminDashboard from '@/routes/admin/dashboard';
import * as stuckDocuments from '@/routes/admin/stuck-documents';

type Paginated<T> = {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
    links: { prev: string | null; next: string | null };
};

type OpenDocument = {
    id: number;
    title: string;
    formType: string;
    organizationName: string;
    state: 'in_review' | 'returned';
    waitingOn: string;
    waitingOnLine: string;
    idleDays: number;
    tier: IdleTier;
    href: string;
};

type UpcomingActivity = {
    id: number;
    name: string;
    organizationName: string;
    date: string;
};

type Props = {
    mode: 'documents' | 'upcoming';
    documents: Paginated<OpenDocument> | null;
    activities: Paginated<UpcomingActivity> | null;
    filters: {
        waiting_on: string | null;
        approver: string | null;
        role: string | null;
        form_type: string | null;
        idle: number | null;
        search: string;
    };
    approvers: { key: string; name: string; line: string }[];
    formTypes: { value: string; label: string }[];
    stats: { withApprovers: number; returned: number };
};

const ALL = 'all';

const WAITING_ON_OPTIONS = [
    { value: ALL, label: 'Everyone' },
    { value: 'approver', label: 'An approver' },
    { value: 'org', label: 'The organization (returned)' },
];

const IDLE_OPTIONS = [
    { value: ALL, label: 'Any time' },
    { value: '3', label: '3 days or more' },
    { value: '7', label: '7 days or more' },
];

function formatDate(date: string): string {
    return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });
}

function Pager({
    page,
    onGo,
}: {
    page: Paginated<unknown>;
    onGo: (url: string | null) => void;
}) {
    return (
        <Card>
            <CardContent className="flex items-center justify-between gap-4">
                <p className="text-sm text-muted-foreground">
                    Showing {page.meta.from} to {page.meta.to} of {page.meta.total}
                </p>
                <div className="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={!page.links.prev}
                        onClick={() => onGo(page.links.prev)}
                    >
                        Previous
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={!page.links.next}
                        onClick={() => onGo(page.links.next)}
                    >
                        Next
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

export default function StuckDocumentsIndex({
    mode,
    documents,
    activities,
    filters,
    approvers,
    formTypes,
    stats,
}: Props) {
    const [waitingOn, setWaitingOn] = useState(filters.waiting_on ?? ALL);
    const [approver, setApprover] = useState(filters.approver ?? ALL);
    const [formType, setFormType] = useState(filters.form_type ?? ALL);
    const [idle, setIdle] = useState(filters.idle ? String(filters.idle) : ALL);
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const debounceTimer = useRef<ReturnType<typeof setTimeout>>(undefined);
    const isFirstRender = useRef(true);

    function reload(params: Record<string, string>) {
        setLoading(true);
        router.get(stuckDocuments.index().url, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['documents', 'filters', 'stats'],
            onFinish: () => setLoading(false),
        });
    }

    // One debounced effect covers every filter, like the Activity Log page.
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

            if (waitingOn !== ALL) {
                params.waiting_on = waitingOn;
            }

            if (approver !== ALL) {
                params.approver = approver;
            }

            if (filters.role) {
                params.role = filters.role;
            }

            if (formType !== ALL) {
                params.form_type = formType;
            }

            if (idle !== ALL) {
                params.idle = idle;
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
    }, [waitingOn, approver, formType, idle, search, filters.role]);

    const hasFilters =
        waitingOn !== ALL ||
        approver !== ALL ||
        formType !== ALL ||
        idle !== ALL ||
        search.trim() !== '' ||
        filters.role !== null;

    function clearFilters() {
        setWaitingOn(ALL);
        setApprover(ALL);
        setFormType(ALL);
        setIdle(ALL);
        setSearch('');
        router.get(stuckDocuments.index().url, {}, { preserveState: true, replace: true });
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
                only: ['documents', 'activities', 'filters', 'stats'],
                onFinish: () => setLoading(false),
            },
        );
    }

    if (mode === 'upcoming' && activities) {
        return (
            <>
                <Head title="Stuck documents" />

                <div className="space-y-6">
                    <PageHeader
                        title="Stuck documents"
                        subtitle="Documents waiting on an approver or on the organization's officers."
                    />

                    <Card>
                        <CardHeader className="flex flex-row items-start justify-between gap-2">
                            <div className="grid gap-1.5">
                                <CardTitle className="text-base">
                                    Activities in the next 7 days that are not approved
                                </CardTitle>
                                <p className="text-sm text-muted-foreground">
                                    Soonest first. Open the organization's documents to chase the approval.
                                </p>
                            </div>
                            <Button asChild variant="outline" size="sm">
                                <Link href={stuckDocuments.index().url}>All open documents</Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {activities.data.length === 0 ? (
                                <Empty>
                                    <EmptyHeader>
                                        <EmptyMedia variant="icon">
                                            <CircleCheck />
                                        </EmptyMedia>
                                        <EmptyTitle>Nothing is waiting on approval</EmptyTitle>
                                        <EmptyDescription>
                                            Every activity in the next 7 days is approved.
                                        </EmptyDescription>
                                    </EmptyHeader>
                                </Empty>
                            ) : (
                                <div className="divide-y">
                                    {activities.data.map((activity) => (
                                        <div
                                            key={activity.id}
                                            className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0"
                                        >
                                            <div className="min-w-0">
                                                <p className="text-sm font-semibold max-sm:break-words sm:truncate">
                                                    {activity.name}
                                                </p>
                                                <span className="mt-1 inline-block rounded-sm border px-1.5 py-0.5 text-xs text-muted-foreground">
                                                    {activity.organizationName}
                                                </span>
                                            </div>
                                            <span className="flex shrink-0 items-center gap-1.5 text-sm text-muted-foreground">
                                                <CalendarClock className="size-4" aria-hidden />
                                                {formatDate(activity.date)}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {activities.data.length > 0 && <Pager page={activities} onGo={goToPage} />}
                </div>
            </>
        );
    }

    const rows = documents?.data ?? [];

    return (
        <>
            <Head title="Stuck documents" />

            <div className="space-y-6">
                <PageHeader
                    title="Stuck documents"
                    subtitle="Documents waiting on an approver or on the organization's officers."
                />

                <QueueStatStrip
                    stats={[
                        {
                            label: 'With approvers',
                            value: String(stats.withApprovers),
                            count: stats.withApprovers,
                        },
                        {
                            label: 'Returned to organizations',
                            value: String(stats.returned),
                            count: stats.returned,
                        },
                    ]}
                />

                <Card>
                    <CardContent className="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                        <div className="grid gap-2">
                            <Label htmlFor="stuck-waiting-on">Waiting on</Label>
                            <Select value={waitingOn} onValueChange={setWaitingOn}>
                                <SelectTrigger id="stuck-waiting-on" className="w-full sm:w-56">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {WAITING_ON_OPTIONS.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="stuck-approver">Approver</Label>
                            <Select value={approver} onValueChange={setApprover}>
                                <SelectTrigger id="stuck-approver" className="w-full sm:w-56">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>All approvers</SelectItem>
                                    {approvers.map((a) => (
                                        <SelectItem key={a.key} value={a.key}>
                                            {a.name}, {a.line}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="stuck-form-type">Form type</Label>
                            <Select value={formType} onValueChange={setFormType}>
                                <SelectTrigger id="stuck-form-type" className="w-full sm:w-52">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>All types</SelectItem>
                                    {formTypes.map((t) => (
                                        <SelectItem key={t.value} value={t.value}>
                                            {t.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="stuck-idle">Idle for</Label>
                            <Select value={idle} onValueChange={setIdle}>
                                <SelectTrigger id="stuck-idle" className="w-full sm:w-44">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {IDLE_OPTIONS.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid flex-1 gap-2 sm:min-w-56">
                            <Label htmlFor="stuck-search">Search</Label>
                            <Input
                                id="stuck-search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Document, organization or approver"
                            />
                        </div>

                        {hasFilters && (
                            <Button type="button" variant="ghost" onClick={clearFilters}>
                                Clear filters
                            </Button>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Open documents</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {loading ? (
                            <div className="space-y-3">
                                {Array.from({ length: 5 }).map((_, i) => (
                                    <Skeleton key={i} className="h-12 w-full" />
                                ))}
                            </div>
                        ) : rows.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <CircleCheck />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        {hasFilters ? 'No documents match these filters' : 'Nothing is stuck'}
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        {hasFilters
                                            ? 'Try a different approver, form type, or search term.'
                                            : 'No document is waiting on an approver or returned to an organization.'}
                                    </EmptyDescription>
                                    {hasFilters && (
                                        <Button type="button" variant="outline" size="sm" onClick={clearFilters}>
                                            Clear filters
                                        </Button>
                                    )}
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <div className="divide-y">
                                {rows.map((row) => (
                                    <div
                                        key={row.id}
                                        className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0"
                                    >
                                        <div className="min-w-0">
                                            <Link
                                                href={row.href}
                                                className="block text-sm font-semibold hover:underline max-sm:break-words sm:truncate"
                                            >
                                                {row.title}
                                            </Link>
                                            <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                                                <span className="rounded-sm border px-1.5 py-0.5">
                                                    {row.organizationName}
                                                </span>
                                                <span>
                                                    {row.state === 'returned'
                                                        ? 'Returned, waiting on the organization'
                                                        : `at ${row.waitingOn}`}
                                                </span>
                                            </div>
                                        </div>
                                        <IdleBadge days={row.idleDays} tier={row.tier} label="idle" />
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {!loading && documents && rows.length > 0 && <Pager page={documents} onGo={goToPage} />}
            </div>
        </>
    );
}

StuckDocumentsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin' },
        { title: 'Dashboard', href: adminDashboard.index() },
        { title: 'Stuck documents' },
    ],
};
