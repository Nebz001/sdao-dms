import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import type { TileTone } from '@/components/icon-tile';
import {
    DocumentRow,
    ListCard,
    ListEmptyState,
    ListFooter,
    ListSearch,
    NoMatches,
    StartAction,
    StatusTabs,
    useClientList,
} from '@/components/student-list';
import type { CanStart } from '@/components/student-list';
import StudentPageHeader from '@/components/student-page-header';
import { formatListDate, statusNote } from '@/lib/student-list';
import type { ListFormKind } from '@/lib/student-list';

export type ListRow = {
    id: number;
    title: string;
    status: string;
    created_at: string;
    current_approver: string | null;
};

/**
 * A student list page for a form type the server sends whole: header with the
 * primary action, one card of tabs, search and rows, and the first-run empty
 * state. The pages only say what is specific to them (words, icon, links).
 */
export default function ClientListPage<T extends ListRow>({
    kind,
    icon,
    tone,
    title,
    subtitle,
    searchPlaceholder,
    startLabel,
    startHref,
    canStart,
    rows,
    dateLabel,
    withDrafts = false,
    empty,
    rowAction,
    rowChip,
}: {
    kind: ListFormKind;
    icon: LucideIcon;
    tone: TileTone;
    title: string;
    subtitle: string;
    searchPlaceholder: string;
    startLabel: string;
    startHref: string;
    canStart: CanStart;
    rows: T[];
    /** "Submitted" for most forms, "Received" for calendars. */
    dateLabel: string;
    withDrafts?: boolean;
    empty: { title: string; description: string; steps: string[] };
    rowAction: (row: T) => { label: string; href: string };
    rowChip?: (row: T) => ReactNode;
}) {
    const list = useClientList(rows, { withDrafts });

    return (
        <div className="flex flex-col gap-6">
            <StudentPageHeader
                icon={icon}
                tone={tone}
                title={title}
                subtitle={subtitle}
                actions={
                    rows.length > 0 ? (
                        <StartAction
                            canStart={canStart}
                            href={startHref}
                            label={startLabel}
                        />
                    ) : undefined
                }
            />

            {rows.length === 0 ? (
                <ListEmptyState
                    icon={icon}
                    tone={tone}
                    title={empty.title}
                    description={empty.description}
                    steps={empty.steps}
                    canStart={canStart}
                    startHref={startHref}
                    startLabel={startLabel}
                />
            ) : (
                <ListCard
                    toolbar={
                        <>
                            <StatusTabs
                                tabs={list.tabs}
                                counts={list.counts}
                                value={list.tab}
                                onChange={list.setTab}
                            />
                            <ListSearch
                                value={list.search}
                                onChange={list.setSearch}
                                placeholder={searchPlaceholder}
                            />
                        </>
                    }
                    footer={
                        <ListFooter
                            showing={list.visible.length}
                            total={list.total}
                        />
                    }
                >
                    {list.visible.length === 0 ? (
                        <NoMatches onClear={list.clear} />
                    ) : (
                        <ul>
                            {list.visible.map((row) => {
                                const action = rowAction(row);

                                return (
                                    <DocumentRow
                                        key={row.id}
                                        icon={icon}
                                        tone={tone}
                                        title={row.title}
                                        supporting={
                                            <>
                                                <span>
                                                    {row.status === 'draft' ? 'Started' : dateLabel}{' '}
                                                    {formatListDate(
                                                        row.created_at,
                                                    )}
                                                </span>
                                                {rowChip?.(row)}
                                            </>
                                        }
                                        status={row.status}
                                        note={statusNote(
                                            row.status,
                                            kind,
                                            row.current_approver,
                                        )}
                                        actionLabel={action.label}
                                        actionHref={action.href}
                                    />
                                );
                            })}
                        </ul>
                    )}
                </ListCard>
            )}
        </div>
    );
}
