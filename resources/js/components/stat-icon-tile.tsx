import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

/** Brand tint by default; "warning" is the amber state and "alert" the red one, matching the card they sit in. */
export type StatIconTileTone = 'default' | 'warning' | 'alert';

const TONE_STYLES: Record<StatIconTileTone, string> = {
    default: 'bg-primary/10 text-primary-text',
    warning: 'bg-warning/15 text-warning-foreground',
    alert: 'bg-destructive/15 text-destructive-foreground',
};

/**
 * The small rounded square with a soft tint that sits left of a stat card's
 * title. Decorative — the title already names the card — so it is hidden
 * from assistive tech. Shared by every stat card in the app.
 */
export default function StatIconTile({
    icon: Icon,
    tone = 'default',
    className,
}: {
    icon: LucideIcon;
    tone?: StatIconTileTone;
    className?: string;
}) {
    return (
        <span
            aria-hidden
            className={cn(
                'flex size-8 shrink-0 items-center justify-center rounded-md [&_svg]:size-4',
                TONE_STYLES[tone],
                className,
            )}
        >
            <Icon />
        </span>
    );
}
