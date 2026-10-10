import {
    Building2,
    CalendarDays,
    CalendarPlus,
    FileCheck,
    RefreshCw,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { TileTone } from '@/components/icon-tile';
import type { ListFormKind } from '@/lib/student-list';

/** The icon and colour each form type wears wherever it is listed. */
export const FORM_STYLE: Record<
    ListFormKind,
    { icon: LucideIcon; tone: TileTone }
> = {
    registration: { icon: Building2, tone: 'orange' },
    renewal: { icon: RefreshCw, tone: 'purple' },
    calendar: { icon: CalendarDays, tone: 'teal' },
    proposal: { icon: CalendarPlus, tone: 'blue' },
    report: { icon: FileCheck, tone: 'green' },
};

/** FormType enum value (server) to the list kind used by the client. */
export const FORM_TYPE_KIND: Record<string, ListFormKind> = {
    organization_registration: 'registration',
    organization_renewal: 'renewal',
    activity_calendar: 'calendar',
    activity_proposal: 'proposal',
    after_activity_report: 'report',
};
