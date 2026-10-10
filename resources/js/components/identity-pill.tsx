import type { LucideIcon } from 'lucide-react';
import { ToneBadge } from '@/components/status-badge';
import { Separator } from '@/components/ui/separator';
import { BRAND_TINT_OUTLINE } from '@/lib/brand-tint';
import { cn } from '@/lib/utils';

/**
 * Brand is the user's main role, muted a scope (school, program,
 * organization) and warm an additional role or officer seat. Each tone keeps
 * the app's tinted-outline badge look; none of them carries meaning alone —
 * the icon and the words do.
 */
type IdentityPillTone = 'brand' | 'muted' | 'warm';

const TONE_STYLES: Record<IdentityPillTone, string> = {
    brand: BRAND_TINT_OUTLINE,
    muted: 'border-border bg-muted/40 text-muted-foreground',
    warm: 'border-warning/40 bg-warning/10 text-warning-foreground',
};

type IdentityPillProps = {
    icon: LucideIcon;
    tone: IdentityPillTone;
    /** The small muted word before the divider ("School", "President"). Omit for a value-only pill. */
    label?: string;
    value?: string | null;
};

/**
 * A rounded pill for who the user is: an icon, then either one value or a
 * label, a thin divider and a value. Built on the shared ToneBadge so it
 * sits with every other badge; only the label | value layout is new.
 */
export default function IdentityPill({ icon: Icon, tone, label, value }: IdentityPillProps) {
    const text = value ?? label;

    return (
        <ToneBadge
            tone="neutral"
            className={cn(
                'h-auto max-w-full gap-2 rounded-full px-3 py-1 text-sm font-medium tracking-normal whitespace-normal normal-case',
                TONE_STYLES[tone],
            )}
        >
            <Icon aria-hidden />
            {label && value ? (
                <>
                    <span className={cn(tone === 'muted' ? 'text-muted-foreground' : 'opacity-80')}>{label}</span>
                    <span className="sr-only">:</span>
                    <Separator orientation="vertical" className={cn('h-4', tone === 'brand' ? 'bg-brand-soft-border' : 'bg-current opacity-30')} />
                    <span className={cn('min-w-0 break-words', tone === 'muted' && 'text-foreground')}>{value}</span>
                </>
            ) : (
                <span className="min-w-0 break-words">{text}</span>
            )}
        </ToneBadge>
    );
}
