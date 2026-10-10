import { Head } from '@inertiajs/react';
import { CalendarPlus } from 'lucide-react';
import ClientListPage from '@/components/client-list-page';
import type { ListRow } from '@/components/client-list-page';
import { RowChip } from '@/components/student-list';
import type { CanStart } from '@/components/student-list';
import * as activityProposals from '@/routes/activity-proposals';

type Proposal = ListRow & {
    calendar_mode: string | null;
    form_step: number | null;
};

type Props = {
    proposals: Proposal[];
    canStart: CanStart;
};

export default function ActivityProposalsIndex({ proposals, canStart }: Props) {
    return (
        <>
            <Head title="Activity Proposals" />
            <ClientListPage
                kind="proposal"
                icon={CalendarPlus}
                title="Activity Proposals"
                subtitle="Ask approval for your events and see where each one is"
                searchPlaceholder="Search by activity name"
                startLabel="New Proposal"
                startHref={activityProposals.create().url}
                canStart={canStart}
                rows={proposals}
                dateLabel="Submitted"
                withDrafts
                empty={{
                    title: 'No proposals yet',
                    description:
                        'Ask for approval before you hold an event or activity. Once you send a proposal, you can follow it here.',
                    steps: [
                        'Fill in the request form',
                        'Add the details',
                        'Send for approval',
                    ],
                }}
                rowChip={(row) =>
                    row.calendar_mode === 'on_calendar' ? (
                        <RowChip>In activity calendar</RowChip>
                    ) : row.calendar_mode === 'off_calendar' ? (
                        <RowChip>Not in activity calendar</RowChip>
                    ) : null
                }
                rowAction={(row) =>
                    row.status === 'draft'
                        ? {
                              label: 'Continue',
                              href: activityProposals.continueMethod({
                                  document: row.id,
                              }).url,
                          }
                        : row.status === 'returned'
                          ? {
                                label: 'Revise',
                                href: activityProposals.edit({
                                    document: row.id,
                                }).url,
                            }
                          : {
                                label: 'View',
                                href: activityProposals.show({
                                    document: row.id,
                                }).url,
                            }
                }
            />
        </>
    );
}
