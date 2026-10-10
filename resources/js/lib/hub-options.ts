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
import type { TileTone } from '@/components/icon-tile';

export type HubKey = 'submit' | 'my-documents' | 'review';

/** The nav group each hub page lists the options of. */
export const HUB_GROUP: Record<HubKey, string> = {
    submit: 'Submit',
    'my-documents': 'My Documents',
    review: 'Review',
};

export type OptionMeta = {
    icon: LucideIcon;
    tone: TileTone;
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
        tone: 'orange',
        description:
            'Register your organization with SDAO for the school year.',
    },
    'Submit/Renewal': {
        icon: RefreshCw,
        tone: 'purple',
        description:
            'Renew your organization each school year to keep it active.',
    },
    'Submit/Activity Calendar': {
        icon: CalendarDays,
        tone: 'teal',
        description: 'Plan your organization’s activities for the term.',
    },
    'Submit/Activity Proposal': {
        icon: CalendarPlus,
        tone: 'blue',
        description: 'Ask for approval before you hold an event or activity.',
    },
    'Submit/Report': {
        icon: FileCheck,
        tone: 'green',
        description:
            'Report how an approved activity went, with photos and results.',
    },
    'Submit/Continue a Draft': {
        icon: PencilLine,
        tone: 'slate',
        description: 'Pick up a form you started but have not sent yet.',
    },
    'My Documents/Registrations': {
        icon: Building2,
        tone: 'orange',
        description:
            'Your organization’s registrations and where each one stands.',
    },
    'My Documents/Renewals': {
        icon: RefreshCw,
        tone: 'purple',
        description: 'Your yearly renewals and where each one stands.',
    },
    'My Documents/Calendars': {
        icon: CalendarDays,
        tone: 'teal',
        description: 'Your activity calendars for each term.',
    },
    'My Documents/Proposals': {
        icon: CalendarPlus,
        tone: 'blue',
        description: 'Activity proposals and where each one is in review.',
    },
    'My Documents/Reports': {
        icon: FileCheck,
        tone: 'green',
        description: 'After-activity reports you have filed.',
    },
    'My Documents/Document History': {
        icon: History,
        tone: 'green',
        description:
            'Everything your organization has filed, across every form.',
    },
    'Review/Join Requests': {
        icon: UserPlus,
        tone: 'sky',
        description:
            'Approve or decline students asking to join your organization.',
    },
};

export function optionMeta(group: string, title: string): OptionMeta {
    return (
        OPTION_META[`${group}/${title}`] ?? {
            icon: ClipboardCheck,
            tone: 'amber',
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
