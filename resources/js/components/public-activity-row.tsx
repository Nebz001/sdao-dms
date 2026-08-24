import { formatTimeRange } from '@/lib/utils';
import type { PublicActivity } from '@/types/public-activity';

type Props = {
    activity: PublicActivity;
};

/**
 * Splits `"YYYY-MM-DD"` into the two parts the date badge needs. Parses by
 * component (year/month/day passed straight to `new Date(...)`) rather than
 * `new Date(isoString)` — the same UTC-midnight shift `formatCalendarDate`
 * and `lib/month-grid.ts` already avoid for the same reason.
 */
function formatDateBadge(iso: string): { month: string; day: number } {
    const [year, month, day] = iso.split('-').map(Number);
    const date = new Date(year, month - 1, day);

    return {
        month: date
            .toLocaleDateString(undefined, { month: 'short' })
            .toUpperCase(),
        day: date.getDate(),
    };
}

/**
 * A single row in the public landing page's upcoming-activities list. The
 * date badge is the stable left edge the list scans down; the event name
 * is the loudest text in the row; the time/venue line and the organization
 * pill are both intentionally quieter than the name. Every organization
 * gets the same neutral pill style — no per-org color coding.
 *
 * No status badge — every activity reaching this component is Approved by
 * construction (see HomeController::approvedActivities()).
 */
export default function PublicActivityRow({ activity }: Props) {
    const { month, day } = formatDateBadge(activity.activity_date);
    const timeRange =
        activity.start_time && activity.end_time
            ? formatTimeRange(activity.start_time, activity.end_time)
            : null;

    return (
        <div className="flex items-start gap-3 rounded-md border px-3 py-2">
            <div className="flex size-12 shrink-0 flex-col items-center justify-center rounded-md bg-brand-fixed text-brand-fixed-foreground">
                <span className="text-[10px] font-semibold tracking-wide uppercase">
                    {month}
                </span>
                <span className="text-xl leading-none font-bold">{day}</span>
            </div>

            <div className="min-w-0 flex-1 py-0.5">
                <p className="truncate text-base font-semibold">
                    {activity.name}
                </p>
                <p className="truncate text-sm text-muted-foreground">
                    {timeRange
                        ? `${timeRange} · ${activity.venue}`
                        : activity.venue}
                </p>
                <span className="mt-1.5 inline-flex max-w-full items-center truncate rounded-full border border-primary/20 bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                    {activity.organization}
                </span>
            </div>
        </div>
    );
}
