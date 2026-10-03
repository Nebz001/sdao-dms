import { Deferred, Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, FileText, ListChecks, RefreshCw, Users } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useMemo, useState } from 'react';
import PageHeader from '@/components/page-header';
import PageNotice from '@/components/page-notice';
import DataTable, { RowViewButton } from '@/components/review-queue/data-table';
import type { DataColumn } from '@/components/review-queue/data-table';
import { SectionCard } from '@/components/review-queue/queue-tables';
import StatCard, { StatCardSkeleton, StatValue } from '@/components/review-queue/stat-card';
import ThinProgress from '@/components/review-queue/thin-progress';
import { formatDate, pluralDays } from '@/components/review-queue/types';
import { OrganizationStatusBadge, RequirementBadge, StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import * as organizations from '@/routes/admin/organizations';

type Banner =
    | { type: 'pending'; kind: 'registration' | 'renewal'; documentStatus: string; waitingDays: number; tier: string; href: string | null }
    | { type: 'needs_renewal'; coveredThrough: string | null; window: { open: boolean; label: string }; outstanding: string[] }
    | { type: 'inactive'; reason: 'no_approved_registration' | 'no_active_officers'; since: string | null };

type Summary = {
    status: string;
    school: string | null;
    program: string | null;
    banner: Banner | null;
    tiles: {
        requirementsMet: number;
        requirementsTotal: number;
        officerCount: number;
        registeredOn: string | null;
        renewal: { value: string; note: string | null };
    };
};

type DocumentRef = { id: number; title: string; type: string; status: string; href: string | null };

type RequirementRow = {
    key: string;
    label: string;
    met: boolean;
    detail: string | null;
    date: string | null;
    document: DocumentRef | null;
};

type Period = { key: string; label: string };

type DocumentRow = {
    id: number;
    title: string;
    type: string;
    period: Period;
    submitted_at: string;
    status: string;
    href: string | null;
};

type Officer = { id: number; position: string; name: string; id_number: string | null; since: string | null };

type Props = {
    organization: { id: number; name: string };
    /** Each section is deferred and shows its own skeleton until it lands. */
    summary?: Summary;
    requirements?: RequirementRow[];
    documents?: { rows: DocumentRow[]; periods: Period[] };
    officers?: Officer[];
};

const ALL_PERIODS = 'all';
const DASH = '—';

function StatusBanner({ banner }: { banner: Banner }) {
    if (banner.type === 'pending') {
        const inReview = banner.documentStatus === 'in_review';

        return (
            <PageNotice
                tone={banner.tier === 'overdue' ? 'warning' : 'info'}
                title={
                    inReview
                        ? `A ${banner.kind} is waiting for SDAO review.`
                        : `A ${banner.kind} was returned for revision.`
                }
                action={
                    banner.href && (
                        <Button asChild size="sm">
                            <Link href={banner.href}>{inReview ? 'Open in review queue' : `Open ${banner.kind}`}</Link>
                        </Button>
                    )
                }
            >
                {inReview ? 'It has been waiting' : 'It has been with the students for'} {pluralDays(banner.waitingDays).toLowerCase()}.
            </PageNotice>
        );
    }

    if (banner.type === 'needs_renewal') {
        return (
            <PageNotice tone="warning" title="Renewal needed.">
                {banner.coveredThrough ? `Coverage ended with ${banner.coveredThrough}. ` : ''}
                {banner.window.open
                    ? `The renewal window is open and closes ${banner.window.label}.`
                    : `The next renewal window opens ${banner.window.label}.`}
                {banner.outstanding.length > 0 && ` Still outstanding: ${banner.outstanding.join(', ').toLowerCase()}.`}
            </PageNotice>
        );
    }

    return (
        <PageNotice tone="neutral" title="This organization is inactive.">
            {banner.reason === 'no_active_officers'
                ? `It has no active officers${banner.since ? ` since ${formatDate(banner.since)}` : ''}.`
                : `No approved registration is on record${banner.since ? ` (created ${formatDate(banner.since)})` : ''}.`}{' '}
            The records below are kept as history.
        </PageNotice>
    );
}

function Tile({ icon, title, value, note }: { icon: typeof Users; title: string; value: ReactNode; note?: string | null }) {
    return (
        <StatCard icon={icon} title={title} compact>
            <StatValue size="sm">{value}</StatValue>
            {note && <p className="text-xs text-muted-foreground">{note}</p>}
        </StatCard>
    );
}

function Tiles({ tiles }: { tiles: Summary['tiles'] }) {
    return (
        <>
            <Tile icon={ListChecks} title="Requirements met" value={`${tiles.requirementsMet} of ${tiles.requirementsTotal}`} />
            <Tile icon={Users} title="Officers" value={tiles.officerCount} note="Active president and secretary" />
            <Tile
                icon={FileText}
                title="Date registered"
                value={tiles.registeredOn ? formatDate(tiles.registeredOn) : 'Not yet'}
                note={tiles.registeredOn ? null : 'No registration approved'}
            />
            <Tile icon={RefreshCw} title="Renewal" value={tiles.renewal.value} note={tiles.renewal.note} />
        </>
    );
}

function TilesSkeleton() {
    return (
        <>
            <StatCardSkeleton compact icon={ListChecks} title="Requirements met" />
            <StatCardSkeleton compact icon={Users} title="Officers" />
            <StatCardSkeleton compact icon={FileText} title="Date registered" />
            <StatCardSkeleton compact icon={RefreshCw} title="Renewal" />
        </>
    );
}

function SectionSkeleton({ title }: { title: string }) {
    return (
        <SectionCard title={title}>
            <div aria-busy="true" className="flex flex-col gap-3">
                {[0, 1, 2].map((i) => (
                    <Skeleton key={i} className="h-10 w-full" />
                ))}
            </div>
        </SectionCard>
    );
}

function EmptySection({ title, description }: { title: string; description: string }) {
    return (
        <Empty>
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <FileText />
                </EmptyMedia>
                <EmptyTitle>{title}</EmptyTitle>
                <EmptyDescription>{description}</EmptyDescription>
            </EmptyHeader>
        </Empty>
    );
}

const REQUIREMENT_COLUMNS: DataColumn<RequirementRow>[] = [
    { key: 'label', header: 'Requirement', slot: 'title', cell: (r) => r.label },
    {
        key: 'met',
        header: 'Status',
        slot: 'badge',
        cell: (r) => <RequirementBadge status={r.met ? 'done' : 'action_needed'} />,
    },
    {
        key: 'evidence',
        header: 'Satisfied by',
        cell: (r) =>
            r.document ? (
                <span className="flex flex-col">
                    <span className="font-medium">{r.document.title}</span>
                    <span className="text-xs text-muted-foreground">{r.document.type}</span>
                </span>
            ) : (
                (r.detail ?? DASH)
            ),
    },
    { key: 'date', header: 'Approved', className: 'tabular-nums', cell: (r) => (r.date ? formatDate(r.date) : DASH) },
    {
        key: 'actions',
        header: 'Actions',
        slot: 'action',
        align: 'right',
        cell: (r) => (r.document ? <RowViewButton href={r.document.href} label={r.document.title} /> : null),
    },
];

function RequirementsSection({ rows }: { rows: RequirementRow[] }) {
    const met = rows.filter((r) => r.met).length;

    return (
        <SectionCard title="Requirements" aside={`${met} of ${rows.length} met`}>
            <div className="flex flex-col gap-4">
                <ThinProgress
                    value={met}
                    max={rows.length}
                    label="Requirements met"
                    fillClassName={rows.length > 0 && met / rows.length >= 0.6 ? 'bg-success' : 'bg-warning'}
                />
                <DataTable
                    rows={rows}
                    columns={REQUIREMENT_COLUMNS}
                    rowKey={(r) => r.key}
                    rowClassName={(r) => cn(!r.met && 'text-muted-foreground')}
                />
            </div>
        </SectionCard>
    );
}

const DOCUMENT_COLUMNS: DataColumn<DocumentRow>[] = [
    { key: 'title', header: 'Document', slot: 'title', cell: (d) => <span className="font-semibold">{d.title}</span> },
    { key: 'status', header: 'Status', slot: 'badge', cell: (d) => <StatusBadge status={d.status} /> },
    { key: 'type', header: 'Type', cell: (d) => d.type },
    { key: 'period', header: 'Term', cell: (d) => d.period.label },
    { key: 'submitted', header: 'Submitted', className: 'tabular-nums', cell: (d) => formatDate(d.submitted_at) },
    {
        key: 'actions',
        header: 'Actions',
        slot: 'action',
        align: 'right',
        cell: (d) => <RowViewButton href={d.href} label={d.title} />,
    },
];

function DocumentsSection({ data }: { data: NonNullable<Props['documents']> }) {
    const [period, setPeriod] = useState(ALL_PERIODS);
    const rows = useMemo(
        () => (period === ALL_PERIODS ? data.rows : data.rows.filter((d) => d.period.key === period)),
        [data.rows, period],
    );

    const filter = data.periods.length > 1 && (
        <Select value={period} onValueChange={setPeriod}>
            <SelectTrigger aria-label="Filter by term" size="sm" className="w-48">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={ALL_PERIODS}>All terms</SelectItem>
                {data.periods.map((p) => (
                    <SelectItem key={p.key} value={p.key}>
                        {p.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );

    return (
        <SectionCard title="Submitted documents" count={data.rows.length} aside={filter || undefined}>
            {rows.length === 0 ? (
                <EmptySection
                    title={data.rows.length === 0 ? 'No documents submitted yet' : 'No documents in this term'}
                    description={
                        data.rows.length === 0
                            ? 'Registrations, calendars, proposals and reports appear here once the organization submits them.'
                            : 'Choose another term to see its documents.'
                    }
                />
            ) : (
                <DataTable rows={rows} columns={DOCUMENT_COLUMNS} rowKey={(d) => d.id} />
            )}
        </SectionCard>
    );
}

const OFFICER_COLUMNS: DataColumn<Officer>[] = [
    { key: 'name', header: 'Name', slot: 'title', cell: (o) => o.name },
    { key: 'position', header: 'Position', slot: 'badge', cell: (o) => o.position },
    { key: 'id_number', header: 'ID number', cell: (o) => o.id_number ?? DASH },
    { key: 'since', header: 'Since', className: 'tabular-nums', cell: (o) => (o.since ? formatDate(o.since) : DASH) },
];

function OfficersSection({ rows }: { rows: Officer[] }) {
    return (
        <SectionCard title="Officers" count={rows.length}>
            {rows.length === 0 ? (
                <EmptySection
                    title="No officers on record"
                    description="The adviser binds a president and a secretary once the organization is approved."
                />
            ) : (
                <DataTable rows={rows} columns={OFFICER_COLUMNS} rowKey={(o) => o.id} />
            )}
        </SectionCard>
    );
}

export default function OrganizationShow({ organization, summary, requirements, documents, officers }: Props) {
    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: 'Admin' },
                { title: 'Organizations', href: organizations.index() },
                { title: organization.name },
            ],
        });
    }, [organization.name]);

    return (
        <>
            <Head title={organization.name} />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-3">
                    <Button asChild variant="ghost" size="sm" className="self-start">
                        <Link href={organizations.index().url}>
                            <ArrowLeft data-icon="inline-start" />
                            Back to organizations
                        </Link>
                    </Button>
                    <Deferred
                        data="summary"
                        fallback={<PageHeader title={organization.name} badge={<Skeleton className="h-5 w-24" />} subtitle={<Skeleton className="h-4 w-64" />} />}
                    >
                        {summary && (
                            <PageHeader
                                title={organization.name}
                                badge={<OrganizationStatusBadge status={summary.status} />}
                                subtitle={[summary.school ?? 'No college', summary.program ?? 'No program'].join(' · ')}
                            />
                        )}
                    </Deferred>
                </div>

                <Deferred data="summary" fallback={null}>
                    {summary?.banner && <StatusBanner banner={summary.banner} />}
                </Deferred>

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <Deferred data="summary" fallback={<TilesSkeleton />}>
                        {summary && <Tiles tiles={summary.tiles} />}
                    </Deferred>
                </div>

                <Deferred data="requirements" fallback={<SectionSkeleton title="Requirements" />}>
                    {requirements && <RequirementsSection rows={requirements} />}
                </Deferred>

                <Deferred data="documents" fallback={<SectionSkeleton title="Submitted documents" />}>
                    {documents && <DocumentsSection data={documents} />}
                </Deferred>

                <Deferred data="officers" fallback={<SectionSkeleton title="Officers" />}>
                    {officers && <OfficersSection rows={officers} />}
                </Deferred>
            </div>
        </>
    );
}

OrganizationShow.layout = {
    breadcrumbs: [{ title: 'Admin' }, { title: 'Organizations', href: organizations.index() }, { title: 'Organization' }],
};
