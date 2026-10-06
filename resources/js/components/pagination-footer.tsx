import { Button } from '@/components/ui/button';

export type PaginationMeta = {
    from: number | null;
    to: number | null;
    total: number;
};

export type PaginationLinks = { prev: string | null; next: string | null };

/**
 * The one pagination footer. It goes inside the table or list card, at the
 * bottom, under a thin divider (never in a card of its own): "Showing X to Y
 * of Z" on the left, Previous and Next on the right. On a narrow screen the
 * text sits above the buttons and the buttons share the row. Renders nothing
 * when there are no results, so each page's own empty state is all that shows.
 */
export default function PaginationFooter({
    meta,
    links,
    onNavigate,
}: {
    meta: PaginationMeta;
    links: PaginationLinks;
    /** Called with the target page URL; the page decides how to visit it. */
    onNavigate: (url: string) => void;
}) {
    if (meta.total === 0) {
        return null;
    }

    return (
        <nav
            aria-label="Pagination"
            className="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <p className="text-sm text-muted-foreground" aria-live="polite">
                Showing {meta.from} to {meta.to} of {meta.total}
            </p>
            <div className="flex gap-2 [&>button]:flex-1 sm:[&>button]:flex-none">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    aria-label="Previous page"
                    disabled={!links.prev}
                    onClick={() => links.prev && onNavigate(links.prev)}
                >
                    Previous
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    aria-label="Next page"
                    disabled={!links.next}
                    onClick={() => links.next && onNavigate(links.next)}
                >
                    Next
                </Button>
            </div>
        </nav>
    );
}
