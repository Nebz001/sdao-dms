import { router, usePage } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { useIdleTimeout } from '@/hooks/use-idle-timeout';
import { logout } from '@/routes';

const IDLE_MS = 15 * 60 * 1000;
const WARNING_MS = 60 * 1000;

/**
 * Logs an authenticated user out after IDLE_MS of no tracked interaction,
 * warning them WARNING_MS before it happens so they can stay signed in.
 * Client-side only — background polling (useDocumentUpdates, the
 * notification bell) would otherwise silently slide Laravel's own
 * (120-minute) session lifetime forever, so a genuine "real interaction"
 * timer is the only thing that can actually distinguish idle from active.
 *
 * Mounted once in app-sidebar-layout.tsx, alongside <Toaster/> — the one
 * persistent, authenticated-only layout mount point, so guests (who never
 * render that layout) never see this at all.
 */
export default function IdleTimeoutDialog() {
    const user = usePage().props.auth?.user;
    const [loggingOut, setLoggingOut] = useState(false);

    const handleTimeout = useCallback(() => {
        router.post(
            logout.url(),
            {},
            {
                onSuccess: () => router.flushAll(),
            },
        );
    }, []);

    const { status, secondsRemaining, stayActive } = useIdleTimeout({
        idleMs: IDLE_MS,
        warningMs: WARNING_MS,
        onTimeout: handleTimeout,
    });

    if (!user) {
        return null;
    }

    return (
        <Dialog open={status === 'warning'} onOpenChange={() => {}}>
            <DialogContent
                className="[&>button:last-child]:hidden"
                onEscapeKeyDown={(e) => e.preventDefault()}
                onInteractOutside={(e) => e.preventDefault()}
            >
                <DialogTitle>Still there?</DialogTitle>
                <DialogDescription>
                    You've been inactive for a while. For your security,
                    you'll be signed out in {secondsRemaining}s unless you
                    stay signed in.
                </DialogDescription>
                <DialogFooter className="gap-2">
                    <Button
                        type="button"
                        variant="destructive"
                        disabled={loggingOut}
                        onClick={() => {
                            setLoggingOut(true);
                            handleTimeout();
                        }}
                    >
                        Log out now
                    </Button>
                    <Button
                        type="button"
                        disabled={loggingOut}
                        onClick={stayActive}
                        autoFocus
                    >
                        Stay signed in
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
