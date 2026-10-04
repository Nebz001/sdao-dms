import { Form } from '@inertiajs/react';
import AccountController from '@/actions/App/Http/Controllers/Admin/AccountController';
import ConfirmDialog from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import PageNotice from '@/components/page-notice';
import { Button } from '@/components/ui/button';
import { DialogClose, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export type DeactivatableOfficer = {
    user_id: number;
    name: string;
    position: string;
    /** Active officers the organization would have left if this account were deactivated. */
    remaining_officers: number;
};

/**
 * The warning shown in the dialog, only when it is actually true: the org would
 * be left with no active officers, or only one. Null when two or more remain.
 */
export function remainingOfficersWarning(
    remaining: number,
    organizationName: string,
): { title: string; detail: string } | null {
    if (remaining <= 0) {
        return {
            title: `${organizationName} will have no active officers.`,
            detail: 'Nobody can submit documents for it until the adviser binds a new officer. The adviser is notified.',
        };
    }

    if (remaining === 1) {
        return {
            title: 'Only one active officer will remain.',
            detail: 'The adviser is notified and can bind another.',
        };
    }

    return null;
}

/**
 * SDAO's "Deactivate account" on an officer row of the organization page. This
 * closes the person's WHOLE account (and so their seat), which is a different
 * thing from the adviser's "End seat" in Manage Officers — the copy spells that
 * out so nobody closes an account believing they are only removing an officer.
 */
export default function DeactivateOfficerAccountDialog({
    officer,
    organizationName,
}: {
    officer: DeactivatableOfficer;
    organizationName: string;
}) {
    const warning = remainingOfficersWarning(
        officer.remaining_officers,
        organizationName,
    );

    return (
        <ConfirmDialog
            trigger={
                <Button type="button" size="sm" variant="destructive">
                    Deactivate account
                </Button>
            }
            title={`Deactivate ${officer.name}'s account?`}
            description={
                <>
                    <span className="block">
                        This closes {officer.name}&apos;s whole account, not
                        just the officer seat. They are signed out everywhere
                        and can&apos;t log in or reset their password.
                    </span>
                    <span className="mt-2 block">
                        It also ends their {officer.position} seat of{' '}
                        {organizationName}. History is kept. You can reactivate
                        the account later, but the seat is not restored — the
                        adviser would have to bind them again.
                    </span>
                    <span className="mt-2 block">
                        To remove only the officer seat and keep the account,
                        the adviser uses End seat in Manage Officers.
                    </span>
                </>
            }
        >
            {(close) => (
                <Form
                    {...AccountController.deactivate.form(officer.user_id)}
                    options={{ preserveScroll: true }}
                    onSuccess={close}
                >
                    {({ processing, errors }) => (
                        <div className="flex flex-col gap-3">
                            {warning && (
                                <PageNotice
                                    tone="warning"
                                    title={warning.title}
                                >
                                    {warning.detail}
                                </PageNotice>
                            )}
                            <div className="flex flex-col gap-2">
                                <Label
                                    htmlFor={`deactivate-officer-reason-${officer.user_id}`}
                                >
                                    Reason (optional)
                                </Label>
                                <Textarea
                                    id={`deactivate-officer-reason-${officer.user_id}`}
                                    name="reason"
                                    rows={3}
                                    maxLength={500}
                                    placeholder="For example, graduated or left the university"
                                />
                                <InputError
                                    message={errors.reason || errors.account}
                                />
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        disabled={processing}
                                    >
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    loading={processing}
                                    loadingText="Deactivating…"
                                >
                                    Deactivate account
                                </Button>
                            </DialogFooter>
                        </div>
                    )}
                </Form>
            )}
        </ConfirmDialog>
    );
}
