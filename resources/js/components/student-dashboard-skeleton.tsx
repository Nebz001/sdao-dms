import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Mirrors student-dashboard.tsx's own layout (header line, Needs Your
 * Action, Tracker + Checklist, five KPI tiles, Quick Submit + Upcoming,
 * Chart + Notifications) so the swap from skeleton to real content never
 * shifts the page. Shown while dashboard.tsx's `student` <Deferred> group is
 * still loading — same recipe as ApproverDashboardSkeleton.
 */
export default function StudentDashboardSkeleton() {
    return (
        <div className="flex flex-col gap-4" aria-hidden>
            <Card>
                <CardHeader>
                    <Skeleton className="h-5 w-40" />
                </CardHeader>
                <CardContent className="space-y-3">
                    {Array.from({ length: 2 }, (_, i) => (
                        <Skeleton key={i} className="h-12 w-full" />
                    ))}
                </CardContent>
            </Card>

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                <Card className="lg:col-span-8">
                    <CardHeader>
                        <Skeleton className="h-5 w-32" />
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {Array.from({ length: 3 }, (_, i) => (
                            <Skeleton key={i} className="h-12 w-full" />
                        ))}
                    </CardContent>
                </Card>
                <Card className="lg:col-span-4">
                    <CardHeader>
                        <Skeleton className="h-5 w-32" />
                    </CardHeader>
                    <CardContent className="flex items-center gap-4">
                        <Skeleton className="size-20 shrink-0 rounded-full" />
                        <div className="flex-1 space-y-2">
                            <Skeleton className="h-3 w-full" />
                            <Skeleton className="h-3 w-full" />
                            <Skeleton className="h-3 w-2/3" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                {Array.from({ length: 5 }, (_, i) => (
                    <Card
                        key={i}
                        className="gap-0 border-border/60 py-4 shadow-none"
                    >
                        <CardContent className="px-4">
                            <Skeleton className="h-3 w-20" />
                            <Skeleton className="mt-2 h-6 w-12" />
                        </CardContent>
                    </Card>
                ))}
            </div>

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                <Card className="lg:col-span-6">
                    <CardHeader>
                        <Skeleton className="h-5 w-32" />
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {Array.from({ length: 5 }, (_, i) => (
                            <Skeleton key={i} className="h-9 w-full" />
                        ))}
                    </CardContent>
                </Card>
                <Card className="lg:col-span-6">
                    <CardHeader>
                        <Skeleton className="h-5 w-40" />
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {Array.from({ length: 3 }, (_, i) => (
                            <Skeleton key={i} className="h-10 w-full" />
                        ))}
                    </CardContent>
                </Card>
            </div>

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                <Card className="lg:col-span-8">
                    <CardHeader>
                        <Skeleton className="h-5 w-40" />
                    </CardHeader>
                    <CardContent>
                        <Skeleton className="h-[180px] w-full" />
                    </CardContent>
                </Card>
                <Card className="lg:col-span-4">
                    <CardHeader>
                        <Skeleton className="h-5 w-32" />
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {Array.from({ length: 3 }, (_, i) => (
                            <Skeleton key={i} className="h-12 w-full" />
                        ))}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
