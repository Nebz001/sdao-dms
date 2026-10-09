import { Form } from '@inertiajs/react';
import { Check, Undo2, X } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import type { ConfirmActions } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DialogClose, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { RouteFormDefinition } from '@/wayfinder';

/** Mirrors the 1000 character cap ReviewActionRequest puts on an approval's comment. */
const APPROVE_REMARKS_MAX_LENGTH = 1000;

type ApproveConfig = {
    /** Trigger button label. Default "Approve". */
    label?: string;
    disabled?: boolean;
    /** Renders this banner INSTEAD of the trigger — e.g. adviser-unavailable or venue-conflict guards. */
    blocked?: ReactNode;
    confirmTitle: string;
    confirmDescription: ReactNode;
    /** An inline message under the description, e.g. the error from a failed approval (PageNotice destructive, urgent). */
    confirmNotice?: ReactNode;
    confirmLabel?: string;
    confirmDisabled?: boolean;
    /** `actions.remarks` is the optional remarks text; send it as `comment`. */
    onConfirm: (actions: ConfirmActions) => void;
};

type ReturnConfig = {
    formProps: RouteFormDefinition<'post'>;
    placeholder: string;
    /** The section chips for this form type — <SectionFlagFields/> or <CalendarSectionFlagFields/>. */
    flagFields: ReactNode;
};

type RejectConfig = {
    formProps: RouteFormDefinition<'post'>;
    confirmTitle: string;
    confirmDescription: string;
    placeholder?: string;
};

type Props = {
    /** The card title while choosing a decision. Default "Record your decision". */
    title?: ReactNode;
    /** e.g. the SDAO "Approved by: …" line. */
    note?: ReactNode;
    approve: ApproveConfig;
    return: ReturnConfig;
    reject: RejectConfig;
};

/**
 * The Approve / Return for revision / Reject decision panel, shared by every
 * approver review page (SDAO on the four short-chain forms; Adviser, Program
 * Chair, Dean, Principal, SDAO, and the three directors on activity
 * proposals). Per-page variation (confirm copy, the approve-blocked banner,
 * which section chips to render) comes in through props.
 *
 * Choosing Return for revision swaps this card for the return form in place:
 * the section chips, a message to the organization, "Send back for revision"
 * and Cancel. `ConfirmDialog` drives Approve programmatically via
 * `onConfirm`, while Return and Reject are Inertia `<Form>`s built from a
 * Wayfinder `.return.form()` / `.reject.form()` call. Reject's reason field
 * lives inside the dialog itself (not crossing the Radix portal via a form id)
 * so a blank-comment validation error stays visible with the dialog still
 * open, instead of rendering behind it.
 */
export default function ApprovalActionsCard({
    title = 'Record your decision',
    note,
    approve,
    return: returnConfig,
    reject,
}: Props) {
    const [returning, setReturning] = useState(false);

    if (returning) {
        return (
            <Card>
                <Form {...returnConfig.formProps} options={{ preserveScroll: true }} className="contents">
                    {({ processing, errors }) => (
                        <>
                            <CardHeader>
                                <CardTitle className="text-xl">Return for revision</CardTitle>
                                <CardDescription>
                                    The organization edits the flagged parts, then it comes straight back to you.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-5">
                                {returnConfig.flagFields}
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="return-comment">Message to the organization</Label>
                                    <Textarea
                                        id="return-comment"
                                        name="comment"
                                        placeholder={returnConfig.placeholder}
                                        rows={4}
                                        required
                                        aria-invalid={errors.comment ? true : undefined}
                                    />
                                    <InputError message={errors.comment} />
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Button type="submit" loading={processing}>
                                        Send back for revision
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                        onClick={() => setReturning(false)}
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </CardContent>
                        </>
                    )}
                </Form>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-xl">{title}</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {note}

                {approve.blocked ? (
                    approve.blocked
                ) : (
                    <ConfirmDialog
                        trigger={
                            <Button className="w-full" disabled={approve.disabled}>
                                <Check data-icon="inline-start" />
                                {approve.label ?? 'Approve'}
                            </Button>
                        }
                        title={approve.confirmTitle}
                        description={approve.confirmDescription}
                        notice={approve.confirmNotice}
                        remarks={{
                            maxLength: APPROVE_REMARKS_MAX_LENGTH,
                            placeholder: 'Add a note for the organization or the next approver…',
                        }}
                        confirmLabel={approve.confirmLabel ?? 'Confirm Approval'}
                        confirmDisabled={approve.confirmDisabled}
                        onConfirm={approve.onConfirm}
                    />
                )}

                <Button type="button" variant="outline" className="w-full" onClick={() => setReturning(true)}>
                    <Undo2 data-icon="inline-start" />
                    Return for revision
                </Button>

                <ConfirmDialog
                    trigger={
                        <Button
                            type="button"
                            variant="outline"
                            className="w-full border-destructive/40 bg-destructive/10 text-destructive-foreground hover:bg-destructive/20 hover:text-destructive-foreground"
                        >
                            <X data-icon="inline-start" />
                            Reject
                        </Button>
                    }
                    title={reject.confirmTitle}
                    description={reject.confirmDescription}
                >
                    {(close) => (
                        <Form {...reject.formProps} options={{ preserveScroll: true }} onSuccess={close}>
                            {({ processing, errors }) => (
                                <>
                                    <Textarea
                                        name="comment"
                                        placeholder={reject.placeholder ?? 'Reason for rejection…'}
                                        rows={3}
                                        required
                                    />
                                    <InputError message={errors.comment} />
                                    <DialogFooter className="mt-4 gap-2">
                                        <DialogClose asChild>
                                            <Button type="button" variant="secondary" disabled={processing}>
                                                Cancel
                                            </Button>
                                        </DialogClose>
                                        <Button type="submit" variant="destructive" loading={processing}>
                                            Reject
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </ConfirmDialog>

                <p className="text-sm text-muted-foreground">
                    Approving sends this to the next step. Rejecting ends the review and cannot be undone.
                </p>
            </CardContent>
        </Card>
    );
}
