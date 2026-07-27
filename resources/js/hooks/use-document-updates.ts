import { router } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * Polls for document updates every 5 seconds while the component is mounted.
 *
 * This is the single swap point for Supabase Realtime (see resources/js/lib/realtime.ts).
 * Replace the body of this hook with a Supabase channel subscription in Slice 6;
 * all callers remain unchanged.
 *
 * `async: true` is required here, not cosmetic: Inertia's default (sync)
 * request stream allows only one in-flight visit and interrupts whatever's
 * already running when a new one starts (@inertiajs/core RequestStream,
 * maxConcurrent: 1, interruptible: true). Every review show page that hosts
 * a confirm-and-submit action (approve/reject/return) also runs this poller,
 * so a poll tick landing on top of that submit would cancel it outright —
 * the request never reaches the server, and the modal driven by its
 * onSuccess/onFinish never resolves. Marking the poll async moves it onto
 * the separate, non-interrupting async stream instead.
 *
 * @param props - Inertia partial props to reload (defaults to document, history, queue).
 */
export function useDocumentUpdates(
    props: string[] = ['document', 'history', 'queue'],
): void {
    useEffect(() => {
        const interval = setInterval(() => {
            router.reload({ only: props, async: true });
        }, 5000);

        return () => {
            clearInterval(interval);
        };
    }, [props.join(',')]); // eslint-disable-line react-hooks/exhaustive-deps
}
