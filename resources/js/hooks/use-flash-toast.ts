import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

export function useFlashToast(): void {
    const flash = usePage().props.flash;
    const data = flash?.toast;

    useEffect(() => {
        if (!data) {
            return;
        }

        toast[data.type](data.message);
        // `data` is a fresh object per Inertia response (or absent/null between
        // visits), so this only fires once per actual flash — partial reloads
        // from useDocumentUpdates() don't request `flash`, so it stays stable.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data?.type, data?.message]);
}
