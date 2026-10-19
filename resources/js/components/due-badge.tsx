import { ToneBadge } from '@/components/status-badge';
import { toneFor } from '@/lib/status-tones';
import { cn } from '@/lib/utils';

/** Mirrors App\Approval\StepDeadline::toArray(). */
export type StepDeadline = {
    state: 'on_track' | 'due_soon' | 'overdue';
    /** "Due in 2 days", "Overdue by 1 day". */
    label: string;
    leadTimeDays: number;
    /** "3 days". */
    leadTimeLabel: string;
    dueAt: string;
    /** Display-time due date, "10/22/2026 9:00 AM". */
    dueDate: string;
    arrivedAt: string;
    stepPosition: number;
    daysOverdue: number;
    daysRemaining: number;
};

type DueBadgeProps = {
    deadline: StepDeadline | null | undefined;
    className?: string;
};

/**
 * Where the document's current step stands against its allotted lead time:
 * "Due in 2 days", "Overdue by 1 day". Toned by the state the server computed
 * (StepDeadline::state(); the due math lives only in PHP), with the words
 * always printed so the tone is never the only signal. Renders nothing when a
 * document has no running step clock (not in review).
 */
export default function DueBadge({ deadline, className }: DueBadgeProps) {
    if (!deadline) {
        return null;
    }

    return (
        <ToneBadge
            tone={toneFor('deadline', deadline.state)}
            title={`Due ${deadline.dueDate}. ${deadline.leadTimeLabel} allotted for this step.`}
            className={cn('font-mono normal-case tracking-normal tabular-nums', className)}
        >
            {deadline.label}
        </ToneBadge>
    );
}
