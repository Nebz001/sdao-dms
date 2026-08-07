import { Link } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Minus } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';

export type WeeklyDelta = {
    thisWeek: number;
    lastWeek: number;
    delta: number;
    /**
     * Names what changed, e.g. "submitted", "registered" — so the trend row
     * can never be misread as a change in the tile's own count.
     */
    noun: string;
};

type StatTileProps = {
    label: string;
    count: number;
    href: string;
    icon: LucideIcon;
    /** Trend vs. last week. Omit when this tile has no honest historical baseline to compare against. */
    weekly?: WeeklyDelta;
};

/**
 * Up = success / down = destructive / flat = muted — shared by every weekly
 * comparison on the admin dashboard: this tile's own trend row below, and
 * the page-level "documents submitted this week" line in admin/dashboard.tsx.
 */
export function deltaLabel(delta: number): {
    text: string;
    icon: LucideIcon;
    className: string;
} {
    if (delta > 0) {
        return { text: `+${delta}`, icon: ArrowUp, className: 'text-success' };
    }

    if (delta < 0) {
        return {
            text: `${delta}`,
            icon: ArrowDown,
            className: 'text-destructive',
        };
    }

    return { text: '±0', icon: Minus, className: 'text-muted-foreground' };
}

/**
 * "+3 submitted vs. last week" / "−1 registered vs. last week" / "No change
 * vs. last week" — a real minus sign (−), not a hyphen, and the noun so the
 * delta reads as its own event count, not a change to the tile's headline.
 */
function trendCopy(weekly: WeeklyDelta): string {
    if (weekly.delta === 0) {
        return 'No change vs. last week';
    }

    const signed =
        weekly.delta > 0 ? `+${weekly.delta}` : `−${Math.abs(weekly.delta)}`;

    return `${signed} ${weekly.noun} vs. last week`;
}

/**
 * A single clickable count — the admin dashboard's "quick links" strip.
 * Distinct from QueueStatStrip (a non-clickable stat row inside one Card):
 * every stat here is a genuinely new destination that had no visibility
 * before this dashboard existed (Pending Accounts, unassigned advisers),
 * so the whole tile is a link rather than a plain number.
 */
export default function StatTile({
    label,
    count,
    href,
    icon: Icon,
    weekly,
}: StatTileProps) {
    const trend = weekly ? deltaLabel(weekly.delta) : null;
    const TrendIcon = trend?.icon;

    return (
        <Card className="transition-colors hover:border-primary/40">
            <CardContent>
                <Link
                    href={href}
                    className="flex items-start justify-between gap-2 rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <div>
                        <p className="text-sm text-muted-foreground">{label}</p>
                        <p className="mt-1 text-2xl font-semibold tabular-nums">
                            {count}
                        </p>
                        {weekly && trend && TrendIcon && (
                            <p
                                className={`mt-1.5 flex items-center gap-1 text-xs ${trend.className}`}
                            >
                                <TrendIcon className="size-3.5 shrink-0" />
                                <span>{trendCopy(weekly)}</span>
                            </p>
                        )}
                    </div>
                    <Icon className="size-5 shrink-0 text-muted-foreground" />
                </Link>
            </CardContent>
        </Card>
    );
}
