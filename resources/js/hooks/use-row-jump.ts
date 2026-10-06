import { useEffect, useRef, useState } from 'react';

/**
 * "Review" links on a stat card scroll to a row, focus it (so a screen
 * reader announces it) and tint it briefly. Rows opt in with data-row-id
 * (DataTable's rowId). Both the table and stacked-card layouts render the
 * row, and only one is visible.
 */
export function useRowJump() {
    const [highlightedId, setHighlightedId] = useState<number | null>(null);
    const timer = useRef<number | undefined>(undefined);

    useEffect(() => () => window.clearTimeout(timer.current), []);

    function jumpToRow(id: number) {
        const matches = Array.from(document.querySelectorAll<HTMLElement>(`[data-row-id="${id}"]`));
        const row = matches.find((el) => el.offsetParent !== null) ?? matches[0];
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        row?.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' });
        row?.focus({ preventScroll: true });

        setHighlightedId(id);
        window.clearTimeout(timer.current);
        timer.current = window.setTimeout(() => setHighlightedId(null), 2500);
    }

    return { highlightedId, jumpToRow };
}
