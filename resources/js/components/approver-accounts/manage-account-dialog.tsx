import { Form, router } from '@inertiajs/react';
import { useState } from 'react';
import AccountController from '@/actions/App/Http/Controllers/Admin/AccountController';
import ConfirmDialog from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { FlagBadge, ToneBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { splitAccountName } from '@/lib/account-name';
import type { ApproverAccount } from './types';

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

/**
 * The row's one action. Manage opens a summary of the account; Deactivate and
 * Reactivate live inside it, each behind the same confirm dialog (and the same
 * server-side authorization) the bare row buttons used before.
 */
export default function ManageAccountDialog({
    account,
    onChanged,
}: {
    account: ApproverAccount;
    onChanged?: () => void;
}) {
    const [open, setOpen] = useState(false);
    const [editingName, setEditingName] = useState(false);
    const deactivated = account.deactivated_at !== null;
    const displayName = splitAccountName(account.name).name;

    function done() {
        setOpen(false);
        onChanged?.();
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    setEditingName(false);
                }
            }}
        >
            <DialogTrigger asChild>
                <Button type="button" size="sm" variant="outline">
                    Manage<span className="sr-only"> {displayName}</span>
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Manage {displayName}</DialogTitle>
                <DialogDescription>{account.email}</DialogDescription>

                <dl className="grid grid-cols-[auto_1fr] items-baseline gap-x-4 gap-y-2 text-sm">
                    <dt className="text-muted-foreground">Name</dt>
                    <dd className="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span>{displayName}</span>
                        {!editingName && (
                            <Button
                                type="button"
                                variant="link"
                                size="sm"
                                className="h-auto p-0"
                                onClick={() => setEditingName(true)}
                            >
                                Edit name
                                <span className="sr-only"> of {displayName}</span>
                            </Button>
                        )}
                    </dd>
                    <dt className="text-muted-foreground">Status</dt>
                    <dd>
                        {deactivated ? (
                            <FlagBadge flag="deactivated" />
                        ) : (
                            <ToneBadge tone="success">Active</ToneBadge>
                        )}
                    </dd>
                    <dt className="text-muted-foreground">
                        {account.roles.length > 1 ? 'Roles' : 'Role'}
                    </dt>
                    <dd className="flex flex-col gap-0.5">
                        {account.roles.length === 0 ? (
                            <span>No role</span>
                        ) : (
                            account.roles.map((r, i) => (
                                <span key={i}>
                                    {r.label} · {r.scope}
                                </span>
                            ))
                        )}
                    </dd>
                    {deactivated && (
                        <>
                            <dt className="text-muted-foreground">
                                Deactivated
                            </dt>
                            <dd>
                                {formatDate(account.deactivated_at as string)}{' '}
                                by {account.deactivated_by}
                                {account.deactivated_reason
                                    ? `. Reason: ${account.deactivated_reason}`
                                    : ''}
                            </dd>
                        </>
                    )}
                </dl>

                {editingName && (
                    <Form
                        {...AccountController.updateName.form(account.id)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => {
                            setEditingName(false);
                            onChanged?.();
                        }}
                        className="grid gap-4 rounded-lg border p-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor={`first-name-${account.id}`}>
                                            First name
                                        </Label>
                                        <Input
                                            id={`first-name-${account.id}`}
                                            name="first_name"
                                            defaultValue={account.first_name ?? ''}
                                            required
                                            autoFocus
                                            maxLength={100}
                                            autoComplete="off"
                                            aria-invalid={errors.first_name ? true : undefined}
                                        />
                                        <InputError message={errors.first_name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor={`last-name-${account.id}`}>
                                            Last name
                                        </Label>
                                        <Input
                                            id={`last-name-${account.id}`}
                                            name="last_name"
                                            defaultValue={account.last_name ?? ''}
                                            required
                                            maxLength={100}
                                            autoComplete="off"
                                            aria-invalid={errors.last_name ? true : undefined}
                                        />
                                        <InputError message={errors.last_name} />
                                    </div>
                                </div>
                                <div className="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        disabled={processing}
                                        onClick={() => setEditingName(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" loading={processing}>
                                        Save name
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}

                {account.is_self && !deactivated && (
                    <p className="text-sm text-muted-foreground">
                        You cannot deactivate your own account.
                    </p>
                )}

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            Close
                        </Button>
                    </DialogClose>
                    {deactivated ? (
                        <ConfirmDialog
                            trigger={
                                <Button type="button" variant="outline">
                                    Reactivate
                                </Button>
                            }
                            title={`Reactivate ${account.name}?`}
                            description="They will be able to log in again with their existing password. Sessions that were ended stay ended."
                            confirmLabel="Reactivate"
                            onConfirm={({ close, stopProcessing }) => {
                                router.post(
                                    AccountController.reactivate.url(
                                        account.id,
                                    ),
                                    {},
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            close();
                                            done();
                                        },
                                        onFinish: stopProcessing,
                                    },
                                );
                            }}
                        />
                    ) : (
                        !account.is_self && (
                            <ConfirmDialog
                                trigger={
                                    <Button type="button" variant="destructive">
                                        Deactivate
                                    </Button>
                                }
                                title={`Deactivate ${account.name}?`}
                                description={
                                    <>
                                        <span className="block">
                                            {account.name} will be signed out
                                            everywhere, including the mobile
                                            app, and will not be able to log in
                                            or reset their password.
                                        </span>
                                        <span className="mt-2 block">
                                            Their history stays and keeps
                                            showing their name. You can
                                            reactivate them later.
                                        </span>
                                    </>
                                }
                            >
                                {(close) => (
                                    <Form
                                        {...AccountController.deactivate.form(
                                            account.id,
                                        )}
                                        options={{ preserveScroll: true }}
                                        onSuccess={() => {
                                            close();
                                            done();
                                        }}
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <Label
                                                    htmlFor={`deactivate-reason-${account.id}`}
                                                >
                                                    Reason (optional)
                                                </Label>
                                                <Textarea
                                                    id={`deactivate-reason-${account.id}`}
                                                    name="reason"
                                                    rows={3}
                                                    maxLength={500}
                                                    placeholder="For example, replaced as SDAO member"
                                                />
                                                <InputError
                                                    message={
                                                        errors.reason ||
                                                        errors.account
                                                    }
                                                />
                                                <DialogFooter className="mt-4 gap-2">
                                                    <DialogClose asChild>
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
                                                        loading={processing}
                                                    >
                                                        Deactivate
                                                    </Button>
                                                </DialogFooter>
                                            </>
                                        )}
                                    </Form>
                                )}
                            </ConfirmDialog>
                        )
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
