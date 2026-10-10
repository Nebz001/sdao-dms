import { Head } from '@inertiajs/react';
import { History } from 'lucide-react';
import {
    DocumentRow,
    ListCard,
    ListFooter,
    ListSearch,
    NoMatches,
    RowChip,
    StatusTabs,
} from '@/components/student-list';
import StudentPageHeader from '@/components/student-page-header';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useServerList } from '@/hooks/use-server-list';
import { FORM_STYLE, FORM_TYPE_KIND } from '@/lib/form-style';
import { formatListDate, isTabKey, statusNote } from '@/lib/student-list';
import type { TabKey } from '@/lib/student-list';
import * as documentHistory from '@/routes/document-history';

type HistoryDocument = {
    id: number;
    title: string;
    status: string;
    formType: string;
    formTypeLabel: string;
    lastActivityAt: string;
    currentApprover: string | null;
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
    formTypes: { value: string; label: string }[];
    stats: {
        total: number;
        inProgress: number;
        inReview: number;
        approved: number;
        returned: number;
        rejected: number;
    };
};

const TABS: TabKey[] = ['all', 'in_review', 'approved', 'returned'];

export default function DocumentHistoryIndex({
    documents,
    filters,
    formTypes,
    stats,
}: Props) {
    const list = useServerList({
        url: documentHistory.index().url,
        only: ['documents', 'filters', 'stats'],
        initial: {
            status: filters.status ?? '',
            form_type: filters.form_type ?? '',
            search: filters.search,
        },
    });
    const tab: TabKey = isTabKey(list.filters.status)
        ? list.filters.status
        : 'all';
    const { meta } = documents;
    const neverFiled = stats.total === 0 && !list.isFiltered;

    return (
        <>
            <Head title="Document History" />

            <div className="flex flex-col gap-6">
                <StudentPageHeader
                    icon={History}
                    tone="green"
                    title="Document History"
                    subtitle="Everything your organization has filed. All officers see the same list."
                />

                {neverFiled ? (
                    <Empty className="border">
                        <EmptyHeader>
                            <EmptyTitle>No documents yet</EmptyTitle>
                            <EmptyDescription>
                                Once your organization files a document, it
                                shows up here with where it stands.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <ListCard
                        toolbar={
                            <>
                                <StatusTabs
                                    tabs={TABS}
                                    counts={{
                                        all: stats.total,
                                        in_review: stats.inReview,
                                        approved: stats.approved,
                                        returned: stats.returned,
                                        draft: 0,
                                    }}
                                    value={tab}
                                    onChange={(next) =>
                                        list.set(
                                            'status',
                                            next === 'all' ? '' : next,
                                        )
                                    }
                                />
                                <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                                    <Select
                                        value={list.filters.form_type || 'all'}
                                        onValueChange={(value) =>
                                            list.set('form_type', value)
                                        }
                                    >
                                        <SelectTrigger
                                            aria-label="Filter by form type"
                                            className="h-9 w-full sm:w-40"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">
                                                All forms
                                            </SelectItem>
                                            {formTypes.map((type) => (
                                                <SelectItem
                                                    key={type.value}
                                                    value={type.value}
                                                >
                                                    {type.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <ListSearch
                                        value={list.filters.search}
                                        onChange={(value) =>
                                            list.set('search', value)
                                        }
                                        placeholder="Search by name"
                                    />
                                </div>
                            </>
                        }
                        footer={
                            <ListFooter
                                showing={
                                    meta.from === null || meta.to === null
                                        ? 0
                                        : meta.to - meta.from + 1
                                }
                                total={meta.total}
                                pagination={{
                                    prev: documents.links.prev,
                                    next: documents.links.next,
                                    onNavigate: list.goToPage,
                                }}
                            />
                        }
                    >
                        {list.loading ? (
                            <div className="space-y-3 p-5" aria-busy="true">
                                {Array.from({ length: 4 }).map((_, i) => (
                                    <Skeleton key={i} className="h-12 w-full" />
                                ))}
                            </div>
                        ) : documents.data.length === 0 ? (
                            <NoMatches onClear={list.clear} />
                        ) : (
                            <ul>
                                {documents.data.map((doc) => {
                                    const kind =
                                        FORM_TYPE_KIND[doc.formType] ??
                                        'proposal';
                                    const style = FORM_STYLE[kind];

                                    return (
                                        <DocumentRow
                                            key={doc.id}
                                            icon={style.icon}
                                            tone={style.tone}
                                            title={doc.title}
                                            supporting={
                                                <>
                                                    <RowChip>
                                                        {doc.formTypeLabel}
                                                    </RowChip>
                                                    <span>
                                                        {formatListDate(
                                                            doc.lastActivityAt,
                                                        )}
                                                    </span>
                                                </>
                                            }
                                            status={doc.status}
                                            note={statusNote(
                                                doc.status,
                                                kind,
                                                doc.currentApprover,
                                            )}
                                            actionHref={doc.href}
                                        />
                                    );
                                })}
                            </ul>
                        )}
                    </ListCard>
                )}
            </div>
        </>
    );
}
