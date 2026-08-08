import { Link } from '@inertiajs/react';
import { AlertCircle, Minus, TrendingDown, TrendingUp } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

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
    /**
     * Marks this tile as needing correction, not just routine queue depth —
     * e.g. an unassigned adviser is a misconfiguration, unlike "documents
     * awaiting review", which is expected workflow volume. Renders an
     * AlertCircle badge + capped pulse + tooltip in the corner instead of
     * the ordinary nonzero left-border accent, so there's exactly one
     * urgent signal per card, not two competing ones.
     */
    urgent?: boolean;
};

/**
 * Up = success / down = destructive / flat = muted — shared by every weekly
 * comparison on the admin dashboard's stat tiles.
 */
export function deltaLabel(delta: number): {
    icon: LucideIcon;
    className: string;
} {
    if (delta > 0) {
        return { icon: TrendingUp, className: 'text-success' };
    }

    if (delta < 0) {
        return { icon: TrendingDown, className: 'text-destructive' };
    }

    return { icon: Minus, className: 'text-muted-foreground' };
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
 * "1 adviser needs to be assigned" / "3 advisers need to be assigned" —
 * shared by the urgent badge's accessible name and its tooltip text.
 */
function urgentCopy(count: number): string {
    const plural = count === 1 ? '' : 's';
    const verb = count === 1 ? 'needs' : 'need';

    return `${count} adviser${plural} ${verb} to be assigned`;
}

/**
 * A single clickable count — the admin dashboard's "quick links" strip.
 * Distinct from QueueStatStrip (a non-clickable stat row inside one Card):
 * every stat here is a genuinely new destination that had no visibility
 * before this dashboard existed (Pending Accounts, unassigned advisers),
 * so the whole tile is a link rather than a plain number.
 *
 * Deliberately lighter than a section Card (softer border, no shadow,
 * tighter padding, smaller type) — every tile here sits directly above a
 * heavier section Card, and the two need to read as different tiers, not
 * identical chrome repeated twice. A nonzero count is always actionable
 * (there's no purely-informational tile in this app), so it gets a
 * `--primary` left-border + icon accent; a zero count stays fully neutral.
 * `urgent` tiles drop the left-border in favor of the corner alert badge
 * below — see that prop's docblock.
 */
export default function StatTile({
    label,
    count,
    href,
    icon: Icon,
    weekly,
    urgent,
}: StatTileProps) {
    const trend = weekly ? deltaLabel(weekly.delta) : null;
    const TrendIcon = trend?.icon;
    const isActionable = count > 0;

    return (
        <Card
            className={cn(
                'relative gap-0 border-border/60 py-4 shadow-none transition-colors hover:border-primary/40',
                isActionable && !urgent && 'border-l-2 border-l-primary',
            )}
        >
            {urgent && (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <button
                            type="button"
                            aria-label={urgentCopy(count)}
                            className="absolute -top-2 -right-2 inline-flex size-6 items-center justify-center rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            {/* Keyed on count so a value change (e.g. via a
                                future poll) remounts this node, restarting
                                the capped ping — not just an "on load" run. */}
                            <span key={count} className="relative inline-flex">
                                <span className="absolute inline-flex size-full rounded-full bg-destructive/60 motion-safe:animate-[ping_1.4s_ease-out_3]" />
                                <span className="relative inline-flex rounded-full bg-destructive p-1">
                                    <AlertCircle className="size-3.5 text-white" />
                                </span>
                            </span>
                        </button>
                    </TooltipTrigger>
                    <TooltipContent>{urgentCopy(count)}</TooltipContent>
                </Tooltip>
            )}
            <CardContent className="px-4">
                <Link
                    href={href}
                    className="flex items-start justify-between gap-2 rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <div>
                        <p className="text-xs font-medium text-muted-foreground">
                            {label}
                        </p>
                        <p className="mt-1 text-xl font-semibold tabular-nums">
                            {count}
                        </p>
                        {weekly && trend && TrendIcon && (
                            <div
                                className={cn(
                                    'mt-2.5 inline-flex w-fit items-center gap-1 rounded-full bg-muted/60 px-2 py-0.5 text-xs font-medium',
                                    trend.className,
                                )}
                            >
                                <TrendIcon className="size-3 shrink-0" />
                                <span>{trendCopy(weekly)}</span>
                            </div>
                        )}
                    </div>
                    <Icon
                        className={cn(
                            'size-5 shrink-0',
                            isActionable
                                ? 'text-primary'
                                : 'text-muted-foreground',
                        )}
                    />
                </Link>
            </CardContent>
        </Card>
    );
}
