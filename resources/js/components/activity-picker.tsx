import { ChevronDown, Clock, MapPin } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverAnchor,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    dateBadgeParts,
    formatActivityDate,
    formatClockRange,
} from '@/lib/activity-time';
import { cn } from '@/lib/utils';

export type PickerActivity = {
    id: string;
    title: string;
    /** "YYYY-MM-DD", Asia/Manila wall date. */
    date: string;
    start_time?: string | null;
    end_time?: string | null;
    venue?: string | null;
    /** Heading the row is listed under, e.g. "1st Term, 2026-2027". */
    group?: string | null;
    /** Why the row cannot be picked ("Already proposed"). Null or absent: pickable. */
    disabledReason?: string | null;
};

/** The month-over-day tile. Blue when its row is active or picked. */
export function DateBadge({
    date,
    active = false,
    muted = false,
    className,
}: {
    date: string;
    active?: boolean;
    muted?: boolean;
    className?: string;
}) {
    const { month, day } = dateBadgeParts(date);

    return (
        <span
            aria-hidden
            className={cn(
                'flex size-11 shrink-0 flex-col items-center justify-center rounded-lg leading-none transition-colors',
                active
                    ? 'bg-blue-500/15 text-blue-700 dark:bg-blue-400/20 dark:text-blue-300'
                    : 'bg-muted text-muted-foreground',
                muted && 'opacity-70',
                className,
            )}
        >
            <span className="text-[0.625rem] font-semibold tracking-wide">
                {month}
            </span>
            <span className="text-lg font-bold">{day}</span>
        </span>
    );
}

/** "8:00 AM to 5:00 PM" with a clock icon, then the venue with a pin icon. */
function ActivityMeta({
    activity,
    showDate = false,
}: {
    activity: PickerActivity;
    showDate?: boolean;
}) {
    const time = formatClockRange(activity.start_time, activity.end_time);

    return (
        <span className="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
            {showDate && <span>Held {formatActivityDate(activity.date)}</span>}
            {time && (
                <span className="flex items-center gap-1">
                    <Clock aria-hidden className="size-3.5" />
                    {time}
                </span>
            )}
            {activity.venue && (
                <span className="flex min-w-0 items-center gap-1">
                    <MapPin aria-hidden className="size-3.5 shrink-0" />
                    <span className="truncate">{activity.venue}</span>
                </span>
            )}
        </span>
    );
}

/**
 * The picked activity: blue date badge, title, time and venue, and (when the
 * activity can still be changed) a "Change" link. Also used on its own, with
 * no onChange, on the edit-and-resubmit pages, where the activity is fixed.
 */
export function ActivityCard({
    activity,
    onChange,
    showDate = false,
    changeRef,
}: {
    activity: PickerActivity;
    onChange?: () => void;
    /** Adds "Held Oct 15, 2026" to the line under the title. */
    showDate?: boolean;
    changeRef?: React.Ref<HTMLButtonElement>;
}) {
    return (
        <div className="flex items-center gap-3 rounded-lg border bg-muted/30 px-3 py-2.5">
            <DateBadge date={activity.date} active />
            <div className="min-w-0 flex-1 space-y-0.5">
                <p className="truncate text-sm font-semibold">{activity.title}</p>
                <ActivityMeta activity={activity} showDate={showDate} />
            </div>
            {onChange && (
                <Button
                    ref={changeRef}
                    type="button"
                    variant="link"
                    size="sm"
                    onClick={onChange}
                    aria-label={`Change activity, currently ${activity.title}`}
                >
                    Change
                </Button>
            )}
        </div>
    );
}

type Props = {
    /** The trigger's id, so a <label htmlFor> and focus-on-error find it. */
    id: string;
    activities: PickerActivity[];
    /** The picked activity's id, or '' for none. */
    value: string;
    onChange: (id: string) => void;
    placeholder: string;
    /** Submitted under this name (a hidden input) when given. */
    name?: string;
    /** One muted line under the selected card. */
    helper?: ReactNode;
    showDate?: boolean;
    invalid?: boolean;
    describedBy?: string;
};

/**
 * Searchable activity picker (shadcn Popover + Command). Closed with nothing
 * picked it is a select-style trigger; open it is a list that filters by title
 * and venue, grouped by term when the list spans more than one, with rows that
 * cannot be picked kept visible, faded, and labelled with the reason. Once an
 * activity is picked the trigger gives way to its card, whose "Change" link
 * reopens the same list. cmdk supplies arrow keys, Enter and Escape.
 */
export default function ActivityPicker({
    id,
    activities,
    value,
    onChange,
    placeholder,
    name,
    helper,
    showDate = false,
    invalid = false,
    describedBy,
}: Props) {
    const [open, setOpen] = useState(false);
    const listId = useId();
    const triggerRef = useRef<HTMLButtonElement>(null);
    const changeRef = useRef<HTMLButtonElement>(null);

    const picked = activities.find((activity) => activity.id === value);
    const groups = [...new Set(activities.map((a) => a.group ?? ''))];
    const showHeadings = groups.length > 1;

    function pick(activity: PickerActivity) {
        onChange(activity.id);
        setOpen(false);
    }

    function row(activity: PickerActivity) {
        const disabled = Boolean(activity.disabledReason);
        const reasonId = `${listId}-${activity.id}-reason`;

        return (
            <CommandItem
                key={activity.id}
                value={`${activity.id}`}
                keywords={[
                    activity.title,
                    activity.venue ?? '',
                    formatActivityDate(activity.date),
                ]}
                disabled={disabled}
                onSelect={() => pick(activity)}
                aria-describedby={disabled ? reasonId : undefined}
                className="group gap-3 px-2 py-2 data-[selected=true]:bg-blue-500/10 dark:data-[selected=true]:bg-blue-400/10"
            >
                <DateBadge
                    date={activity.date}
                    muted={disabled}
                    className="group-data-[selected=true]:bg-blue-500/15 group-data-[selected=true]:text-blue-700 dark:group-data-[selected=true]:bg-blue-400/20 dark:group-data-[selected=true]:text-blue-300"
                />
                <span className="min-w-0 flex-1 space-y-0.5">
                    <span className="block truncate text-sm font-semibold text-foreground">
                        {activity.title}
                    </span>
                    <span className="block truncate text-xs text-muted-foreground">
                        {[
                            formatClockRange(activity.start_time, activity.end_time),
                            activity.venue,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </span>
                </span>
                {disabled && (
                    <span
                        id={reasonId}
                        className="shrink-0 rounded-full bg-muted px-2 py-0.5 text-[0.6875rem] font-medium text-muted-foreground"
                    >
                        {activity.disabledReason}
                    </span>
                )}
            </CommandItem>
        );
    }

    return (
        <div className="grid gap-2">
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverAnchor asChild>
                    <div>
                        {picked ? (
                            <ActivityCard
                                activity={picked}
                                showDate={showDate}
                                changeRef={changeRef}
                                onChange={() => setOpen(true)}
                            />
                        ) : (
                            <PopoverTrigger asChild>
                                <button
                                    ref={triggerRef}
                                    id={id}
                                    type="button"
                                    role="combobox"
                                    aria-expanded={open}
                                    aria-controls={open ? listId : undefined}
                                    aria-haspopup="listbox"
                                    aria-invalid={invalid ? true : undefined}
                                    aria-describedby={describedBy}
                                    onKeyDown={(event) => {
                                        if (event.key === 'ArrowDown') {
                                            event.preventDefault();
                                            setOpen(true);
                                        }
                                    }}
                                    className={cn(
                                        'border-input flex h-10 w-full items-center justify-between gap-2 rounded-md border bg-transparent px-3 text-left text-sm shadow-xs transition-[color,box-shadow]',
                                        'text-muted-foreground focus-visible:focus-ring',
                                        'aria-expanded:border-primary-text',
                                        'aria-invalid:border-destructive',
                                    )}
                                >
                                    <span className="truncate">{placeholder}</span>
                                    <ChevronDown
                                        aria-hidden
                                        className={cn(
                                            'size-4 shrink-0 transition-transform',
                                            open && 'rotate-180',
                                        )}
                                    />
                                </button>
                            </PopoverTrigger>
                        )}
                    </div>
                </PopoverAnchor>

                <PopoverContent
                    align="start"
                    className="w-(--radix-popper-anchor-width) max-w-[calc(100vw-2rem)] p-0"
                    onCloseAutoFocus={(event) => {
                        // The trigger is gone once something is picked, so
                        // hand focus to whichever control stands in for it.
                        event.preventDefault();
                        (picked ? changeRef : triggerRef).current?.focus();
                    }}
                >
                    <Command
                        filter={(_value, search, keywords) =>
                            (keywords ?? [])
                                .join(' ')
                                .toLowerCase()
                                .includes(search.trim().toLowerCase())
                                ? 1
                                : 0
                        }
                    >
                        <CommandInput placeholder="Search activities" aria-label="Search activities" />
                        <CommandList id={listId}>
                            <CommandEmpty>No activities match</CommandEmpty>
                            {groups.map((group) => {
                                const inGroup = activities.filter(
                                    (a) => (a.group ?? '') === group,
                                );

                                return (
                                    <CommandGroup
                                        key={group || 'all'}
                                        heading={showHeadings ? group : undefined}
                                    >
                                        {inGroup.map(row)}
                                    </CommandGroup>
                                );
                            })}
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>

            {picked && helper && (
                <p className="text-xs text-muted-foreground">{helper}</p>
            )}
            {name && <input type="hidden" name={name} value={value} />}
        </div>
    );
}
