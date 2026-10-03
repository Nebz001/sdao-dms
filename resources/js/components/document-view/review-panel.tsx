import { CircleCheck, CircleX, Clock, FileEdit, Hourglass, Undo2 } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { formatLongDate } from './format';
import type { DocumentViewData } from './types';

type PanelTone = 'success' | 'destructive' | 'warning' | 'info' | 'neutral';

/** Tinted card surfaces built from the existing status tokens only. */
const SURFACE: Record<PanelTone, { card: string; icon: string; title: string }> = {
    success: {
        card: 'border-success/40 bg-success/10',
        icon: 'bg-success/15 text-success-foreground',
        title: 'text-success-foreground',
    },
    destructive: {
        card: 'border-destructive/40 bg-destructive/10',
        icon: 'bg-destructive/15 text-destructive-foreground',
        title: 'text-destructive-foreground',
    },
    warning: {
        card: 'border-warning/40 bg-warning/10',
        icon: 'bg-warning/15 text-warning-foreground',
        title: 'text-warning-foreground',
    },
    info: {
        card: 'border-info/40 bg-info/10',
        icon: 'bg-info/15 text-info-foreground',
        title: 'text-info-foreground',
    },
    neutral: {
        card: 'border-border bg-muted/40',
        icon: 'bg-muted text-muted-foreground',
        title: 'text-foreground',
    },
};

/** An icon, a bold title and one sentence, on a surface tinted for the state. */
export function StateBanner({
    tone,
    icon: Icon,
    title,
    children,
}: {
    tone: PanelTone;
    icon: LucideIcon;
    title: string;
    children: ReactNode;
}) {
    const surface = SURFACE[tone];

    return (
        <div
            role={tone === 'destructive' ? 'alert' : 'status'}
            className={cn('flex gap-4 rounded-xl border p-5', surface.card)}
        >
            <span className={cn('flex size-10 shrink-0 items-center justify-center rounded-lg', surface.icon)}>
                <Icon className="size-5" aria-hidden />
            </span>
            <div className="flex min-w-0 flex-col gap-1">
                <p className={cn('text-lg leading-tight font-semibold', surface.title)}>{title}</p>
                <div className="text-sm text-foreground/80">{children}</div>
            </div>
        </div>
    );
}

type Props = {
    view: DocumentViewData;
    status: string;
    /** The noun used in sentences: "registration", "proposal". */
    noun: string;
    /** Approver pages explain what happens next to the approver; student pages to the organization. */
    audience: 'approver' | 'student';
    /** The viewer is a current approver of this step (server-checked). */
    canAct: boolean;
    /** The viewer already approved this step and is waiting on a co-approver. */
    hasApproved?: boolean;
    /** The decision card (approve, return, reject). Only mounted while the document is waiting on the viewer. */
    decision?: ReactNode;
    /** Page-specific warnings shown above the decision, e.g. a venue conflict. */
    notices?: ReactNode;
};

/**
 * The right column's state-driven top block. Which of these shows is decided
 * here, in one place, from the document's state and the viewer's role:
 *
 * - waiting on the viewer: a "Waiting on you" banner plus the decision card
 * - already decided: a green, red or amber status card
 * - in review but not the viewer's turn: who it is waiting on
 *
 * The decision controls only exist while the document is waiting on the viewer.
 */
export default function ReviewPanel({ notices, ...props }: Props) {
    return (
        <div className="flex flex-col gap-4">
            {notices}
            <PanelBody {...props} />
        </div>
    );
}

function PanelBody({ view, status, noun, audience, canAct, hasApproved = false, decision }: Omit<Props, 'notices'>) {
    const { waiting, quorum } = view;

    if (canAct && waiting && decision) {
        const days = waiting.days === 0 ? 'since today' : `${waiting.days} ${waiting.days === 1 ? 'day' : 'days'}`;

        return (
            <div className="flex flex-col gap-4">
                <StateBanner tone="warning" icon={Clock} title="Waiting on you">
                    Step {waiting.step} of {waiting.totalSteps}, {waiting.stepName}.{' '}
                    {waiting.days === 0 ? 'Submitted today.' : `Waiting ${days}.`}
                    {quorum && quorum.approvedBy.length > 0 && (
                        <>
                            {' '}
                            Approved by {quorum.approvedBy.join(', ')} ({quorum.approvedBy.length} of {quorum.required}).
                        </>
                    )}
                </StateBanner>
                {decision}
            </div>
        );
    }

    switch (status) {
        case 'approved':
            return (
                <StateBanner tone="success" icon={CircleCheck} title="Approved">
                    This {noun} is fully approved. No further action is needed.
                </StateBanner>
            );
        case 'rejected': {
            const on = formatLongDate(view.record.decidedOn);

            return (
                <StateBanner tone="destructive" icon={CircleX} title="Rejected">
                    This {noun} was rejected{on ? ` on ${on}` : ''}. No further action is available.
                    {audience === 'approver' ? ' The organization' : ' Your organization'} must file a new one.
                </StateBanner>
            );
        }
        case 'returned':
            return (
                <StateBanner tone="warning" icon={Undo2} title="Returned for revision">
                    {audience === 'approver'
                        ? `This ${noun} is with the organization for changes. It comes back to the approver who returned it once they resubmit.`
                        : `This ${noun} was sent back to you. Review the message in the history, fix the flagged parts and resubmit.`}
                </StateBanner>
            );
        case 'draft':
            return (
                <StateBanner tone="neutral" icon={FileEdit} title="Draft">
                    This {noun} has not been submitted yet. It enters review once it is submitted.
                </StateBanner>
            );
        default:
            return (
                <div className="flex flex-col gap-4">
                    <StateBanner tone="info" icon={Hourglass} title={hasApproved ? 'Your approval is recorded' : 'In review'}>
                        {hasApproved
                            ? `Waiting for the other approver on this step.${quorum ? ` Approved by ${quorum.approvedBy.join(', ')} (${quorum.approvedBy.length} of ${quorum.required}).` : ''}`
                            : waiting
                              ? audience === 'approver'
                                  ? `Waiting on ${waiting.stepName.toLowerCase()} (step ${waiting.step} of ${waiting.totalSteps}). No action is needed from you.`
                                  : `Waiting on ${waiting.stepName.toLowerCase()} (step ${waiting.step} of ${waiting.totalSteps}).`
                              : `This ${noun} is moving through the approval flow.`}
                    </StateBanner>
                </div>
            );
    }
}
