import { Link } from '@inertiajs/react';
import { Check, Clock, MessageCircle, Undo2, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { CSSProperties } from 'react';
import IconTile from '@/components/icon-tile';
import InitialsAvatar from '@/components/initials-avatar';
import { StatusBadge } from '@/components/status-badge';
import { RowChip } from '@/components/student-list';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Popover, PopoverAnchor, PopoverArrow, PopoverContent } from '@/components/ui/popover';
import { askedExact } from '@/lib/asked-time';
import { FORM_STYLE, FORM_TYPE_KIND } from '@/lib/form-style';
import { pluralize } from '@/lib/student-home';
import { longDate, stepCaption } from '@/lib/track';
import type { TrackRow, TrackStep, TrackStepState } from '@/lib/track';
import { cn } from '@/lib/utils';

/**
 * The open card after step `id` is opened or closed. Closing a step that is not
 * the open one does nothing: when you click another step, its "open" can reach
 * us before the old card's "close", and the late close must not shut the new one.
 */
export function nextOpenStep(current: string | null, id: string, open: boolean): string | null {
    return open ? id : current === id ? null : current;
}

/**
 * The circle of each state: color says what happened, the icon says it too.
 * Every fill is solid (a tint mixed into the card color, never a see-through
 * one), so nothing behind a circle can show through it. The status circles
 * (current, returned, rejected) have a halo: a 4px ring of the same tint, solid
 * too, plus a faint outer glow.
 */
const CIRCLE: Record<TrackStepState, string> = {
    done: 'bg-success text-white',
    current:
        'border-2 border-brand-soft-foreground bg-[color-mix(in_oklab,var(--brand-soft-foreground)_16%,var(--card))] text-brand-soft-foreground shadow-[0_0_0_4px_color-mix(in_oklab,var(--brand-soft-foreground)_14%,var(--card)),0_0_14px_color-mix(in_oklab,var(--brand-soft-foreground)_35%,transparent)]',
    returned:
        'border-2 border-warning bg-[color-mix(in_oklab,var(--warning)_18%,var(--card))] text-warning-foreground shadow-[0_0_0_4px_color-mix(in_oklab,var(--warning)_14%,var(--card)),0_0_14px_color-mix(in_oklab,var(--warning)_35%,transparent)]',
    rejected:
        'border-2 border-destructive bg-[color-mix(in_oklab,var(--destructive)_18%,var(--card))] text-destructive-foreground shadow-[0_0_0_4px_color-mix(in_oklab,var(--destructive)_14%,var(--card)),0_0_14px_color-mix(in_oklab,var(--destructive)_35%,transparent)]',
    upcoming: 'border border-input bg-muted text-muted-foreground',
};

const CAPTION: Record<TrackStepState, string> = {
    done: 'text-muted-foreground',
    current: 'font-medium text-brand-soft-foreground',
    returned: 'font-medium text-warning-foreground',
    rejected: 'font-medium text-destructive-foreground',
    upcoming: 'text-muted-foreground',
};

const STATUS_COLOR: Record<TrackStepState, string> = {
    done: 'text-success-foreground',
    current: 'text-brand-soft-foreground',
    returned: 'text-warning-foreground',
    rejected: 'text-destructive-foreground',
    upcoming: 'text-muted-foreground',
};

function StepIcon({ state, number }: { state: TrackStepState; number: number }) {
    switch (state) {
        case 'done':
            return <Check aria-hidden className="size-4" strokeWidth={3} />;
        case 'current':
            return <Clock aria-hidden className="size-4" />;
        case 'returned':
            return <Undo2 aria-hidden className="size-4" />;
        case 'rejected':
            return <X aria-hidden className="size-4" strokeWidth={3} />;
        default:
            return <span className="text-xs font-semibold tabular-nums">{number}</span>;
    }
}

/**
 * One card per document: who it is, its status and the action it needs, then
 * the stepper of its own approval chain. A draft has no chain yet, so it shows
 * a plain line instead.
 */
export function TrackCard({
    row,
    openStep: controlledOpen,
    onOpenStep: controlledSet,
}: {
    row: TrackRow;
    /** Which detail card is open ("rowId:stepKey"). The page holds it so a data refresh keeps it open; on its own the card keeps its own. */
    openStep?: string | null;
    onOpenStep?: (id: string, open: boolean) => void;
}) {
    const [ownOpen, setOwnOpen] = useState<string | null>(null);
    const openStep = controlledOpen !== undefined ? controlledOpen : ownOpen;
    const onOpenStep = controlledSet ?? ((id: string, open: boolean) => setOwnOpen((prev) => nextOpenStep(prev, id, open)));

    const kind = FORM_TYPE_KIND[row.formType] ?? 'proposal';
    const style = FORM_STYLE[kind];

    return (
        <Card className="gap-0 overflow-hidden py-0 shadow-none" data-status={row.status}>
            <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 px-4 py-4 sm:px-5">
                <div className="flex min-w-0 items-center gap-3">
                    <IconTile icon={style.icon} size="sm" />
                    <div className="min-w-0">
                        <h2 className="font-semibold max-sm:break-words sm:truncate">{row.title}</h2>
                        <div className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
                            {row.showFormType && <RowChip>{row.formTypeLabel}</RowChip>}
                            {row.period && <RowChip>{row.period}</RowChip>}
                            {row.submittedAt && (
                                <span>
                                    {row.status === 'draft' ? 'Started' : 'Submitted'} {longDate(row.submittedAt)}
                                </span>
                            )}
                        </div>
                    </div>
                </div>
                <div className="flex items-center gap-3">
                    <StatusBadge status={row.status} />
                    <Button
                        asChild
                        variant="outline"
                        className={cn(
                            row.action.kind === 'fix' &&
                                'border-warning/60 bg-warning/10 text-warning-foreground hover:bg-warning/20 hover:text-warning-foreground',
                            row.action.kind !== 'fix' && 'border-transparent bg-secondary text-secondary-foreground',
                        )}
                    >
                        <Link href={row.action.href} aria-label={`${row.action.label}: ${row.title}`}>
                            {row.action.label}
                        </Link>
                    </Button>
                </div>
            </div>

            <div className="border-t px-4 py-5 sm:px-5 sm:py-6">
                {row.steps === null ? (
                    <p className="text-sm text-muted-foreground">
                        Not sent yet. Once you finish and submit it, its approval steps show up here.
                    </p>
                ) : (
                    <Stepper rowId={row.id} steps={row.steps} title={row.title} openStep={openStep} onOpenStep={onOpenStep} />
                )}

                {row.note && (
                    <div className="mt-5 grid gap-1.5 rounded-md border border-warning/40 bg-warning/10 px-3 py-2.5 text-sm text-warning-foreground">
                        <p className="flex items-start gap-2">
                            <MessageCircle aria-hidden className="mt-0.5 size-4 shrink-0" />
                            <span>
                                <strong className="font-semibold">{row.note.role}’s note:</strong>{' '}
                                {row.note.comment ?? 'No note was left.'}
                            </span>
                        </p>
                        {row.note.remarks.map((remark, index) => (
                            <p key={index} className="pl-6">
                                <strong className="font-semibold">{remark.name}:</strong> {remark.body}
                            </p>
                        ))}
                    </div>
                )}
            </div>
        </Card>
    );
}

/**
 * Submitted, each step of the document's own chain, Approved. Horizontal from
 * the sm breakpoint up, vertical (the line on the left) on a phone.
 *
 * Every connector is its own segment that runs from the edge of one circle to
 * the edge of the next, so no line ever passes behind a circle. A segment is
 * green when its step is done and the step it leads to has been reached
 * (done, current, returned or rejected): the line runs green from Submitted up
 * to and into the status step. Only the segments after that step are the gray
 * track. Which detail card is open is held by the page (one at a time, by
 * step), so a refresh of the page's data never closes it.
 */
export function Stepper({
    rowId,
    steps,
    title,
    openStep,
    onOpenStep,
}: {
    rowId: number;
    steps: TrackStep[];
    title: string;
    openStep: string | null;
    /** Open or close the card of one step. A close of a card that is no longer the open one is ignored. */
    onOpenStep: (id: string, open: boolean) => void;
}) {
    return (
        <ol
            aria-label={`Approval steps for ${title}`}
            style={{ '--steps': steps.length } as CSSProperties}
            className="flex flex-col sm:grid sm:grid-cols-[repeat(var(--steps),minmax(0,1fr))]"
        >
            {steps.map((step, index) => {
                const id = `${rowId}:${step.key}`;

                return (
                    <StepNode
                        key={step.key}
                        step={step}
                        number={index + 1}
                        last={index === steps.length - 1}
                        segmentDone={step.state === 'done' && steps[index + 1]?.state !== 'upcoming'}
                        open={openStep === id}
                        onOpenChange={(next) => onOpenStep(id, next)}
                    />
                );
            })}
        </ol>
    );
}

function StepNode({
    step,
    number,
    last,
    segmentDone,
    open,
    onOpenChange,
}: {
    step: TrackStep;
    number: number;
    last: boolean;
    segmentDone: boolean;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const caption = stepCaption(step);

    const face = (
        <>
            <span
                data-circle={step.state}
                className={cn(
                    'relative flex size-8 shrink-0 items-center justify-center rounded-full',
                    CIRCLE[step.state],
                )}
            >
                <StepIcon state={step.state} number={number} />
            </span>
            <span
                className={cn(
                    'flex flex-col items-start gap-0.5 sm:items-center sm:text-center',
                    step.muted && 'opacity-60',
                )}
            >
                <span
                    className={cn(
                        'text-sm font-semibold',
                        step.state === 'upcoming' && 'font-medium text-muted-foreground',
                    )}
                >
                    {step.label}
                </span>
                {caption && <span className={cn('text-xs', CAPTION[step.state])}>{caption}</span>}
            </span>
        </>
    );

    const faceClass = 'flex w-full items-start gap-3 sm:flex-col sm:items-center sm:gap-2';

    return (
        <li data-state={step.state} className="relative pb-6 last:pb-0 sm:flex sm:flex-col sm:items-center sm:pb-0">
            {/* The segment after this step: from this circle's edge to the next circle's edge. */}
            {!last && (
                <>
                    <span
                        aria-hidden
                        data-segment={segmentDone ? 'done' : 'track'}
                        className={cn(
                            'absolute top-8 left-4 -ml-px h-[calc(100%-2rem)] w-0.5 sm:hidden',
                            segmentDone ? 'bg-success' : 'bg-border',
                        )}
                    />
                    <span
                        aria-hidden
                        data-segment={segmentDone ? 'done' : 'track'}
                        className={cn(
                            'absolute top-4 left-[calc(50%+1rem)] -mt-px hidden h-0.5 w-[calc(100%-2rem)] sm:block',
                            segmentDone ? 'bg-success' : 'bg-border',
                        )}
                    />
                </>
            )}

            {step.popover ? (
                <StepDetail step={step} open={open} onOpenChange={onOpenChange} className={faceClass}>
                    {face}
                </StepDetail>
            ) : (
                <div className={faceClass}>{face}</div>
            )}
        </li>
    );
}

/** How long the card waits after the pointer leaves before it closes, so the pointer can cross the gap into it. */
const CLOSE_DELAY_MS = 150;

/**
 * A step with its detail card: a Popover (portaled, so the parent card never
 * clips it) that is also a hover card. It opens on mouse hover, on keyboard
 * focus and on a tap or click. It closes when the pointer has left both the
 * step and the card, on Escape, or when another step opens (the page holds
 * which one is open). The delay before closing lets the pointer travel from
 * the step into the card without it vanishing.
 */
function StepDetail({
    step,
    open,
    onOpenChange,
    className,
    children,
}: {
    step: TrackStep;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    className: string;
    children: React.ReactNode;
}) {
    const popover = step.popover!;
    const closeTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const lastPointer = useRef<string>('mouse');
    const stepRef = useRef<HTMLButtonElement>(null);

    const cancelClose = useCallback(() => {
        if (closeTimer.current !== null) {
            clearTimeout(closeTimer.current);
            closeTimer.current = null;
        }
    }, []);
    const openNow = () => {
        cancelClose();
        onOpenChange(true);
    };
    const closeSoon = () => {
        cancelClose();
        closeTimer.current = setTimeout(() => onOpenChange(false), CLOSE_DELAY_MS);
    };
    const closeNow = () => {
        cancelClose();
        onOpenChange(false);
    };

    useEffect(() => cancelClose, [cancelClose]);

    // Hover is for a mouse or pen only: a finger has no hover, it taps.
    const whenMouse = (event: React.PointerEvent, handler: () => void) => {
        if (event.pointerType !== 'touch') {
            handler();
        }
    };

    return (
        <Popover open={open} onOpenChange={(next) => (next ? openNow() : closeNow())}>
            <PopoverAnchor asChild>
                <button
                    ref={stepRef}
                    type="button"
                    aria-label={`${step.label}: ${popover.status}. Show details`}
                    aria-expanded={open}
                    className={cn('rounded-md focus-visible:focus-ring', className)}
                    onPointerDown={(event) => {
                        lastPointer.current = event.pointerType;
                    }}
                    onPointerEnter={(event) => whenMouse(event, openNow)}
                    onPointerLeave={(event) => whenMouse(event, closeSoon)}
                    onFocus={(event) => {
                        // Keyboard focus opens it; a click's focus is handled by the click.
                        if (event.currentTarget.matches(':focus-visible')) {
                            openNow();
                        }
                    }}
                    onClick={() => {
                        // A tap toggles it (there is no hover to leave); a mouse click keeps it open.
                        if (lastPointer.current === 'touch' && open) {
                            closeNow();
                        } else {
                            openNow();
                        }
                    }}
                >
                    {children}
                </button>
            </PopoverAnchor>
            <PopoverContent
                side="top"
                align="center"
                sideOffset={10}
                collisionPadding={12}
                className="w-80 p-0"
                // The step itself is not "outside": pressing or focusing it must not dismiss its own card.
                onInteractOutside={(event) => {
                    if (event.target instanceof Node && stepRef.current?.contains(event.target)) {
                        event.preventDefault();
                    }
                }}
                onOpenAutoFocus={(event) => event.preventDefault()}
                onCloseAutoFocus={(event) => event.preventDefault()}
                onPointerEnter={(event) => whenMouse(event, cancelClose)}
                onPointerLeave={(event) => whenMouse(event, closeSoon)}
            >
                <PopoverArrow />
                <div className="grid gap-3 p-4">
                    {popover.people.length === 0 ? (
                        <p className="text-sm">
                            {step.state === 'upcoming' ? (
                                <span className="font-semibold">{popover.roleText}</span>
                            ) : (
                                <span className="text-muted-foreground">No one is assigned to this step yet.</span>
                            )}
                        </p>
                    ) : (
                        popover.people.map((person) => (
                            <div key={person.name} className="flex items-center gap-3">
                                <InitialsAvatar name={person.name} className="size-10" />
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-semibold">{person.name}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {person.role}
                                        {person.approved && (
                                            <span className="ml-1.5 font-medium text-success-foreground">Approved</span>
                                        )}
                                    </p>
                                </div>
                            </div>
                        ))
                    )}
                    <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 border-t pt-3 text-sm">
                        <dt className="text-muted-foreground">Status</dt>
                        <dd className={cn('font-medium', STATUS_COLOR[step.state])}>{popover.status}</dd>
                        {popover.receivedAt && (
                            <>
                                <dt className="text-muted-foreground">Received</dt>
                                <dd>{askedExact(popover.receivedAt)}</dd>
                            </>
                        )}
                        {popover.decision?.at && (
                            <>
                                <dt className="text-muted-foreground">{popover.decision.label}</dt>
                                <dd>{askedExact(popover.decision.at)}</dd>
                            </>
                        )}
                        {popover.waitingDays !== null && (
                            <>
                                <dt className="text-muted-foreground">Waiting for</dt>
                                <dd>{popover.waitingDays === 0 ? 'Since today' : pluralize(popover.waitingDays, 'day')}</dd>
                            </>
                        )}
                        {popover.people.length === 1 && popover.people[0].email && (
                            <>
                                <dt className="text-muted-foreground">Email</dt>
                                <dd className="min-w-0 break-words">{popover.people[0].email}</dd>
                            </>
                        )}
                    </dl>
                </div>
            </PopoverContent>
        </Popover>
    );
}
