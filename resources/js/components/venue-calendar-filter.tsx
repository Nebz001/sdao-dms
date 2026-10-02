import { Check, ChevronsUpDown, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

export type ActivityScope = 'mine' | 'all';

type ScopeToggleProps = {
    value: ActivityScope;
    onChange: (value: ActivityScope) => void;
};

/** RSO users: their own organization's bookings, or everyone's. */
export function ScopeToggle({ value, onChange }: ScopeToggleProps) {
    return (
        <ToggleGroup
            type="single"
            variant="outline"
            size="sm"
            value={value}
            onValueChange={(next) => next && onChange(next as ActivityScope)}
            aria-label="Which activities to show"
        >
            <ToggleGroupItem value="mine">My activities</ToggleGroupItem>
            <ToggleGroupItem value="all">All activities</ToggleGroupItem>
        </ToggleGroup>
    );
}

type OrganizationFilterProps = {
    organizations: string[];
    /** The chosen organization, or null for "All activities". */
    value: string | null;
    onChange: (value: string | null) => void;
};

/**
 * Admin and other full-calendar roles: a searchable organization picker with
 * a clear button. Built on the app's DropdownMenu (a search field above the
 * items) because the project has no Command/Popover component installed.
 */
export function OrganizationFilter({
    organizations,
    value,
    onChange,
}: OrganizationFilterProps) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');

    const matches = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return needle
            ? organizations.filter((name) =>
                  name.toLowerCase().includes(needle),
              )
            : organizations;
    }, [organizations, query]);

    function choose(next: string | null) {
        onChange(next);
        setOpen(false);
    }

    return (
        <div className="flex items-center gap-1">
            <DropdownMenu
                open={open}
                onOpenChange={(next) => {
                    setOpen(next);
                    setQuery('');
                }}
            >
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="outline"
                        size="sm"
                        className="max-w-56 justify-between gap-2 font-normal"
                        aria-label="Filter by organization"
                    >
                        <span className="truncate">
                            {value ?? 'All activities'}
                        </span>
                        <ChevronsUpDown
                            data-icon="inline-end"
                            className="opacity-60"
                        />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-64 p-1">
                    <Input
                        autoFocus
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        // Typing must not trigger the menu's own type-ahead;
                        // arrows and Escape still reach the menu.
                        onKeyDown={(e) => {
                            if (e.key.length === 1 || e.key === 'Backspace') {
                                e.stopPropagation();
                            }
                        }}
                        placeholder="Search organizations"
                        aria-label="Search organizations"
                        className="mb-1 h-8"
                    />
                    <DropdownMenuGroup className="max-h-60 overflow-y-auto">
                        <DropdownMenuItem onSelect={() => choose(null)}>
                            <span className="flex-1">All activities</span>
                            {value === null && <Check aria-hidden />}
                        </DropdownMenuItem>
                        {matches.map((name) => (
                            <DropdownMenuItem
                                key={name}
                                onSelect={() => choose(name)}
                            >
                                <span className="flex-1">{name}</span>
                                {value === name && <Check aria-hidden />}
                            </DropdownMenuItem>
                        ))}
                        {matches.length === 0 && (
                            <p className="px-2 py-3 text-center text-sm text-muted-foreground">
                                No organizations found
                            </p>
                        )}
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>
            {value !== null && (
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    onClick={() => onChange(null)}
                    aria-label="Clear organization filter"
                >
                    <X />
                </Button>
            )}
        </div>
    );
}
