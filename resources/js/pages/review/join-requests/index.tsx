import { Form, Head } from '@inertiajs/react';
import { UserRoundPlus } from 'lucide-react';
import { useState } from 'react';
import JoinRequestReviewController from '@/actions/App/Http/Controllers/JoinRequestReviewController';
import AccountName from '@/components/account-name';
import ConfirmDialog from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import QueueStatStrip from '@/components/queue-stat-strip';
import { RequestStatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { DialogClose, DialogFooter } from '@/components/ui/dialog';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useDocumentUpdates } from '@/hooks/use-document-updates';

type PositionOption = { value: string; label: string };

type JoinRequestQueueItem = {
    id: number;
    student: { id: number; name: string; email: string };
    organization: { id: number; name: string };
    created_at: string;
    open_positions: string[];
};

type ClosedJoinRequest = {
    id: number;
    student: { id: number; name: string; email: string };
    organization: { id: number; name: string };
    closed_at: string;
    reason: string | null;
};

type Props = {
    queue: JoinRequestQueueItem[];
    closed: ClosedJoinRequest[];
    positions: PositionOption[];
};

export default function JoinRequestsIndex({ queue, closed, positions }: Props) {
    // 5s poll, same convention as every other review queue — a decision
    // made from another tab/reviewer shouldn't leave a stale row on screen.
    useDocumentUpdates(['queue', 'closed']);

    const oldest =
        queue.length > 0
            ? new Date(
                  Math.min(
                      ...queue.map((r) => new Date(r.created_at).getTime()),
                  ),
              ).toLocaleDateString()
            : '—';

    return (
        <>
            <Head title="Join Requests" />

            <div className="space-y-6">
                <PageHeader
                    title="Join Requests"
                    subtitle="Students asking to join your organization. Approving binds them as an officer immediately; declining is permanent — they'd need to file a new request."
                />

                <QueueStatStrip
                    stats={[
                        {
                            label: 'Pending',
                            value: String(queue.length),
                            count: queue.length,
                        },
                        { label: 'Oldest waiting', value: oldest },
                    ]}
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Awaiting Your Decision
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {queue.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <UserRoundPlus />
                                    </EmptyMedia>
                                    <EmptyTitle>No pending requests</EmptyTitle>
                                    <EmptyDescription>
                                        Students asking to join will show up
                                        here.
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <div className="divide-y">
                                {queue.map((request) => (
                                    <JoinRequestRow
                                        key={request.id}
                                        request={request}
                                        positions={positions}
                                    />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {closed.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Closed automatically
                            </CardTitle>
                            <CardDescription>
                                These no longer need a decision: the student
                                became an officer another way. Shown for 14
                                days.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="divide-y">
                                {closed.map((request) => (
                                    <div
                                        key={request.id}
                                        className="flex flex-col gap-1 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                                    >
                                        <div className="min-w-0">
                                            <AccountName
                                                name={request.student.name}
                                                nameClassName="font-medium"
                                            />
                                            <p className="text-sm text-muted-foreground max-sm:break-words">
                                                Asked to join{' '}
                                                <span className="font-medium text-foreground">
                                                    {request.organization.name}
                                                </span>
                                            </p>
                                            {request.reason && (
                                                <p className="text-xs text-muted-foreground">
                                                    {request.reason}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex shrink-0 items-center gap-2 text-xs text-muted-foreground">
                                            <RequestStatusBadge status="withdrawn" />
                                            {new Date(
                                                request.closed_at,
                                            ).toLocaleDateString()}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

function JoinRequestRow({
    request,
    positions,
}: {
    request: JoinRequestQueueItem;
    positions: PositionOption[];
}) {
    const [position, setPosition] = useState(request.open_positions[0] ?? '');
    const noOpenPositions = request.open_positions.length === 0;
    const positionLabel = positions.find((p) => p.value === position)?.label;

    return (
        <div className="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="min-w-0">
                <AccountName
                    name={request.student.name}
                    nameClassName="font-medium"
                />
                <p className="text-sm text-muted-foreground max-sm:break-words sm:truncate">
                    {request.student.email}
                </p>
                <p className="text-sm text-muted-foreground max-sm:break-words sm:truncate">
                    Wants to join{' '}
                    <span className="font-medium text-foreground">
                        {request.organization.name}
                    </span>
                </p>
                <p className="text-xs text-muted-foreground">
                    Requested{' '}
                    {new Date(request.created_at).toLocaleDateString()}
                </p>
            </div>

            <div className="flex shrink-0 flex-col items-stretch gap-2 sm:flex-row sm:items-center">
                {noOpenPositions ? (
                    <p className="text-xs text-muted-foreground sm:max-w-44">
                        Both officer positions are filled — manage officers
                        first, or decline.
                    </p>
                ) : (
                    <Select value={position} onValueChange={setPosition}>
                        <SelectTrigger size="sm" className="w-36">
                            <SelectValue placeholder="Position…" />
                        </SelectTrigger>
                        <SelectContent>
                            {positions
                                .filter((p) =>
                                    request.open_positions.includes(p.value),
                                )
                                .map((p) => (
                                    <SelectItem key={p.value} value={p.value}>
                                        {p.label}
                                    </SelectItem>
                                ))}
                        </SelectContent>
                    </Select>
                )}

                <ConfirmDialog
                    trigger={
                        <Button
                            type="button"
                            size="sm"
                            disabled={noOpenPositions}
                        >
                            Approve
                        </Button>
                    }
                    title={`Approve ${request.student.name}'s request?`}
                    description={
                        <>
                            {request.student.name} will be bound as{' '}
                            {positionLabel ?? 'an officer'} of{' '}
                            {request.organization.name} immediately.
                        </>
                    }
                >
                    {(close) => (
                        <Form
                            {...JoinRequestReviewController.approve.form(
                                request.id,
                            )}
                            options={{ preserveScroll: true }}
                            onSuccess={close}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="position"
                                        value={position}
                                    />
                                    <InputError
                                        message={
                                            errors.position ||
                                            errors.join_request
                                        }
                                    />
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
                                            loading={processing}
                                        >
                                            Approve
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </ConfirmDialog>

                <ConfirmDialog
                    trigger={
                        <Button type="button" size="sm" variant="destructive">
                            Decline
                        </Button>
                    }
                    title={`Decline ${request.student.name}'s request?`}
                    description="This is permanent — they'd need to file a brand-new request to try again."
                >
                    {(close) => (
                        <Form
                            {...JoinRequestReviewController.decline.form(
                                request.id,
                            )}
                            options={{ preserveScroll: true }}
                            onSuccess={close}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Label
                                        htmlFor={`decline-comment-${request.id}`}
                                    >
                                        Reason (optional)
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
                                            errors.join_request
                                        }
                                    />
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
    );
}

JoinRequestsIndex.layout = {
    breadcrumbs: [{ title: 'Review' }, { title: 'Join Requests' }],
};
