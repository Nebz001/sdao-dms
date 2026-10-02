import { Link } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export type WaitingSplit = {
    total: number;
    approver: { count: number; href: string };
    org: { count: number; href: string };
};

type BucketProps = {
    swatchClassName: string;
    title: string;
    count: number;
    helper: string;
    href: string;
};

function Bucket({ swatchClassName, title, count, helper, href }: BucketProps) {
    return (
        <Link
            href={href}
            className="block rounded-lg border bg-muted/40 p-3 transition-colors hover:border-primary-text/40 focus-visible:focus-ring-edge"
        >
            <p className="flex items-center gap-2 text-sm font-medium">
                <span className={cn('size-2.5 shrink-0 rounded-[2px]', swatchClassName)} aria-hidden />
                {title}
            </p>
            <p className="mt-1 text-3xl font-semibold tabular-nums">{count}</p>
            <p className="mt-1 text-xs text-muted-foreground">{helper}</p>
        </Link>
    );
}

/**
 * Two separate follow-up buckets, never mixed: documents sitting with an
 * approver (SDAO follows up with the approver) and documents returned to the
 * organization (SDAO follows up with the officers). The split bar is a
 * summary only; the two labelled boxes under it carry the actual numbers.
 */
export default function WaitingSplitCard({ data }: { data: WaitingSplit }) {
    const approverShare = data.total > 0 ? (data.approver.count / data.total) * 100 : 0;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Who are we waiting on</CardTitle>
                <CardDescription>
                    {data.total === 1
                        ? '1 document is open right now'
                        : `${data.total} documents are open right now`}
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {data.total > 0 && (
                    <div
                        role="img"
                        aria-label={`${data.approver.count} waiting on an approver, ${data.org.count} returned and waiting on the organization`}
                        className="flex h-2 overflow-hidden rounded-full bg-muted"
                    >
                        <div className="bg-info" style={{ width: `${approverShare}%` }} />
                        <div className="flex-1 bg-warning" />
                    </div>
                )}
                <Bucket
                    swatchClassName="bg-info"
                    title="Waiting on an approver"
                    count={data.approver.count}
                    helper="SDAO should follow up with the approver"
                    href={data.approver.href}
                />
                <Bucket
                    swatchClassName="bg-warning"
                    title="Returned, waiting on the org"
                    count={data.org.count}
                    helper="SDAO should follow up with the officers"
                    href={data.org.href}
                />
            </CardContent>
        </Card>
    );
}
