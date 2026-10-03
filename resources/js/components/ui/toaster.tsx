import { CircleCheck, CircleX, Info, TriangleAlert, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ComponentType } from 'react';
import { Button } from '@/components/ui/button';
import { useFlashToast } from '@/hooks/use-flash-toast';
import {
    dismissToast,
    runToastAction,
    useToasts,
    type ToastItem,
    type ToastKind,
} from '@/lib/toast';
import { cn } from '@/lib/utils';

const KIND_STYLES: Record<
    ToastKind,
    { icon: ComponentType<{ className?: string }>; edge: string; chip: string; bar: string }
> = {
    success: {
        icon: CircleCheck,
        edge: 'bg-success',
        chip: 'bg-success/15 text-success-foreground',
        bar: 'bg-success',
    },
    error: {
        icon: CircleX,
        edge: 'bg-destructive',
        chip: 'bg-destructive/15 text-destructive-foreground',
        bar: 'bg-destructive',
    },
    warning: {
        icon: TriangleAlert,
        edge: 'bg-warning',
        chip: 'bg-warning/15 text-warning-foreground',
        bar: 'bg-warning',
    },
    info: {
        icon: Info,
        edge: 'bg-primary-text',
        chip: 'bg-primary-text/15 text-primary-text',
        bar: 'bg-primary-text',
    },
};

function ToastCard({ toast }: { toast: ToastItem }) {
    const { id, kind, title, message, actions, duration } = toast;
    const { icon: Icon, edge, chip, bar } = KIND_STYLES[kind];

    // Hover or keyboard focus pauses the timer; the progress bar pauses with
    // it (animation-play-state) and the timeout resumes with what was left.
    const [paused, setPaused] = useState(false);
    const remaining = useRef(duration);
    const startedAt = useRef(0);

    useEffect(() => {
        if (duration === null || paused) {
            return;
        }

        startedAt.current = performance.now();

        const timer = window.setTimeout(() => dismissToast(id), remaining.current ?? 0);

        return () => {
            window.clearTimeout(timer);
            remaining.current = (remaining.current ?? 0) - (performance.now() - startedAt.current);
        };
    }, [id, duration, paused]);

    const isError = kind === 'error';

    return (
        <div
            // Errors interrupt (assertive); everything else waits its turn (polite).
            role={isError ? 'alert' : 'status'}
            aria-live={isError ? 'assertive' : 'polite'}
            aria-atomic="true"
            data-slot="toast"
            data-kind={kind}
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            onFocus={() => setPaused(true)}
            onBlur={() => setPaused(false)}
            onKeyDown={(event) => {
                if (event.key === 'Escape') {
                    dismissToast(id);
                }
            }}
            className="pointer-events-auto relative overflow-hidden rounded-xl border border-card-border bg-card text-card-foreground shadow-lg animate-in fade-in-0 slide-in-from-bottom-2 duration-200 motion-reduce:animate-none"
        >
            <span aria-hidden className={cn('absolute inset-y-0 left-0 w-[3px]', edge)} />

            <div className="flex items-start gap-3 py-[18px] pr-12 pl-5">
                <span
                    aria-hidden
                    className={cn('flex size-9 shrink-0 items-center justify-center rounded-lg [&_svg]:size-[18px]', chip)}
                >
                    <Icon />
                </span>

                <div className="flex min-w-0 flex-1 flex-col gap-1">
                    <p className="text-sm leading-5 font-semibold">{title}</p>
                    <p className="text-sm leading-5 text-muted-foreground">{message}</p>

                    {actions && actions.length > 0 && (
                        <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1">
                            {actions.map((action) => (
                                <Button
                                    key={action.label}
                                    type="button"
                                    variant="link"
                                    className="h-auto p-0"
                                    onClick={() => {
                                        dismissToast(id);
                                        runToastAction(action);
                                    }}
                                >
                                    {action.label}
                                </Button>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label={`Dismiss: ${title}`}
                className="absolute top-3 right-3 size-7 text-muted-foreground"
                onClick={() => dismissToast(id)}
            >
                <X />
            </Button>

            {duration !== null && (
                <span
                    aria-hidden
                    data-slot="toast-progress"
                    className={cn('absolute bottom-0 left-0 h-0.5 w-full origin-left motion-reduce:hidden', bar)}
                    style={{
                        animation: `toast-progress ${duration}ms linear forwards`,
                        animationPlayState: paused ? 'paused' : 'running',
                    }}
                />
            )}
        </div>
    );
}

/**
 * Mounted once per layout (app + auth). Bottom right, 20px from the edges,
 * newest on top, 12px apart, at most MAX_VISIBLE_TOASTS at a time (the rest
 * wait in the queue in lib/toast.ts). Under `sm` it spans the screen with
 * the same 20px side margins.
 */
function Toaster() {
    const toasts = useToasts();

    useFlashToast();

    return (
        <section
            aria-label="Notifications"
            className="pointer-events-none fixed right-5 bottom-5 left-5 z-[100] ml-auto flex flex-col gap-3 sm:left-auto sm:w-[420px]"
        >
            {toasts.map((toast) => (
                <ToastCard key={toast.id} toast={toast} />
            ))}
        </section>
    );
}

export { Toaster };
