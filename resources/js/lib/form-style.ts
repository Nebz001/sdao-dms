import {
    Building2,
    CalendarDays,
    CalendarPlus,
    FileCheck,
    RefreshCw,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ListFormKind } from '@/lib/student-list';

/** The icon each form type wears wherever it is listed. */
export const FORM_STYLE: Record<
    ListFormKind,
    { icon: LucideIcon }
> = {
    registration: { icon: Building2 },
    renewal: { icon: RefreshCw },
    calendar: { icon: CalendarDays },
    proposal: { icon: CalendarPlus },
    report: { icon: FileCheck },
};

/** FormType enum value (server) to the list kind used by the client. */
export const FORM_TYPE_KIND: Record<string, ListFormKind> = {
    organization_registration: 'registration',
    organization_renewal: 'renewal',
    activity_calendar: 'calendar',
    activity_proposal: 'proposal',
    after_activity_report: 'report',
};
