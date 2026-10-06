import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Mirrors approver-dashboard.tsx's layout (banner, three stat cards, then the
 * two-column body) so the swap from skeleton to real content never shifts the
 * page. Shown while dashboard.tsx's <Deferred> group is still loading.
 */
export default function ApproverDashboardSkeleton() {
    return (
        <div className="flex flex-col gap-6" aria-hidden>
            <Skeleton className="h-16 w-full" />

            <div className="grid gap-4 md:grid-cols-3">
                {Array.from({ length: 3 }, (_, i) => (
                    <Card key={i} className="gap-3 py-4">
                        <CardHeader className="px-4">
                            <Skeleton className="h-4 w-32" />
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3 px-4">
                            <Skeleton className="h-8 w-16" />
                            <Skeleton className="h-2 w-full" />
                            <Skeleton className="h-10 w-full" />
                        </CardContent>
                    </Card>
                ))}
            </div>

            <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div className="flex flex-col gap-4">
                    {Array.from({ length: 2 }, (_, i) => (
                        <Card key={i}>
                            <CardHeader>
                                <Skeleton className="h-5 w-48" />
                            </CardHeader>
                            <CardContent className="flex flex-col gap-3">
                                {Array.from({ length: 3 }, (_, j) => (
                                    <Skeleton key={j} className="h-12 w-full" />
                                ))}
                            </CardContent>
                        </Card>
                    ))}
                </div>
                <div className="flex flex-col gap-4">
                    {Array.from({ length: 2 }, (_, i) => (
                        <Card key={i}>
                            <CardHeader>
                                <Skeleton className="h-5 w-32" />
                            </CardHeader>
                            <CardContent className="flex flex-col gap-3">
                                {Array.from({ length: 3 }, (_, j) => (
                                    <Skeleton key={j} className="h-12 w-full" />
                                ))}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </div>
    );
}
