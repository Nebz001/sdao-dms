import { Children } from 'react';
import type { ReactNode } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';

/** A card header: the title on the left, a muted count or note on the right. */
export function CardHeading({ title, aside }: { title: string; aside?: ReactNode }) {
    return (
        <CardHeader className="flex-row items-center justify-between gap-3">
            <CardTitle className="text-xl">{title}</CardTitle>
            {aside && <span className="text-sm text-muted-foreground tabular-nums">{aside}</span>}
        </CardHeader>
    );
}

export const SMALL_CAPS = 'text-xs font-medium tracking-wide uppercase';

/** The left column's main card: a title, then labeled sections separated by rules. */
export function DetailsCard({ title, children }: { title: string; children: ReactNode }) {
    const sections = Children.toArray(children).filter(Boolean);

    return (
        <Card>
            <CardHeading title={title} />
            <CardContent className="flex flex-col gap-6">
                {sections.map((section, index) => (
                    <div key={index} className="flex flex-col gap-6">
                        {index > 0 && <Separator />}
                        {section}
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

/** A labeled group of fields: an accent small-caps heading over a two column grid. */
export function DetailSection({ label, children }: { label: string; children: ReactNode }) {
    return (
        <section aria-label={label} className="flex flex-col gap-4">
            <h3 className={cn(SMALL_CAPS, 'text-primary-text')}>{label}</h3>
            <dl className="grid grid-cols-1 gap-x-8 gap-y-5 sm:grid-cols-2">{children}</dl>
        </section>
    );
}

/**
 * One label and value. `wide` spans both columns (long text, lists). An empty
 * value reads "Not provided" so a missing optional field is never a blank gap;
 * `hideIfEmpty` drops the field instead.
 */
export function DetailField({
    label,
    children,
    wide = false,
    hideIfEmpty = false,
    plain = false,
}: {
    label: string;
    children?: ReactNode;
    wide?: boolean;
    hideIfEmpty?: boolean;
    /** Body text rather than a bold value: used for paragraphs. */
    plain?: boolean;
}) {
    const empty = children === null || children === undefined || children === false || children === '';

    if (empty && hideIfEmpty) {
        return null;
    }

    return (
        <div className={cn('flex min-w-0 flex-col gap-1', wide && 'sm:col-span-2')}>
            <dt className={cn(SMALL_CAPS, 'text-muted-foreground')}>{label}</dt>
            <dd className={cn('break-words', plain ? 'leading-relaxed whitespace-pre-wrap' : 'font-semibold')}>
                {empty ? <span className="font-normal text-muted-foreground">Not provided</span> : children}
            </dd>
        </div>
    );
}

/** A section that is one block of prose, e.g. "Purpose of organization". */
export function DetailText({ label, children }: { label: string; children?: string | null }) {
    return (
        <section aria-label={label} className="flex flex-col gap-3">
            <h3 className={cn(SMALL_CAPS, 'text-primary-text')}>{label}</h3>
            {children ? (
                <p className="leading-relaxed break-words whitespace-pre-wrap">{children}</p>
            ) : (
                <p className="text-muted-foreground">Not provided</p>
            )}
        </section>
    );
}
