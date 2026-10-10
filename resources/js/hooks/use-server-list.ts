import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/** A filter value that means "no filter" and is left out of the request. */
const UNSET = ['', 'all'];

/**
 * The filter state of a list the server filters and paginates (Registrations,
 * Document History). Typing and tab or select changes share one debounced
 * request, so combining filters triggers a single reload instead of racing
 * ones. Only the named props are reloaded, in place, without scrolling.
 */
export function useServerList<F extends Record<string, string>>({
    url,
    only,
    initial,
}: {
    url: string;
    only: string[];
    initial: F;
}) {
    const [filters, setFilters] = useState<F>(initial);
    const [loading, setLoading] = useState(false);
    const timer = useRef<ReturnType<typeof setTimeout>>(undefined);
    const isFirstRender = useRef(true);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        if (timer.current) {
            clearTimeout(timer.current);
        }

        timer.current = setTimeout(() => {
            const params = Object.fromEntries(
                Object.entries(filters)
                    .map(([key, value]) => [key, value.trim()])
                    .filter(([, value]) => !UNSET.includes(value)),
            );

            setLoading(true);
            router.get(url, params, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only,
                onFinish: () => setLoading(false),
            });
        }, 400);

        return () => {
            if (timer.current) {
                clearTimeout(timer.current);
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [filters]);

    function set<K extends keyof F>(key: K, value: F[K]) {
        setFilters((current) => ({ ...current, [key]: value }));
    }

    function clear() {
        setFilters(
            Object.fromEntries(
                Object.keys(initial).map((key) => [key, '']),
            ) as F,
        );
    }

    function goToPage(target: string) {
        setLoading(true);
        router.get(
            target,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only,
                onFinish: () => setLoading(false),
            },
        );
    }

    return {
        filters,
        set,
        clear,
        goToPage,
        loading,
        isFiltered: Object.values(filters).some(
            (v) => !UNSET.includes(v.trim()),
        ),
    };
}
