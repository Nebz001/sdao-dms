import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
    initialsOf,
    parseCriteriaMechanics,
    parseObjectives,
    parseResponsiblePersons,
    toParagraphs,
} from '@/lib/proposal-text';
import type { CriteriaRow } from '@/lib/proposal-text';
import { cn } from '@/lib/utils';
import { SMALL_CAPS } from './details';

/**
 * The proposal's free-text narrative sections, shown in their real shape.
 * Display only: each one reads the stored text and falls back to plain
 * paragraphs when it does not fit. Shared by every page that shows a proposal
 * (the officer view and the approver review).
 */

const LABEL = cn(SMALL_CAPS, 'text-muted-foreground');

/** A small muted uppercase label over its content. */
export function NarrativeSection({
    label,
    labelId,
    children,
}: {
    label: string;
    labelId: string;
    children: React.ReactNode;
}) {
    return (
        <section aria-labelledby={labelId} className="flex flex-col gap-2">
            <h4 id={labelId} className={LABEL}>
                {label}
            </h4>
            {children}
        </section>
    );
}

/** Blank-line separated paragraphs, extra blank lines collapsed. */
export function Paragraphs({ text }: { text: string }) {
    return (
        <div className="flex flex-col gap-3">
            {toParagraphs(text).map((paragraph, i) => (
                <p key={i} className="leading-relaxed whitespace-pre-wrap">
                    {paragraph}
                </p>
            ))}
        </div>
    );
}

export function ObjectivesSection({ text }: { text: string }) {
    const { paragraphs, specific } = parseObjectives(text);

    return (
        <NarrativeSection label="Objectives" labelId="proposal-objectives">
            {paragraphs.length > 0 && (
                <div className="flex flex-col gap-3">
                    {paragraphs.map((paragraph, i) => (
                        <p key={i} className="leading-relaxed whitespace-pre-wrap">
                            {paragraph}
                        </p>
                    ))}
                </div>
            )}
            {specific && (
                <div className={cn('flex flex-col gap-3', paragraphs.length > 0 && 'mt-2')}>
                    <h5 id="proposal-specific-objectives" className={LABEL}>
                        Specific objectives
                    </h5>
                    <ol role="list" aria-labelledby="proposal-specific-objectives" className="flex flex-col gap-3">
                        {specific.map((item, i) => (
                            <li key={i} className="flex items-start gap-3">
                                <Badge
                                    variant="secondary"
                                    aria-hidden
                                    className="mt-0.5 size-6 rounded-full p-0 tabular-nums"
                                >
                                    {i + 1}
                                </Badge>
                                <span className="min-w-0 leading-relaxed whitespace-pre-wrap">{item}</span>
                            </li>
                        ))}
                    </ol>
                </div>
            )}
        </NarrativeSection>
    );
}

export function CriteriaMechanicsSection({ text }: { text: string }) {
    const rows = parseCriteriaMechanics(text);
    // Consecutive "Label: value" lines share one description list; any other line stays a paragraph, in order.
    const blocks: ({ kind: 'pairs'; rows: Extract<CriteriaRow, { kind: 'pair' }>[] } | { kind: 'text'; text: string })[] = [];

    for (const row of rows) {
        const last = blocks[blocks.length - 1];

        if (row.kind === 'pair') {
            if (last?.kind === 'pairs') {
                last.rows.push(row);
            } else {
                blocks.push({ kind: 'pairs', rows: [row] });
            }
        } else {
            blocks.push({ kind: 'text', text: row.text });
        }
    }

    return (
        <NarrativeSection label="Criteria and mechanics" labelId="proposal-criteria">
            <div className="flex flex-col gap-3">
                {blocks.map((block, i) =>
                    block.kind === 'text' ? (
                        <p key={i} className="leading-relaxed whitespace-pre-wrap">
                            {block.text}
                        </p>
                    ) : (
                        <dl key={i} className="flex flex-col divide-y">
                            {block.rows.map((row, j) => (
                                <div
                                    key={j}
                                    className="flex flex-col gap-1 py-3 first:pt-0 last:pb-0 sm:grid sm:grid-cols-[minmax(7rem,12rem)_1fr] sm:gap-x-6"
                                >
                                    <dt className="text-sm text-muted-foreground">{row.label}</dt>
                                    <dd className="leading-relaxed whitespace-pre-wrap">{row.value}</dd>
                                </div>
                            ))}
                        </dl>
                    ),
                )}
            </div>
        </NarrativeSection>
    );
}

export function ResponsiblePersonsSection({ entries }: { entries: string[] | null }) {
    const persons = parseResponsiblePersons(entries);

    if (persons.length === 0) {
        return null;
    }

    return (
        <NarrativeSection label="Responsible persons" labelId="proposal-responsible-persons">
            <ul role="list" className="flex flex-col divide-y">
                {persons.map((person, i) => (
                    <li key={i} className="flex items-center gap-3 py-3 first:pt-1 last:pb-0">
                        <Avatar className="size-9">
                            <AvatarFallback aria-hidden className="text-xs font-medium">
                                {initialsOf(person.name)}
                            </AvatarFallback>
                        </Avatar>
                        <div className="flex min-w-0 flex-col">
                            <span className="font-medium break-words">{person.name}</span>
                            {person.role && <span className="text-sm text-muted-foreground break-words">{person.role}</span>}
                        </div>
                    </li>
                ))}
            </ul>
        </NarrativeSection>
    );
}
