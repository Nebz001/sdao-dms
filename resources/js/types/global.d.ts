import type { Auth } from '@/types/auth';
import type { FlashToast } from '@/types/ui';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            flash: {
                toast?: FlashToast;
                warnings?: unknown[];
                message?: string;
            } | null;
            /** e.g. "1st Term" — the admin-controlled current-term setting, see App\Support\CurrentTerm. */
            currentTerm: string;
            /** e.g. "2026-2027" — see App\Support\AcademicYear. */
            academicYear: string;
            [key: string]: unknown;
        };
    }
}
