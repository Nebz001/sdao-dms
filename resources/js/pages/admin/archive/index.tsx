import { Head, router } from '@inertiajs/react';
import { Archive as ArchiveIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import DocumentArchiveStats from '@/components/document-archive-stats';
import type { DocumentArchiveStatsData } from '@/components/document-archive-stats';
import { FormTypeLabelBadge } from '@/components/form-type-badge';
import PageHeader from '@/components/page-header';
import PageNotice from '@/components/page-notice';
import PaginationFooter from '@/components/pagination-footer';
import DataTable, { RowViewButton } from '@/components/review-queue/data-table';
import type { DataColumn } from '@/components/review-queue/data-table';
import { SectionCard } from '@/components/review-queue/queue-tables';
import { formatDate } from '@/components/review-queue/types';
import { StatusBadge } from '@/components/status-badge';
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
import * as archive from '@/routes/admin/archive';

type FormTypeOption = { value: string; label: string };

type ArchivedDocument = {
    id: number;
    title: string;
    status: string;
    form_type: string;
    form_type_label: string;
    organization: { id: number; name: string };
    college: string;
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
        academic_year?: string | null;
    };
    formTypes: FormTypeOption[];
    stats: DocumentArchiveStatsData;
};

const ALL_TYPES = 'all';
const ALL_STATUSES = 'all';

export default function DocumentArchiveIndex({
    documents,
    filters,
    formTypes,
    stats,
}: Props) {
    const [formType, setFormType] = useState(filters.form_type ?? ALL_TYPES);
    const [status, setStatus] = useState(filters.status ?? ALL_STATUSES);
    const [search, setSearch] = useState(filters.search);
    const [yearOnly, setYearOnly] = useState(filters.academic_year === 'current');
    const [loading, setLoading] = useState(false);
    const debounceTimer = useRef<ReturnType<typeof setTimeout>>(undefined);
    const isFirstRender = useRef(true);

    // The stat cards cover the whole archive, so only the table and filters reload.
    function reload(params: Record<string, string>) {
        setLoading(true);
        router.get(archive.index().url, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['documents', 'filters'],
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

            if (yearOnly) {
                params.academic_year = 'current';
            }

            reload(params);
        }, 400);

        return () => {
            if (debounceTimer.current) {
                clearTimeout(debounceTimer.current);
            }
        };
    }, [formType, status, search, yearOnly]);

    const hasFilters =
        formType !== ALL_TYPES ||
        status !== ALL_STATUSES ||
        search.trim() !== '';

    function clearFilters() {
        setYearOnly(false);
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
                only: ['documents', 'filters'],
                onFinish: () => setLoading(false),
            },
        );
    }

    const columns: DataColumn<ArchivedDocument>[] = [
        {
            key: 'document',
            header: 'Document',
            slot: 'title',
            cell: (d) => (
                <div className="flex flex-col items-start gap-1.5">
                    <FormTypeLabelBadge label={d.form_type_label} />
                    <span className="font-semibold">{d.title}</span>
                </div>
            ),
        },
        {
            key: 'organization',
            header: 'Organization',
            cell: (d) => (
                <div className="flex flex-col">
                    <span className="font-medium">{d.organization.name}</span>
                    <span className="text-sm text-muted-foreground">
                        {d.college}
                    </span>
                </div>
            ),
        },
        {
            key: 'decided',
            header: 'Decided on',
            className: 'tabular-nums',
            cell: (d) => formatDate(d.decided_at),
        },
        {
            key: 'result',
            header: 'Result',
            slot: 'badge',
            cell: (d) => (
                <StatusBadge
                    status={d.status}
                    className="text-xs tracking-normal normal-case"
                />
            ),
        },
        {
            key: 'actions',
            header: 'Action',
            slot: 'action',
            align: 'right',
            cell: (d) => (
                <RowViewButton
                    href={d.href}
                    label={`${d.form_type_label}: ${d.title}`}
                />
            ),
        },
    ];

    return (
        <>
            <Head title="Document Archive" />

            <div className="flex flex-col gap-6">
                <PageHeader
                    title="Document Archive"
                    subtitle="Approved and rejected documents of every form type, after they leave the review queues."
                />

                <DocumentArchiveStats stats={stats} />

                <Card className="shadow-none">
                    <CardContent className="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                        <div className="grid gap-2">
                            <Label htmlFor="archive-form-type">Form type</Label>
                            <Select
                                value={formType}
                                onValueChange={setFormType}
                            >
                                <SelectTrigger
                                    id="archive-form-type"
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
                            <Label htmlFor="archive-status">Status</Label>
                            <Select value={status} onValueChange={setStatus}>
                                <SelectTrigger
                                    id="archive-status"
                                    className="w-full sm:w-52"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL_STATUSES}>
                                        Approved and Rejected
                                    </SelectItem>
                                    <SelectItem value="approved">
                                        Approved
                                    </SelectItem>
                                    <SelectItem value="rejected">
                                        Rejected
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid flex-1 gap-2">
                            <Label htmlFor="archive-search">Search</Label>
                            <Input
                                id="archive-search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Title or organization"
                            />
                        </div>

                        {yearOnly && (
                            <div className="sm:basis-full">
                                <PageNotice
                                    tone="info"
                                    action={
                                        <Button
                                            type="button"
                                            variant="link"
                                            className="h-auto p-0"
                                            onClick={() => setYearOnly(false)}
                                        >
                                            Show all years
                                        </Button>
                                    }
                                >
                                    Showing documents created in the current academic year.
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

                <SectionCard
                    title="Decided documents"
                    count={documents.meta.total}
                    aside="Newest first"
                >
                    {loading ? (
                        <div className="space-y-3" aria-busy="true">
                            {Array.from({ length: 5 }).map((_, i) => (
                                <Skeleton key={i} className="h-14 w-full" />
                            ))}
                        </div>
                    ) : documents.data.length === 0 ? (
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <ArchiveIcon />
                                </EmptyMedia>
                                <EmptyTitle>
                                    {hasFilters
                                        ? 'No documents match these filters'
                                        : 'Nothing decided yet'}
                                </EmptyTitle>
                                <EmptyDescription>
                                    {hasFilters
                                        ? 'Try a different form type, status, or search term.'
                                        : 'Approved and rejected documents will show up here once SDAO finalizes them.'}
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
                            <DataTable
                                rows={documents.data}
                                columns={columns}
                                rowKey={(d) => d.id}
                                roomy
                            />
                            <PaginationFooter
                                meta={documents.meta}
                                links={documents.links}
                                onNavigate={goToPage}
                            />
                        </div>
                    )}
                </SectionCard>
            </div>
        </>
    );
}

DocumentArchiveIndex.layout = {
    breadcrumbs: [{ title: 'Admin' }, { title: 'Document Archive' }],
};
