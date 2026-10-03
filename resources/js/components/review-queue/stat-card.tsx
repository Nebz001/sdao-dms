import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

/** Shared shell for the four queue stat cards: icon chip + title, then content. */
export default function StatCard({
    icon: Icon,
    title,
    tone = 'default',
    children,
    className,
}: {
    icon: LucideIcon;
    title: string;
    /** "alert" is the warm red tint used when something is overdue. */
    tone?: 'default' | 'alert';
    children: ReactNode;
    className?: string;
}) {
    return (
        <Card
            className={cn(
                'gap-4 py-5 shadow-none',
                tone === 'alert' && 'border-destructive/40 bg-destructive/10',
                className,
            )}
        >
            <CardHeader className="flex flex-row items-center gap-3 px-5">
                <span
                    className={cn(
                        'flex size-8 shrink-0 items-center justify-center rounded-md [&_svg]:size-4',
                        tone === 'alert'
                            ? 'bg-destructive/15 text-destructive-foreground'
                            : 'bg-primary/10 text-primary-text',
                    )}
                    aria-hidden
                >
                    <Icon />
                </span>
                <CardTitle className="text-sm font-medium text-foreground/90">{title}</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-1 flex-col gap-4 px-5">{children}</CardContent>
        </Card>
    );
}

/** The big number every stat card leads with. */
export function StatValue({ children }: { children: ReactNode }) {
    return <p className="text-4xl leading-none font-semibold tabular-nums">{children}</p>;
}

/** Same card shape while a deferred figure loads. */
export function StatCardSkeleton({ title, icon }: { title: string; icon: LucideIcon }) {
    return (
        <StatCard icon={icon} title={title}>
            <div aria-busy="true" className="flex flex-col gap-4">
                <Skeleton className="h-9 w-16" />
                <Skeleton className="h-10 w-full" />
                <Skeleton className="h-4 w-24" />
            </div>
        </StatCard>
    );
}
