import { Head } from '@inertiajs/react';
import { Route, SearchX } from 'lucide-react';
import { useState } from 'react';
import { NotAnOfficerBlocked } from '@/components/student-blocked';
import { ListFooter, ListSearch } from '@/components/student-list';
import StudentPageHeader from '@/components/student-page-header';
import { TrackCard, nextOpenStep } from '@/components/track-documents';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useServerList } from '@/hooks/use-server-list';
import type { TrackRow, TrackTab } from '@/lib/track';
import { cn } from '@/lib/utils';
import * as trackRoutes from '@/routes/track';

type Props = {
    organization: { id: number; name: string } | null;
    /** The academic year the page is scoped to, e.g. "2026-2027". */
    period: string;
    search: string;
    track: {
        rows: TrackRow[];
        counts: Record<TrackTab, number>;
        tab: TrackTab;
        meta: {
            current_page: number;
            last_page: number;
            from: number | null;
            to: number | null;
            total: number;
        };
        links: { prev: string | null; next: string | null };
    };
};

const TAB_LABELS: Record<TrackTab, string> = {
    all: 'All',
    action: 'Needs your action',
    moving: 'Moving',
    approved: 'Approved',
    stopped: 'Stopped',
};

const EMPTY: Record<TrackTab, { title: string; description: string }> = {
    all: {
        title: 'No documents to track',
        description:
            'Once your organization files a document, you can follow it through its approval steps here.',
    },
    action: {
        title: 'Nothing needs your action',
        description: 'Returned documents and unfinished drafts show up here.',
    },
    moving: {
        title: 'Nothing is moving right now',
        description: 'Documents waiting on an approver show up here.',
    },
    approved: {
        title: 'Nothing approved yet',
        description:
            'Approved documents show up here with the date of each step.',
    },
    stopped: {
        title: 'Nothing was stopped',
        description:
            'A document an approver rejected shows up here. It cannot be revived, so file a new one.',
    },
};

export default function TrackDocuments({
    organization,
    period,
    search,
    track,
}: Props) {
    // The open detail card lives here, not in the cards, so a background refresh
    // of the page's data (or a card re-rendering) never closes it.
    const [openStep, setOpenStep] = useState<string | null>(null);
    const list = useServerList({
        url: trackRoutes.index().url,
        only: ['track', 'search'],
        initial: { tab: track.tab as string, search },
    });

    if (organization === null) {
        return (
            <>
                <Head title="Track Documents" />
                <NotAnOfficerBlocked action="track your organization’s documents" />
            </>
        );
    }

    const tab = (list.filters.tab || 'all') as TrackTab;
    // "Stopped" only appears when something was, so most officers never see it.
    const tabs = (Object.keys(TAB_LABELS) as TrackTab[]).filter(
        (key) =>
            key !== 'stopped' || track.counts.stopped > 0 || tab === 'stopped',
    );
    const { meta } = track;
    const pageUrl = (page: string) =>
        `${trackRoutes.index().url}?${new URLSearchParams({
            ...(tab !== 'all' ? { tab } : {}),
            ...(list.filters.search.trim()
                ? { search: list.filters.search.trim() }
                : {}),
            page,
        }).toString()}`;

    return (
        <>
            <Head title="Track Documents" />

            <div className="flex flex-col gap-6">
                <StudentPageHeader
                    icon={Route}
                    title="Track Documents"
                    subtitle="See where each of your organization’s documents is in the approval chain. Hover over a step for a quick note, or click it to see who has it."
                />

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <ToggleGroup
                        type="single"
                        value={tab}
                        // A filter always has one choice, so clicking the selected tab again does nothing.
                        onValueChange={(next) =>
                            next && list.set('tab', next === 'all' ? '' : next)
                        }
                        aria-label="Filter documents"
                        className="w-fit max-w-full flex-wrap gap-1 rounded-lg border bg-muted/30 p-1"
                    >
                        {tabs.map((key) => (
                            <ToggleGroupItem
                                key={key}
                                value={key}
                                aria-label={`${TAB_LABELS[key]}, ${track.counts[key]}`}
                                className="h-8 gap-2 rounded-md px-3 text-sm data-[state=on]:bg-accent data-[state=on]:font-semibold"
                            >
                                {TAB_LABELS[key]}
                                <span
                                    aria-hidden
                                    className={cn(
                                        'inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-xs font-medium tabular-nums',
                                        tab === key
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground',
                                    )}
                                >
                                    {track.counts[key]}
                                </span>
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                    <ListSearch
                        value={list.filters.search}
                        onChange={(value) => list.set('search', value)}
                        placeholder="Search documents"
                    />
                </div>

                {list.loading ? (
                    <div className="grid gap-4" aria-busy="true">
                        {Array.from({ length: 3 }).map((_, i) => (
                            <Skeleton key={i} className="h-40 w-full" />
                        ))}
                    </div>
                ) : track.rows.length === 0 ? (
                    <Empty className="border">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <SearchX />
                            </EmptyMedia>
                            <EmptyTitle>
                                {list.filters.search.trim()
                                    ? 'No documents match'
                                    : EMPTY[tab].title}
                            </EmptyTitle>
                            <EmptyDescription>
                                {list.filters.search.trim()
                                    ? `Nothing matches “${list.filters.search.trim()}” here. Try a different word.`
                                    : EMPTY[tab].description}
                            </EmptyDescription>
                        </EmptyHeader>
                        {(list.isFiltered || tab !== 'all') && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={list.clear}
                            >
                                Clear filters
                            </Button>
                        )}
                    </Empty>
                ) : (
                    <div className="grid gap-4">
                        {track.rows.map((row) => (
                            <TrackCard
                                key={row.id}
                                row={row}
                                openStep={openStep}
                                onOpenStep={(id, open) =>
                                    setOpenStep((prev) =>
                                        nextOpenStep(prev, id, open),
                                    )
                                }
                            />
                        ))}
                    </div>
                )}

                <div className="text-sm text-muted-foreground">
                    <ListFooter
                        showing={track.rows.length}
                        total={meta.total}
                        pagination={{
                            prev: track.links.prev
                                ? pageUrl(track.links.prev)
                                : null,
                            next: track.links.next
                                ? pageUrl(track.links.next)
                                : null,
                            onNavigate: list.goToPage,
                        }}
                    />
                    <p className="mt-1 text-xs">
                        Showing {period} and anything still open.
                    </p>
                </div>
            </div>
        </>
    );
}

TrackDocuments.layout = {
    breadcrumbs: [{ title: 'Track Documents' }],
};
