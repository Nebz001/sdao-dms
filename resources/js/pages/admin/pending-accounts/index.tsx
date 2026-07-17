import { Form, Head } from '@inertiajs/react';
import { UserRoundCheck } from 'lucide-react';
import PendingAccountController from '@/actions/App/Http/Controllers/Admin/PendingAccountController';
import QueueStatStrip from '@/components/queue-stat-strip';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Spinner } from '@/components/ui/spinner';

type PendingAccount = {
    id: number;
    name: string;
    email: string;
    created_at: string;
};

type Props = {
    accounts: PendingAccount[];
};

export default function PendingAccountsIndex({ accounts }: Props) {
    const oldest = accounts.length > 0
        ? new Date(Math.min(...accounts.map((a) => new Date(a.created_at).getTime()))).toLocaleDateString()
        : '—';

    return (
        <>
            <Head title="Pending Accounts" />

            <div className="mx-auto max-w-3xl space-y-6 p-8">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-balance">Pending Accounts</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Self-registered students awaiting SDAO review. Verified accounts can submit
                        documents and be adviser-bound as officers; Rejected accounts permanently
                        lose that ability but are never deleted.
                    </p>
                </div>

                <QueueStatStrip
                    stats={[
                        { label: 'Pending', value: String(accounts.length) },
                        { label: 'Oldest waiting', value: oldest },
                    ]}
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Awaiting Review</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {accounts.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <UserRoundCheck />
                                    </EmptyMedia>
                                    <EmptyTitle>No pending accounts</EmptyTitle>
                                    <EmptyDescription>
                                        Self-registered students awaiting review will show up here.
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <div className="divide-y">
                                {accounts.map((account) => (
                                    <div
                                        key={account.id}
                                        className="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">{account.name}</p>
                                            <p className="truncate text-sm text-muted-foreground">{account.email}</p>
                                            <p className="text-xs text-muted-foreground">
                                                Registered {new Date(account.created_at).toLocaleDateString()}
                                            </p>
                                        </div>

                                        <div className="flex shrink-0 items-center gap-2">
                                            <Form
                                                {...PendingAccountController.verify.form(account.id)}
                                                options={{ preserveScroll: true }}
                                            >
                                                {({ processing }) => (
                                                    <Button type="submit" size="sm" disabled={processing}>
                                                        {processing ? (
                                                            <>
                                                                <Spinner /> Verifying…
                                                            </>
                                                        ) : (
                                                            'Verify'
                                                        )}
                                                    </Button>
                                                )}
                                            </Form>

                                            <Dialog>
                                                <DialogTrigger asChild>
                                                    <Button type="button" size="sm" variant="destructive">
                                                        Reject
                                                    </Button>
                                                </DialogTrigger>
                                                <DialogContent>
                                                    <DialogTitle>Reject {account.name}&apos;s account?</DialogTitle>
                                                    <DialogDescription>
                                                        This is permanent. {account.name} will never be able to
                                                        submit documents or be bound as an officer. Their account
                                                        is not deleted.
                                                    </DialogDescription>

                                                    <Form
                                                        {...PendingAccountController.reject.form(account.id)}
                                                        options={{ preserveScroll: true }}
                                                    >
                                                        {({ processing }) => (
                                                            <DialogFooter className="gap-2">
                                                                <DialogClose asChild>
                                                                    <Button type="button" variant="secondary">
                                                                        Cancel
                                                                    </Button>
                                                                </DialogClose>
                                                                <Button
                                                                    type="submit"
                                                                    variant="destructive"
                                                                    disabled={processing}
                                                                >
                                                                    {processing ? (
                                                                        <>
                                                                            <Spinner /> Rejecting…
                                                                        </>
                                                                    ) : (
                                                                        'Reject Account'
                                                                    )}
                                                                </Button>
                                                            </DialogFooter>
                                                        )}
                                                    </Form>
                                                </DialogContent>
                                            </Dialog>
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

PendingAccountsIndex.layout = {
    breadcrumbs: [{ title: 'Admin' }, { title: 'Pending Accounts' }],
};
