import DateBadge from '@/components/date-badge';
import TagBadge from '@/components/tag-badge';
import { formatTimeRange } from '@/lib/utils';
import type { PublicActivity } from '@/types/public-activity';

type Props = {
    activity: PublicActivity;
};

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
    const timeRange =
        activity.start_time && activity.end_time
            ? formatTimeRange(activity.start_time, activity.end_time)
            : null;

    return (
        <div className="flex items-start gap-3 rounded-md border px-3 py-2">
            <DateBadge iso={activity.activity_date} />

            <div className="min-w-0 flex-1 py-0.5">
                <p className="truncate text-base font-semibold">
                    {activity.name}
                </p>
                <p className="truncate text-sm text-muted-foreground">
                    {timeRange
                        ? `${timeRange} · ${activity.venue}`
                        : activity.venue}
                </p>
                <TagBadge className="mt-1.5">{activity.organization}</TagBadge>
            </div>
        </div>
    );
}
