import type { Auth } from '@/types';

/**
 * A student account is a signed-in user who holds no approver, adviser or SDAO
 * role. Only student accounts use the top-navbar layout; everyone else keeps
 * the sidebar. Reads the shared `auth.roles`, which lists role assignments (a
 * plain or not-yet-affiliated student has none, or only `student`).
 */
export function isStudentAccount(
    auth: Pick<Auth, 'user' | 'roles'> | null | undefined,
): boolean {
    return (
        Boolean(auth?.user) &&
        (auth?.roles ?? []).every((assignment) => assignment.role === 'student')
    );
}
