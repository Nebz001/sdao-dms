import { Form, Head } from '@inertiajs/react';
import { UserRoundPlus } from 'lucide-react';
import { useState } from 'react';
import JoinRequestReviewController from '@/actions/App/Http/Controllers/JoinRequestReviewController';
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

type Props = {
    queue: JoinRequestQueueItem[];
    positions: PositionOption[];
};

export default function JoinRequestsIndex({ queue, positions }: Props) {
    // 5s poll, same convention as every other review queue — a decision
    // made from another tab/reviewer shouldn't leave a stale row on screen.
    useDocumentUpdates(['queue']);

    const oldest =
        queue.length > 0
            ? new Date(
                  Math.min(...queue.map((r) => new Date(r.created_at).getTime())),
              ).toLocaleDateString()
            : '—';

    return (
        <>
            <Head title="Join Requests" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-balance">
                        Join Requests
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Students asking to join your organization. Approving
                        binds them as an officer immediately; declining is
                        permanent — they&apos;d need to file a new request.
                    </p>
                </div>

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
    const [position, setPosition] = useState(
        request.open_positions[0] ?? '',
    );
    const noOpenPositions = request.open_positions.length === 0;
    const positionLabel = positions.find((p) => p.value === position)?.label;

    return (
        <div className="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="min-w-0">
                <p className="truncate font-medium">{request.student.name}</p>
                <p className="truncate text-sm text-muted-foreground">
                    {request.student.email}
                </p>
                <p className="truncate text-sm text-muted-foreground">
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
                                    <Label htmlFor={`decline-comment-${request.id}`}>
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
