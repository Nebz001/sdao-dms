import { usePage } from '@inertiajs/react';
import { Hourglass, UserRoundX } from 'lucide-react';
import type { ReactNode } from 'react';
import BlockedState from '@/components/blocked-state';
import { isStudentAccount } from '@/lib/student-account';
import { dashboard } from '@/routes';
import * as joinOrganization from '@/routes/organizations/join';
import * as registrations from '@/routes/registrations';

type Width = '2xl' | '3xl';

/** The account has not been verified by SDAO yet. */
export function PendingVerificationBlocked({
    extra,
    showHome = true,
    width,
}: {
    /** One more short sentence, e.g. about a join request that is waiting on this. */
    extra?: ReactNode;
    /** A quiet "Back to Home"; off where the page already is Home. */
    showHome?: boolean;
    width?: Width;
}) {
    return (
        <BlockedState
            icon={Hourglass}
            title="Waiting for SDAO to verify your account"
            body={
                <>
                    Once SDAO verifies it, you can join an organization as an officer and submit documents. There’s
                    nothing else to do right now.
                    {extra && <> {extra}</>}
                </>
            }
            secondary={showHome ? { label: 'Back to Home', href: dashboard(), quiet: true } : undefined}
            width={width}
        />
    );
}

/**
 * A page for the officers of an organization, opened by someone who is not one.
 * An unverified account is told that instead (it is the real reason). A
 * verified student is pointed at the two ways to get an organization, both of
 * which they can open; anyone else (an approver on a student-only page) gets
 * the explanation alone.
 */
export function NotAnOfficerBlocked({ action, width }: { action: string; width?: Width }) {
    const { auth } = usePage().props;

    if (auth.user.account_status === 'unverified') {
        return <PendingVerificationBlocked width={width} />;
    }

    const isStudent = isStudentAccount(auth);

    return (
        <BlockedState
            icon={UserRoundX}
            title="You’re not an officer yet"
            body={
                <>
                    You need to be an active president or secretary of an organization to {action}. Ask your adviser to
                    add you, or join an organization first.
                </>
            }
            secondary={
                isStudent && auth.canProposeOrganization
                    ? { label: 'Register a new organization', href: registrations.create() }
                    : undefined
            }
            primary={isStudent ? { label: 'Join an organization', href: joinOrganization.create() } : { label: 'Back to Home', href: dashboard() }}
            width={width}
        />
    );
}
