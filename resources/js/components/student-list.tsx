import { Link, usePage } from '@inertiajs/react';
import { Plus, Search, SearchX } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import IconTile from '@/components/icon-tile';
import type { TileTone } from '@/components/icon-tile';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    isTabKey,
    matchesTab,
    tabCounts,
    TAB_LABELS,
} from '@/lib/student-list';
import type { TabKey } from '@/lib/student-list';
import { cn } from '@/lib/utils';

/** What a list page may start right now (StudentFormAvailability on the server). */
export type CanStart = { enabled: boolean; reason: string | null };

/** The primary "New …" button, or the plain reason it is closed. Never a dead end. */
export function StartAction({
    canStart,
    href,
    label,
}: {
    canStart: CanStart;
    href: string;
    label: string;
}) {
    if (!canStart.enabled) {
        return canStart.reason ? (
            <p className="max-w-64 text-sm text-muted-foreground">
                {canStart.reason}
            </p>
        ) : null;
    }

    return (
        <Button asChild>
            <Link href={href}>
                <Plus data-icon="inline-start" />
                {label}
            </Link>
        </Button>
    );
}

/** The All / In review / Approved / Returned filter, each with its count. */
export function StatusTabs({
    tabs,
    counts,
    value,
    onChange,
}: {
    tabs: TabKey[];
    counts: Record<TabKey, number>;
    value: TabKey;
    onChange: (tab: TabKey) => void;
}) {
    return (
        <ToggleGroup
            type="single"
            value={value}
            // Clicking the selected tab again would clear the group; a filter
            // always has one choice, so ignore the empty value.
            onValueChange={(next) => isTabKey(next) && onChange(next)}
            aria-label="Filter by status"
            className="w-fit max-w-full flex-wrap gap-1 rounded-lg border bg-muted/30 p-1"
        >
            {tabs.map((tab) => (
                <ToggleGroupItem
                    key={tab}
                    value={tab}
                    aria-label={`${TAB_LABELS[tab]}, ${counts[tab]}`}
                    className="h-8 gap-2 rounded-md px-3 text-sm data-[state=on]:bg-accent data-[state=on]:font-semibold"
                >
                    {TAB_LABELS[tab]}
                    <span
                        aria-hidden
                        className={cn(
                            'inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-xs font-medium tabular-nums',
                            value === tab
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground',
                        )}
                    >
                        {counts[tab]}
                    </span>
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}

export function ListSearch({
    value,
    onChange,
    placeholder,
}: {
    value: string;
    onChange: (value: string) => void;
    placeholder: string;
}) {
    return (
        <div role="search" className="relative w-full sm:w-64">
            <Search
                aria-hidden
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                type="search"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                aria-label={placeholder}
                className="h-9 pl-9"
            />
        </div>
    );
}

/**
 * The single card every student list sits in: the toolbar (tabs, search, any
 * extra filter), the rows, and the footer, all inside one border.
 */
export function ListCard({
    toolbar,
    footer,
    children,
}: {
    toolbar: ReactNode;
    footer: ReactNode;
    children: ReactNode;
}) {
    return (
        <Card className="gap-0 overflow-hidden py-0">
            <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-5">
                {toolbar}
            </div>
            <div className="border-t">{children}</div>
            <div className="border-t px-4 py-3 text-sm text-muted-foreground sm:px-5">
                {footer}
            </div>
        </Card>
    );
}

/**
 * One document row. Three columns that stay aligned down the list: what it
 * is, its status with a plain line beneath, and the View button. On a phone
 * they stack. Status and action are never joined onto one line.
 */
export function DocumentRow({
    icon,
    tone,
    title,
    supporting,
    status,
    note,
    actionLabel = 'View',
    actionHref,
}: {
    icon: LucideIcon;
    tone: TileTone;
    title: string;
    supporting: ReactNode;
    status: string;
    note: string | null;
    actionLabel?: string;
    actionHref: string;
}) {
    return (
        <li className="grid grid-cols-1 gap-3 px-4 py-4 sm:grid-cols-[minmax(0,1fr)_13rem_5.5rem] sm:items-center sm:gap-4 sm:px-5 [&:not(:first-child)]:border-t">
            <div className="flex min-w-0 items-center gap-3">
                <IconTile icon={icon} tone={tone} size="sm" />
                <div className="min-w-0">
                    <p className="font-semibold max-sm:break-words sm:truncate">
                        {title}
                    </p>
                    <div className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
                        {supporting}
                    </div>
                </div>
            </div>
            <div className="flex flex-col items-start gap-1">
                <StatusBadge status={status} />
                {note && (
                    <p className="text-xs text-muted-foreground">{note}</p>
                )}
            </div>
            <div className="sm:justify-self-end">
                <Button asChild variant="secondary" size="sm">
                    <Link
                        href={actionHref}
                        aria-label={`${actionLabel} ${title}`}
                    >
                        {actionLabel}
                    </Link>
                </Button>
            </div>
        </li>
    );
}

/** A small neutral chip for a row's supporting line, e.g. "In activity calendar". */
export function RowChip({ children }: { children: ReactNode }) {
    return (
        <span className="inline-flex items-center rounded-full border bg-muted/40 px-2 py-0.5 text-xs font-medium text-foreground/80">
            {children}
        </span>
    );
}

/** Shown when a tab or search leaves nothing to list. */
export function NoMatches({ onClear }: { onClear: () => void }) {
    return (
        <Empty className="border-0">
            <EmptyHeader>
                <SearchX aria-hidden className="size-8 text-muted-foreground" />
                <EmptyTitle>Nothing matches</EmptyTitle>
                <EmptyDescription>
                    Try a different tab or search word.
                </EmptyDescription>
            </EmptyHeader>
            <Button variant="outline" size="sm" onClick={onClear}>
                Clear filters
            </Button>
        </Empty>
    );
}

/**
 * The first-run state of a list (see the Renewals reference): icon, what this
 * is for, the steps as small numbered chips, and the start button. The button
 * only appears when the student may start one now; otherwise the plain reason
 * (for example, when renewal opens) is shown instead.
 */
export function ListEmptyState({
    icon,
    tone,
    title,
    description,
    steps,
    canStart,
    startHref,
    startLabel,
}: {
    icon: LucideIcon;
    tone: TileTone;
    title: string;
    description: string;
    steps: string[];
    canStart: CanStart;
    startHref: string;
    startLabel: string;
}) {
    return (
        <Card className="items-center gap-4 px-6 py-12 text-center">
            <IconTile icon={icon} tone={tone} size="lg" />
            <div className="flex max-w-md flex-col gap-1">
                <h2 className="text-lg font-semibold">{title}</h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
            <ol className="flex flex-wrap items-center justify-center gap-2">
                {steps.map((step, index) => (
                    <li
                        key={step}
                        className="flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm"
                    >
                        <span
                            aria-hidden
                            className="flex size-5 items-center justify-center rounded-full bg-muted text-xs font-medium tabular-nums"
                        >
                            {index + 1}
                        </span>
                        {step}
                    </li>
                ))}
            </ol>
            <StartAction
                canStart={canStart}
                href={startHref}
                label={startLabel}
            />
        </Card>
    );
}

/** "Showing X of Y", with optional previous/next for a paginated list. */
export function ListFooter({
    showing,
    total,
    pagination,
}: {
    showing: number;
    total: number;
    pagination?: {
        prev: string | null;
        next: string | null;
        onNavigate: (url: string) => void;
    };
}) {
    const hasPages = pagination && (pagination.prev || pagination.next);

    return (
        <div className="flex flex-wrap items-center justify-between gap-3">
            <p aria-live="polite">
                Showing {showing} of {total}
            </p>
            {hasPages && (
                <nav aria-label="Pagination" className="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={!pagination.prev}
                        onClick={() =>
                            pagination.prev &&
                            pagination.onNavigate(pagination.prev)
                        }
                    >
                        Previous
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={!pagination.next}
                        onClick={() =>
                            pagination.next &&
                            pagination.onNavigate(pagination.next)
                        }
                    >
                        Next
                    </Button>
                </nav>
            )}
        </div>
    );
}

/**
 * Tabs plus search over a list the server sent whole (renewals, calendars,
 * proposals, reports). The counts follow the search, so a tab always shows
 * what choosing it would list. `?tab=draft` opens that tab, which is where the
 * Submit hub's "Continue a Draft" option lands.
 */
export function useClientList<T extends { status: string; title: string }>(
    items: T[],
    { withDrafts = false }: { withDrafts?: boolean } = {},
) {
    const { url } = usePage();
    const initial = new URLSearchParams(url.split('?')[1] ?? '').get('tab');
    const [tab, setTab] = useState<TabKey>(
        isTabKey(initial) && (initial !== 'draft' || withDrafts)
            ? initial
            : 'all',
    );
    const [search, setSearch] = useState('');

    const searched = useMemo(() => {
        const needle = search.trim().toLowerCase();

        return needle === ''
            ? items
            : items.filter((item) => item.title.toLowerCase().includes(needle));
    }, [items, search]);

    const counts = useMemo(
        () => tabCounts(searched.map((item) => item.status)),
        [searched],
    );
    const tabs: TabKey[] = ['all', 'in_review', 'approved', 'returned'];

    if (withDrafts && counts.draft > 0) {
        tabs.push('draft');
    }

    const activeTab = tabs.includes(tab) ? tab : 'all';
    const visible = searched.filter((item) =>
        matchesTab(item.status, activeTab),
    );

    return {
        tab: activeTab,
        setTab,
        search,
        setSearch,
        tabs,
        counts,
        visible,
        total: items.length,
        isFiltered: search.trim() !== '' || activeTab !== 'all',
        clear: () => {
            setSearch('');
            setTab('all');
        },
    };
}
