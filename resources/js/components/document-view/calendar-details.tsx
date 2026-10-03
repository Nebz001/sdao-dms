import PageNotice from '@/components/page-notice';
import { formatTimeRange } from '@/lib/utils';
import { DetailField, DetailSection, DetailsCard } from './details';
import { formatLongDate, formatPeso } from './format';

export type CalendarActivity = {
    id: number;
    name: string;
    venue: string;
    activity_date: string;
    start_time: string;
    end_time: string;
    description: string | null;
    sdg_labels: string[];
    participant_program_assigned: string | null;
    budget: string | null;
};

export type CalendarData = {
    academic_year: string;
    term: string;
    term_label: string;
    activities: CalendarActivity[];
} | null;

export type ActivityConflicts = Record<number, { confirmed: { name: string; organization: string }[] }>;

/**
 * The details card for an activity calendar, shared by the student and
 * reviewer pages. Each activity is its own labeled section, matching how an
 * approver flags it ("Activity 1"). Reviewers also pass per-activity venue
 * conflicts, shown directly under the activity they affect.
 */
export default function CalendarDetails({
    calendar,
    conflicts = {},
}: {
    calendar: CalendarData;
    conflicts?: ActivityConflicts;
}) {
    const activities = calendar?.activities ?? [];

    return (
        <DetailsCard title={calendar ? `${calendar.term_label}, ${calendar.academic_year} activities` : 'Activities'}>
            {activities.length === 0 ? (
                <p className="text-muted-foreground">No activities were listed on this calendar.</p>
            ) : (
                activities.map((activity, index) => {
                    const confirmed = conflicts[activity.id]?.confirmed ?? [];

                    return (
                        <div key={activity.id} className="flex flex-col gap-4">
                            <DetailSection label={`Activity ${index + 1}`}>
                                <DetailField label="Activity name">{activity.name}</DetailField>
                                <DetailField label="Venue">{activity.venue}</DetailField>
                                <DetailField label="Date">{formatLongDate(activity.activity_date)}</DetailField>
                                <DetailField label="Time">
                                    {formatTimeRange(activity.start_time, activity.end_time)}
                                </DetailField>
                                <DetailField label="SDG" hideIfEmpty>
                                    {activity.sdg_labels.length > 0 ? activity.sdg_labels.join(', ') : null}
                                </DetailField>
                                <DetailField label="Participant/Program assigned" hideIfEmpty>
                                    {activity.participant_program_assigned}
                                </DetailField>
                                <DetailField label="Budget" hideIfEmpty>
                                    {formatPeso(activity.budget)}
                                </DetailField>
                                <DetailField label="Description" wide hideIfEmpty plain>
                                    {activity.description}
                                </DetailField>
                            </DetailSection>
                            {confirmed.length > 0 && (
                                <PageNotice tone="destructive" urgent title="Confirmed conflict.">
                                    {confirmed.map((c) => `"${c.name}" (${c.organization})`).join(', ')}
                                </PageNotice>
                            )}
                        </div>
                    );
                })
            )}
        </DetailsCard>
    );
}
