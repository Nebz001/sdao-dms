import { DEFAULT_FILTERS, ROLE_GROUPS } from './types';
import type { ApproverAccount, Filters, RoleGroup, Tab } from './types';

/** Case-insensitive match on name, email, and what the account approves for (the organization). */
function matchesSearch(account: ApproverAccount, search: string): boolean {
    const needle = search.trim().toLowerCase();

    if (needle === '') {
        return true;
    }

    return [
        account.name,
        account.email,
        account.approves_for?.primary,
        account.approves_for?.secondary,
    ]
        .filter((value): value is string => Boolean(value))
        .some((value) => value.toLowerCase().includes(needle));
}

/** Everything except the tab: the Role, Scope and Status selects, and the search. */
function passesFilters(
    account: ApproverAccount,
    filters: Filters,
    ignoreStatus: boolean,
): boolean {
    const deactivated = account.deactivated_at !== null;

    if (
        !ignoreStatus &&
        filters.status !== 'all' &&
        (filters.status === 'deactivated') !== deactivated
    ) {
        return false;
    }

    if (filters.role !== 'all' && account.group !== filters.role) {
        return false;
    }

    if (filters.scope !== 'all' && account.scope_key !== filters.scope) {
        return false;
    }

    return matchesSearch(account, filters.search);
}

function inTab(account: ApproverAccount, tab: Tab): boolean {
    if (tab === 'deactivated') {
        return account.deactivated_at !== null;
    }

    return tab === 'all' || account.group === tab;
}

/**
 * The accounts one tab shows under the current filters. The Deactivated tab
 * is the whole deactivated list regardless of the Status select, so it can
 * never be emptied by "Active only".
 */
export function accountsForTab(
    accounts: ApproverAccount[],
    filters: Filters,
    tab: Tab,
): ApproverAccount[] {
    return accounts.filter(
        (a) =>
            inTab(a, tab) && passesFilters(a, filters, tab === 'deactivated'),
    );
}

export function tabCounts(
    accounts: ApproverAccount[],
    filters: Filters,
): Record<Tab, number> {
    const tabs: Tab[] = [
        'all',
        ...ROLE_GROUPS.map((g) => g.key),
        'deactivated',
    ];

    return Object.fromEntries(
        tabs.map((tab) => [tab, accountsForTab(accounts, filters, tab).length]),
    ) as Record<Tab, number>;
}

export type AccountGroup = {
    key: RoleGroup | 'deactivated' | 'found';
    accounts: ApproverAccount[];
};

/** Rows grouped by role in display order, then the deactivated group last. Empty groups are left out. */
export function groupAccounts(accounts: ApproverAccount[]): AccountGroup[] {
    const live = accounts.filter((a) => a.deactivated_at === null);
    const groups: AccountGroup[] = ROLE_GROUPS.map((g) => ({
        key: g.key,
        accounts: live.filter((a) => a.group === g.key),
    }));

    groups.push({
        key: 'deactivated',
        accounts: accounts.filter((a) => a.deactivated_at !== null),
    });

    return groups.filter((g) => g.accounts.length > 0);
}

export function hasActiveFilters(filters: Filters): boolean {
    return (Object.keys(DEFAULT_FILTERS) as (keyof Filters)[]).some(
        (key) => filters[key] !== DEFAULT_FILTERS[key],
    );
}
