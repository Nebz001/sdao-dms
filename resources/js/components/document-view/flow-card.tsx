import { Check, X } from 'lucide-react';
import AccountName from '@/components/account-name';
import { ToneBadge } from '@/components/status-badge';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { CardHeading } from './details';
import { formatLongDate } from './format';
import type { FlowNode } from './types';

function Marker({ node, index }: { node: FlowNode; index: number }) {
    const base = 'z-10 flex size-8 shrink-0 items-center justify-center rounded-full border text-sm font-semibold tabular-nums';

    switch (node.state) {
        case 'done':
            return (
                <span className={cn(base, 'border-success/40 bg-success/10 text-success-foreground')}>
                    <Check className="size-4" aria-hidden />
                    <span className="sr-only">Completed</span>
                </span>
            );
        case 'rejected':
            return (
                <span className={cn(base, 'border-destructive/40 bg-destructive/10 text-destructive-foreground')}>
                    <X className="size-4" aria-hidden />
                    <span className="sr-only">Rejected</span>
                </span>
            );
        case 'current':
            return (
                <span className={cn(base, 'border-primary-text/60 bg-primary/20 text-primary-text ring-4 ring-primary/15')}>
                    {index + 1}
                    <span className="sr-only">Current step</span>
                </span>
            );
        default:
            return (
                <span className={cn(base, 'border-border bg-muted text-muted-foreground')}>
                    {index + 1}
                    <span className="sr-only">Not yet reached</span>
                </span>
            );
    }
}

/** Every step of the approval flow, in order, with who acts on it and when it was done. */
export default function FlowCard({ flow }: { flow: FlowNode[] }) {
    return (
        <Card>
            <CardHeading title="Approval flow" />
            <CardContent>
                <ol>
                    {flow.map((node, index) => {
                        const date = formatLongDate(node.date);
                        const reached = node.state !== 'upcoming';
                        const hasActors = node.actors.length > 0;

                        return (
                            <li key={node.key} className="relative flex gap-3 pb-5 last:pb-0">
                                {index < flow.length - 1 && (
                                    <span
                                        aria-hidden
                                        className="absolute top-9 bottom-1 left-4 w-px -translate-x-1/2 bg-border"
                                    />
                                )}
                                <Marker node={node} index={index} />
                                <div className="flex min-w-0 flex-col gap-0.5 pt-1">
                                    <p className={cn('font-semibold', !reached && 'font-medium text-muted-foreground')}>
                                        {node.name}
                                    </p>
                                    {hasActors ? (
                                        <ul className="flex flex-col gap-1.5">
                                            {node.actors.map((actor) => (
                                                <li key={actor}>
                                                    <AccountName name={actor} nameClassName="text-sm font-normal text-muted-foreground" />
                                                </li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            {node.state === 'upcoming' ? 'Not yet reached' : 'No one assigned yet'}
                                        </p>
                                    )}
                                    {date && <p className="text-xs text-muted-foreground">{date}</p>}
                                    {node.isYou && (
                                        <ToneBadge tone="info" className="mt-1 self-start">
                                            You are here
                                        </ToneBadge>
                                    )}
                                </div>
                            </li>
                        );
                    })}
                </ol>
            </CardContent>
        </Card>
    );
}
