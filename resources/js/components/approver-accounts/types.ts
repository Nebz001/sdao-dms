export type RoleGroup =
    | 'adviser'
    | 'program_chair'
    | 'dean'
    | 'sdao'
    | 'director';

export type RoleEntry = { role: string; label: string; scope: string };

export type ApproverAccount = {
    id: number;
    name: string;
    first_name: string | null;
    last_name: string | null;
    email: string;
    is_self: boolean;
    deactivated_at: string | null;
    deactivated_reason: string | null;
    deactivated_by: string | null;
    /** Null when the account holds no approver role (a deactivated account whose role was removed). */
    group: RoleGroup | null;
    role_label: string | null;
    approves_for: { primary: string; secondary: string | null } | null;
    /** "school:{id}", "none", "unassigned" or "global". */
    scope_key: string | null;
    roles: RoleEntry[];
};

export type ApproverStats = {
    active: { total: number; byGroup: Record<RoleGroup, number> };
    unassignedAdvisers: { count: number; href: string };
    missingAdviser: {
        count: number;
        organizations: { id: number; name: string }[];
        href: string;
    };
    deactivated: { count: number; latest: { name: string; at: string } | null };
};

export type InitialFilters = { role: RoleGroup | null; scope: string | null };

export type School = { id: number; name: string };

/** Display order, wording and bar color of every role group (App\Http\Controllers\Admin\ApproverController::GROUPS). */
export const ROLE_GROUPS: {
    key: RoleGroup;
    /** Group header, in the table. */
    heading: string;
    /** Tab and Role filter wording. */
    tab: string;
    /** Legend wording in the stat card. */
    legend: string;
    hint: string;
    barClass: string;
}[] = [
    {
        key: 'adviser',
        heading: 'Advisers',
        tab: 'Advisers',
        legend: 'Advisers',
        hint: 'One organization each',
        barClass: 'bg-chart-1',
    },
    {
        key: 'program_chair',
        heading: 'Program chairs',
        tab: 'Program chairs',
        legend: 'Chairs',
        hint: 'One program each',
        barClass: 'bg-info',
    },
    {
        key: 'dean',
        heading: 'Deans',
        tab: 'Deans',
        legend: 'Deans',
        hint: 'Every org in their college',
        barClass: 'bg-chart-5',
    },
    {
        key: 'sdao',
        heading: 'SDAO members',
        tab: 'SDAO members',
        legend: 'SDAO',
        hint: 'Every organization',
        barClass: 'bg-success',
    },
    {
        key: 'director',
        heading: 'Academic directors',
        tab: 'Academic directors',
        legend: 'Directors',
        hint: 'Final step on proposals',
        barClass: 'bg-foreground/50',
    },
];

export const DEACTIVATED_HINT = 'Kept for the record, cannot approve';

export type Tab = 'all' | RoleGroup | 'deactivated';
export type StatusFilter = 'active' | 'deactivated' | 'all';

export type Filters = {
    tab: Tab;
    role: 'all' | RoleGroup;
    scope: string;
    status: StatusFilter;
    search: string;
};

export const DEFAULT_FILTERS: Filters = {
    tab: 'all',
    role: 'all',
    scope: 'all',
    status: 'active',
    search: '',
};
