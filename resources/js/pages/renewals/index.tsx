import { Head } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import ClientListPage from '@/components/client-list-page';
import type { ListRow } from '@/components/client-list-page';
import type { CanStart } from '@/components/student-list';
import * as renewalRoutes from '@/routes/renewals';

type Props = {
    renewals: ListRow[];
    canStart: CanStart;
};

export default function RenewalsIndex({ renewals, canStart }: Props) {
    return (
        <>
            <Head title="Renewals" />
            <ClientListPage
                kind="renewal"
                icon={RefreshCw}
                tone="purple"
                title="Renewals"
                subtitle="Renew your organization each school year to keep it active"
                searchPlaceholder="Search renewals"
                startLabel="Start a Renewal"
                startHref={renewalRoutes.create().url}
                canStart={canStart}
                rows={renewals}
                dateLabel="Submitted"
                empty={{
                    title: 'No renewals yet',
                    description:
                        'When it’s time to renew your organization for a new school year, start it here. It only takes three steps.',
                    steps: [
                        'Fill in the form',
                        'Attach requirements',
                        'Send to SDAO',
                    ],
                }}
                rowAction={(row) =>
                    row.status === 'returned'
                        ? {
                              label: 'Revise',
                              href: renewalRoutes.edit({ document: row.id })
                                  .url,
                          }
                        : {
                              label: 'View',
                              href: renewalRoutes.show({ document: row.id })
                                  .url,
                          }
                }
            />
        </>
    );
}
