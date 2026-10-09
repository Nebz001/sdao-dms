import { Form, Link } from '@inertiajs/react';
import { UserRoundCog, UserRoundPlus } from 'lucide-react';
import { useState } from 'react';
import ApproverController from '@/actions/App/Http/Controllers/Admin/ApproverController';
import OrganizationAdviserController from '@/actions/App/Http/Controllers/Admin/OrganizationAdviserController';
import ConfirmDialog from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import PageNotice from '@/components/page-notice';
import DataTable from '@/components/review-queue/data-table';
import type { DataColumn } from '@/components/review-queue/data-table';
import { SectionCard } from '@/components/review-queue/queue-tables';
import { formatDate } from '@/components/review-queue/types';
import TagBadge from '@/components/tag-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type AdviserTermRow = {
    id: number;
    name: string;
    started_at: string;
    ended_at: string | null;
    assigned_by: string | null;
    ended_by: string | null;
    outcome: string | null;
};

export type AdviserData = {
    current: {
        id: number;
        name: string;
        email: string;
        since: string | null;
        assigned_by: string | null;
    } | null;
    history: AdviserTermRow[];
    pool: { id: number; name: string; email: string }[];
};

const DASH = '—';

const HISTORY_COLUMNS: DataColumn<AdviserTermRow>[] = [
    { key: 'name', header: 'Adviser', slot: 'title', cell: (t) => t.name },
    {
        key: 'outcome',
        header: 'How it ended',
        slot: 'badge',
        cell: (t) => (t.outcome ? <TagBadge>{t.outcome}</TagBadge> : DASH),
    },
    {
        key: 'term',
        header: 'Term',
        className: 'tabular-nums',
        cell: (t) =>
            `${formatDate(t.started_at)} – ${t.ended_at ? formatDate(t.ended_at) : 'now'}`,
    },
    {
        key: 'assigned_by',
        header: 'Assigned by',
        cell: (t) => t.assigned_by ?? DASH,
    },
    {
        key: 'ended_by',
        header: 'Ended by',
        cell: (t) => t.ended_by ?? DASH,
    },
];

/**
 * SDAO's adviser panel on the organization page: who the adviser is, the dated
 * history of past advisers, and the way to change it — pick an unassigned
 * adviser from the pool (here), or create a new adviser account (the approver
 * form). Both end in the same server action, which closes the old term, opens
 * the new one and tells the people involved.
 */
export default function OrganizationAdviserCard({
    organization,
    data,
}: {
    organization: { id: number; name: string };
    data: AdviserData;
}) {
    const { current, history, pool } = data;

    return (
        <SectionCard
            title="Adviser"
            aside={
                current
                    ? `Reviews documents at the adviser step and manages officers`
                    : undefined
            }
        >
            <div className="flex flex-col gap-6">
                {current ? (
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex min-w-0 flex-col gap-0.5">
                            <span className="font-medium">{current.name}</span>
                            <span className="text-sm break-all text-muted-foreground">
                                {current.email}
                            </span>
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {current.since
                                    ? `Adviser since ${formatDate(current.since)}`
                                    : 'Adviser since an unrecorded date'}
                                {current.assigned_by
                                    ? ` · assigned by ${current.assigned_by}`
                                    : ''}
                            </span>
                        </div>
                        <ChangeAdviserDialog
                            organization={organization}
                            current={current}
                            pool={pool}
                        />
                    </div>
                ) : (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <UserRoundPlus />
                            </EmptyMedia>
                            <EmptyTitle>No adviser assigned</EmptyTitle>
                            <EmptyDescription>
                                Documents waiting at the adviser step have
                                nobody to review them. Assign an adviser to this
                                organization.
                            </EmptyDescription>
                        </EmptyHeader>
                        <ChangeAdviserDialog
                            organization={organization}
                            current={null}
                            pool={pool}
                        />
                    </Empty>
                )}

                {history.length > 0 && (
                    <div className="flex flex-col gap-2">
                        <h3 className="text-sm font-medium">Past advisers</h3>
                        <DataTable
                            rows={history}
                            columns={HISTORY_COLUMNS}
                            rowKey={(t) => t.id}
                        />
                    </div>
                )}
            </div>
        </SectionCard>
    );
}

function ChangeAdviserDialog({
    organization,
    current,
    pool,
}: {
    organization: { id: number; name: string };
    current: AdviserData['current'];
    pool: AdviserData['pool'];
}) {
    const [adviserId, setAdviserId] = useState('');
    const [deactivateOutgoing, setDeactivateOutgoing] = useState(false);

    const label = current ? 'Change adviser' : 'Assign adviser';
    const createUrl = ApproverController.create.url({
        query: { role: 'adviser', organization_id: organization.id },
    });

    return (
        <ConfirmDialog
            trigger={
                <Button type="button" size="sm" variant="outline">
                    <UserRoundCog data-icon="inline-start" />
                    {label}
                </Button>
            }
            title={`${label} for ${organization.name}`}
            description={
                current
                    ? `Choose an unassigned adviser to take over from ${current.name}. This takes effect immediately.`
                    : 'Choose an unassigned adviser. This takes effect immediately.'
            }
        >
            {(close) =>
                pool.length === 0 ? (
                    <div className="flex flex-col gap-3">
                        <PageNotice tone="info" title="No unassigned advisers.">
                            Every adviser account is already assigned, or
                            deactivated. Create a new adviser account for this
                            organization instead.
                        </PageNotice>
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button asChild>
                                <Link href={createUrl}>
                                    Create adviser account
                                </Link>
                            </Button>
                        </DialogFooter>
                    </div>
                ) : (
                    <Form
                        {...OrganizationAdviserController.store.form(
                            organization.id,
                        )}
                        options={{ preserveScroll: true }}
                        onSuccess={() => {
                            setAdviserId('');
                            setDeactivateOutgoing(false);
                            close();
                        }}
                    >
                        {({ processing, errors }) => (
                            <div className="flex flex-col gap-4">
                                <input
                                    type="hidden"
                                    name="adviser_id"
                                    value={adviserId}
                                />
                                <input
                                    type="hidden"
                                    name="current_adviser_id"
                                    value={current?.id ?? ''}
                                />
                                <input
                                    type="hidden"
                                    name="outgoing_adviser"
                                    value={
                                        deactivateOutgoing
                                            ? 'deactivated'
                                            : 'returned_to_pool'
                                    }
                                />

                                <div className="grid gap-2">
                                    <Label htmlFor="pool-adviser">
                                        Unassigned adviser
                                    </Label>
                                    <Select
                                        value={adviserId}
                                        onValueChange={setAdviserId}
                                    >
                                        <SelectTrigger
                                            id="pool-adviser"
                                            className="w-full"
                                            aria-invalid={
                                                errors.adviser_id ||
                                                errors.adviser
                                                    ? true
                                                    : undefined
                                            }
                                        >
                                            <SelectValue placeholder="Select an adviser…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {pool.map((a) => (
                                                    <SelectItem
                                                        key={a.id}
                                                        value={String(a.id)}
                                                    >
                                                        {a.name} ({a.email})
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                    <p className="text-sm text-muted-foreground">
                                        Only active advisers with no
                                        organization are listed.{' '}
                                        <Link
                                            href={createUrl}
                                            className="underline underline-offset-4"
                                        >
                                            Create a new adviser account
                                        </Link>{' '}
                                        instead.
                                    </p>
                                    <InputError
                                        message={
                                            errors.adviser_id || errors.adviser
                                        }
                                    />
                                </div>

                                {current && (
                                    <div className="flex flex-col gap-3">
                                        <PageNotice
                                            tone="warning"
                                            title={`${current.name} stops being the adviser of ${organization.name}.`}
                                        >
                                            Documents waiting at the adviser
                                            step and pending join requests go to
                                            the new adviser. {current.name} is
                                            told their role ended. The
                                            organization&apos;s officers are
                                            told who the new adviser is.
                                        </PageNotice>
                                        <div className="flex items-start gap-3">
                                            <Checkbox
                                                id="deactivate-outgoing-adviser"
                                                checked={deactivateOutgoing}
                                                onCheckedChange={(checked) =>
                                                    setDeactivateOutgoing(
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            <div className="grid gap-1">
                                                <Label htmlFor="deactivate-outgoing-adviser">
                                                    Also deactivate{' '}
                                                    {current.name}&apos;s
                                                    account
                                                </Label>
                                                <p className="text-sm text-muted-foreground">
                                                    {deactivateOutgoing
                                                        ? 'They are signed out everywhere and can no longer log in. You can reactivate them later.'
                                                        : 'Left unchecked, they return to the pool of unassigned advisers and keep their account.'}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                )}

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
                                        disabled={adviserId === ''}
                                        loading={processing}
                                        loadingText="Assigning…"
                                    >
                                        {label}
                                    </Button>
                                </DialogFooter>
                            </div>
                        )}
                    </Form>
                )
            }
        </ConfirmDialog>
    );
}
