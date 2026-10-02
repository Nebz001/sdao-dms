import { Link } from '@inertiajs/react';
import { History } from 'lucide-react';
import { ActionBadge } from '@/components/status-badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { useInitials } from '@/hooks/use-initials';
import { formatActivityTime } from '@/lib/utils';

export type ActivityEntry = {
    id: number;
    actorName: string;
    /** One of the four outcomes the card has badges for. */
    badge: 'submitted' | 'approved' | 'returned' | 'rejected';
    organizationName: string;
    /** "{Form type}: {subject}", with a flagged-sections or resubmitted prefix where it applies. */
    summary: string;
    createdAt: string;
    href: string;
};

type RecentActivityCardProps = {
    entries: ActivityEntry[];
    viewAllHref: string;
};

/**
 * The last few actions on documents. Every row reads the same way: initials
 * avatar, then the actor in bold with an outcome badge and the organization on
 * the first line (time at the far end), and the document on a second muted
 * line. The time is a fixed label, not a ticking counter.
 */
export default function RecentActivityCard({ entries, viewAllHref }: RecentActivityCardProps) {
    const getInitials = useInitials();

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-2">
                <div className="grid gap-1.5">
                    <CardTitle role="heading" aria-level={2} className="text-base">Recent activity</CardTitle>
                    <CardDescription>Newest first</CardDescription>
                </div>
                <Link href={viewAllHref} className="shrink-0 text-sm text-primary-text hover:underline">
                    View all activity
                </Link>
            </CardHeader>
            <CardContent>
                {entries.length === 0 ? (
                    <Empty className="gap-4 p-6">
                        <EmptyHeader>
                            <EmptyMedia variant="icon" className="size-8 [&_svg]:size-5">
                                <History />
                            </EmptyMedia>
                            <EmptyTitle>Nothing has happened yet</EmptyTitle>
                            <EmptyDescription>
                                Submissions and approvals will show up here as they happen.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <ul className="divide-y">
                        {entries.map((entry) => (
                            <li key={entry.id} className="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                                <Avatar className="size-9">
                                    <AvatarFallback className="bg-muted text-xs font-medium">
                                        {getInitials(entry.actorName)}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <span className="text-sm font-semibold">{entry.actorName}</span>
                                        <ActionBadge action={entry.badge} appearance="outline" />
                                        <Badge
                                            variant="secondary"
                                            className="h-auto max-w-40 text-left text-[0.7rem] font-normal break-words whitespace-normal"
                                            title={entry.organizationName}
                                        >
                                            {entry.organizationName}
                                        </Badge>
                                        <span className="ml-auto text-xs whitespace-nowrap text-muted-foreground">
                                            {formatActivityTime(entry.createdAt)}
                                        </span>
                                    </div>
                                    <Link
                                        href={entry.href}
                                        className="mt-0.5 block text-xs text-muted-foreground hover:underline"
                                    >
                                        {entry.summary}
                                    </Link>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
