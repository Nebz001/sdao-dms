import { Deferred, Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    FileText,
    ListChecks,
    RefreshCw,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useMemo, useState } from 'react';
import DeactivateOfficerAccountDialog from '@/components/deactivate-officer-account-dialog';
import ErrorBoundary from '@/components/error-boundary';
import PageHeader from '@/components/page-header';
import PageNotice from '@/components/page-notice';
import DataTable, { RowViewButton } from '@/components/review-queue/data-table';
import type { DataColumn } from '@/components/review-queue/data-table';
import { SectionCard } from '@/components/review-queue/queue-tables';
import StatCard, {
    StatCardSkeleton,
    StatValue,
} from '@/components/review-queue/stat-card';
import ThinProgress from '@/components/review-queue/thin-progress';
import { formatDate, pluralDays } from '@/components/review-queue/types';
import {
    OrganizationStatusBadge,
    RequirementBadge,
    StatusBadge,
} from '@/components/status-badge';
import TagBadge from '@/components/tag-badge';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { NO_SCHOOL_LABEL } from '@/lib/school';
import { cn } from '@/lib/utils';
import * as organizations from '@/routes/admin/organizations';

type Banner =
    | {
          type: 'pending';
          kind: 'registration' | 'renewal';
          documentStatus: string;
          waitingDays: number;
          tier: string;
          href: string | null;
      }
    | {
          type: 'needs_renewal';
          coveredThrough: string | null;
          window: { open: boolean; label: string };
          outstanding: string[];
      }
    | {
          type: 'inactive';
          reason: 'no_approved_registration' | 'no_active_officers';
          since: string | null;
      };

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

type DocumentRef = {
    id: number;
    title: string;
    type: string;
    status: string;
    href: string | null;
};

type RequirementRow = {
    key: string;
    label: string;
    met: boolean;
    detail: string | null;
    /** Muted line under `detail`, e.g. the academic year a registration covers. */
    detailNote: string | null;
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

type Officer = {
    id: number;
    user_id: number;
    position: string;
    name: string;
    id_number: string | null;
    since: string | null;
    remaining_officers: number;
};

type Props = {
    /** Null when the id matches no organization (the response is a 404). */
    organization: { id: number; name: string } | null;
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
        const days = banner.waitingDays;
        const since = days === 0 ? 'since today' : `for ${pluralDays(days)}`;
        const copy: Record<
            string,
            { title: string; detail: string; action: string | null }
        > = {
            in_review: {
                title: `A ${banner.kind} is waiting for SDAO review.`,
                detail: `It has been waiting ${since}.`,
                action: 'Open in review queue',
            },
            returned: {
                title: `A ${banner.kind} was returned for revision.`,
                detail: `It has been with the students ${since}.`,
                action: `Open ${banner.kind}`,
            },
            draft: {
                title: `A ${banner.kind} has been started but not submitted.`,
                detail: 'Nothing is waiting on SDAO yet.',
                action: null,
            },
        };
        const { title, detail, action } =
            copy[banner.documentStatus] ?? copy.in_review;

        return (
            <PageNotice
                tone={
                    banner.documentStatus === 'in_review' &&
                    banner.tier === 'overdue'
                        ? 'warning'
                        : 'info'
                }
                title={title}
                action={
                    banner.href &&
                    action && (
                        <Button asChild size="sm">
                            <Link href={banner.href}>{action}</Link>
                        </Button>
                    )
                }
            >
                {detail}
            </PageNotice>
        );
    }

    if (banner.type === 'needs_renewal') {
        return (
            <PageNotice tone="warning" title="Renewal needed.">
                {banner.coveredThrough
                    ? `Coverage ended with ${banner.coveredThrough}. `
                    : ''}
                {banner.window.open
                    ? `The renewal window is open and closes ${banner.window.label}.`
                    : `The next renewal window opens ${banner.window.label}.`}
                {(banner.outstanding ?? []).length > 0 &&
                    ` Still outstanding: ${(banner.outstanding ?? []).join(', ').toLowerCase()}.`}
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

function Tile({
    icon,
    title,
    value,
    note,
}: {
    icon: typeof Users;
    title: string;
    value: ReactNode;
    note?: string | null;
}) {
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
            <Tile
                icon={ListChecks}
                title="Requirements met"
                value={`${tiles.requirementsMet} of ${tiles.requirementsTotal}`}
            />
            <Tile
                icon={Users}
                title="Officers"
                value={tiles.officerCount}
                note="Active president and secretary"
            />
            <Tile
                icon={FileText}
                title="Date registered"
                value={
                    tiles.registeredOn
                        ? formatDate(tiles.registeredOn)
                        : 'Not yet'
                }
                note={tiles.registeredOn ? null : 'No registration approved'}
            />
            <Tile
                icon={RefreshCw}
                title="Renewal"
                value={tiles.renewal.value}
                note={tiles.renewal.note}
            />
        </>
    );
}

function TilesSkeleton() {
    return (
        <>
            <StatCardSkeleton
                compact
                icon={ListChecks}
                title="Requirements met"
            />
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

function EmptySection({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
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
    {
        key: 'label',
        header: 'Requirement',
        slot: 'title',
        cell: (r) => r.label,
    },
    {
        key: 'met',
        header: 'Status',
        slot: 'badge',
        cell: (r) => (
            <RequirementBadge status={r.met ? 'done' : 'action_needed'} />
        ),
    },
    {
        key: 'evidence',
        header: 'Satisfied by',
        cell: (r) =>
            r.key === 'registration_approved' ? (
                r.detail ? (
                    <span className="flex flex-col">
                        <span>{r.detail}</span>
                        {r.detailNote && (
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {r.detailNote}
                            </span>
                        )}
                    </span>
                ) : (
                    DASH
                )
            ) : r.document ? (
                <span className="flex flex-col">
                    <span className="font-medium">{r.document.title}</span>
                    <span className="text-xs text-muted-foreground">
                        {r.document.type}
                    </span>
                </span>
            ) : (
                (r.detail ?? DASH)
            ),
    },
    {
        key: 'date',
        header: 'Approved',
        className: 'tabular-nums',
        cell: (r) => (r.date ? formatDate(r.date) : DASH),
    },
    {
        key: 'actions',
        header: 'Action',
        slot: 'action',
        align: 'right',
        cell: (r) =>
            r.document ? (
                <RowViewButton
                    href={r.document.href}
                    label={r.document.title}
                />
            ) : null,
    },
];

function RequirementsSection({ rows }: { rows: RequirementRow[] }) {
    const met = rows.filter((r) => r.met).length;

    return (
        <SectionCard
            title="Requirements"
            aside={`${met} of ${rows.length} met`}
        >
            <div className="flex flex-col gap-4">
                <ThinProgress
                    value={met}
                    max={rows.length}
                    label="Requirements met"
                    fillClassName={
                        rows.length > 0 && met / rows.length >= 0.6
                            ? 'bg-success'
                            : 'bg-warning'
                    }
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
    {
        key: 'title',
        header: 'Document',
        slot: 'title',
        cell: (d) => <span className="font-semibold">{d.title}</span>,
    },
    {
        key: 'status',
        header: 'Status',
        slot: 'badge',
        cell: (d) => <StatusBadge status={d.status} />,
    },
    { key: 'type', header: 'Type', cell: (d) => d.type },
    { key: 'period', header: 'Term', cell: (d) => d.period?.label ?? DASH },
    {
        key: 'submitted',
        header: 'Submitted',
        className: 'tabular-nums',
        cell: (d) => formatDate(d.submitted_at),
    },
    {
        key: 'actions',
        header: 'Action',
        slot: 'action',
        align: 'right',
        cell: (d) => <RowViewButton href={d.href} label={d.title} />,
    },
];

function DocumentsSection({ data }: { data: NonNullable<Props['documents']> }) {
    const [period, setPeriod] = useState(ALL_PERIODS);
    const allRows = useMemo(() => data.rows ?? [], [data.rows]);
    const periods = data.periods ?? [];
    const rows = useMemo(
        () =>
            period === ALL_PERIODS
                ? allRows
                : allRows.filter((d) => d.period?.key === period),
        [allRows, period],
    );

    const filter = periods.length > 1 && (
        <Select value={period} onValueChange={setPeriod}>
            <SelectTrigger
                aria-label="Filter by term"
                size="sm"
                className="w-48"
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={ALL_PERIODS}>All terms</SelectItem>
                {periods.map((p) => (
                    <SelectItem key={p.key} value={p.key}>
                        {p.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );

    return (
        <SectionCard
            title="Submitted documents"
            count={allRows.length}
            aside={filter || undefined}
        >
            {rows.length === 0 ? (
                <EmptySection
                    title={
                        allRows.length === 0
                            ? 'No documents submitted yet'
                            : 'No documents in this term'
                    }
                    description={
                        allRows.length === 0
                            ? 'Registrations, calendars, proposals and reports appear here once the organization submits them.'
                            : 'Choose another term to see its documents.'
                    }
                />
            ) : (
                <DataTable
                    rows={rows}
                    columns={DOCUMENT_COLUMNS}
                    rowKey={(d) => d.id}
                />
            )}
        </SectionCard>
    );
}

function officerColumns(organizationName: string): DataColumn<Officer>[] {
    return [
        { key: 'name', header: 'Name', slot: 'title', cell: (o) => o.name },
        {
            key: 'position',
            header: 'Position',
            slot: 'badge',
            cell: (o) => o.position,
        },
        {
            key: 'id_number',
            header: 'ID number',
            cell: (o) => o.id_number ?? DASH,
        },
        {
            key: 'since',
            header: 'Since',
            className: 'tabular-nums',
            cell: (o) => (o.since ? formatDate(o.since) : DASH),
        },
        {
            key: 'actions',
            header: 'Action',
            slot: 'action',
            cell: (o) => (
                <DeactivateOfficerAccountDialog
                    officer={o}
                    organizationName={organizationName}
                />
            ),
        },
    ];
}

function OfficersSection({
    rows,
    organizationName,
}: {
    rows: Officer[];
    organizationName: string;
}) {
    return (
        <SectionCard title="Officers" count={rows.length}>
            {rows.length === 0 ? (
                <EmptySection
                    title="No officers on record"
                    description="The adviser binds a president and a secretary once the organization is approved."
                />
            ) : (
                <DataTable
                    rows={rows}
                    columns={officerColumns(organizationName)}
                    rowKey={(o) => o.id}
                />
            )}
        </SectionCard>
    );
}

function NotFound() {
    return (
        <Empty>
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <Building2 />
                </EmptyMedia>
                <EmptyTitle>Organization not found</EmptyTitle>
                <EmptyDescription>
                    No organization has this id. It may have been removed, or
                    the link may be wrong.
                </EmptyDescription>
            </EmptyHeader>
            <Button asChild size="sm">
                <Link href={organizations.index().url}>
                    <ArrowLeft data-icon="inline-start" />
                    Back to organizations
                </Link>
            </Button>
        </Empty>
    );
}

type LoadedProps = Omit<Props, 'organization'> & {
    organization: NonNullable<Props['organization']>;
};

/** Every section is its own Deferred: skeleton while loading, content once it lands. */
function OrganizationDetail({
    organization,
    summary,
    requirements,
    documents,
    officers,
}: LoadedProps) {
    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-col gap-3">
                <Button
                    asChild
                    variant="ghost"
                    size="sm"
                    className="self-start"
                >
                    <Link href={organizations.index().url}>
                        <ArrowLeft data-icon="inline-start" />
                        Back to organizations
                    </Link>
                </Button>
                <Deferred
                    data="summary"
                    fallback={
                        <PageHeader
                            title={organization.name}
                            badge={<Skeleton className="h-5 w-24" />}
                            subtitle="Status, requirements and submitted documents"
                        />
                    }
                >
                    <PageHeader
                        title={organization.name}
                        badge={
                            summary ? (
                                <span className="flex flex-wrap items-center gap-2">
                                    <OrganizationStatusBadge
                                        status={summary.status}
                                    />
                                    <TagBadge>
                                        {summary.school ?? NO_SCHOOL_LABEL}
                                    </TagBadge>
                                    {summary.program && (
                                        <TagBadge>{summary.program}</TagBadge>
                                    )}
                                </span>
                            ) : undefined
                        }
                        subtitle="Status, requirements and submitted documents"
                    />
                </Deferred>
            </div>

            <Deferred
                data="summary"
                fallback={<Skeleton className="h-14 w-full rounded-lg" />}
            >
                {summary?.banner ? (
                    <StatusBanner banner={summary.banner} />
                ) : null}
            </Deferred>

            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <Deferred data="summary" fallback={<TilesSkeleton />}>
                    {summary?.tiles ? <Tiles tiles={summary.tiles} /> : null}
                </Deferred>
            </div>

            <Deferred
                data="requirements"
                fallback={<SectionSkeleton title="Requirements" />}
            >
                <RequirementsSection rows={requirements ?? []} />
            </Deferred>

            <Deferred
                data="documents"
                fallback={<SectionSkeleton title="Submitted documents" />}
            >
                <DocumentsSection
                    data={documents ?? { rows: [], periods: [] }}
                />
            </Deferred>

            <Deferred
                data="officers"
                fallback={<SectionSkeleton title="Officers" />}
            >
                <OfficersSection
                    rows={officers ?? []}
                    organizationName={organization.name}
                />
            </Deferred>
        </div>
    );
}

export default function OrganizationShow(props: Props) {
    const name = props.organization?.name;

    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [
                { title: 'Admin' },
                { title: 'Organizations', href: organizations.index() },
                { title: name ?? 'Not found' },
            ],
        });
    }, [name]);

    return (
        <>
            <Head title={name ?? 'Organization not found'} />

            <ErrorBoundary
                backHref={organizations.index().url}
                backLabel="Back to organizations"
            >
                {props.organization ? (
                    <OrganizationDetail
                        {...props}
                        organization={props.organization}
                    />
                ) : (
                    <NotFound />
                )}
            </ErrorBoundary>
        </>
    );
}

OrganizationShow.layout = {
    breadcrumbs: [
        { title: 'Admin' },
        { title: 'Organizations', href: organizations.index() },
        { title: 'Organization' },
    ],
};
