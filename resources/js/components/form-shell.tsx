import { Check, Info, Lock } from 'lucide-react';
import { useEffect, useRef } from 'react';
import type { ComponentProps, ReactNode } from 'react';
import CenteredContainer from '@/components/centered-container';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Card } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/**
 * The shared shell for the student filing forms: one centered column, the page
 * header, an optional step indicator, and one card holding the whole form
 * (FormStrip on top, FormSection blocks split by dividers, FormFooter at the
 * bottom). The column width matches AppTopbarLayout's `columnWidth` layout
 * prop, which is what lines the "Back to …" link up with the card's left edge.
 */
export const FORM_COLUMN_WIDTH = '3xl' as const;

export function FormShell({
    title,
    subtitle,
    steps,
    currentStep = 1,
    children,
}: {
    title: string;
    subtitle: string;
    /** Labels of a multi-step filing, e.g. ['Request form', 'Narrative']. */
    steps?: string[];
    /** 1-based index of the step being filled. */
    currentStep?: number;
    children: ReactNode;
}) {
    return (
        <CenteredContainer maxWidth={FORM_COLUMN_WIDTH} className="space-y-6">
            <div className="space-y-4">
                <PageHeader title={title} subtitle={subtitle} />
                {steps && <FormStepper steps={steps} current={currentStep} />}
            </div>
            {children}
        </CenteredContainer>
    );
}

/** "1 Request form — 2 Narrative". Done steps show a check. */
export function FormStepper({
    steps,
    current,
}: {
    steps: string[];
    current: number;
}) {
    return (
        <ol
            aria-label="Filing progress"
            className="flex flex-wrap items-center gap-x-3 gap-y-2"
        >
            {steps.map((label, index) => {
                const position = index + 1;
                const done = position < current;
                const active = position === current;

                return (
                    <li
                        key={label}
                        aria-current={active ? 'step' : undefined}
                        className="flex items-center gap-3"
                    >
                        <span className="flex items-center gap-2">
                            <span
                                aria-hidden
                                className={cn(
                                    'flex size-6 items-center justify-center rounded-full border text-xs font-semibold',
                                    active &&
                                        'border-primary bg-primary text-primary-foreground',
                                    done &&
                                        'border-success bg-success/15 text-success-foreground',
                                    !active &&
                                        !done &&
                                        'border-input text-muted-foreground',
                                )}
                            >
                                {done ? (
                                    <Check className="size-3.5" strokeWidth={3} />
                                ) : (
                                    position
                                )}
                            </span>
                            <span
                                className={cn(
                                    'text-sm',
                                    active
                                        ? 'font-semibold'
                                        : 'text-muted-foreground',
                                )}
                            >
                                <span className="sr-only">
                                    {`Step ${position} of ${steps.length}: `}
                                </span>
                                {label}
                            </span>
                        </span>
                        {position < steps.length && (
                            <span
                                aria-hidden
                                className={cn(
                                    'hidden h-px w-10 sm:block',
                                    done ? 'bg-success' : 'bg-border',
                                )}
                            />
                        )}
                    </li>
                );
            })}
        </ol>
    );
}

/** The card that holds every section of a form. */
export function FormCard({
    className,
    children,
}: {
    className?: string;
    children: ReactNode;
}) {
    return (
        <Card className={cn('gap-0 overflow-hidden py-0', className)}>
            {children}
        </Card>
    );
}

/** The top strip: who is filing on the left, a short note (the school) on the right. */
export function FormStrip({
    left,
    right,
}: {
    left: ReactNode;
    right?: ReactNode;
}) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-b bg-muted/40 px-5 py-3 text-sm text-muted-foreground sm:px-6">
            <p className="min-w-0">{left}</p>
            {right && <p className="min-w-0">{right}</p>}
        </div>
    );
}

/** A titled block of fields. Sections are split from each other by a divider. */
export function FormSection({
    title,
    aside,
    children,
    className,
}: {
    title: string;
    /** Small muted text at the end of the heading row ("Pick at least one"). */
    aside?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section className="space-y-4 border-b px-5 py-5 last:border-b-0 sm:px-6">
            <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <h2 className="text-base font-semibold">{title}</h2>
                {aside && (
                    <p className="text-xs text-muted-foreground" aria-live="polite">
                        {aside}
                    </p>
                )}
            </div>
            <div className={cn('space-y-4', className)}>{children}</div>
        </section>
    );
}

/**
 * Label above the control, "optional" in muted small text next to an optional
 * label, helper text under the control, and the inline error under the field.
 * `htmlFor` ties the label to the control; the helper and error ids are
 * returned to the render function so the control can reference them in
 * aria-describedby.
 */
export function FormField({
    id,
    label,
    optional = false,
    helper,
    helperAbove = false,
    error,
    className,
    children,
}: {
    id: string;
    label: ReactNode;
    optional?: boolean;
    helper?: ReactNode;
    /** Puts the helper between the label and the control (attachment slots). */
    helperAbove?: boolean;
    error?: string;
    className?: string;
    children: (aria: {
        'aria-describedby': string | undefined;
        'aria-invalid': true | undefined;
    }) => ReactNode;
}) {
    const helperId = helper ? `${id}-helper` : undefined;
    const errorId = error ? `${id}-error` : undefined;
    const helperNode = helper ? (
        <p id={helperId} className="text-xs text-muted-foreground">
            {helper}
        </p>
    ) : null;

    return (
        <div className={cn('grid gap-1.5', className)}>
            <Label htmlFor={id} className="leading-snug">
                {label}
                {optional && (
                    <span className="ml-1.5 text-xs font-normal text-muted-foreground">
                        optional
                    </span>
                )}
            </Label>
            {helperAbove && helperNode}
            {children({
                'aria-describedby':
                    [helperId, errorId].filter(Boolean).join(' ') || undefined,
                'aria-invalid': error ? true : undefined,
            })}
            {!helperAbove && helperNode}
            <InputError id={errorId} message={error} />
        </div>
    );
}

/** The footer strip: status text on the left, the buttons on the right. */
export function FormFooter({
    status,
    children,
}: {
    status?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 border-t bg-muted/40 px-5 py-4 sm:px-6">
            <p
                className="min-w-0 flex-1 basis-48 text-sm text-muted-foreground"
                aria-live="polite"
            >
                {status}
            </p>
            <div className="flex flex-wrap items-center justify-end gap-2">
                {children}
            </div>
        </div>
    );
}

/**
 * After a failed submit, moves focus to the first invalid control. Mount it
 * inside the form (it renders nothing); it finds the form through its own
 * marker node, so it works with Inertia's <Form> render prop. Controls opt in
 * with aria-invalid, which FormField hands out whenever it has an error.
 */
export function FocusFirstError({
    errors,
}: {
    errors: Record<string, string | undefined>;
}) {
    const marker = useRef<HTMLSpanElement>(null);
    const signature = JSON.stringify(errors);

    useEffect(() => {
        if (signature === '{}') {
            return;
        }

        const form = marker.current?.closest('form');
        const first = form?.querySelector<HTMLElement>('[aria-invalid="true"]');

        first?.focus();
        first?.scrollIntoView?.({ block: 'center', behavior: 'smooth' });
    }, [signature]);

    return <span ref={marker} hidden />;
}

/**
 * An input with a fixed addon on one side: a prefix ("₱") or a suffix ("%",
 * "people"). The addon is decoration; the label and aria wiring stay on the
 * input itself.
 */
export function AddonInput({
    prefix,
    suffix,
    className,
    invalid,
    ...props
}: Omit<ComponentProps<'input'>, 'prefix'> & {
    prefix?: ReactNode;
    suffix?: ReactNode;
    invalid?: boolean;
}) {
    const addon =
        'flex items-center bg-muted/50 px-3 text-sm text-muted-foreground';

    return (
        <div
            className={cn(
                'border-input focus-within:focus-ring flex h-9 items-stretch overflow-hidden rounded-md border shadow-xs',
                invalid && 'border-destructive',
                className,
            )}
        >
            {prefix && (
                <span aria-hidden className={cn(addon, 'border-r')}>
                    {prefix}
                </span>
            )}
            <input
                aria-invalid={invalid ? true : undefined}
                className="placeholder:text-muted-foreground min-w-0 flex-1 bg-transparent px-3 text-base outline-none md:text-sm"
                {...props}
            />
            {suffix && (
                <span aria-hidden className={cn(addon, 'border-l')}>
                    {suffix}
                </span>
            )}
        </div>
    );
}

/** A value that cannot be changed here: lock icon, a dotted box, and why. */
export function LockedValue({
    label,
    value,
    note,
}: {
    label: string;
    value: string;
    note: string;
}) {
    return (
        <div className="grid gap-1.5">
            <span className="text-sm leading-snug font-medium">{label}</span>
            <div className="flex items-center justify-between gap-3 rounded-md border border-dashed bg-muted/30 px-3 py-2 text-sm">
                <span>{value}</span>
                <span className="flex shrink-0 items-center gap-1.5 text-xs text-muted-foreground">
                    <Lock aria-hidden className="size-3.5" />
                    {note}
                </span>
            </div>
        </div>
    );
}

/** A tinted strip under the top strip, for one line of guidance about the whole form. */
export function FormInfoStrip({ children }: { children: ReactNode }) {
    return (
        <div
            role="status"
            className="flex items-center gap-2 border-b bg-info/10 px-5 py-2.5 text-sm text-info-foreground sm:px-6"
        >
            <Info aria-hidden className="size-4 shrink-0" />
            <p>{children}</p>
        </div>
    );
}
