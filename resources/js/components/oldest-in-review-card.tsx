import { Link } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import IdleBadge from '@/components/idle-badge';
import type { IdleTier } from '@/components/idle-badge';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';

export type OldestDocument = {
    id: number;
    /** "{Form type}: {subject}". */
    title: string;
    organizationName: string;
    approverName: string;
    idleDays: number;
    tier: IdleTier;
    href: string;
};

type OldestInReviewCardProps = {
    documents: OldestDocument[];
    viewAllHref: string;
};

/**
 * The documents that have been idle longest while waiting on an approver, from
 * the latest entry in the transition log (never the last edit). Returned
 * documents are waiting on the organization, so they are not listed here.
 */
export default function OldestInReviewCard({ documents, viewAllHref }: OldestInReviewCardProps) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-2">
                <div className="grid gap-1.5">
                    <CardTitle role="heading" aria-level={2} className="text-base">Oldest documents in review</CardTitle>
                    <CardDescription>
                        Idle time counted from the last real action, not from the last edit
                    </CardDescription>
                </div>
                <Link href={viewAllHref} className="shrink-0 text-sm text-primary-text hover:underline">
                    View all
                </Link>
            </CardHeader>
            <CardContent>
                {documents.length === 0 ? (
                    <Empty className="gap-4 p-6">
                        <EmptyHeader>
                            <EmptyMedia variant="icon" className="size-8 [&_svg]:size-5">
                                <CircleCheck />
                            </EmptyMedia>
                            <EmptyTitle>Nothing is waiting on an approver</EmptyTitle>
                            <EmptyDescription>
                                Every document in review has moved on, or none are in review.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <ul className="divide-y">
                        {documents.map((doc) => (
                            <li
                                key={doc.id}
                                className="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                            >
                                <div className="min-w-0">
                                    <Link
                                        href={doc.href}
                                        className="block text-sm font-semibold hover:underline break-words"
                                        title={doc.title}
                                    >
                                        {doc.title}
                                    </Link>
                                    <div className="mt-1 flex min-w-0 items-center gap-2 text-xs text-muted-foreground">
                                        <Badge
                                            variant="outline"
                                            className="h-auto max-w-40 text-left text-[0.7rem] font-medium break-words whitespace-normal text-foreground"
                                            title={doc.organizationName}
                                        >
                                            {doc.organizationName}
                                        </Badge>
                                        <span className="break-words">at {doc.approverName}</span>
                                    </div>
                                </div>
                                <IdleBadge days={doc.idleDays} tier={doc.tier} label="idle" />
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
