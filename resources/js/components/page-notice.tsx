import {
    CircleAlert,
    CircleCheck,
    Info,
    TriangleAlert,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { cn } from '@/lib/utils';

/**
 * The five tones every inline message uses. Only the color and the icon
 * change between them; the layout is identical.
 */
export type PageNoticeTone = 'destructive' | 'warning' | 'info' | 'success' | 'neutral';

const TONES: Record<PageNoticeTone, { icon: LucideIcon; lead: string }> = {
    destructive: { icon: TriangleAlert, lead: 'text-destructive-foreground' },
    warning: { icon: CircleAlert, lead: 'text-warning-foreground' },
    info: { icon: Info, lead: 'text-info-foreground' },
    success: { icon: CircleCheck, lead: 'text-success-foreground' },
    neutral: { icon: Info, lead: 'text-foreground' },
};

type PageNoticeProps = {
    tone: PageNoticeTone;
    /** The bold lead sentence, in the tone's color. */
    title?: ReactNode;
    /** Overrides the tone's default icon (e.g. an inbox for a queue count). */
    icon?: LucideIcon;
    /** Extra classes for the Alert, for rare layout tweaks. */
    className?: string;
    /** A link or button aligned to the end of the message, e.g. "Open these 3". On phones it moves under the text. */
    action?: ReactNode;
    /**
     * An urgent message (an error, a blocked action) is announced right away
     * with `role="alert"`. Everything else is `role="status"` and is read
     * politely when the user reaches it.
     */
    urgent?: boolean;
    /** The details, in a softer text color than the lead. */
    children?: ReactNode;
};

/**
 * The one place a page shows an inline message about its own data or state.
 * Soft tinted background, a border of the same color, an icon on the left, a
 * bold lead sentence in the tone color, the details in muted text, and an
 * optional action aligned right. It is not a toast and not a modal. Pages
 * render it only when there is something to say.
 */
export default function PageNotice({
    tone,
    title,
    icon,
    className,
    action,
    urgent = false,
    children,
}: PageNoticeProps) {
    const Icon = icon ?? TONES[tone].icon;

    return (
        <Alert variant={tone} role={urgent ? 'alert' : 'status'} className={className}>
            <Icon aria-hidden />
            <AlertDescription className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <div className="min-w-0 flex-1 basis-64">
                    {title && (
                        <strong className={cn('font-semibold', TONES[tone].lead)}>{title}</strong>
                    )}
                    {title && children ? ' ' : null}
                    {children}
                </div>
                {action && <div className="shrink-0">{action}</div>}
            </AlertDescription>
        </Alert>
    );
}
