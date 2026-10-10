export type RequirementItem = { key: string; label: string; met: boolean };

/** What the row's button does. The page maps `target` to a real link. */
export type RequirementAction = {
    label: string;
    target: 'registrations' | 'officer-change' | 'renewal';
};

export type OpenRequirement = {
    key: string;
    title: string;
    description: string;
    action: RequirementAction | null;
};

export type RequirementRows = {
    /** Labels of the requirements that are met, in order. */
    done: { key: string; label: string }[];
    open: OpenRequirement[];
    doneCount: number;
    total: number;
};

const DONE_LABELS: Record<string, string> = {
    registration_approved: 'Registration approved',
    adviser_bound: 'Adviser assigned',
    president_bound: 'Active president',
    secretary_bound: 'Active secretary',
    renewal_filed: 'Renewal filed for next year',
};

/**
 * Turns the server's requirement checklist into the "X of Y done" card. The
 * four standing requirements always count; the renewal row only exists while
 * renewal actually applies (`renewalDue`, the same predicate that decides
 * whether the org can submit a renewal), so it never pads the total or shows
 * as open in the wrong season.
 */
export function buildRequirementRows(
    requirements: RequirementItem[],
    renewalDue: boolean,
    organizationName: string,
    coversThroughAcademicYear: string | null,
): RequirementRows {
    const applicable = requirements.filter(
        (item) => item.key !== 'renewal_filed' || renewalDue,
    );

    const openFor = (item: RequirementItem): OpenRequirement => {
        switch (item.key) {
            case 'registration_approved':
                return {
                    key: item.key,
                    title: 'Registration is not approved yet',
                    description: 'SDAO is still reviewing your registration.',
                    action: {
                        label: 'View registrations',
                        target: 'registrations',
                    },
                };
            case 'adviser_bound':
                return {
                    key: item.key,
                    title: 'No adviser assigned',
                    description:
                        'SDAO assigns an adviser to your organization.',
                    action: null,
                };
            case 'president_bound':
                return {
                    key: item.key,
                    title: 'Add a president',
                    description: `${organizationName} has no active president yet`,
                    action: {
                        label: 'Request officer change',
                        target: 'officer-change',
                    },
                };
            case 'secretary_bound':
                return {
                    key: item.key,
                    title: 'Add a secretary',
                    description: `${organizationName} has no active secretary yet`,
                    action: {
                        label: 'Request officer change',
                        target: 'officer-change',
                    },
                };
            default:
                return {
                    key: item.key,
                    title: 'File a renewal for next year',
                    description: coversThroughAcademicYear
                        ? `Keeps ${organizationName} active after ${coversThroughAcademicYear}`
                        : `Keeps ${organizationName} active next year`,
                    action: { label: 'Start renewal', target: 'renewal' },
                };
        }
    };

    return {
        done: applicable
            .filter((item) => item.met)
            .map((item) => ({
                key: item.key,
                label: DONE_LABELS[item.key] ?? item.label,
            })),
        open: applicable.filter((item) => !item.met).map(openFor),
        doneCount: applicable.filter((item) => item.met).length,
        total: applicable.length,
    };
}

/** "1 officer, 1 adviser", singular and plural right. */
export function peopleSummary(officers: number, advisers: number): string {
    const part = (count: number, noun: string) =>
        `${count} ${noun}${count === 1 ? '' : 's'}`;

    return `${part(officers, 'officer')}, ${part(advisers, 'adviser')}`;
}
