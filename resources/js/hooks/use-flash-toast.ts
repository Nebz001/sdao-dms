import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';

export function useFlashToast(): void {
    const flash = usePage().props.flash;
    const data = flash?.toast;

    // Guards against React StrictMode (app.tsx sets `strictMode: true`)
    // double-invoking an effect on a component's FIRST mount in dev — mount,
    // synthetic cleanup, mount again — which fires this effect twice back to
    // back with identical deps. Normally invisible here because the layout
    // (and this hook) is already mounted by the time most flashes arrive; the
    // one path where a layout mounts for the very first time WITH flash data
    // already present is login-via-registration landing on a fresh
    // AppLayout, which is exactly where this doubled a toast. Reset to null
    // whenever there's no flash so a genuinely new later toast with the same
    // text still shows.
    const shownKeyRef = useRef<string | null>(null);

    useEffect(() => {
        if (!data) {
            shownKeyRef.current = null;

            return;
        }

        const key = `${data.type}:${data.message}`;

        if (shownKeyRef.current === key) {
            return;
        }

        shownKeyRef.current = key;
        toast[data.type](data.message);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data?.type, data?.message]);
}
