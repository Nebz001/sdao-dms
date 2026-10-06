import { BarChart3, Building2, History, Hourglass } from 'lucide-react';
import SegmentedBar from '@/components/review-queue/segmented-bar';
import Sparkline from '@/components/review-queue/sparkline';
import StatCard, { StatValue } from '@/components/review-queue/stat-card';

export type ActivityLogStatsData = {
    termLabel: string;
    total: {
        total: number;
        submitted: number;
        approved: number;
        returned: number;
        rejected: number;
    };
    perWeek: { thisWeek: number; weeks: number[] };
    slowestStep: {
        role: string;
        label: string;
        avgDays: number;
        finishedWaits: number;
        waitingNow: number;
    } | null;
    topOrganization: {
        id: number;
        name: string;
        events: number;
        submitted: number;
    } | null;
};

function plural(count: number, word: string): string {
    return `${count} ${word}${count === 1 ? '' : 's'}`;
}

function TotalEventsCard({ data }: { data: ActivityLogStatsData['total'] }) {
    return (
        <StatCard icon={History} title="Total events this term">
            <StatValue>{data.total}</StatValue>
            <SegmentedBar
                ariaLabel={`Events this term: ${data.submitted} submitted, ${data.approved} approved, ${data.returned} returned, ${data.rejected} rejected`}
                segments={[
                    { label: 'Submitted', count: data.submitted, className: 'bg-info' },
                    { label: 'Approved', count: data.approved, className: 'bg-success' },
                    { label: 'Returned', count: data.returned, className: 'bg-warning' },
                    { label: 'Rejected', count: data.rejected, className: 'bg-destructive' },
                ]}
            />
        </StatCard>
    );
}

function EventsThisWeekCard({ data }: { data: ActivityLogStatsData['perWeek'] }) {
    return (
        <StatCard icon={BarChart3} title="Events this week">
            <StatValue>{data.thisWeek}</StatValue>
            <Sparkline
                values={data.weeks}
                label={`Events per week, last ${data.weeks.length} weeks: ${data.weeks.join(', ')}`}
            />
            <p className="text-sm text-muted-foreground">Last {data.weeks.length} weeks</p>
        </StatCard>
    );
}

function SlowestStepCard({ data }: { data: ActivityLogStatsData['slowestStep'] }) {
    return (
        <StatCard icon={Hourglass} title="Slowest approval step">
            {data ? (
                <>
                    <p className="text-2xl leading-tight font-semibold break-words">{data.label}</p>
                    <dl className="mt-auto grid grid-cols-[1fr_auto] gap-x-6 gap-y-1.5 text-sm">
                        <dt className="text-muted-foreground">Avg. wait</dt>
                        <dd className="text-right font-semibold tabular-nums">
                            {data.avgDays.toFixed(1)} {data.avgDays === 1 ? 'day' : 'days'}
                        </dd>
                        <dt className="text-muted-foreground">Waiting now</dt>
                        <dd className="text-right font-semibold tabular-nums">{plural(data.waitingNow, 'document')}</dd>
                    </dl>
                </>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Not enough finished reviews this term yet to say which step is slowest.
                </p>
            )}
        </StatCard>
    );
}

function MostActiveOrgCard({ data }: { data: ActivityLogStatsData['topOrganization'] }) {
    return (
        <StatCard icon={Building2} title="Most active org">
            {data ? (
                <>
                    <p className="text-2xl leading-tight font-semibold break-words">{data.name}</p>
                    <dl className="mt-auto grid grid-cols-[1fr_auto] gap-x-6 gap-y-1.5 text-sm">
                        <dt className="text-muted-foreground">Events</dt>
                        <dd className="text-right font-semibold tabular-nums">{data.events}</dd>
                        <dt className="text-muted-foreground">Submitted</dt>
                        <dd className="text-right font-semibold tabular-nums">{data.submitted}</dd>
                    </dl>
                </>
            ) : (
                <p className="text-sm text-muted-foreground">No events yet this term.</p>
            )}
        </StatCard>
    );
}

/** The four Activity Log cards. They cover the current term and never change with the filters. */
export default function ActivityLogStats({ stats }: { stats: ActivityLogStatsData }) {
    return (
        <section aria-label="Activity log figures" className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <TotalEventsCard data={stats.total} />
            <EventsThisWeekCard data={stats.perWeek} />
            <SlowestStepCard data={stats.slowestStep} />
            <MostActiveOrgCard data={stats.topOrganization} />
        </section>
    );
}
