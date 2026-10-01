import { Check, Circle, Undo2 } from 'lucide-react';
import { cn } from '@/lib/utils';

export type TrackerStep = {
    position: number;
    label: string;
    state: string;
    requiredApprovals: number;
    approvalsSoFar: number;
};

type DocumentStepTrackerProps = {
    steps: TrackerStep[];
};

const STATE_DOT_CLASS: Record<string, string> = {
    done: 'bg-success text-background',
    current: 'bg-primary text-primary-foreground ring-1 ring-brand-edge',
    returned: 'bg-warning text-background',
    upcoming: 'bg-muted text-muted-foreground',
};

const STATE_LABEL_CLASS: Record<string, string> = {
    done: 'text-foreground',
    current: 'font-semibold text-foreground',
    returned: 'font-semibold text-foreground',
    upcoming: 'text-muted-foreground',
};

const STATE_LINE_CLASS: Record<string, string> = {
    done: 'bg-success',
    current: 'bg-muted',
    returned: 'bg-muted',
    upcoming: 'bg-muted',
};

/**
 * A compact horizontal step tracker for one document's approval chain — the
 * student-dashboard sibling of the approver side's own progress cues, reused
 * across every tracker row rather than duplicated per card. `state` and the
 * "N of M" approval count are both server-computed
 * (StudentDashboardData::trackerSteps()); this component only picks the icon
 * and color per state. The "N of M" badge only renders for the active step
 * (current or returned) of a multi-approver step like SDAO's — a done or
 * upcoming step's approvalsSoFar is always 0 server-side (only the current
 * step's count is actually tallied), so showing it there would misleadingly
 * read as "0 of 2" for a step that already passed.
 */
export default function DocumentStepTracker({
    steps,
}: DocumentStepTrackerProps) {
    if (steps.length === 0) {
        return null;
    }

    return (
        <ol className="flex items-start gap-0">
            {steps.map((step, index) => {
                const isActive = step.state === 'current' || step.state === 'returned';
                const showApprovalCount = isActive && step.requiredApprovals > 1;

                return (
                    <li
                        key={step.position}
                        className={cn(
                            'flex flex-1 flex-col items-center gap-1.5',
                            index !== 0 && 'relative',
                        )}
                    >
                        {index !== 0 && (
                            <span
                                aria-hidden
                                className={cn(
                                    'absolute top-3 right-1/2 h-0.5 w-full -translate-y-1/2',
                                    STATE_LINE_CLASS[steps[index - 1].state] ??
                                        STATE_LINE_CLASS.upcoming,
                                )}
                            />
                        )}
                        <span
                            className={cn(
                                'relative flex size-6 shrink-0 items-center justify-center rounded-full',
                                STATE_DOT_CLASS[step.state] ??
                                    STATE_DOT_CLASS.upcoming,
                            )}
                        >
                            {step.state === 'done' && (
                                <Check className="size-3.5" aria-hidden />
                            )}
                            {step.state === 'returned' && (
                                <Undo2 className="size-3.5" aria-hidden />
                            )}
                            {(step.state === 'current' ||
                                step.state === 'upcoming') && (
                                <Circle
                                    className="size-2 fill-current"
                                    aria-hidden
                                />
                            )}
                        </span>
                        <div className="flex flex-col items-center text-center">
                            <span
                                className={cn(
                                    'text-xs',
                                    STATE_LABEL_CLASS[step.state] ??
                                        STATE_LABEL_CLASS.upcoming,
                                )}
                            >
                                {step.label}
                            </span>
                            {showApprovalCount && (
                                <span className="text-[11px] text-muted-foreground tabular-nums">
                                    {step.approvalsSoFar} of{' '}
                                    {step.requiredApprovals}
                                </span>
                            )}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
