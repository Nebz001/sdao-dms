import { Head, Link } from '@inertiajs/react';
import { SearchIcon, ShieldCheck } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import AccountController from '@/actions/App/Http/Controllers/Admin/AccountController';
import AccountName from '@/components/account-name';
import {
    accountsForTab,
    groupAccounts,
    hasActiveFilters,
    tabCounts,
} from '@/components/approver-accounts/filter-accounts';
import type { AccountGroup } from '@/components/approver-accounts/filter-accounts';
import ManageAccountDialog from '@/components/approver-accounts/manage-account-dialog';
import {
    ActiveApproversCard,
    DeactivatedCard,
    MissingAdviserCard,
    UnassignedAdvisersCard,
} from '@/components/approver-accounts/stat-cards';
import {
    DEACTIVATED_HINT,
    DEFAULT_FILTERS,
    ROLE_GROUPS,
} from '@/components/approver-accounts/types';
import type {
    ApproverAccount,
    ApproverStats,
    InitialFilters,
    Filters,
    RoleGroup,
    School,
    StatusFilter,
    Tab,
} from '@/components/approver-accounts/types';
import CountBadge from '@/components/count-badge';
import PageHeader from '@/components/page-header';
import PageNotice from '@/components/page-notice';
import { FlagBadge, ToneBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { NO_SCHOOL_LABEL } from '@/lib/school';
import { cn } from '@/lib/utils';
import * as approvers from '@/routes/admin/approvers';

type Props = {
    approvers: ApproverAccount[];
    stats: ApproverStats;
    schools: School[];
    /** Role and scope a link (the unassigned advisers card) preselects. */
    initialFilters: InitialFilters;
};

const SEARCH_DEBOUNCE_MS = 400;

const HEAD =
    'text-xs font-medium tracking-wide text-muted-foreground uppercase';

const TABS: { value: Tab; label: string }[] = [
    { value: 'all', label: 'All' },
    ...ROLE_GROUPS.map((g) => ({ value: g.key as Tab, label: g.tab })),
    { value: 'deactivated', label: 'Deactivated' },
];

const GROUP_META: Record<
    AccountGroup['key'],
    { heading: string; hint: string }
> = {
    ...(Object.fromEntries(
        ROLE_GROUPS.map((g) => [g.key, { heading: g.heading, hint: g.hint }]),
    ) as Record<RoleGroup, { heading: string; hint: string }>),
    deactivated: { heading: 'Deactivated', hint: DEACTIVATED_HINT },
    found: {
        heading: 'Other accounts',
        hint: 'Found by search, no approver role',
    },
};

function RoleBadge({ account }: { account: ApproverAccount }) {
    return (
        <ToneBadge
            tone={account.role_label ? 'info' : 'neutral'}
            className="h-auto max-w-full text-left break-words whitespace-normal"
        >
            {account.role_label ?? 'No role'}
        </ToneBadge>
    );
}

function StatusBadge({ account }: { account: ApproverAccount }) {
    return account.deactivated_at ? (
        <FlagBadge flag="deactivated" />
    ) : (
        <ToneBadge tone="success">Active</ToneBadge>
    );
}

function ApprovesFor({ account }: { account: ApproverAccount }) {
    if (!account.approves_for) {
        return <span className="text-muted-foreground">None</span>;
    }

    return (
        <div className="flex flex-col">
            <span>{account.approves_for.primary}</span>
            {account.approves_for.secondary && (
                <span className="text-sm text-muted-foreground">
                    {account.approves_for.secondary}
                </span>
            )}
        </div>
    );
}

function Approver({ account }: { account: ApproverAccount }) {
    return (
        <div className="flex min-w-0 flex-col">
            <AccountName name={account.name} nameClassName="font-medium" />
            <span className="text-sm break-all text-muted-foreground">
                {account.email}
            </span>
        </div>
    );
}

function GroupHeading({
    group,
    count,
}: {
    group: AccountGroup['key'];
    count: number;
}) {
    return (
        <span className="flex items-center gap-2 text-sm font-semibold tracking-wide uppercase">
            {GROUP_META[group].heading}
            <CountBadge count={count} />
        </span>
    );
}

/**
 * One header row, then a body per role group opened by a group header row
 * (role, count, quiet hint). Below `md` the same groups stack as cards, so a
 * narrow screen never scrolls sideways.
 */
function AccountsTable({
    groups,
    onChanged,
}: {
    groups: AccountGroup[];
    onChanged: () => void;
}) {
    return (
        <>
            <div className="hidden md:block">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className={HEAD}>Approver</TableHead>
                            <TableHead className={HEAD}>Role</TableHead>
                            <TableHead className={HEAD}>Approves for</TableHead>
                            <TableHead className={HEAD}>Status</TableHead>
                            <TableHead className={cn(HEAD, 'text-right')}>
                                Action
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    {groups.map((group) => (
                        <TableBody key={group.key}>
                            <TableRow className="bg-muted/30 hover:bg-muted/30">
                                <th
                                    scope="colgroup"
                                    colSpan={5}
                                    className="px-2 py-3 text-left font-normal"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                                        <GroupHeading
                                            group={group.key}
                                            count={group.accounts.length}
                                        />
                                        <span className="text-sm text-muted-foreground">
                                            {GROUP_META[group.key].hint}
                                        </span>
                                    </div>
                                </th>
                            </TableRow>
                            {group.accounts.map((account) => (
                                <TableRow key={account.id}>
                                    <TableCell className="whitespace-normal">
                                        <Approver account={account} />
                                    </TableCell>
                                    <TableCell className="whitespace-normal">
                                        <RoleBadge account={account} />
                                    </TableCell>
                                    <TableCell className="whitespace-normal">
                                        <ApprovesFor account={account} />
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge account={account} />
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <ManageAccountDialog
                                            account={account}
                                            onChanged={onChanged}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    ))}
                </Table>
            </div>

            <div className="flex flex-col gap-6 md:hidden">
                {groups.map((group) => (
                    <section
                        key={group.key}
                        aria-label={GROUP_META[group.key].heading}
                        className="flex flex-col gap-3"
                    >
                        <div className="flex flex-col gap-0.5">
                            <GroupHeading
                                group={group.key}
                                count={group.accounts.length}
                            />
                            <span className="text-sm text-muted-foreground">
                                {GROUP_META[group.key].hint}
                            </span>
                        </div>
                        <ul className="flex flex-col gap-3">
                            {group.accounts.map((account) => (
                                <li
                                    key={account.id}
                                    className="flex flex-col gap-3 rounded-lg border p-4"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <Approver account={account} />
                                        <div className="shrink-0">
                                            <StatusBadge account={account} />
                                        </div>
                                    </div>
                                    <dl className="grid grid-cols-[auto_1fr] items-baseline gap-x-4 gap-y-1.5 text-sm">
                                        <dt className="text-muted-foreground">
                                            Role
                                        </dt>
                                        <dd className="min-w-0">
                                            <RoleBadge account={account} />
                                        </dd>
                                        <dt className="text-muted-foreground">
                                            Approves for
                                        </dt>
                                        <dd className="min-w-0">
                                            <ApprovesFor account={account} />
                                        </dd>
                                    </dl>
                                    <div className="[&_button]:w-full">
                                        <ManageAccountDialog
                                            account={account}
                                            onChanged={onChanged}
                                        />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}
            </div>
        </>
    );
}

function FilterSelect({
    id,
    label,
    value,
    onChange,
    disabled,
    children,
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
    children: ReactNode;
}) {
    return (
        <div className="flex min-w-0 flex-col gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Select value={value} onValueChange={onChange} disabled={disabled}>
                <SelectTrigger id={id} className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>{children}</SelectGroup>
                </SelectContent>
            </Select>
        </div>
    );
}

export default function AdminApproversIndex({
    approvers: items,
    stats,
    schools,
    initialFilters,
}: Props) {
    const [filters, setFilters] = useState<Filters>(() => ({
        ...DEFAULT_FILTERS,
        ...(initialFilters.role && { role: initialFilters.role }),
        ...(initialFilters.scope && { scope: initialFilters.scope }),
    }));

    // A link to this same page with a role or scope (the card) changes the
    // props without remounting, so apply them when they change.
    const linkedFilters = `${initialFilters.role}|${initialFilters.scope}`;
    const [appliedFilters, setAppliedFilters] = useState(linkedFilters);

    if (appliedFilters !== linkedFilters) {
        setAppliedFilters(linkedFilters);
        setFilters((current) => ({
            ...current,
            ...(initialFilters.role && {
                role: initialFilters.role,
                tab: 'all' as const,
            }),
            ...(initialFilters.scope && { scope: initialFilters.scope }),
        }));
    }

    const [found, setFound] = useState<ApproverAccount[]>([]);
    const [searching, setSearching] = useState(false);
    const [searchFailed, setSearchFailed] = useState(false);
    const latestSearch = useRef('');
    const debounceTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const update = (patch: Partial<Filters>) =>
        setFilters((current) => ({ ...current, ...patch }));

    const counts = useMemo(() => tabCounts(items, filters), [items, filters]);
    const visible = useMemo(
        () => accountsForTab(items, filters, filters.tab),
        [items, filters],
    );
    const listedIds = useMemo(() => new Set(items.map((a) => a.id)), [items]);

    // Accounts that hold no role are not on the list; the server search reaches them.
    // Only shown for an unnarrowed role/scope/tab, since such an account has neither.
    const foundExtra = useMemo(
        () =>
            filters.tab === 'all' &&
            filters.role === 'all' &&
            filters.scope === 'all' &&
            filters.status !== 'deactivated'
                ? found.filter((a) => !listedIds.has(a.id))
                : [],
        [
            found,
            listedIds,
            filters.tab,
            filters.role,
            filters.scope,
            filters.status,
        ],
    );

    const groups = useMemo(() => {
        const base = groupAccounts(visible);

        return foundExtra.length > 0
            ? [...base, { key: 'found' as const, accounts: foundExtra }]
            : base;
    }, [visible, foundExtra]);

    useEffect(() => {
        return () => {
            if (debounceTimer.current) {
                clearTimeout(debounceTimer.current);
            }
        };
    }, []);

    function runSearch(text: string) {
        setSearching(true);
        setSearchFailed(false);

        fetch(AccountController.search.url({ query: { q: text } }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((res) => {
                if (!res.ok) {
                    throw new Error('search failed');
                }

                return res.json();
            })
            .then((data) => {
                if (latestSearch.current === text) {
                    setFound(data.accounts ?? []);
                    setSearching(false);
                }
            })
            .catch(() => {
                if (latestSearch.current === text) {
                    setSearchFailed(true);
                    setSearching(false);
                }
            });
    }

    function handleSearch(text: string) {
        update({ search: text });
        const trimmed = text.trim();
        latestSearch.current = trimmed;

        if (debounceTimer.current) {
            clearTimeout(debounceTimer.current);
        }

        if (trimmed.length < 2) {
            setFound([]);
            setSearching(false);
            setSearchFailed(false);

            return;
        }

        debounceTimer.current = setTimeout(
            () => runSearch(trimmed),
            SEARCH_DEBOUNCE_MS,
        );
    }

    function refreshSearch() {
        if (latestSearch.current.length >= 2) {
            runSearch(latestSearch.current);
        }
    }

    function clearFilters() {
        setFilters(DEFAULT_FILTERS);
        latestSearch.current = '';
        setFound([]);
        setSearchFailed(false);
    }

    const onDeactivatedTab = filters.tab === 'deactivated';

    return (
        <>
            <Head title="Approvers" />

            <div className="flex flex-col gap-6">
                <PageHeader
                    title="Approver Accounts"
                    subtitle="Everyone who approves documents, grouped by the role they hold."
                    actions={
                        <Button asChild>
                            <Link href={approvers.create().url}>
                                Provision approver
                            </Link>
                        </Button>
                    }
                />

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1.3fr)_minmax(0,1fr)]">
                    <ActiveApproversCard stats={stats.active} />
                    <UnassignedAdvisersCard stats={stats.unassignedAdvisers} />
                    <MissingAdviserCard stats={stats.missingAdviser} />
                    <DeactivatedCard stats={stats.deactivated} />
                </div>

                <Card className="shadow-none">
                    <CardContent>
                        <form
                            role="search"
                            aria-label="Filter approver accounts"
                            onSubmit={(e) => e.preventDefault()}
                            className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-[repeat(3,minmax(0,1fr))_minmax(0,1.4fr)]"
                        >
                            <FilterSelect
                                id="filter-role"
                                label="Role"
                                value={filters.role}
                                onChange={(v) =>
                                    update({ role: v as Filters['role'] })
                                }
                            >
                                <SelectItem value="all">All roles</SelectItem>
                                {ROLE_GROUPS.map((g) => (
                                    <SelectItem key={g.key} value={g.key}>
                                        {g.tab}
                                    </SelectItem>
                                ))}
                            </FilterSelect>
                            <FilterSelect
                                id="filter-scope"
                                label="Scope"
                                value={filters.scope}
                                onChange={(v) => update({ scope: v })}
                            >
                                <SelectItem value="all">All scopes</SelectItem>
                                <SelectItem value="global">
                                    Whole school
                                </SelectItem>
                                {schools.map((s) => (
                                    <SelectItem
                                        key={s.id}
                                        value={`school:${s.id}`}
                                    >
                                        {s.name}
                                    </SelectItem>
                                ))}
                                <SelectItem value="none">
                                    {NO_SCHOOL_LABEL}
                                </SelectItem>
                                <SelectItem value="unassigned">
                                    Not assigned yet
                                </SelectItem>
                            </FilterSelect>
                            <FilterSelect
                                id="filter-status"
                                label="Status"
                                value={
                                    onDeactivatedTab
                                        ? 'deactivated'
                                        : filters.status
                                }
                                onChange={(v) =>
                                    update({ status: v as StatusFilter })
                                }
                                disabled={onDeactivatedTab}
                            >
                                <SelectItem value="active">
                                    Active only
                                </SelectItem>
                                <SelectItem value="deactivated">
                                    Deactivated only
                                </SelectItem>
                                <SelectItem value="all">
                                    All statuses
                                </SelectItem>
                            </FilterSelect>
                            <div className="flex min-w-0 flex-col gap-2 sm:col-span-2 lg:col-span-1">
                                <Label htmlFor="filter-search">Search</Label>
                                <div className="relative">
                                    <SearchIcon
                                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden
                                    />
                                    <Input
                                        id="filter-search"
                                        type="search"
                                        value={filters.search}
                                        onChange={(e) =>
                                            handleSearch(e.target.value)
                                        }
                                        placeholder="Name, email, or organization"
                                        autoComplete="off"
                                        className="pl-9"
                                    />
                                </div>
                            </div>
                        </form>
                        <div aria-live="polite" className="mt-3 empty:hidden">
                            {searching && (
                                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Spinner className="size-3.5" /> Searching
                                    accounts…
                                </p>
                            )}
                            {!searching && searchFailed && (
                                <PageNotice
                                    tone="destructive"
                                    urgent
                                    title="Could not search accounts just now."
                                >
                                    Try again.
                                </PageNotice>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <div
                    role="group"
                    aria-label="Show accounts by role"
                    className="flex flex-wrap gap-1.5"
                >
                    {TABS.map((tab) => {
                        const active = tab.value === filters.tab;

                        return (
                            <Button
                                key={tab.value}
                                type="button"
                                size="sm"
                                variant={active ? 'secondary' : 'ghost'}
                                aria-pressed={active}
                                onClick={() => update({ tab: tab.value })}
                            >
                                {tab.label}
                                <CountBadge
                                    count={counts[tab.value]}
                                    variant="outline"
                                />
                            </Button>
                        );
                    })}
                </div>

                <Card className="shadow-none">
                    <CardContent>
                        {groups.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <ShieldCheck />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        {items.length === 0
                                            ? 'No approvers provisioned yet'
                                            : emptyTitle(filters)}
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        {items.length === 0
                                            ? 'Provisioned approver accounts will show up here.'
                                            : emptyDescription(filters)}
                                    </EmptyDescription>
                                </EmptyHeader>
                                {items.length > 0 &&
                                    hasActiveFilters(filters) && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={clearFilters}
                                        >
                                            Clear filters
                                        </Button>
                                    )}
                            </Empty>
                        ) : (
                            <AccountsTable
                                groups={groups}
                                onChanged={refreshSearch}
                            />
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function emptyTitle(filters: Filters): string {
    return filters.tab === 'deactivated'
        ? 'No deactivated accounts'
        : 'No matching accounts';
}

function emptyDescription(filters: Filters): string {
    if (
        filters.tab === 'deactivated' &&
        !hasActiveFilters({ ...filters, tab: 'all' })
    ) {
        return 'Accounts you deactivate are kept here for the record.';
    }

    return 'Try a different search, or clear the filters to see everyone.';
}

AdminApproversIndex.layout = {
    breadcrumbs: [{ title: 'Admin' }, { title: 'Approvers' }],
};
