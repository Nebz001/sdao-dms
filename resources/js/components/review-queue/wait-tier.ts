import type { Tone } from '@/lib/status-tones';
import type { WaitTier } from './types';

/**
 * How each waiting-time band looks. The day thresholds themselves live only
 * in PHP (ReviewQueueData::tierFor(): 0 to 2 fresh, 3 to 7 aging, 8+ overdue)
 * and arrive as a `tier` on every row, so nothing here counts days.
 *
 * This is the one place the bands become colors. The wait pill and the
 * "Oldest waiting" card both read it, so a card and the pill inside it can
 * never disagree.
 */
export const TIER_TONE: Record<WaitTier, Tone> = {
    overdue: 'destructive',
    aging: 'warning',
    fresh: 'neutral',
};

/** The StatCard surface for a band. Fresh keeps the normal card surface. */
export const TIER_CARD_TONE: Record<WaitTier, 'default' | 'warning' | 'alert'> = {
    fresh: 'default',
    aging: 'warning',
    overdue: 'alert',
};

/** Text accent for the card's heading and link, from the same status tokens as the pill. */
export const TIER_ACCENT_TEXT: Record<WaitTier, string> = {
    fresh: 'text-primary-text',
    aging: 'text-warning-foreground',
    overdue: 'text-destructive-foreground',
};
