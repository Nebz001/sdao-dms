import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

export type TileTone =
    | 'blue'
    | 'green'
    | 'amber'
    | 'teal'
    | 'purple'
    | 'orange'
    | 'sky'
    | 'slate';

/** Soft tinted tile and a matching icon colour, readable in both themes. */
const TILE_TONES: Record<TileTone, string> = {
    blue: 'bg-blue-500/10 text-blue-700 dark:bg-blue-400/15 dark:text-blue-300',
    green: 'bg-emerald-500/10 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300',
    amber: 'bg-amber-500/10 text-amber-800 dark:bg-amber-400/15 dark:text-amber-300',
    teal: 'bg-teal-500/10 text-teal-700 dark:bg-teal-400/15 dark:text-teal-300',
    purple: 'bg-violet-500/10 text-violet-700 dark:bg-violet-400/15 dark:text-violet-300',
    orange: 'bg-orange-500/10 text-orange-700 dark:bg-orange-400/15 dark:text-orange-300',
    sky: 'bg-sky-500/10 text-sky-700 dark:bg-sky-400/15 dark:text-sky-300',
    slate: 'bg-slate-500/10 text-slate-700 dark:bg-slate-400/15 dark:text-slate-300',
};

const SIZES = {
    sm: { box: 'size-10 rounded-lg', icon: 'size-5' },
    md: { box: 'size-12 rounded-lg', icon: 'size-6' },
    lg: { box: 'size-14 rounded-xl', icon: 'size-7' },
} as const;

/** The coloured icon square used on the home cards, hub options, headers and list rows. */
export default function IconTile({
    icon: Icon,
    tone,
    size = 'md',
    className,
}: {
    icon: LucideIcon;
    tone: TileTone;
    size?: keyof typeof SIZES;
    className?: string;
}) {
    return (
        <span
            aria-hidden
            className={cn(
                'flex shrink-0 items-center justify-center',
                SIZES[size].box,
                TILE_TONES[tone],
                className,
            )}
        >
            <Icon className={SIZES[size].icon} />
        </span>
    );
}
