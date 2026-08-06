import { Form } from '@inertiajs/react';
import { CircleCheck, CircleX, Undo2 } from 'lucide-react';
import type { ReactNode } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import type { ConfirmActions } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DialogClose, DialogFooter } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import type { RouteFormDefinition } from '@/wayfinder';

type ApproveConfig = {
    /** Trigger button label. Default "Approve" — activity-proposals passes "Already Approved" once `hasApproved`. */
    label?: string;
    disabled?: boolean;
    /** Renders this banner INSTEAD of the trigger — e.g. adviser-unavailable or venue-conflict guards. */
    blocked?: ReactNode;
    confirmTitle: string;
    confirmDescription: ReactNode;
    confirmLabel?: string;
    confirmDisabled?: boolean;
    onConfirm: (actions: ConfirmActions) => void;
};

type ReturnConfig = {
    formProps: RouteFormDefinition<'post'>;
    placeholder: string;
    /** The section checklist for this form type — <SectionFlagFields/> or <CalendarSectionFlagFields/>. */
    flagFields: ReactNode;
};

type RejectConfig = {
    formProps: RouteFormDefinition<'post'>;
    confirmTitle: string;
    confirmDescription: string;
    placeholder?: string;
};

type Props = {
    /** "Review Actions", or (activity-proposals) a role name + optional quorum badge. */
    title: ReactNode;
    /** e.g. the SDAO "Approved by: …" line. Omitted by pages that show quorum in their own separate Card. */
    note?: ReactNode;
    approve: ApproveConfig;
    return: ReturnConfig;
    reject: RejectConfig;
};

/**
 * The Approve / Return for Revision / Reject decision panel, shared by every
 * approver review page (SDAO on the four short-chain forms; Adviser, Program
 * Chair, Dean, Principal, SDAO, and the three directors on activity
 * proposals). Previously five separately hand-rolled copies — four had
 * drifted into near-identical alignment, and activity-proposals' copy had
 * diverged into a `flex flex-wrap` row that crammed all three decisions onto
 * one baseline. This is now the one implementation; per-page variation
 * (titles, confirm copy, the approve-blocked banner, which checklist to
 * render) comes in through props instead of a second hand-rolled copy.
 *
 * Mechanically identical to what registrations/renewals/reports/
 * activity-calendars already did by hand: `ConfirmDialog` drives Approve
 * programmatically via `onConfirm`, while Return and Reject are Inertia
 * `<Form>`s built from a Wayfinder `.return.form()` / `.reject.form()` call.
 * Reject's reason field lives inside the dialog itself (not crossing the
 * Radix portal via a form id) so a blank-comment validation error stays
 * visible with the dialog still open, instead of rendering behind it.
 */
export default function ApprovalActionsCard({
    title,
    note,
    approve,
    return: returnConfig,
    reject,
}: Props) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{title}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {note}

                {/* Approve */}
                <div className="space-y-2">
                    <p className="flex items-center gap-1.5 text-sm font-medium">
                        <CircleCheck className="size-4 text-success" />
                        Approve
                    </p>
                    {approve.blocked ? (
                        approve.blocked
                    ) : (
                        <ConfirmDialog
                            trigger={
                                <Button
                                    className="w-full sm:w-auto"
                                    disabled={approve.disabled}
                                >
                                    {approve.label ?? 'Approve'}
                                </Button>
                            }
                            title={approve.confirmTitle}
                            description={approve.confirmDescription}
                            confirmLabel={
                                approve.confirmLabel ?? 'Confirm Approval'
                            }
                            confirmDisabled={approve.confirmDisabled}
                            onConfirm={approve.onConfirm}
                        />
                    )}
                </div>

                {/* Return for revision */}
                <Form
                    {...returnConfig.formProps}
                    className="space-y-2 border-t pt-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <p className="flex items-center gap-1.5 text-sm font-medium">
                                <Undo2 className="size-4 text-warning" />
                                Return for Revision
                            </p>
                            <Textarea
                                name="comment"
                                placeholder={returnConfig.placeholder}
                                rows={3}
                                required
                            />
                            <InputError message={errors.comment} />
                            {returnConfig.flagFields}
                            <Button
                                type="submit"
                                variant="outline"
                                className="w-full sm:w-auto"
                                disabled={processing}
                            >
                                Return for Revision
                            </Button>
                        </>
                    )}
                </Form>

                {/* Reject */}
                <div className="space-y-2 border-t pt-4">
                    <p className="flex items-center gap-1.5 text-sm font-medium text-destructive">
                        <CircleX className="size-4" />
                        Reject (permanent)
                    </p>
                    <ConfirmDialog
                        trigger={
                            <Button
                                type="button"
                                variant="destructive"
                                className="w-full sm:w-auto"
                            >
                                Reject
                            </Button>
                        }
                        title={reject.confirmTitle}
                        description={reject.confirmDescription}
                    >
                        {(close) => (
                            <Form
                                {...reject.formProps}
                                options={{ preserveScroll: true }}
                                onSuccess={close}
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <Textarea
                                            name="comment"
                                            placeholder={
                                                reject.placeholder ??
                                                'Reason for rejection…'
                                            }
                                            rows={3}
                                            required
                                        />
                                        <InputError message={errors.comment} />
                                        <DialogFooter className="mt-4 gap-2">
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
                                            >
                                                Reject
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        )}
                    </ConfirmDialog>
                </div>
            </CardContent>
        </Card>
    );
}
