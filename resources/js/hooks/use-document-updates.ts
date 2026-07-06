import { router } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * Polls for document updates every 5 seconds while the component is mounted.
 *
 * This is the single swap point for Supabase Realtime (see resources/js/lib/realtime.ts).
 * Replace the body of this hook with a Supabase channel subscription in Slice 6;
 * all callers remain unchanged.
 *
 * @param props - Inertia partial props to reload (defaults to document, history, queue).
 */
export function useDocumentUpdates(
    props: string[] = ['document', 'history', 'queue'],
): void {
    useEffect(() => {
        const interval = setInterval(() => {
            router.reload({ only: props });
        }, 5000);

        return () => {
            clearInterval(interval);
        };
    }, [props.join(',')]); // eslint-disable-line react-hooks/exhaustive-deps
}
