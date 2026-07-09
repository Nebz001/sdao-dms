import { Form, Head } from '@inertiajs/react';
import PendingAccountController from '@/actions/App/Http/Controllers/Admin/PendingAccountController';
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
    return (
        <>
            <Head title="Pending Accounts" />

            <div className="mx-auto max-w-3xl space-y-6 p-8">
                <div>
                    <h1 className="text-xl font-semibold">Pending Accounts</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Self-registered students awaiting SDAO review. Verified accounts can submit
                        documents and be adviser-bound as officers; Rejected accounts permanently
                        lose that ability but are never deleted.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Awaiting Review</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {accounts.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No pending accounts right now.</p>
                        ) : (
                            <div className="divide-y">
                                {accounts.map((account) => (
                                    <div
                                        key={account.id}
                                        className="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div>
                                            <p className="font-medium">{account.name}</p>
                                            <p className="text-sm text-muted-foreground">{account.email}</p>
                                            <p className="text-xs text-muted-foreground">
                                                Registered {new Date(account.created_at).toLocaleDateString()}
                                            </p>
                                        </div>

                                        <div className="flex items-center gap-2">
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
