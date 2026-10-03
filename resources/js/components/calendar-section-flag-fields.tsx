import { useState } from 'react';
import { FlagChip } from '@/components/section-flag-fields';

type Props = {
    activities: { name: string }[];
};

/**
 * Activity Calendar's variant of section-flag-fields.tsx. Calendar has no
 * static section registry: each currently-submitted activity row is its own
 * flaggable unit, keyed by its 0-based position ("activity_0", "activity_1",
 * …) rather than a database id, since CalendarActivity rows are deleted and
 * recreated on every resubmit (row ids aren't stable — see
 * UpdateActivityCalendar::execute()). Built from the same
 * `calendar.activities` array the review page already has loaded, not a
 * separate fetch.
 */
export default function CalendarSectionFlagFields({ activities }: Props) {
    const [checked, setChecked] = useState<Record<string, boolean>>({});

    if (activities.length === 0) {
        return null;
    }

    return (
        <fieldset className="flex flex-col gap-3">
            <legend className="mb-2 text-sm font-medium">Which activities need fixing</legend>
            <div className="flex flex-wrap gap-2">
                {activities.map((activity, index) => {
                    const key = `activity_${index}`;

                    return (
                        <FlagChip
                            key={key}
                            id={`section-${key}`}
                            value={key}
                            checked={checked[key] ?? false}
                            onCheckedChange={(value) => setChecked((prev) => ({ ...prev, [key]: value }))}
                        >
                            Activity {index + 1}: {activity.name}
                        </FlagChip>
                    );
                })}
            </div>
        </fieldset>
    );
}
