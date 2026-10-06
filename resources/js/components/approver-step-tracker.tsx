import { Circle, CircleCheck, CircleDot } from 'lucide-react';
import { Fragment } from 'react';
import { cn } from '@/lib/utils';

export type TrackerStep = {
    label: string;
    state: 'done' | 'current' | 'upcoming';
};

type ApproverStepTrackerProps = {
    steps: TrackerStep[];
    /** The whole tracker read as one sentence, e.g. "Approved by Adviser and you, now with SDAO". */
    label: string;
    className?: string;
};

const ICONS = {
    done: CircleCheck,
    current: CircleDot,
    upcoming: Circle,
} as const;

/**
 * Four points at most — the step before the approver, "You", where the
 * document is now, and "Done" — never the whole chain, which can run to
 * eight steps. State is carried by the icon (check, ring, empty circle) and
 * by the label weight, not by color; the wrapper exposes one sentence so a
 * screen reader hears the story, not four disconnected labels.
 */
export default function ApproverStepTracker({ steps, label, className }: ApproverStepTrackerProps) {
    return (
        <div role="img" aria-label={label} className={cn('flex items-start', className)}>
            {steps.map((step, index) => {
                const Icon = ICONS[step.state];
                const joinsDoneSteps = index > 0 && steps[index - 1].state === 'done' && step.state === 'done';

                return (
                    <Fragment key={`${step.label}-${index}`}>
                        {index > 0 && (
                            <span
                                aria-hidden
                                className={cn('mt-2.5 h-px w-4 shrink-0', joinsDoneSteps ? 'bg-primary' : 'bg-border')}
                            />
                        )}
                        <span aria-hidden className="flex w-16 flex-col items-center gap-1 text-center">
                            <Icon
                                className={cn(
                                    'size-5',
                                    step.state === 'done' && 'text-primary-text',
                                    step.state === 'current' && 'text-primary-text',
                                    step.state === 'upcoming' && 'text-muted-foreground',
                                )}
                            />
                            <span
                                className={cn(
                                    'text-xs leading-tight break-words',
                                    step.state === 'current' ? 'font-semibold text-foreground' : 'text-muted-foreground',
                                )}
                            >
                                {step.label}
                            </span>
                        </span>
                    </Fragment>
                );
            })}
        </div>
    );
}
