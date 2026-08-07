import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';

type Stat = {
    label: string;
    value: string;
    /**
     * The stat's real numeric value, when it has one — e.g. `queue.length`,
     * not a formatted date like "Oldest waiting". Drives the `--primary`
     * actionable dot/tint when positive; omit for non-numeric stats, which
     * have no notion of "nonzero".
     */
    count?: number;
};

type QueueStatStripProps = {
    stats: Stat[];
};

/**
 * Compact stat row shown above a review queue (populated or empty), so the
 * page always has structural content beyond one card — instead of a single
 * list card floating in an otherwise blank page. Values are derived
 * client-side from data already on the page (see call sites); no backend
 * change needed.
 *
 * Deliberately lighter than the section Card it always sits directly above
 * (softer border, no shadow, smaller type) — this is the app's other
 * stat-tile-row pattern outside the admin dashboard's `StatTile` grid, and
 * the two need to read as the same lighter tier wherever they appear. A
 * stat with a positive `count` is always actionable (there's no purely
 * informational stat in this app), so it gets a `--primary` dot + tinted
 * value instead of a left-border accent — this is a single shared Card with
 * stats side by side, not separate cards, so a left-border accent doesn't
 * map cleanly per stat the way it does on `StatTile`.
 */
export default function QueueStatStrip({ stats }: QueueStatStripProps) {
    return (
        <Card className="border-border/60 py-4 shadow-none">
            <CardContent className="flex items-stretch gap-6 px-4">
                {stats.map((stat, index) => {
                    const isActionable = (stat.count ?? 0) > 0;

                    return (
                        <div
                            key={stat.label}
                            className="flex items-center gap-6"
                        >
                            {index > 0 && (
                                <Separator
                                    orientation="vertical"
                                    className="h-10"
                                />
                            )}
                            <div>
                                <p className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                    {isActionable && (
                                        <span
                                            className="size-1.5 shrink-0 rounded-full bg-primary"
                                            aria-hidden
                                        />
                                    )}
                                    {stat.label}
                                </p>
                                <p
                                    className={cn(
                                        'mt-1 text-xl font-semibold tabular-nums',
                                        isActionable && 'text-primary',
                                    )}
                                >
                                    {stat.value}
                                </p>
                            </div>
                        </div>
                    );
                })}
            </CardContent>
        </Card>
    );
}
