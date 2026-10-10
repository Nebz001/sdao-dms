import { Search } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import InitialsAvatar from '@/components/initials-avatar';
import PageNotice from '@/components/page-notice';
import { ToneBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import * as registrations from '@/routes/registrations';

export type AdviserResult = { id: number; name: string; email: string; is_available: boolean };

type Props = {
    id: string;
    selected: AdviserResult | null;
    onSelect: (adviser: AdviserResult | null) => void;
    /**
     * The adviser the document already has (edit and resubmit). Shown as the
     * picked row until the student chooses "Change" and picks someone else;
     * leaving it alone keeps it.
     */
    current?: { name: string; email?: string | null } | null;
    invalid?: boolean;
    describedBy?: string;
};

/**
 * Adviser typeahead (Phase 2 item 5): a search box until an adviser is
 * picked, then a row with their initials, name and email and a "Change"
 * link. Search results come from the same endpoint as before, and an adviser
 * already assigned elsewhere is flagged, never blocked (the approve-time
 * re-check is the real guard). Typing is debounced and a slow reply for an
 * older query is ignored.
 */
export default function AdviserPicker({ id, selected, onSelect, current, invalid, describedBy }: Props) {
    const [changing, setChanging] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<AdviserResult[]>([]);
    const [status, setStatus] = useState<'idle' | 'searching' | 'done'>('idle');
    const [failed, setFailed] = useState(false);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const latest = useRef('');
    const searchRef = useRef<HTMLInputElement>(null);
    const changeRef = useRef<HTMLButtonElement>(null);

    const shown = selected ?? (current && !changing ? { name: current.name, email: current.email ?? null } : null);

    const search = useCallback((q: string) => {
        if (q.trim() === '') {
            setResults([]);
            setStatus('idle');
            setFailed(false);

            return;
        }

        setStatus('searching');
        setFailed(false);

        fetch(registrations.adviserSearch.url({ query: { q } }), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((res) => res.json())
            .then((data) => {
                if (latest.current !== q) {
                    return;
                }

                setResults(data.advisers ?? []);
                setStatus('done');
            })
            .catch(() => {
                if (latest.current !== q) {
                    return;
                }

                setFailed(true);
                setStatus('done');
            });
    }, []);

    useEffect(() => {
        latest.current = query;

        if (timer.current) {
            clearTimeout(timer.current);
        }

        timer.current = setTimeout(() => search(query), 600);

        return () => {
            if (timer.current) {
                clearTimeout(timer.current);
            }
        };
    }, [query, search]);

    function startChange() {
        setChanging(true);
        onSelect(null);
        setQuery('');
        // The search box mounts on the next render.
        setTimeout(() => searchRef.current?.focus(), 0);
    }

    function pick(adviser: AdviserResult) {
        onSelect(adviser);
        setResults([]);
        setStatus('idle');
        setQuery('');
        setTimeout(() => changeRef.current?.focus(), 0);
    }

    if (shown) {
        const unavailable = selected !== null && !selected.is_available;

        return (
            <div className="grid gap-2">
                <div className="flex items-center gap-3 rounded-lg border px-3 py-2.5">
                    <InitialsAvatar name={shown.name} className="size-9" />
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-semibold">{shown.name}</p>
                        {shown.email && <p className="truncate text-xs text-muted-foreground">{shown.email}</p>}
                    </div>
                    <Button
                        ref={changeRef}
                        type="button"
                        variant="link"
                        size="sm"
                        onClick={startChange}
                        aria-label={`Change adviser, currently ${shown.name}`}
                    >
                        Change
                    </Button>
                </div>
                {unavailable && (
                    <PageNotice tone="warning" title="This adviser is already assigned to another organization.">
                        You may still submit, but SDAO will need a different adviser to approve this.
                    </PageNotice>
                )}
            </div>
        );
    }

    return (
        <div className="grid gap-2">
            <div className="relative">
                <Search
                    aria-hidden
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    ref={searchRef}
                    id={id}
                    type="search"
                    placeholder="Search by name or email"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    autoComplete="off"
                    className="pl-9"
                    aria-invalid={invalid ? true : undefined}
                    aria-describedby={describedBy}
                />
            </div>
            <div aria-live="polite" className="grid gap-2">
                {query.trim() !== '' && status === 'searching' && (
                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Spinner className="size-3.5" /> Searching advisers…
                    </p>
                )}
                {status === 'done' && failed && (
                    <PageNotice tone="destructive" urgent title="Couldn't search advisers just now.">
                        Try again.
                    </PageNotice>
                )}
                {status === 'done' && !failed && results.length === 0 && query.trim() !== '' && (
                    <p className="text-sm text-muted-foreground">
                        No matching adviser found. Check the spelling, or contact SDAO if this adviser should be listed.
                    </p>
                )}
            </div>
            {results.length > 0 && (
                <ul aria-label="Matching advisers" className="divide-y overflow-hidden rounded-lg border">
                    {results.map((a) => (
                        <li key={a.id}>
                            <button
                                type="button"
                                onClick={() => pick(a)}
                                className="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-accent focus-visible:focus-ring"
                            >
                                <InitialsAvatar name={a.name} className="size-9" />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm font-medium">{a.name}</span>
                                    <span className="block truncate text-xs text-muted-foreground">{a.email}</span>
                                </span>
                                {!a.is_available && <ToneBadge tone="warning">Assigned elsewhere</ToneBadge>}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
