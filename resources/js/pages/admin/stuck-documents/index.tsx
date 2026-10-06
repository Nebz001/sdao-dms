import { Head, Link, router } from '@inertiajs/react';
import { CalendarClock, CircleCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { FormTypeLabelBadge } from '@/components/form-type-badge';
import PageHeader from '@/components/page-header';
import PaginationFooter from '@/components/pagination-footer';
import RemindDocumentButton from '@/components/remind-document-button';
import DataTable from '@/components/review-queue/data-table';
import type { DataColumn } from '@/components/review-queue/data-table';
import { SectionCard } from '@/components/review-queue/queue-tables';
import { ToneBadge } from '@/components/status-badge';
import StuckDocumentsStats, { pluralDays } from '@/components/stuck-documents-stats';
import type { StuckDocumentsStatsData } from '@/components/stuck-documents-stats';
import TagBadge from '@/components/tag-badge';
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
import type { Tone } from '@/lib/status-tones';
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
    formTypeLabel: string;
    organizationName: string;
    college: string;
    state: 'in_review' | 'returned';
    waitingOn: string;
    waitingOnLine: string;
    remindTo: string;
    remindAvailableLabel: string | null;
    sinceDate: string;
    idleDays: number;
    idleTone: Tone;
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
        idle: string | null;
        search: string;
    };
    approvers: { key: string; name: string; line: string }[];
    formTypes: { value: string; label: string }[];
    stats: StuckDocumentsStatsData;
};

const ALL = 'all';

const WAITING_ON_OPTIONS = [
    { value: ALL, label: 'Anyone' },
    { value: 'approver', label: 'An approver' },
    { value: 'org', label: 'The organization (returned)' },
];

const IDLE_OPTIONS = [
    { value: ALL, label: 'Any length' },
    { value: 'under_7', label: 'Under 7 days' },
    { value: '7_14', label: '7 to 14 days' },
    { value: '15_30', label: '15 to 30 days' },
    { value: 'over_30', label: 'Over 30 days' },
];

function formatDate(date: string): string {
    return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });
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
    const [idle, setIdle] = useState(filters.idle ?? ALL);
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
            only: ['documents', 'filters'],
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
                only: ['documents', 'activities', 'filters'],
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
                        title="Stuck Documents"
                        subtitle="Activities in the next 7 days that still need an approval."
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
                                                <p className="text-sm font-semibold break-words">
                                                    {activity.name}
                                                </p>
                                                <TagBadge className="mt-1 text-[0.7rem]">{activity.organizationName}</TagBadge>
                                            </div>
                                            <span className="flex shrink-0 items-center gap-1.5 text-sm text-muted-foreground">
                                                <CalendarClock className="size-4" aria-hidden />
                                                {formatDate(activity.date)}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}
                            <PaginationFooter meta={activities.meta} links={activities.links} onNavigate={goToPage} />
                        </CardContent>
                    </Card>
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
                    title="Stuck Documents"
                    subtitle="Documents sitting too long with an approver or with the organization that has to fix them."
                />

                <StuckDocumentsStats stats={stats} />

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
                                placeholder="Document, organization, or approver"
                            />
                        </div>

                        {hasFilters && (
                            <div className="sm:basis-full">
                                <Button type="button" variant="link" className="h-auto p-0" onClick={clearFilters}>
                                    Clear filters
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <SectionCard title="Open documents" count={documents?.meta.total ?? 0} aside="Longest idle first">
                    {loading ? (
                        <div className="space-y-3" aria-busy="true">
                            {Array.from({ length: 5 }).map((_, i) => (
                                <Skeleton key={i} className="h-14 w-full" />
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
                                        ? 'Try a different approver, form type, idle length, or search term.'
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
                        <div className="flex flex-col gap-4">
                            <DataTable rows={rows} columns={columns} rowKey={(r) => r.id} roomy />
                            {documents && (
                                <PaginationFooter meta={documents.meta} links={documents.links} onNavigate={goToPage} />
                            )}
                        </div>
                    )}
                </SectionCard>
            </div>
        </>
    );
}

const columns: DataColumn<OpenDocument>[] = [
    {
        key: 'document',
        header: 'Document',
        slot: 'title',
        cell: (r) => (
            <div className="flex flex-col items-start gap-1.5">
                <FormTypeLabelBadge label={r.formTypeLabel} />
                <Link href={r.href} className="font-semibold hover:underline">
                    {r.title}
                </Link>
            </div>
        ),
    },
    {
        key: 'organization',
        header: 'Organization',
        cell: (r) => (
            <div className="flex flex-col">
                <span className="font-medium">{r.organizationName}</span>
                <span className="text-sm text-muted-foreground">{r.college}</span>
            </div>
        ),
    },
    {
        key: 'waiting_on',
        header: 'Waiting on',
        cell: (r) => (
            <div className="flex flex-col">
                <span className="font-semibold">{r.waitingOn}</span>
                <span className="text-sm text-muted-foreground">{r.waitingOnLine}</span>
            </div>
        ),
    },
    {
        key: 'since',
        header: 'Since',
        className: 'tabular-nums',
        cell: (r) => r.sinceDate,
    },
    {
        key: 'idle',
        header: 'Idle',
        slot: 'badge',
        cell: (r) => (
            <ToneBadge tone={r.idleTone} className="text-xs tracking-normal normal-case tabular-nums">
                {pluralDays(r.idleDays)}
            </ToneBadge>
        ),
    },
    {
        key: 'actions',
        header: 'Action',
        slot: 'action',
        align: 'right',
        cell: (r) => (
            <RemindDocumentButton
                documentId={r.id}
                documentTitle={r.title}
                remindTo={r.remindTo}
                availableLabel={r.remindAvailableLabel}
            />
        ),
    },
];

StuckDocumentsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin' },
        { title: 'Dashboard', href: adminDashboard.index() },
        { title: 'Stuck documents' },
    ],
};
