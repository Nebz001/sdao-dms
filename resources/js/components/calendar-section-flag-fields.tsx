import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

type Props = {
    activities: { name: string }[];
};

/**
 * Phase 2 item 9 — Activity Calendar's variant of section-flag-fields.tsx.
 * Calendar has no static section registry: each currently-submitted activity
 * row is its own flaggable unit, keyed by its 0-based position ("activity_0",
 * "activity_1", …) rather than a database id, since CalendarActivity rows are
 * deleted and recreated on every resubmit (row ids aren't stable — see
 * UpdateActivityCalendar::execute()). Built from the same `calendar.activities`
 * array the review page already has loaded, not a separate fetch.
 */
export default function CalendarSectionFlagFields({ activities }: Props) {
    if (activities.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-2">
            <span className="text-sm font-medium">Flag activities needing revision (optional)</span>
            <div className="grid gap-2 sm:grid-cols-2">
                {activities.map((activity, index) => {
                    const key = `activity_${index}`;

                    return (
                        <div key={key} className="flex items-center gap-2">
                            <Checkbox id={`section-${key}`} name="sections[]" value={key} />
                            <Label htmlFor={`section-${key}`} className="font-normal">
                                Activity {index + 1}: {activity.name}
                            </Label>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
