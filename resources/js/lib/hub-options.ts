import {
    Building2,
    CalendarDays,
    CalendarPlus,
    ClipboardCheck,
    FileCheck,
    History,
    PencilLine,
    RefreshCw,
    UserPlus,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

export type HubKey = 'submit' | 'my-documents' | 'review';

/** The nav group each hub page lists the options of. */
export const HUB_GROUP: Record<HubKey, string> = {
    submit: 'Submit',
    'my-documents': 'My Documents',
    review: 'Review',
};

export type OptionMeta = {
    icon: LucideIcon;
    description: string;
};

/**
 * Words, icon and colour for each option on a hub page, keyed by
 * `{group}/{option title}`. The options themselves, and their links, come from
 * the nav config; this only dresses them, so an option the config adds later
 * still shows (with the generic fallback) rather than going missing.
 */
const OPTION_META: Record<string, OptionMeta> = {
    'Submit/Registration': {
        icon: Building2,
        description:
            'Register your organization with SDAO for the school year.',
    },
    'Submit/Renewal': {
        icon: RefreshCw,
        description:
            'Renew your organization each school year to keep it active.',
    },
    'Submit/Activity Calendar': {
        icon: CalendarDays,
        description: 'Plan your organization’s activities for the term.',
    },
    'Submit/Activity Proposal': {
        icon: CalendarPlus,
        description: 'Ask for approval before you hold an event or activity.',
    },
    'Submit/Report': {
        icon: FileCheck,
        description:
            'Report how an approved activity went, with photos and results.',
    },
    'Submit/Continue a Draft': {
        icon: PencilLine,
        description: 'Pick up a form you started but have not sent yet.',
    },
    'My Documents/Registrations': {
        icon: Building2,
        description:
            'Your organization’s registrations and where each one stands.',
    },
    'My Documents/Renewals': {
        icon: RefreshCw,
        description: 'Your yearly renewals and where each one stands.',
    },
    'My Documents/Calendars': {
        icon: CalendarDays,
        description: 'Your activity calendars for each term.',
    },
    'My Documents/Proposals': {
        icon: CalendarPlus,
        description: 'Activity proposals and where each one is in review.',
    },
    'My Documents/Reports': {
        icon: FileCheck,
        description: 'After-activity reports you have filed.',
    },
    'My Documents/Document History': {
        icon: History,
        description:
            'Everything your organization has filed, across every form.',
    },
    'Review/Join Requests': {
        icon: UserPlus,
        description:
            'Approve or decline students asking to join your organization.',
    },
};

export function optionMeta(group: string, title: string): OptionMeta {
    return (
        OPTION_META[`${group}/${title}`] ?? {
            icon: ClipboardCheck,
            description: `Open ${title.toLowerCase()}.`,
        }
    );
}

/** Which server chip (StudentDashboardData::hubChips()) belongs to which Submit option. */
export const OPTION_CHIP_KEY: Record<string, string> = {
    Registration: 'registration',
    Renewal: 'renewal',
    'Activity Calendar': 'activity_calendar',
    Report: 'report',
    'Continue a Draft': 'draft',
};
