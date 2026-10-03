import { formatTimeRange } from '@/lib/utils';
import { DetailField, DetailSection, DetailText, DetailsCard } from './details';
import { formatLongDate } from './format';

export type ReportData = {
    summary: string;
    outcomes: string | null;
    participant_count: number | null;
    activity_chairs: string[] | null;
    prepared_by: string | null;
    event_program: string | null;
    target_participants_percentage: number | null;
    activity: {
        title: string;
        venue: string | null;
        activity_date: string | null;
        start_time: string | null;
        end_time: string | null;
    } | null;
} | null;

/** The details card for an after-activity report, shared by the student and reviewer pages. */
export default function ReportDetails({ report }: { report: ReportData }) {
    const activity = report?.activity;
    const chairs = report?.activity_chairs ?? [];

    return (
        <DetailsCard title="Report details">
            <DetailSection label="Event">
                <DetailField label="Name of event">{activity?.title}</DetailField>
                <DetailField label="Venue">{activity?.venue}</DetailField>
                <DetailField label="Date of event">{formatLongDate(activity?.activity_date)}</DetailField>
                <DetailField label="Time of event">
                    {activity?.start_time && activity.end_time
                        ? formatTimeRange(activity.start_time, activity.end_time)
                        : null}
                </DetailField>
                <DetailField label="Participants">
                    {report?.participant_count != null ? String(report.participant_count) : null}
                </DetailField>
                <DetailField label="Target participants reached">
                    {report?.target_participants_percentage != null
                        ? `${report.target_participants_percentage}%`
                        : null}
                </DetailField>
            </DetailSection>
            <DetailSection label="People">
                <DetailField label="Prepared by">{report?.prepared_by}</DetailField>
                <DetailField label="Activity chair/s">
                    {chairs.length > 0 ? chairs.join(', ') : null}
                </DetailField>
            </DetailSection>
            <DetailText label="Summary">{report?.summary}</DetailText>
            {report?.event_program && <DetailText label="Program">{report.event_program}</DetailText>}
            {report?.outcomes && <DetailText label="Outcomes">{report.outcomes}</DetailText>}
        </DetailsCard>
    );
}
