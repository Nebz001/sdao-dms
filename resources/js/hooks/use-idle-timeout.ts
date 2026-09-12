import { useCallback, useEffect, useRef, useState } from 'react';

export type IdleTimeoutStatus = 'active' | 'warning';

type UseIdleTimeoutOptions = {
    /** Milliseconds of no tracked activity before the warning appears. */
    idleMs: number;
    /** Milliseconds the warning stays up before onTimeout fires. */
    warningMs: number;
    onTimeout: () => void;
};

type UseIdleTimeoutResult = {
    status: IdleTimeoutStatus;
    secondsRemaining: number;
    /** Cancels the pending logout and returns to the active state — call
     *  this from the warning dialog's "Stay signed in" button only; ambient
     *  activity (mouse/keyboard) is deliberately ignored once the warning is
     *  showing, so ordinary background input can't silently dismiss it. */
    stayActive: () => void;
};

const ACTIVITY_EVENTS = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'] as const;
const TICK_MS = 1000;

/**
 * Tracks real user interaction (not background polling — see
 * use-document-updates.ts, which would otherwise keep the server session
 * alive forever) and fires onTimeout once idleMs + warningMs of inactivity
 * elapses. A single recurring interval checks elapsed time against a cheap
 * ref timestamp, rather than re-arming a setTimeout on every event — the
 * activity events themselves fire far too often for that.
 */
export function useIdleTimeout({ idleMs, warningMs, onTimeout }: UseIdleTimeoutOptions): UseIdleTimeoutResult {
    // Date.now() is impure, so it can't be called as a useRef() initializer
    // during render — seeded to 0 and set for real in the mount effect below.
    const lastActivityRef = useRef(0);
    const statusRef = useRef<IdleTimeoutStatus>('active');
    const firedRef = useRef(false);
    const [status, setStatus] = useState<IdleTimeoutStatus>('active');
    const [secondsRemaining, setSecondsRemaining] = useState(Math.ceil(warningMs / 1000));

    const stayActive = useCallback(() => {
        lastActivityRef.current = Date.now();
        firedRef.current = false;
        statusRef.current = 'active';
        setStatus('active');
        setSecondsRemaining(Math.ceil(warningMs / 1000));
    }, [warningMs]);

    useEffect(() => {
        lastActivityRef.current = Date.now();

        function handleActivity() {
            if (statusRef.current === 'warning') {
                return;
            }

            lastActivityRef.current = Date.now();
        }

        ACTIVITY_EVENTS.forEach((event) => window.addEventListener(event, handleActivity, { passive: true }));

        return () => {
            ACTIVITY_EVENTS.forEach((event) => window.removeEventListener(event, handleActivity));
        };
    }, []);

    useEffect(() => {
        const tick = setInterval(() => {
            const elapsed = Date.now() - lastActivityRef.current;

            if (elapsed >= idleMs + warningMs) {
                if (!firedRef.current) {
                    firedRef.current = true;
                    onTimeout();
                }

                return;
            }

            if (elapsed >= idleMs) {
                statusRef.current = 'warning';
                setStatus('warning');
                setSecondsRemaining(Math.max(0, Math.ceil((idleMs + warningMs - elapsed) / TICK_MS)));
            }
        }, TICK_MS);

        return () => clearInterval(tick);
    }, [idleMs, warningMs, onTimeout]);

    return { status, secondsRemaining, stayActive };
}
