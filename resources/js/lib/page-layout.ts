import { isStudentAccount } from '@/lib/student-account';
import type { Auth } from '@/types';

export type LayoutKind = 'none' | 'auth' | 'topbar' | 'sidebar';

/**
 * Which shell a page renders in. Student accounts get the top navbar on every
 * signed-in page, with no sidebar; approvers, advisers and SDAO keep the
 * sidebar. app.tsx maps the kind to the real layout component.
 */
export function layoutKindFor(
    pageName: string,
    auth: Pick<Auth, 'user' | 'roles'> | null | undefined,
): LayoutKind {
    if (pageName === 'welcome' || pageName === 'errors/error') {
        return 'none';
    }

    if (pageName.startsWith('auth/')) {
        return 'auth';
    }

    return isStudentAccount(auth) ? 'topbar' : 'sidebar';
}
