import { useCallback, useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    /** Accessible name for the scrollable region. */
    label: string;
    className?: string;
    children: ReactNode;
};

/**
 * A vertically scrolling region with a thin theme-colored scrollbar and a soft
 * fade at the top and bottom edge while there is more to scroll to. It fills
 * whatever height its parent gives it (`min-h-0 flex-1`), so a card can cap its
 * height and let only this list scroll. Focusable so the list can be scrolled
 * with the keyboard.
 */
export default function FadeScroll({ label, className, children }: Props) {
    const scrollRef = useRef<HTMLDivElement>(null);
    const [edges, setEdges] = useState({ top: false, bottom: false });

    const update = useCallback(() => {
        const el = scrollRef.current;

        if (!el) {
            return;
        }

        const top = el.scrollTop > 1;
        const bottom = el.scrollTop + el.clientHeight < el.scrollHeight - 1;

        setEdges((prev) =>
            prev.top === top && prev.bottom === bottom ? prev : { top, bottom },
        );
    }, []);

    useEffect(() => {
        const el = scrollRef.current;

        if (!el) {
            return;
        }

        update();

        const observer = new ResizeObserver(update);

        observer.observe(el);

        if (el.firstElementChild) {
            observer.observe(el.firstElementChild);
        }

        return () => observer.disconnect();
    }, [update]);

    return (
        <div className={cn('relative flex min-h-0 flex-1 flex-col', className)}>
            <div
                ref={scrollRef}
                onScroll={update}
                role="region"
                aria-label={label}
                tabIndex={0}
                className="min-h-0 flex-1 [scrollbar-width:thin] [scrollbar-color:var(--border)_transparent] overflow-y-auto overscroll-contain focus-visible:focus-ring-inset"
            >
                {children}
            </div>
            <div
                aria-hidden
                className={cn(
                    'pointer-events-none absolute inset-x-0 top-0 h-6 bg-linear-to-b from-card to-transparent transition-opacity',
                    edges.top ? 'opacity-100' : 'opacity-0',
                )}
            />
            <div
                aria-hidden
                className={cn(
                    'pointer-events-none absolute inset-x-0 bottom-0 h-6 bg-linear-to-t from-card to-transparent transition-opacity',
                    edges.bottom ? 'opacity-100' : 'opacity-0',
                )}
            />
        </div>
    );
}
