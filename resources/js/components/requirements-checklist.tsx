import { Link } from '@inertiajs/react';
import { Check, CircleDashed, Clock, Info } from 'lucide-react';
import { cn } from '@/lib/utils';

export type RequirementState =
    | 'done'
    | 'in_progress'
    | 'action_needed'
    | 'not_applicable'
    | 'info';

export type RequirementItem = {
    key: string;
    label: string;
    tier: 'required' | 'conditional' | 'info';
    state: RequirementState;
    detail: string;
    href: string | null;
};

export type RequirementsData = {
    done: number;
    applicable: number;
    items: RequirementItem[];
};

const STATE_ICON: Record<RequirementState, typeof Check> = {
    done: Check,
    in_progress: Clock,
    action_needed: CircleDashed,
    not_applicable: CircleDashed,
    info: Info,
};

const STATE_ICON_CLASS: Record<RequirementState, string> = {
    done: 'bg-success/15 text-success',
    in_progress: 'bg-info/15 text-info',
    action_needed: 'bg-warning/15 text-warning',
    not_applicable: 'bg-muted text-muted-foreground',
    info: 'bg-muted text-muted-foreground',
};

const STATE_LABEL: Record<RequirementState, string> = {
    done: 'Done',
    in_progress: 'In review',
    action_needed: 'Missing',
    not_applicable: 'Not due',
    info: 'Info',
};

const STATE_BADGE_CLASS: Record<RequirementState, string> = {
    done: 'border-transparent bg-success text-background',
    in_progress: 'border-transparent bg-info text-background',
    action_needed: 'border-transparent bg-warning text-background',
    not_applicable: 'border-transparent bg-muted text-muted-foreground',
    info: 'border-transparent bg-muted text-muted-foreground',
};

const RING_RADIUS = 30;
const RING_STROKE = 6;
const RING_CIRCUMFERENCE = 2 * Math.PI * RING_RADIUS;

/**
 * A small SVG progress ring for "N of M required items done" — no shadcn
 * Progress component exists in ui/ yet, and a hand-rolled two-circle ring is
 * simpler here than pulling in Recharts' RadialBar for a single static
 * value. `role="img"` + `aria-label` carries the meaning for assistive tech;
 * the numeric label inside is `aria-hidden` since it would otherwise be
 * announced twice.
 */
function ProgressRing({ done, applicable }: { done: number; applicable: number }) {
    const fraction = applicable > 0 ? done / applicable : 0;
    const offset = RING_CIRCUMFERENCE * (1 - fraction);

    return (
        <div
            className="relative inline-flex size-20 shrink-0 items-center justify-center"
            role="img"
            aria-label={`${done} of ${applicable} required items done`}
        >
            <svg viewBox="0 0 72 72" className="size-20 -rotate-90">
                <circle
                    cx="36"
                    cy="36"
                    r={RING_RADIUS}
                    fill="none"
                    stroke="var(--muted)"
                    strokeWidth={RING_STROKE}
                />
                <circle
                    cx="36"
                    cy="36"
                    r={RING_RADIUS}
                    fill="none"
                    stroke="var(--success)"
                    strokeWidth={RING_STROKE}
                    strokeLinecap="round"
                    strokeDasharray={RING_CIRCUMFERENCE}
                    strokeDashoffset={offset}
                    className="transition-[stroke-dashoffset] duration-500 motion-reduce:transition-none"
                />
            </svg>
            <span
                aria-hidden
                className="absolute text-sm font-semibold tabular-nums"
            >
                {done}/{applicable}
            </span>
        </div>
    );
}

type RequirementsChecklistProps = {
    data: RequirementsData;
};

export default function RequirementsChecklist({
    data,
}: RequirementsChecklistProps) {
    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-center gap-4">
                <ProgressRing done={data.done} applicable={data.applicable} />
                <div>
                    <p className="text-sm font-semibold">
                        {data.done} of {data.applicable} required this term
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Required and currently-due items for this academic
                        year
                    </p>
                </div>
            </div>

            <ul className="flex flex-col divide-y">
                {data.items.map((item) => {
                    const Icon = STATE_ICON[item.state];
                    const row = (
                        <div className="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
                            <span
                                className={cn(
                                    'flex size-7 shrink-0 items-center justify-center rounded-full',
                                    STATE_ICON_CLASS[item.state],
                                )}
                            >
                                <Icon className="size-3.5" aria-hidden />
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium">
                                    {item.label}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {item.detail}
                                </p>
                            </div>
                            <span
                                className={cn(
                                    'shrink-0 rounded-md px-2 py-0.5 text-[11px] font-semibold',
                                    STATE_BADGE_CLASS[item.state],
                                )}
                            >
                                {STATE_LABEL[item.state]}
                            </span>
                        </div>
                    );

                    return (
                        <li key={item.key}>
                            {item.href ? (
                                <Link
                                    href={item.href}
                                    className="-mx-1 block rounded-sm px-1 hover:bg-accent/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    {row}
                                </Link>
                            ) : (
                                row
                            )}
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
