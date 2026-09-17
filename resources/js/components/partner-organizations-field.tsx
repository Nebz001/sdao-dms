import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import * as activityProposals from '@/routes/activity-proposals';

export type PartnerOrganization = {
    organization_id: number | null;
    name: string;
};

type OrganizationResult = {
    id: number;
    name: string;
    school: string | null;
    program: string | null;
};

type PartnerOrganizationsFieldProps = {
    initial?: PartnerOrganization[] | null;
    errors: Record<string, string>;
};

/**
 * The "+ Add"/"Remove" repeater for Partner Organization(s)/School(s)/RSO.
 * Each row is a searchable combobox (PartnerOrganizationRow below) backed by
 * the organizations table, with typing-without-picking as the free-text
 * fallback — there's no separate mode toggle, and no cmdk/Popover primitive
 * to build a real combobox from (none installed in this project; see
 * components/sdg-checkbox-group.tsx's docblock for the same reasoning on a
 * different field). Hand-rolled instead, following the debounced Input +
 * bordered result-list pattern already established by
 * pages/organizations/join/create.tsx and pages/registrations/create.tsx's
 * adviser search.
 *
 * Owns the whole repeater's state (not just one row) since both
 * activity-proposals/create.tsx and edit.tsx duplicated this entire block
 * identically before — same reason components/expense-items-table.tsx was
 * extracted for that field's pair of pages.
 */
export default function PartnerOrganizationsField({ initial, errors }: PartnerOrganizationsFieldProps) {
    const [entries, setEntries] = useState<PartnerOrganization[]>(
        initial && initial.length > 0 ? initial : [{ organization_id: null, name: '' }],
    );

    function updateEntry(index: number, next: PartnerOrganization) {
        setEntries((prev) => {
            const updated = [...prev];
            updated[index] = next;

            return updated;
        });
    }

    return (
        <div className="space-y-1">
            <div className="flex items-center justify-between">
                <Label>Partner Organization(s)/School(s)/RSO</Label>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setEntries((prev) => [...prev, { organization_id: null, name: '' }])}
                >
                    + Add
                </Button>
            </div>
            {entries.map((entry, i) => (
                <PartnerOrganizationRow
                    key={i}
                    index={i}
                    value={entry}
                    onChange={(next) => updateEntry(i, next)}
                    onRemove={() => setEntries((prev) => prev.filter((_, idx) => idx !== i))}
                    canRemove={entries.length > 1}
                    // .name covers a missing/too-long name; .organization_id
                    // covers a tampered or since-deleted id (the `exists`
                    // rule) — either can fire for the same row, never both
                    // at once in practice, so showing whichever is present
                    // is enough.
                    error={errors[`partner_organizations.${i}.name`] ?? errors[`partner_organizations.${i}.organization_id`]}
                />
            ))}
            <InputError message={errors.partner_organizations} />
        </div>
    );
}

type PartnerOrganizationRowProps = {
    index: number;
    value: PartnerOrganization;
    onChange: (value: PartnerOrganization) => void;
    onRemove: () => void;
    canRemove: boolean;
    error?: string;
};

/**
 * One combobox row. The visible Input carries the real submitted value
 * itself (name={`partner_organizations[${index}][name]`}) — unlike the
 * adviser typeahead, where only the resolved id is ever valid, free text IS
 * a legitimate final value here, so the search box doubles as the actual
 * form field. Only organization_id needs a companion hidden input (same
 * one-hidden-input shape as registrations/edit.tsx's adviser picker).
 *
 * Search is scheduled directly from the input's onChange, not from a
 * useEffect keyed on `value.name` — deliberately, because picking a result
 * also changes `value.name` (to the picked org's name), and an effect keyed
 * on that value would schedule a fresh 600ms search right after selecting,
 * silently reopening the result list the user just closed. Scheduling only
 * from the actual keystroke handler avoids that without needing an extra
 * "did this change come from typing or picking" flag.
 */
function PartnerOrganizationRow({ index, value, onChange, onRemove, canRemove, error }: PartnerOrganizationRowProps) {
    const [results, setResults] = useState<OrganizationResult[]>([]);
    const [status, setStatus] = useState<'idle' | 'searching' | 'done'>('idle');
    const [searchFailed, setSearchFailed] = useState(false);
    const debounceTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const latestQuery = useRef('');

    useEffect(() => {
        return () => {
            if (debounceTimer.current) {
                clearTimeout(debounceTimer.current);
            }
        };
    }, []);

    function search(query: string) {
        setStatus('searching');
        setSearchFailed(false);

        fetch(activityProposals.partnerOrganizationSearch.url({ query: { q: query } }), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((res) => res.json())
            .then((data) => {
                // Stale-response guard: ignore a slow reply for a query the
                // student has since changed or cleared.
                if (latestQuery.current !== query) {
                    return;
                }

                setResults(data.organizations ?? []);
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

    function handleInputChange(text: string) {
        onChange({ organization_id: null, name: text });

        latestQuery.current = text;

        if (debounceTimer.current) {
            clearTimeout(debounceTimer.current);
        }

        if (text.trim() === '') {
            setResults([]);
            setStatus('idle');
            setSearchFailed(false);

            return;
        }

        debounceTimer.current = setTimeout(() => search(text), 600);
    }

    function selectResult(org: OrganizationResult) {
        if (debounceTimer.current) {
            clearTimeout(debounceTimer.current);
        }

        onChange({ organization_id: org.id, name: org.name });
        setResults([]);
        setStatus('idle');
        setSearchFailed(false);
    }

    return (
        <div className="space-y-1">
            <div className="flex items-center gap-2">
                <Input
                    name={`partner_organizations[${index}][name]`}
                    value={value.name}
                    onChange={(e) => handleInputChange(e.target.value)}
                    placeholder="Search organizations, or type a name…"
                    autoComplete="off"
                />
                {canRemove && (
                    <Button type="button" variant="ghost" size="sm" onClick={onRemove}>
                        Remove
                    </Button>
                )}
            </div>
            <input type="hidden" name={`partner_organizations[${index}][organization_id]`} value={value.organization_id ?? ''} />
            {value.name.trim() !== '' && status === 'searching' && (
                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Spinner className="size-3.5" /> Searching organizations…
                </p>
            )}
            {status === 'done' && searchFailed && (
                <p className="text-sm text-destructive">Couldn't search organizations just now. Try again.</p>
            )}
            {status === 'done' && !searchFailed && results.length === 0 && value.organization_id === null && (
                <p className="text-sm text-muted-foreground">
                    No matching organization found — you can still enter this as free text.
                </p>
            )}
            {results.length > 0 && (
                <div className="rounded-md border divide-y">
                    {results.map((org) => (
                        <button
                            key={org.id}
                            type="button"
                            onClick={() => selectResult(org)}
                            className="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left text-sm hover:bg-accent"
                        >
                            <span>{org.name}</span>
                            {(org.school || org.program) && (
                                <span className="text-xs text-muted-foreground">
                                    {[org.school, org.program].filter(Boolean).join(' · ')}
                                </span>
                            )}
                        </button>
                    ))}
                </div>
            )}
            <InputError message={error} />
        </div>
    );
}
