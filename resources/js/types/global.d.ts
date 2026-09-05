import type { Auth } from '@/types/auth';
import type { NotificationsProp } from '@/types/notifications';
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
            /** The admin-controlled current academic period, see App\Support\CurrentPeriod. */
            currentPeriod: {
                academic_year: string;
                term: string;
                term_label: string;
                label: string;
                is_renewal_season: boolean;
            };
            /** null for guests. See HandleInertiaRequests::share() — a closure prop, only queried when requested. */
            notifications: NotificationsProp;
            [key: string]: unknown;
        };
    }
}
