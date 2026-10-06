import { router } from '@inertiajs/react';
import { useEffect } from 'react';

const POLL_INTERVAL_MS = 5000;
const MAX_BACKOFF_MS = 60000;
/** Safety net: if a poll never reports finishing, stop treating it as in flight. */
const IN_FLIGHT_TIMEOUT_MS = 30000;

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
 * The poller must also never make a struggling server worse, or surface its
 * own failure as the page:
 *  - Ticks never overlap: a tick is skipped while the previous poll is still
 *    in flight, so a slow response cannot stack requests up on the origin.
 *  - Ticks are skipped while the tab is hidden, and one poll runs when it
 *    becomes visible again.
 *  - A 5xx or network failure is swallowed (returning false from
 *    onHttpException stops Inertia from opening its full-screen dialog with
 *    the raw gateway-error HTML) and backs the next attempt off, doubling up
 *    to a minute; the first success resets the cadence. 4xx responses are
 *    left to Inertia's default handling so an expired session or a revoked
 *    permission is still surfaced.
 *
 * @param props - Inertia partial props to reload (defaults to document, history, queue).
 */
export function useDocumentUpdates(
    props: string[] = ['document', 'history', 'queue'],
): void {
    useEffect(() => {
        let inFlightSince: number | null = null;
        let failures = 0;
        let nextAllowedAt = 0;
        let stopped = false;

        const poll = () => {
            const stuck = inFlightSince !== null && Date.now() - inFlightSince > IN_FLIGHT_TIMEOUT_MS;

            if (stopped || (inFlightSince !== null && !stuck) || document.hidden || Date.now() < nextAllowedAt) {
                return;
            }

            inFlightSince = Date.now();

            router.reload({
                only: props,
                async: true,
                onSuccess: () => {
                    failures = 0;
                    nextAllowedAt = 0;
                },
                onHttpException: (response) => {
                    if (response.status < 500) {
                        return;
                    }

                    failures += 1;
                    nextAllowedAt = Date.now() + Math.min(POLL_INTERVAL_MS * 2 ** failures, MAX_BACKOFF_MS);

                    return false;
                },
                onNetworkError: () => {
                    failures += 1;
                    nextAllowedAt = Date.now() + Math.min(POLL_INTERVAL_MS * 2 ** failures, MAX_BACKOFF_MS);

                    return false;
                },
                onFinish: () => {
                    inFlightSince = null;
                },
            });
        };

        const interval = setInterval(poll, POLL_INTERVAL_MS);
        const onVisible = () => {
            if (!document.hidden) {
                poll();
            }
        };

        document.addEventListener('visibilitychange', onVisible);

        return () => {
            stopped = true;
            clearInterval(interval);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [props.join(',')]); // eslint-disable-line react-hooks/exhaustive-deps
}
