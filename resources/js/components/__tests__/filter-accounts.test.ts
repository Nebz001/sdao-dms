import { describe, expect, it } from 'vitest';
import { accountsForTab, groupAccounts, tabCounts } from '@/components/approver-accounts/filter-accounts';
import { DEFAULT_FILTERS } from '@/components/approver-accounts/types';
import type { ApproverAccount, Filters, RoleGroup } from '@/components/approver-accounts/types';

let nextId = 1;

function account(group: RoleGroup | null, overrides: Partial<ApproverAccount> = {}): ApproverAccount {
    const id = nextId++;

    return {
        id,
        name: `Person ${id}`,
        email: `person${id}@nu-lipa.edu.ph`,
        is_self: false,
        deactivated_at: null,
        deactivated_reason: null,
        deactivated_by: null,
        group,
        role_label: group,
        approves_for: { primary: 'Whole school', secondary: null },
        scope_key: 'global',
        roles: [],
        ...overrides,
    };
}

const adviser = account('adviser', {
    name: 'Ramon Dela Cruz',
    approves_for: { primary: 'MTSC', secondary: 'No college' },
    scope_key: 'none',
});
const dean = account('dean', { approves_for: { primary: 'School of Computing', secondary: '4 organizations' }, scope_key: 'school:1' });
const sdao = account('sdao');
const gone = account('sdao', { deactivated_at: '2026-10-01T00:00:00Z' });
const all = [adviser, dean, sdao, gone];

const filters = (patch: Partial<Filters> = {}): Filters => ({ ...DEFAULT_FILTERS, ...patch });

describe('accountsForTab', () => {
    it('shows active accounts only on the All tab by default', () => {
        expect(accountsForTab(all, filters(), 'all')).toEqual([adviser, dean, sdao]);
    });

    it('includes deactivated accounts when the status is All statuses', () => {
        expect(accountsForTab(all, filters({ status: 'all' }), 'all')).toHaveLength(4);
    });

    it('scopes a role tab to that role', () => {
        expect(accountsForTab(all, filters(), 'dean')).toEqual([dean]);
    });

    it('shows every deactivated account on the Deactivated tab even with Active only selected', () => {
        expect(accountsForTab(all, filters({ status: 'active' }), 'deactivated')).toEqual([gone]);
    });

    it('narrows by the Role select and the Scope select', () => {
        expect(accountsForTab(all, filters({ role: 'adviser' }), 'all')).toEqual([adviser]);
        expect(accountsForTab(all, filters({ scope: 'school:1' }), 'all')).toEqual([dean]);
    });

    it('searches name, email and organization, ignoring case', () => {
        expect(accountsForTab(all, filters({ search: 'ramon' }), 'all')).toEqual([adviser]);
        expect(accountsForTab(all, filters({ search: adviser.email.toUpperCase() }), 'all')).toEqual([adviser]);
        expect(accountsForTab(all, filters({ search: 'mtsc' }), 'all')).toEqual([adviser]);
        expect(accountsForTab(all, filters({ search: 'nothing like this' }), 'all')).toEqual([]);
    });
});

describe('tabCounts', () => {
    it('counts each tab under the current filters', () => {
        const counts = tabCounts(all, filters());

        expect(counts).toMatchObject({ all: 3, adviser: 1, dean: 1, sdao: 1, director: 0, deactivated: 1 });
    });
});

describe('groupAccounts', () => {
    it('groups by role in display order with the deactivated group last, leaving empty groups out', () => {
        const groups = groupAccounts(accountsForTab(all, filters({ status: 'all' }), 'all'));

        expect(groups.map((g) => g.key)).toEqual(['adviser', 'dean', 'sdao', 'deactivated']);
        expect(groups[3].accounts).toEqual([gone]);
    });
});
