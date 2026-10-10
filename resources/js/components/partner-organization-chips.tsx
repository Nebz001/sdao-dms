import { Link2, X } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { FormField } from '@/components/form-shell';
import type { PartnerOrganization } from '@/components/partner-organizations-field';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import * as activityProposals from '@/routes/activity-proposals';

type OrganizationResult = {
    id: number;
    name: string;
    school: string | null;
    program: string | null;
};

type Props = {
    initial?: PartnerOrganization[] | null;
    errors: Record<string, string | undefined>;
    label?: string;
    helper?: string;
    /** Marks the label "optional" (the server rule decides whether it is). */
    optional?: boolean;
};

const SEARCH_DELAY_MS = 400;

/**
 * Partner Organization(s)/School(s)/RSO as removable chips inside one input.
 * Type to search the organizations table and pick a result (a "Linked" chip,
 * carrying organization_id), or press Enter to keep what was typed as free
 * text (organization_id stays empty). Submits the same fields as the repeater
 * it replaces: partner_organizations[i][name] and [i][organization_id].
 *
 * It is a combobox: arrow keys move through the results, Enter picks, Escape
 * closes, Backspace in an empty box removes the last chip. Text still sitting
 * in the box when focus leaves is kept as a free-text chip rather than lost.
 */
export default function PartnerOrganizationChips({
    initial,
    errors,
    label = 'Partner organizations, schools, or RSOs',
    helper,
    optional = false,
}: Props) {
    const inputId = useId();
    const listId = `${inputId}-results`;
    const [entries, setEntries] = useState<PartnerOrganization[]>(initial ?? []);
    const [text, setText] = useState('');
    const [results, setResults] = useState<OrganizationResult[]>([]);
    const [status, setStatus] = useState<'idle' | 'searching' | 'done'>('idle');
    const [searchFailed, setSearchFailed] = useState(false);
    const [activeIndex, setActiveIndex] = useState(-1);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const latestQuery = useRef('');
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(
        () => () => {
            if (timer.current) {
                clearTimeout(timer.current);
            }
        },
        [],
    );

    const errorMessage =
        errors.partner_organizations ??
        Object.entries(errors).find(([key]) =>
            key.startsWith('partner_organizations.'),
        )?.[1];

    function search(query: string) {
        setStatus('searching');
        setSearchFailed(false);

        fetch(
            activityProposals.partnerOrganizationSearch.url({
                query: { q: query },
            }),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        )
            .then((res) => res.json())
            .then((data) => {
                if (latestQuery.current !== query) {
                    return;
                }

                setResults(data.organizations ?? []);
                setActiveIndex(-1);
                setStatus('done');
            })
            .catch(() => {
                if (latestQuery.current !== query) {
                    return;
                }

                setSearchFailed(true);
                setStatus('done');
            });
    }

    function clearSearch() {
        if (timer.current) {
            clearTimeout(timer.current);
        }

        latestQuery.current = '';
        setText('');
        setResults([]);
        setStatus('idle');
        setSearchFailed(false);
        setActiveIndex(-1);
    }

    function addEntry(entry: PartnerOrganization) {
        const name = entry.name.trim();

        if (name === '') {
            return;
        }

        const exists = entries.some(
            (current) => current.name.toLowerCase() === name.toLowerCase(),
        );

        if (!exists) {
            setEntries((prev) => [...prev, { ...entry, name }]);
        }

        clearSearch();
    }

    function handleInput(value: string) {
        setText(value);
        latestQuery.current = value;

        if (timer.current) {
            clearTimeout(timer.current);
        }

        if (value.trim() === '') {
            setResults([]);
            setStatus('idle');
            setSearchFailed(false);

            return;
        }

        timer.current = setTimeout(() => search(value), SEARCH_DELAY_MS);
    }

    function handleKeyDown(event: KeyboardEvent<HTMLInputElement>) {
        if (event.key === 'ArrowDown' && results.length > 0) {
            event.preventDefault();
            setActiveIndex((index) => (index + 1) % results.length);
        } else if (event.key === 'ArrowUp' && results.length > 0) {
            event.preventDefault();
            setActiveIndex((index) =>
                index <= 0 ? results.length - 1 : index - 1,
            );
        } else if (event.key === 'Enter') {
            // Never let Enter submit the whole form from inside this box.
            event.preventDefault();

            const picked = results[activeIndex];

            if (picked) {
                addEntry({ organization_id: picked.id, name: picked.name });
            } else {
                addEntry({ organization_id: null, name: text });
            }
        } else if (event.key === 'Escape') {
            setResults([]);
            setActiveIndex(-1);
        } else if (event.key === 'Backspace' && text === '' && entries.length > 0) {
            setEntries((prev) => prev.slice(0, -1));
        }
    }

    const listOpen = results.length > 0;

    return (
        <FormField
            id={inputId}
            label={label}
            optional={optional}
            helper={helper}
            error={errorMessage}
        >
            {(aria) => (
                <div
                    className="relative"
                    onBlur={(event) => {
                        if (
                            !event.currentTarget.contains(
                                event.relatedTarget as Node | null,
                            )
                        ) {
                            addEntry({ organization_id: null, name: text });
                        }
                    }}
                >
                    <div
                        onClick={() => inputRef.current?.focus()}
                        className={cn(
                            'border-input focus-within:focus-ring flex min-h-9 w-full cursor-text flex-wrap items-center gap-1.5 rounded-md border bg-transparent px-2 py-1.5 shadow-xs',
                            aria['aria-invalid'] && 'border-destructive',
                        )}
                    >
                        {entries.map((entry, index) => (
                            <span
                                key={entry.name}
                                className="inline-flex max-w-full items-center gap-1.5 rounded-md bg-muted py-1 pr-1 pl-2 text-xs font-medium"
                            >
                                <span className="truncate">{entry.name}</span>
                                {entry.organization_id !== null && (
                                    <span className="inline-flex items-center gap-0.5 rounded-sm bg-success/15 px-1 text-[0.6875rem] font-medium text-success-foreground">
                                        <Link2 aria-hidden className="size-3" />
                                        Linked
                                    </span>
                                )}
                                <button
                                    type="button"
                                    onClick={() =>
                                        setEntries((prev) =>
                                            prev.filter((_, i) => i !== index),
                                        )
                                    }
                                    aria-label={`Remove ${entry.name}`}
                                    className="flex size-5 items-center justify-center rounded-sm text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:focus-ring"
                                >
                                    <X aria-hidden className="size-3" />
                                </button>
                            </span>
                        ))}
                        <input
                            ref={inputRef}
                            id={inputId}
                            role="combobox"
                            aria-expanded={listOpen}
                            aria-controls={listId}
                            aria-autocomplete="list"
                            aria-activedescendant={
                                activeIndex >= 0
                                    ? `${listId}-${activeIndex}`
                                    : undefined
                            }
                            aria-describedby={aria['aria-describedby']}
                            aria-invalid={aria['aria-invalid']}
                            value={text}
                            maxLength={255}
                            autoComplete="off"
                            placeholder={
                                entries.length === 0
                                    ? 'Search or type a name, then press Enter'
                                    : 'Add another'
                            }
                            onChange={(event) => handleInput(event.target.value)}
                            onKeyDown={handleKeyDown}
                            className="placeholder:text-muted-foreground min-w-40 flex-1 bg-transparent px-1 py-0.5 text-base outline-none md:text-sm"
                        />
                    </div>

                    {listOpen && (
                        <ul
                            id={listId}
                            role="listbox"
                            aria-label="Matching organizations"
                            className="absolute z-20 mt-1 max-h-60 w-full divide-y overflow-auto rounded-md border bg-popover text-popover-foreground shadow-md"
                        >
                            {results.map((org, index) => (
                                <li
                                    key={org.id}
                                    id={`${listId}-${index}`}
                                    role="option"
                                    aria-selected={index === activeIndex}
                                    // Keep focus in the input so the blur
                                    // handler doesn't commit free text first.
                                    onMouseDown={(event) => event.preventDefault()}
                                    onClick={() =>
                                        addEntry({
                                            organization_id: org.id,
                                            name: org.name,
                                        })
                                    }
                                    className={cn(
                                        'flex cursor-pointer flex-col gap-0.5 px-3 py-2 text-sm hover:bg-accent',
                                        index === activeIndex && 'bg-accent',
                                    )}
                                >
                                    <span>{org.name}</span>
                                    {(org.school || org.program) && (
                                        <span className="text-xs text-muted-foreground">
                                            {[org.school, org.program]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}

                    <div aria-live="polite" className="mt-1.5 text-xs text-muted-foreground">
                        {status === 'searching' && text.trim() !== '' && (
                            <span className="flex items-center gap-2">
                                <Spinner className="size-3.5" /> Searching
                                organizations…
                            </span>
                        )}
                        {status === 'done' && searchFailed && (
                            <span className="text-destructive">
                                Couldn&apos;t search organizations just now. You
                                can still press Enter to add what you typed.
                            </span>
                        )}
                        {status === 'done' &&
                            !searchFailed &&
                            results.length === 0 &&
                            text.trim() !== '' && (
                                <span>
                                    No matching organization. Press Enter to add
                                    it as free text.
                                </span>
                            )}
                    </div>

                    {entries.map((entry, index) => (
                        <span key={entry.name}>
                            <input
                                type="hidden"
                                name={`partner_organizations[${index}][name]`}
                                value={entry.name}
                            />
                            <input
                                type="hidden"
                                name={`partner_organizations[${index}][organization_id]`}
                                value={entry.organization_id ?? ''}
                            />
                        </span>
                    ))}
                </div>
            )}
        </FormField>
    );
}
