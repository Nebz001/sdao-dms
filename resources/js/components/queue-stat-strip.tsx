import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';

type Stat = {
    label: string;
    value: string;
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
 */
export default function QueueStatStrip({ stats }: QueueStatStripProps) {
    return (
        <Card>
            <CardContent className="flex items-stretch gap-6">
                {stats.map((stat, index) => (
                    <div key={stat.label} className="flex items-center gap-6">
                        {index > 0 && <Separator orientation="vertical" className="h-10" />}
                        <div>
                            <p className="text-sm text-muted-foreground">{stat.label}</p>
                            <p className="mt-1 text-2xl font-semibold tabular-nums">{stat.value}</p>
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
