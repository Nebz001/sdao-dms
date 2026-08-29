import { Head, Link, router } from '@inertiajs/react';
import { History } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import QueueStatStrip from '@/components/queue-stat-strip';
import { RelativeTime } from '@/components/relative-time';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { statusLabel } from '@/lib/utils';
import * as documentHistory from '@/routes/document-history';

type FormTypeOption = { value: string; label: string };
type StatusOption = { value: string };

type HistoryDocument = {
    id: number;
    title: string;
    status: string;
    formType: string;
    formTypeLabel: string;
    lastActivityAt: string;
    href: string;
};

type Props = {
    documents: {
        data: HistoryDocument[];
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
        status: string | null;
        search: string;
    };
    formTypes: FormTypeOption[];
    statuses: StatusOption[];
    stats: {
        total: number;
        inProgress: number;
        approved: number;
        rejected: number;
    };
};

const ALL_TYPES = 'all';
const ALL_STATUSES = 'all';

export default function DocumentHistoryIndex({ documents, filters, formTypes, statuses, stats }: Props) {
    const [formType, setFormType] = useState(filters.form_type ?? ALL_TYPES);
    const [status, setStatus] = useState(filters.status ?? ALL_STATUSES);
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const debounceTimer = useRef<ReturnType<typeof setTimeout>>(undefined);
    const isFirstRender = useRef(true);

    function reload(params: Record<string, string>) {
        setLoading(true);
        router.get(documentHistory.index().url, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['documents', 'filters', 'stats'],
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
    }, [formType, status, search]);

    const hasFilters = formType !== ALL_TYPES || status !== ALL_STATUSES || search.trim() !== '';

    function clearFilters() {
        setFormType(ALL_TYPES);
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
                only: ['documents', 'filters', 'stats'],
                onFinish: () => setLoading(false),
            },
        );
    }

    return (
        <>
            <Head title="Document History" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-balance">Document History</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Every document your organization has ever filed, across every form type and status —
                        president and secretary see the same full list.
                    </p>
                </div>

                <QueueStatStrip
                    stats={[
                        { label: 'Total', value: String(stats.total), count: stats.total },
                        { label: 'In Progress', value: String(stats.inProgress), count: stats.inProgress },
                        { label: 'Approved', value: String(stats.approved), count: stats.approved },
                        { label: 'Rejected', value: String(stats.rejected), count: stats.rejected },
                    ]}
                />

                <Card>
                    <CardContent className="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                        <div className="grid gap-2">
                            <Label htmlFor="history-form-type">Form type</Label>
                            <Select value={formType} onValueChange={setFormType}>
                                <SelectTrigger id="history-form-type" className="w-full sm:w-56">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL_TYPES}>All types</SelectItem>
                                    {formTypes.map((t) => (
                                        <SelectItem key={t.value} value={t.value}>
                                            {t.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="history-status">Status</Label>
                            <Select value={status} onValueChange={setStatus}>
                                <SelectTrigger id="history-status" className="w-full sm:w-44">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL_STATUSES}>All statuses</SelectItem>
                                    {statuses.map((s) => (
                                        <SelectItem key={s.value} value={s.value}>
                                            {statusLabel(s.value)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid flex-1 gap-2">
                            <Label htmlFor="history-search">Search</Label>
                            <Input
                                id="history-search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search by title…"
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
                        <CardTitle className="text-base">Documents</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {loading ? (
                            <div className="space-y-3">
                                {Array.from({ length: 5 }).map((_, i) => (
                                    <Skeleton key={i} className="h-12 w-full" />
                                ))}
                            </div>
                        ) : documents.data.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <History />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        {hasFilters ? 'No documents match these filters' : 'Nothing filed yet'}
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        {hasFilters
                                            ? 'Try a different form type, status, or search term.'
                                            : "Your organization's registrations, renewals, calendars, proposals, and reports will show up here as they're filed."}
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
                                {documents.data.map((doc) => (
                                    <div key={doc.id} className="flex items-center justify-between gap-4 py-3">
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">{doc.title}</p>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {doc.formTypeLabel} · <RelativeTime dateString={doc.lastActivityAt} />
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-2">
                                            <StatusBadge status={doc.status} />
                                            <Button asChild size="sm" variant="outline">
                                                <Link href={doc.href}>View</Link>
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {!loading && documents.data.length > 0 && (
                    <Card>
                        <CardContent className="flex items-center justify-between gap-4">
                            <p className="text-sm text-muted-foreground">
                                Showing {documents.meta.from}–{documents.meta.to} of {documents.meta.total}
                            </p>
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={!documents.links.prev}
                                    onClick={() => goToPage(documents.links.prev)}
                                >
                                    Previous
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={!documents.links.next}
                                    onClick={() => goToPage(documents.links.next)}
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

DocumentHistoryIndex.layout = {
    breadcrumbs: [{ title: 'My Documents' }, { title: 'Document History' }],
};
