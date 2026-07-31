import { Head, Link, router } from '@inertiajs/react';
import { Archive as ArchiveIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import QueueStatStrip from '@/components/queue-stat-strip';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import * as archive from '@/routes/admin/archive';

type FormTypeOption = { value: string; label: string };

type ArchivedDocument = {
    id: number;
    title: string;
    status: string;
    form_type: string;
    form_type_label: string;
    organization: { id: number; name: string };
    decided_at: string;
    href: string;
};

type Props = {
    documents: {
        data: ArchivedDocument[];
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
    stats: { approved: number; rejected: number; total: number };
};

const ALL_TYPES = 'all';
const ALL_STATUSES = 'all';

export default function DocumentArchiveIndex({ documents, filters, formTypes, stats }: Props) {
    const [formType, setFormType] = useState(filters.form_type ?? ALL_TYPES);
    const [status, setStatus] = useState(filters.status ?? ALL_STATUSES);
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const debounceTimer = useRef<ReturnType<typeof setTimeout>>(undefined);
    const isFirstRender = useRef(true);

    function reload(params: Record<string, string>) {
        setLoading(true);
        router.get(archive.index().url, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['documents', 'filters', 'stats'],
            onFinish: () => setLoading(false),
        });
    }

    // A single debounced effect covers all three filters (select changes and
    // keystrokes alike) so clearing/combining filters triggers exactly one
    // reload instead of racing separate effects per field.
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
        router.get(url, {}, {
            preserveState: true,
            preserveScroll: true,
            only: ['documents', 'filters', 'stats'],
            onFinish: () => setLoading(false),
        });
    }

    return (
        <>
            <Head title="Document Archive" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-balance">Document Archive</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Approved and rejected documents across every form type, once they&apos;ve left the
                        review queues.
                    </p>
                </div>

                <QueueStatStrip
                    stats={[
                        { label: 'Approved', value: String(stats.approved) },
                        { label: 'Rejected', value: String(stats.rejected) },
                        { label: 'Total decided', value: String(stats.total) },
                    ]}
                />

                <Card>
                    <CardContent className="flex flex-col gap-4 sm:flex-row sm:items-end sm:flex-wrap">
                        <div className="grid gap-2">
                            <Label htmlFor="archive-form-type">Form type</Label>
                            <Select value={formType} onValueChange={setFormType}>
                                <SelectTrigger id="archive-form-type" className="w-full sm:w-56">
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
                            <Label htmlFor="archive-status">Status</Label>
                            <Select value={status} onValueChange={setStatus}>
                                <SelectTrigger id="archive-status" className="w-full sm:w-40">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL_STATUSES}>Approved & Rejected</SelectItem>
                                    <SelectItem value="approved">Approved</SelectItem>
                                    <SelectItem value="rejected">Rejected</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid flex-1 gap-2">
                            <Label htmlFor="archive-search">Search</Label>
                            <Input
                                id="archive-search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Title or organization…"
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
                        <CardTitle className="text-base">Decided Documents</CardTitle>
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
                                        <ArchiveIcon />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        {hasFilters ? 'No documents match these filters' : 'Nothing decided yet'}
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        {hasFilters
                                            ? 'Try a different form type, status, or search term.'
                                            : 'Approved and rejected documents will show up here once SDAO finalizes them.'}
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
                                                {doc.organization.name} · {doc.form_type_label} ·{' '}
                                                {new Date(doc.decided_at).toLocaleDateString()}
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
                    <div className="flex items-center justify-between gap-4">
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
                    </div>
                )}
            </div>
        </>
    );
}

DocumentArchiveIndex.layout = {
    breadcrumbs: [{ title: 'Admin' }, { title: 'Document Archive' }],
};
