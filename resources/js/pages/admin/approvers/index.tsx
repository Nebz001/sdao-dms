import { Form, Head, Link, router } from '@inertiajs/react';
import { SearchIcon, ShieldCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import AccountController from '@/actions/App/Http/Controllers/Admin/AccountController';
import ConfirmDialog from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DialogClose, DialogFooter } from '@/components/ui/dialog';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import * as approvers from '@/routes/admin/approvers';

type RoleEntry = { role: string; label: string; scope: string };

type AccountEntry = {
    id: number;
    name: string;
    email: string;
    is_self: boolean;
    deactivated_at: string | null;
    deactivated_reason: string | null;
    deactivated_by: string | null;
    roles: RoleEntry[];
};

type Props = {
    approvers: AccountEntry[];
};

const SEARCH_DEBOUNCE_MS = 400;

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function AccountRow({ account, onChanged }: { account: AccountEntry; onChanged?: () => void }) {
    const deactivated = account.deactivated_at !== null;

    return (
        <div className="flex flex-col gap-3 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
            <div className={deactivated ? 'min-w-0 opacity-70' : 'min-w-0'}>
                <div className="flex flex-wrap items-center gap-2">
                    <p className="truncate font-medium">{account.name}</p>
                    {deactivated && <Badge variant="destructive">Deactivated</Badge>}
                </div>
                <p className="truncate text-sm text-muted-foreground">{account.email}</p>
                {deactivated && (
                    <p className="mt-1 text-sm text-muted-foreground">
                        Deactivated {formatDate(account.deactivated_at as string)} by {account.deactivated_by}.
                        {account.deactivated_reason ? ` Reason: ${account.deactivated_reason}` : ''}
                    </p>
                )}
                <div className="mt-2 flex flex-wrap gap-1">
                    {account.roles.length === 0 ? (
                        <Badge variant="outline">No role</Badge>
                    ) : (
                        account.roles.map((r, i) => (
                            <Badge
                                key={i}
                                variant="secondary"
                                className="h-auto max-w-full text-left whitespace-normal"
                            >
                                {r.label} · {r.scope}
                            </Badge>
                        ))
                    )}
                </div>
            </div>

            <div className="shrink-0">
                {deactivated ? (
                    <ConfirmDialog
                        trigger={
                            <Button type="button" size="sm" variant="outline">
                                Reactivate
                            </Button>
                        }
                        title={`Reactivate ${account.name}?`}
                        description="They will be able to log in again with their existing password. Sessions that were ended stay ended."
                        confirmLabel="Reactivate"
                        onConfirm={({ close, stopProcessing }) => {
                            router.post(
                                AccountController.reactivate.url(account.id),
                                {},
                                {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        close();
                                        onChanged?.();
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
                                <Button type="button" size="sm" variant="destructive">
                                    Deactivate
                                </Button>
                            }
                            title={`Deactivate ${account.name}?`}
                            description={
                                <>
                                    <span className="block">
                                        {account.name} will be signed out everywhere, including the mobile app, and
                                        will not be able to log in or reset their password.
                                    </span>
                                    <span className="mt-2 block">
                                        Their history stays and keeps showing their name. You can reactivate them
                                        later.
                                    </span>
                                </>
                            }
                        >
                            {(close) => (
                                <Form
                                    {...AccountController.deactivate.form(account.id)}
                                    options={{ preserveScroll: true }}
                                    onSuccess={() => {
                                        close();
                                        onChanged?.();
                                    }}
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <Label htmlFor={`deactivate-reason-${account.id}`}>Reason (optional)</Label>
                                            <Textarea
                                                id={`deactivate-reason-${account.id}`}
                                                name="reason"
                                                rows={3}
                                                maxLength={500}
                                                placeholder="For example, replaced as SDAO member"
                                            />
                                            <InputError message={errors.reason || errors.account} />
                                            <DialogFooter className="mt-4 gap-2">
                                                <DialogClose asChild>
                                                    <Button type="button" variant="secondary" disabled={processing}>
                                                        Cancel
                                                    </Button>
                                                </DialogClose>
                                                <Button type="submit" variant="destructive" loading={processing}>
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
            </div>
        </div>
    );
}

/**
 * "Find an account": reaches any non student account by name or email,
 * including one that no longer holds a role and so is not on the list below
 * (for example an old test account). Same debounced fetch and stale response
 * guard as the other typeaheads.
 */
function FindAccount() {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<AccountEntry[]>([]);
    const [status, setStatus] = useState<'idle' | 'searching' | 'done'>('idle');
    const [searchFailed, setSearchFailed] = useState(false);
    const debounceTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const latestQuery = useRef('');

    useEffect(() => {
        return () => {
            if (debounceTimer.current) {
                clearTimeout(debounceTimer.current);
            }
        };
    }, []);

    function search(text: string) {
        setStatus('searching');
        setSearchFailed(false);

        fetch(AccountController.search.url({ query: { q: text } }), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((res) => {
                if (!res.ok) {
                    throw new Error('search failed');
                }

                return res.json();
            })
            .then((data) => {
                if (latestQuery.current !== text) {
                    return;
                }

                setResults(data.accounts ?? []);
                setStatus('done');
            })
            .catch(() => {
                if (latestQuery.current !== text) {
                    return;
                }

                setSearchFailed(true);
                setStatus('done');
            });
    }

    function handleChange(text: string) {
        setQuery(text);
        latestQuery.current = text;

        if (debounceTimer.current) {
            clearTimeout(debounceTimer.current);
        }

        if (text.trim().length < 2) {
            setResults([]);
            setStatus('idle');
            setSearchFailed(false);

            return;
        }

        debounceTimer.current = setTimeout(() => search(text.trim()), SEARCH_DEBOUNCE_MS);
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Find an account</CardTitle>
                <CardDescription>
                    Search by name or email to deactivate an account that no longer holds a role. Student accounts
                    are not included.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                <div className="relative">
                    <SearchIcon className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        value={query}
                        onChange={(e) => handleChange(e.target.value)}
                        placeholder="Search by name or email…"
                        aria-label="Find an account"
                        autoComplete="off"
                        className="pl-9"
                    />
                </div>

                {status === 'searching' && (
                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Spinner className="size-3.5" /> Searching accounts…
                    </p>
                )}
                {status === 'done' && searchFailed && (
                    <p className="text-sm text-destructive">Could not search accounts just now. Try again.</p>
                )}
                {status === 'done' && !searchFailed && results.length === 0 && (
                    <p className="text-sm text-muted-foreground">No matching accounts.</p>
                )}
                {results.length > 0 && (
                    <div className="divide-y">
                        {results.map((a) => (
                            <AccountRow key={a.id} account={a} onChanged={() => search(latestQuery.current.trim())} />
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default function AdminApproversIndex({ approvers: items }: Props) {
    return (
        <>
            <Head title="Approvers" />

            <div className="space-y-6">
                <PageHeader title="Approver Accounts" subtitle="Accounts for everyone who approves documents" actions={
<Button asChild>
                        <Link href={approvers.create().url}>Provision Approver</Link>
                    </Button>
} />

                <FindAccount />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">All Approvers</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {items.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <ShieldCheck />
                                    </EmptyMedia>
                                    <EmptyTitle>No approvers provisioned yet</EmptyTitle>
                                    <EmptyDescription>
                                        Provisioned approver accounts will show up here.
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <div className="divide-y">
                                {items.map((a) => (
                                    <AccountRow key={a.id} account={a} />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminApproversIndex.layout = {
    breadcrumbs: [{ title: 'Admin' }, { title: 'Approvers' }],
};
