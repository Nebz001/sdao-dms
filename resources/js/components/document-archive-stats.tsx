import { Archive, BarChart3, Building2, FileText } from 'lucide-react';
import SegmentedBar from '@/components/review-queue/segmented-bar';
import Sparkline from '@/components/review-queue/sparkline';
import StatCard, { StatValue } from '@/components/review-queue/stat-card';

export type DocumentArchiveStatsData = {
    total: { total: number; approved: number; rejected: number };
    byFormType: { form_type: string; label: string; count: number }[];
    perWeek: { thisWeek: number; weeks: number[] };
    topOrganization: {
        id: number;
        name: string;
        approved: number;
        rejected: number;
        total: number;
    } | null;
};

function TotalDecidedCard({ data }: { data: DocumentArchiveStatsData['total'] }) {
    return (
        <StatCard icon={Archive} title="Total decided">
            <StatValue>{data.total}</StatValue>
            <SegmentedBar
                ariaLabel={`Total decided: ${data.approved} approved, ${data.rejected} rejected`}
                segments={[
                    { label: 'Approved', count: data.approved, className: 'bg-success' },
                    { label: 'Rejected', count: data.rejected, className: 'bg-destructive' },
                ]}
            />
        </StatCard>
    );
}

function ByFormTypeCard({ rows }: { rows: DocumentArchiveStatsData['byFormType'] }) {
    const max = Math.max(1, ...rows.map((r) => r.count));

    return (
        <StatCard icon={FileText} title="By form type">
            <ul className="flex flex-col gap-2.5">
                {rows.map((row) => (
                    <li
                        key={row.form_type}
                        className="grid grid-cols-[minmax(0,1fr)_4.5rem_1.5rem] items-center gap-3 text-sm"
                    >
                        <span className="min-w-0 break-words">{row.label}</span>
                        <span aria-hidden className="h-1.5 overflow-hidden rounded-full bg-muted">
                            <span
                                className="block h-full rounded-full bg-primary-text"
                                style={{ width: `${(row.count / max) * 100}%` }}
                            />
                        </span>
                        <span className="text-right font-semibold tabular-nums">{row.count}</span>
                    </li>
                ))}
            </ul>
        </StatCard>
    );
}

function DecidedPerWeekCard({ data }: { data: DocumentArchiveStatsData['perWeek'] }) {
    return (
        <StatCard icon={BarChart3} title="Decided per week">
            <div className="flex items-end justify-between gap-3">
                <StatValue>{data.thisWeek}</StatValue>
                <span className="text-sm text-muted-foreground">this week</span>
            </div>
            <Sparkline
                values={data.weeks}
                label={`Documents decided per week, last ${data.weeks.length} weeks: ${data.weeks.join(', ')}`}
            />
            <p className="text-sm text-muted-foreground">Last {data.weeks.length} weeks</p>
        </StatCard>
    );
}

function MostDocumentsCard({ data }: { data: DocumentArchiveStatsData['topOrganization'] }) {
    return (
        <StatCard icon={Building2} title="Most documents">
            {data ? (
                <>
                    <p className="text-2xl leading-tight font-semibold break-words">{data.name}</p>
                    <dl className="mt-auto grid grid-cols-[1fr_auto] gap-x-6 gap-y-1.5 text-sm">
                        <dt className="text-muted-foreground">Approved</dt>
                        <dd className="text-right font-semibold tabular-nums">{data.approved}</dd>
                        <dt className="text-muted-foreground">Rejected</dt>
                        <dd className="text-right font-semibold tabular-nums">{data.rejected}</dd>
                    </dl>
                </>
            ) : (
                <p className="text-sm text-muted-foreground">No documents have been decided yet.</p>
            )}
        </StatCard>
    );
}

/** The four archive cards. Whole-archive figures: filtering the table never changes them. */
export default function DocumentArchiveStats({ stats }: { stats: DocumentArchiveStatsData }) {
    return (
        <section aria-label="Document archive figures" className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <TotalDecidedCard data={stats.total} />
            <ByFormTypeCard rows={stats.byFormType} />
            <DecidedPerWeekCard data={stats.perWeek} />
            <MostDocumentsCard data={stats.topOrganization} />
        </section>
    );
}
