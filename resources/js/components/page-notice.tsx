import {
    CircleAlert,
    Info,
    Minus,
    TrendingDown,
    TrendingUp,
    TriangleAlert,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';

/**
 * up / down / flat describe a change against a baseline (green, red, grey);
 * info is neutral context; warning is something that needs attention.
 */
export type PageNoticeTone = 'up' | 'down' | 'flat' | 'info' | 'warning';

const TONES: Record<
    PageNoticeTone,
    { variant: 'success' | 'destructive' | 'default' | 'info' | 'warning'; icon: LucideIcon }
> = {
    up: { variant: 'success', icon: TrendingUp },
    down: { variant: 'destructive', icon: TrendingDown },
    flat: { variant: 'default', icon: Minus },
    info: { variant: 'info', icon: Info },
    warning: { variant: 'warning', icon: TriangleAlert },
};

type PageNoticeProps = {
    tone: PageNoticeTone;
    /** Overrides the tone's default icon (e.g. an inbox for a queue count). */
    icon?: LucideIcon;
    children: ReactNode;
};

/**
 * The one place a page shows a dynamic message about its own data: an inline
 * block under the header, never in the sublabel. It is not a toast and not a
 * modal. `role="status"` (polite) because these notices describe state the
 * user navigated to, not an error that should interrupt a screen reader.
 * Pages render it only when there is something to say.
 */
export default function PageNotice({ tone, icon, children }: PageNoticeProps) {
    const { variant, icon: DefaultIcon } = TONES[tone];
    const Icon = icon ?? DefaultIcon ?? CircleAlert;

    return (
        <Alert variant={variant} role="status">
            <Icon aria-hidden />
            <AlertDescription>{children}</AlertDescription>
        </Alert>
    );
}
