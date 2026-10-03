import { router } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';

export type ToastKind = 'success' | 'error' | 'warning' | 'info';

export type ToastAction = {
    label: string;
    /** Inertia visit target. `post` actions (Undo) hit a real revert endpoint. */
    href?: string;
    method?: 'get' | 'post';
    onClick?: () => void;
};

export type ToastInput = {
    title: string;
    message: string;
    actions?: ToastAction[];
};

export type ToastItem = ToastInput & {
    id: number;
    kind: ToastKind;
    /** ms until auto close; null = stays until dismissed. */
    duration: number | null;
};

/** Cards on screen at once; anything beyond waits in a queue. */
export const MAX_VISIBLE_TOASTS = 3;

const AUTO_CLOSE_MS = 5000;

let nextId = 1;
let visible: ToastItem[] = [];
const queued: ToastItem[] = [];
const listeners = new Set<() => void>();

function emit(): void {
    listeners.forEach((listener) => listener());
}

function drain(): void {
    while (visible.length < MAX_VISIBLE_TOASTS && queued.length > 0) {
        // Newest first: the array order IS the on-screen order (top → bottom).
        visible = [queued.shift() as ToastItem, ...visible];
    }
}

function push(kind: ToastKind, input: ToastInput): number {
    const item: ToastItem = {
        ...input,
        id: nextId++,
        kind,
        // Errors and warnings must be read, so they wait for the person.
        duration: kind === 'success' || kind === 'info' ? AUTO_CLOSE_MS : null,
    };

    queued.push(item);
    drain();
    emit();

    return item.id;
}

export function dismissToast(id: number): void {
    const before = visible.length;

    visible = visible.filter((item) => item.id !== id);

    const queuedIndex = queued.findIndex((item) => item.id === id);

    if (queuedIndex !== -1) {
        queued.splice(queuedIndex, 1);
    }

    if (visible.length !== before || queuedIndex !== -1) {
        drain();
        emit();
    }
}

export const notify = {
    success: (input: ToastInput) => push('success', input),
    error: (input: ToastInput) => push('error', input),
    warning: (input: ToastInput) => push('warning', input),
    info: (input: ToastInput) => push('info', input),
};

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

export function getToasts(): ToastItem[] {
    return visible;
}

export function useToasts(): ToastItem[] {
    return useSyncExternalStore(subscribe, getToasts, getToasts);
}

/**
 * Runs a toast action. An Undo (`post`) goes through Inertia like any other
 * write, so the revert's own flash toast confirms it; a failed revert
 * (e.g. the record changed in the meantime) surfaces as an error toast
 * instead of a silent no-op.
 */
export function runToastAction(action: ToastAction): void {
    if (action.onClick) {
        action.onClick();

        return;
    }

    if (!action.href) {
        return;
    }

    if (action.method === 'post') {
        router.post(
            action.href,
            {},
            {
                preserveScroll: true,
                onError: (errors) => {
                    notify.error({
                        title: `Couldn't ${action.label.toLowerCase()}`,
                        message:
                            Object.values(errors)[0] ?? 'Please try again.',
                    });
                },
            },
        );

        return;
    }

    router.visit(action.href);
}
