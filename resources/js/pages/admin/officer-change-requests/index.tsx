import { Form, Head, router } from '@inertiajs/react';
import { UserRoundCog } from 'lucide-react';
import { useState } from 'react';
import OfficerChangeReviewController from '@/actions/App/Http/Controllers/Admin/OfficerChangeReviewController';
import ConfirmDialog from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import QueueStatStrip from '@/components/queue-stat-strip';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DialogClose, DialogFooter } from '@/components/ui/dialog';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useDocumentUpdates } from '@/hooks/use-document-updates';

type OfficerChangeQueueItem = {
    id: number;
    organization: { id: number; name: string };
    position_label: string;
    requester: { id: number; name: string };
    nominee: { id: number; name: string };
    outgoing_officer: { id: number; name: string } | null;
    reason: string | null;
    created_at: string;
    is_stale: boolean;
};

type Props = {
    requests: OfficerChangeQueueItem[];
};

export default function OfficerChangeRequestsIndex({ requests }: Props) {
    // 5s poll, same convention as every other review queue.
    useDocumentUpdates(['requests']);

    // Scoped to the request that failed, not a page-wide flag — mirrors
    // admin/pending-accounts/index.tsx's rejectError.
    const [approveError, setApproveError] = useState<{
        requestId: number;
        message: string;
    } | null>(null);

    const oldest =
        requests.length > 0
            ? new Date(
                  Math.min(
                      ...requests.map((r) => new Date(r.created_at).getTime()),
                  ),
              ).toLocaleDateString()
            : '—';

    return (
        <>
            <Head title="Officer Change Requests" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-balance">
                        Officer Change Requests
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        A current president or secretary has requested a
                        change to their organization&apos;s roster. Approving
                        performs the change immediately; declining is
                        permanent — they&apos;d need to file a new request.
                    </p>
                </div>

                <QueueStatStrip
                    stats={[
                        {
                            label: 'Pending',
                            value: String(requests.length),
                            count: requests.length,
                        },
                        { label: 'Oldest waiting', value: oldest },
                    ]}
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Awaiting Review
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {requests.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <UserRoundCog />
                                    </EmptyMedia>
                                    <EmptyTitle>No pending requests</EmptyTitle>
                                    <EmptyDescription>
                                        Officer change requests will show up
                                        here.
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <div className="divide-y">
                                {requests.map((request) => (
                                    <div
                                        key={request.id}
                                        className="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {request.organization.name}
                                                {' — '}
                                                {request.position_label}
                                            </p>
                                            <p className="truncate text-sm text-muted-foreground">
                                                Requested by{' '}
                                                {request.requester.name},
                                                nominating{' '}
                                                <span className="font-medium text-foreground">
                                                    {request.nominee.name}
                                                </span>
                                            </p>
                                            {request.reason && (
                                                <p className="truncate text-sm text-muted-foreground">
                                                    &ldquo;{request.reason}
                                                    &rdquo;
                                                </p>
                                            )}
                                            {request.is_stale && (
                                                <p className="text-xs text-amber-600 dark:text-amber-500">
                                                    {request.outgoing_officer
                                                        ? `The seat has changed hands since this was filed (was ${request.outgoing_officer.name}).`
                                                        : 'The seat has changed hands since this was filed.'}
                                                </p>
                                            )}
                                            {approveError?.requestId ===
                                                request.id && (
                                                <p className="text-xs text-destructive">
                                                    {approveError.message}
                                                </p>
                                            )}
                                            <p className="text-xs text-muted-foreground">
                                                Requested{' '}
                                                {new Date(
                                                    request.created_at,
                                                ).toLocaleDateString()}
                                            </p>
                                        </div>

                                        <div className="flex shrink-0 items-center gap-2">
                                            <ConfirmDialog
                                                trigger={
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                    >
                                                        Approve
                                                    </Button>
                                                }
                                                title={`Approve this change to ${request.organization.name}'s ${request.position_label}?`}
                                                description={
                                                    <>
                                                        {request.nominee.name}{' '}
                                                        will become{' '}
                                                        {request.position_label}{' '}
                                                        of{' '}
                                                        {request.organization.name}{' '}
                                                        immediately.
                                                    </>
                                                }
                                                confirmLabel="Approve"
                                                onConfirm={({
                                                    close,
                                                    stopProcessing,
                                                }) => {
                                                    setApproveError(null);
                                                    router.post(
                                                        OfficerChangeReviewController.approve.url(
                                                            request.id,
                                                        ),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                            onSuccess: close,
                                                            onError: (errors) =>
                                                                setApproveError({
                                                                    requestId:
                                                                        request.id,
                                                                    message:
                                                                        errors.officer_change_request ??
                                                                        'Could not approve this request. Please try again.',
                                                                }),
                                                            onFinish:
                                                                stopProcessing,
                                                        },
                                                    );
                                                }}
                                            />

                                            <ConfirmDialog
                                                trigger={
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="destructive"
                                                    >
                                                        Decline
                                                    </Button>
                                                }
                                                title={`Decline this request?`}
                                                description="This is permanent — the organization would need to file a brand-new request to try again."
                                            >
                                                {(close) => (
                                                    <Form
                                                        {...OfficerChangeReviewController.decline.form(
                                                            request.id,
                                                        )}
                                                        options={{
                                                            preserveScroll: true,
                                                        }}
                                                        onSuccess={close}
                                                    >
                                                        {({
                                                            processing,
                                                            errors,
                                                        }) => (
                                                            <>
                                                                <Label
                                                                    htmlFor={`decline-comment-${request.id}`}
                                                                >
                                                                    Reason
                                                                    (optional)
                                                                </Label>
                                                                <Textarea
                                                                    id={`decline-comment-${request.id}`}
                                                                    name="comment"
                                                                    placeholder="Let them know why, if you'd like…"
                                                                    rows={3}
                                                                />
                                                                <InputError
                                                                    message={
                                                                        errors.comment ||
                                                                        errors.officer_change_request
                                                                    }
                                                                />
                                                                <DialogFooter className="mt-4 gap-2">
                                                                    <DialogClose
                                                                        asChild
                                                                    >
                                                                        <Button
                                                                            type="button"
                                                                            variant="secondary"
                                                                            disabled={
                                                                                processing
                                                                            }
                                                                        >
                                                                            Cancel
                                                                        </Button>
                                                                    </DialogClose>
                                                                    <Button
                                                                        type="submit"
                                                                        variant="destructive"
                                                                        loading={
                                                                            processing
                                                                        }
                                                                    >
                                                                        Decline
                                                                    </Button>
                                                                </DialogFooter>
                                                            </>
                                                        )}
                                                    </Form>
                                                )}
                                            </ConfirmDialog>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

OfficerChangeRequestsIndex.layout = {
    breadcrumbs: [{ title: 'Admin' }, { title: 'Officer Change Requests' }],
};
