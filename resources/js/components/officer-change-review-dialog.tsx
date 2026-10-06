import { Form, router } from '@inertiajs/react';
import { useState } from 'react';
import OfficerChangeReviewController from '@/actions/App/Http/Controllers/Admin/OfficerChangeReviewController';
import ConfirmDialog from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import PageNotice from '@/components/page-notice';
import { formatDate } from '@/components/review-queue/types';
import { RequestStatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Person = { id: number; name: string };

type OfficerChangeBase = {
    id: number;
    organization: { id: number; name: string };
    /** The organization's college, or the app-wide "no college" label. */
    college: string;
    /** "Replace Secretary" or "Add Secretary". */
    change_type: string;
    /** "From Mark Dizon to Ana Reyes" or "Ella Ramos as Auditor". */
    change_detail: string;
    position_label: string;
    requester: Person;
    /** The position the requester holds in the organization, when known. */
    requester_position: string | null;
    nominee: Person;
    outgoing_officer: Person | null;
    reason: string | null;
    created_at: string;
};

export type PendingOfficerChange = OfficerChangeBase & {
    days_waiting: number;
    tier: 'fresh' | 'aging' | 'overdue';
    is_stale: boolean;
};

export type DecidedOfficerChange = OfficerChangeBase & {
    result: 'approved' | 'declined';
    decided_at: string;
    decided_by: string | null;
    decision_comment: string | null;
};

export type OfficerChangeRow = PendingOfficerChange | DecidedOfficerChange;

function isDecided(row: OfficerChangeRow): row is DecidedOfficerChange {
    return 'result' in row;
}

function Fact({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <>
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="min-w-0 font-medium break-words">{children}</dd>
        </>
    );
}

/**
 * One dialog for both sides of the page: "Review" on a waiting request (with
 * Approve and Decline, each behind its own confirmation) and "View" on a
 * decided one (read only, with the outcome). Closes itself when the row
 * leaves the list, which is what happens once a request is approved or
 * declined.
 */
export default function OfficerChangeReviewDialog({
    request,
    onClose,
}: {
    request: OfficerChangeRow | null;
    onClose: () => void;
}) {
    return (
        <Dialog open={request !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-xl">
                {request && (
                    <>
                        <DialogHeader>
                            <DialogTitle>
                                {isDecided(request) ? request.change_type : `Review: ${request.change_type}`}
                            </DialogTitle>
                            <DialogDescription>
                                {request.organization.name}, {request.college}
                            </DialogDescription>
                        </DialogHeader>

                        {!isDecided(request) && request.is_stale && (
                            <PageNotice
                                tone="warning"
                                title="The seat has changed hands since this was filed."
                            >
                                {request.outgoing_officer
                                    ? `The previous officer was ${request.outgoing_officer.name}.`
                                    : null}
                            </PageNotice>
                        )}

                        <dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                            <Fact label="Change">{request.change_detail}</Fact>
                            <Fact label="Requested by">
                                {request.requester.name}
                                {request.requester_position ? `, ${request.requester_position}` : ''}
                            </Fact>
                            <Fact label="Submitted">
                                <span className="tabular-nums">{formatDate(request.created_at)}</span>
                            </Fact>
                            {request.reason && <Fact label="Reason">{request.reason}</Fact>}
                            {isDecided(request) && (
                                <>
                                    <Fact label="Result">
                                        <RequestStatusBadge
                                            status={request.result}
                                            className="text-xs tracking-normal normal-case"
                                        />
                                    </Fact>
                                    <Fact label="Decided on">
                                        <span className="tabular-nums">{formatDate(request.decided_at)}</span>
                                    </Fact>
                                    {request.decided_by && <Fact label="Decided by">{request.decided_by}</Fact>}
                                    {request.decision_comment && (
                                        <Fact label="Comment">{request.decision_comment}</Fact>
                                    )}
                                </>
                            )}
                        </dl>

                        <DialogFooter className="gap-2">
                            {isDecided(request) ? (
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Close
                                    </Button>
                                </DialogClose>
                            ) : (
                                <PendingActions key={request.id} request={request} />
                            )}
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}

function PendingActions({ request }: { request: PendingOfficerChange }) {
    // Keyed by request in the parent, so an error never carries over to another one.
    const [approveError, setApproveError] = useState<string | null>(null);

    return (
        <>
            <ConfirmDialog
                trigger={
                    <Button type="button" variant="destructive">
                        Decline
                    </Button>
                }
                title="Decline this request?"
                description="This is permanent. The organization would need to file a brand-new request to try again."
            >
                {(close) => (
                    <Form
                        {...OfficerChangeReviewController.decline.form(request.id)}
                        options={{ preserveScroll: true }}
                        onSuccess={close}
                    >
                        {({ processing, errors }) => (
                            <>
                                <Label htmlFor={`decline-comment-${request.id}`}>Reason (optional)</Label>
                                <Textarea
                                    id={`decline-comment-${request.id}`}
                                    name="comment"
                                    placeholder="Let them know why, if you'd like…"
                                    rows={3}
                                />
                                <InputError message={errors.comment || errors.officer_change_request} />
                                <DialogFooter className="mt-4 gap-2">
                                    <DialogClose asChild>
                                        <Button type="button" variant="secondary" disabled={processing}>
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" variant="destructive" loading={processing}>
                                        Decline
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </ConfirmDialog>

            <ConfirmDialog
                trigger={<Button type="button">Approve</Button>}
                title={`Approve this change to ${request.organization.name}'s ${request.position_label}?`}
                description={
                    <>
                        {request.nominee.name} will become {request.position_label} of{' '}
                        {request.organization.name} immediately.
                    </>
                }
                notice={approveError && <PageNotice tone="destructive" urgent title={approveError} />}
                confirmLabel="Approve"
                onConfirm={({ close, stopProcessing }) => {
                    setApproveError(null);
                    router.post(
                        OfficerChangeReviewController.approve.url(request.id),
                        {},
                        {
                            preserveScroll: true,
                            onSuccess: close,
                            onError: (errors) =>
                                setApproveError(
                                    errors.officer_change_request ??
                                        'Could not approve this request. Please try again.',
                                ),
                            onFinish: stopProcessing,
                        },
                    );
                }}
            />
        </>
    );
}
