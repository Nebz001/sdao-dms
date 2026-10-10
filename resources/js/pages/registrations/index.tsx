import { Head } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import {
    DocumentRow,
    ListCard,
    ListEmptyState,
    ListFooter,
    ListSearch,
    NoMatches,
    StartAction,
    StatusTabs,
} from '@/components/student-list';
import type { CanStart } from '@/components/student-list';
import StudentPageHeader from '@/components/student-page-header';
import { Skeleton } from '@/components/ui/skeleton';
import { useServerList } from '@/hooks/use-server-list';
import { formatListDate, isTabKey, statusNote } from '@/lib/student-list';
import type { TabKey } from '@/lib/student-list';
import * as registrationRoutes from '@/routes/registrations';

type Registration = {
    id: number;
    title: string;
    status: string;
    organization: { id: number; name: string };
    created_at: string;
    current_approver: string | null;
    href: string;
};

type Props = {
    registrations: {
        data: Registration[];
        meta: {
            current_page: number;
            last_page: number;
            from: number | null;
            to: number | null;
            total: number;
        };
        links: { prev: string | null; next: string | null };
    };
    filters: { status: string | null; search: string };
    stats: {
        total: number;
        inProgress: number;
        inReview: number;
        approved: number;
        returned: number;
        rejected: number;
    };
    canStart: CanStart;
};

const TABS: TabKey[] = ['all', 'in_review', 'approved', 'returned'];

export default function RegistrationsIndex({
    registrations,
    filters,
    stats,
    canStart,
}: Props) {
    const list = useServerList({
        url: registrationRoutes.index().url,
        only: ['registrations', 'filters', 'stats'],
        initial: { status: filters.status ?? '', search: filters.search },
    });
    const tab: TabKey = isTabKey(list.filters.status)
        ? list.filters.status
        : 'all';
    const { meta } = registrations;
    const neverFiled = stats.total === 0 && !list.isFiltered;

    return (
        <>
            <Head title="Registrations" />

            <div className="flex flex-col gap-6">
                <StudentPageHeader
                    icon={Building2}
                    title="Registrations"
                    subtitle="Register your organization with SDAO and check its status"
                    actions={
                        neverFiled ? undefined : (
                            <StartAction
                                canStart={canStart}
                                href={registrationRoutes.create().url}
                                label="New Registration"
                            />
                        )
                    }
                />

                {neverFiled ? (
                    <ListEmptyState
                        icon={Building2}
                        title="No registrations yet"
                        description="Register your organization with SDAO, then follow its review here. It only takes three steps."
                        steps={[
                            'Fill in the form',
                            'Attach requirements',
                            'Send to SDAO',
                        ]}
                        canStart={canStart}
                        startHref={registrationRoutes.create().url}
                        startLabel="New Registration"
                    />
                ) : (
                    <ListCard
                        toolbar={
                            <>
                                <StatusTabs
                                    tabs={TABS}
                                    counts={{
                                        all: stats.total,
                                        in_review: stats.inReview,
                                        approved: stats.approved,
                                        returned: stats.returned,
                                        draft: 0,
                                    }}
                                    value={tab}
                                    onChange={(next) =>
                                        list.set(
                                            'status',
                                            next === 'all' ? '' : next,
                                        )
                                    }
                                />
                                <ListSearch
                                    value={list.filters.search}
                                    onChange={(value) =>
                                        list.set('search', value)
                                    }
                                    placeholder="Search registrations"
                                />
                            </>
                        }
                        footer={
                            <ListFooter
                                showing={
                                    meta.from === null || meta.to === null
                                        ? 0
                                        : meta.to - meta.from + 1
                                }
                                total={meta.total}
                                pagination={{
                                    prev: registrations.links.prev,
                                    next: registrations.links.next,
                                    onNavigate: list.goToPage,
                                }}
                            />
                        }
                    >
                        {list.loading ? (
                            <div className="space-y-3 p-5" aria-busy="true">
                                {Array.from({ length: 3 }).map((_, i) => (
                                    <Skeleton key={i} className="h-12 w-full" />
                                ))}
                            </div>
                        ) : registrations.data.length === 0 ? (
                            <NoMatches onClear={list.clear} />
                        ) : (
                            <ul>
                                {registrations.data.map((row) => (
                                    <DocumentRow
                                        key={row.id}
                                        icon={Building2}
                                        title={row.title}
                                        supporting={
                                            <span>
                                                {row.status === 'draft' ? 'Started' : 'Submitted'}{' '}
                                                {formatListDate(row.created_at)}
                                            </span>
                                        }
                                        status={row.status}
                                        note={statusNote(
                                            row.status,
                                            'registration',
                                            row.current_approver,
                                        )}
                                        actionLabel={
                                            row.status === 'returned'
                                                ? 'Revise'
                                                : 'View'
                                        }
                                        actionHref={
                                            row.status === 'returned'
                                                ? registrationRoutes.edit({
                                                      document: row.id,
                                                  }).url
                                                : row.href
                                        }
                                    />
                                ))}
                            </ul>
                        )}
                    </ListCard>
                )}
            </div>
        </>
    );
}
