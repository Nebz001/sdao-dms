import { Link } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronRight,
    CircleAlert,
    CircleCheck,
    Clock,
    FilePlus2,
    Inbox,
    TriangleAlert,
    UserPlus,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

export type HomeBannerTone = 'red' | 'orange' | 'blue' | 'green' | 'gray';

export type HomeBannerIcon =
    | 'alert-circle'
    | 'warning'
    | 'clock'
    | 'check-circle'
    | 'file-plus'
    | 'inbox'
    | 'user-plus'
    | 'calendar';

/** What every home page's banner is built from, whether the server or the page worked it out. */
export type HomeBannerData = {
    tone: HomeBannerTone;
    icon: HomeBannerIcon;
    title: string;
    subtitle: string;
    buttonLabel: string;
    href: string;
};

const ICONS: Record<HomeBannerIcon, LucideIcon> = {
    'alert-circle': CircleAlert,
    warning: TriangleAlert,
    clock: Clock,
    'check-circle': CircleCheck,
    'file-plus': FilePlus2,
    inbox: Inbox,
    'user-plus': UserPlus,
    calendar: CalendarDays,
};

/**
 * One tinted recipe per state, all from the app's semantic tokens so light and
 * dark both keep their contrast. The gray state is the only one whose button
 * is the solid primary: it asks the user to start something.
 */
const TONES: Record<HomeBannerTone, { card: string; tile: string; button: string | null }> = {
    red: {
        card: 'border-destructive/40 bg-destructive/10',
        tile: 'bg-destructive/15 text-destructive-foreground',
        button: 'border-destructive/50 bg-destructive/10 text-destructive-foreground hover:bg-destructive/20 hover:text-destructive-foreground',
    },
    orange: {
        card: 'border-warning/40 bg-warning/10',
        tile: 'bg-warning/15 text-warning-foreground',
        button: 'border-warning/50 bg-warning/10 text-warning-foreground hover:bg-warning/20 hover:text-warning-foreground',
    },
    blue: {
        card: 'border-info/40 bg-info/10',
        tile: 'bg-info/15 text-info-foreground',
        button: 'border-info/50 bg-info/10 text-info-foreground hover:bg-info/20 hover:text-info-foreground',
    },
    green: {
        card: 'border-success/40 bg-success/10',
        tile: 'bg-success/15 text-success-foreground',
        button: 'border-success/50 bg-success/10 text-success-foreground hover:bg-success/20 hover:text-success-foreground',
    },
    gray: {
        card: 'border-border bg-muted/30',
        tile: 'bg-muted text-muted-foreground',
        button: null,
    },
};

/**
 * The one banner at the top of every home page (officer, approver, SDAO).
 * Only the words, icon and link change between pages and states; the look is
 * identical. Icon, text and button share one center line, and on a phone the
 * button drops under the text at full width.
 */
export default function HomeBanner({ tone, icon, title, subtitle, buttonLabel, href }: HomeBannerData) {
    const style = TONES[tone];
    const Icon = ICONS[icon];

    return (
        <div
            data-slot="home-banner"
            data-tone={tone}
            role={tone === 'red' ? 'alert' : 'status'}
            className={cn('flex flex-wrap items-center gap-x-4 gap-y-3 rounded-xl border px-4 py-3.5', style.card)}
        >
            <span
                aria-hidden
                className={cn('flex size-10 shrink-0 items-center justify-center rounded-lg', style.tile)}
            >
                <Icon className="size-5" />
            </span>
            <div className="min-w-0 flex-1 basis-56">
                <p className="font-semibold">{title}</p>
                <p className="text-sm text-muted-foreground">{subtitle}</p>
            </div>
            <Button
                asChild
                variant={style.button === null ? 'default' : 'outline'}
                className={cn('w-full sm:w-auto', style.button)}
            >
                <Link href={href} prefetch>
                    {buttonLabel}
                    <ChevronRight data-icon="inline-end" />
                </Link>
            </Button>
        </div>
    );
}

export function HomeBannerSkeleton() {
    return (
        <div aria-hidden className="flex items-center gap-4 rounded-xl border px-4 py-3.5">
            <Skeleton className="size-10 rounded-lg" />
            <div className="flex flex-1 flex-col gap-2">
                <Skeleton className="h-4 w-40" />
                <Skeleton className="h-4 w-72 max-w-full" />
            </div>
        </div>
    );
}
