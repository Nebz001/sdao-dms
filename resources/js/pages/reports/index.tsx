import { Head } from '@inertiajs/react';
import { FileCheck } from 'lucide-react';
import ClientListPage from '@/components/client-list-page';
import type { ListRow } from '@/components/client-list-page';
import type { CanStart } from '@/components/student-list';
import * as reportRoutes from '@/routes/reports';

type Props = {
    reports: ListRow[];
    canStart: CanStart;
};

export default function ReportsIndex({ reports, canStart }: Props) {
    return (
        <>
            <Head title="Reports" />
            <ClientListPage
                kind="report"
                icon={FileCheck}
                tone="green"
                title="After-Activity Reports"
                subtitle="Report how each approved activity went"
                searchPlaceholder="Search reports"
                startLabel="New Report"
                startHref={reportRoutes.create().url}
                canStart={canStart}
                rows={reports}
                dateLabel="Submitted"
                empty={{
                    title: 'No reports yet',
                    description:
                        'After an approved activity takes place, report how it went with photos and results.',
                    steps: [
                        'Pick the activity',
                        'Add photos and results',
                        'Send for review',
                    ],
                }}
                rowAction={(row) =>
                    row.status === 'returned'
                        ? {
                              label: 'Revise',
                              href: reportRoutes.edit({ document: row.id }).url,
                          }
                        : {
                              label: 'View',
                              href: reportRoutes.show({ document: row.id }).url,
                          }
                }
            />
        </>
    );
}
