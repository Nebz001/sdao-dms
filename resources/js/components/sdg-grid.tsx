import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

export type SdgOption = { value: string; label: string };

type Props = {
    /** Accessible name of the group. */
    label: string;
    options: SdgOption[];
    selected: string[];
    onChange: (next: string[]) => void;
    /** Submitted field name for each checked tile, e.g. "target_sdg[]". Omit where the page builds its own payload. */
    name?: string;
    invalid?: boolean;
    describedBy?: string;
    /** Unique per picker on a page, so tile ids never collide. */
    idPrefix?: string;
};

/** "4. Quality Education" -> { number: "4", text: "Quality Education" } */
export function splitSdgLabel(label: string): {
    number: string | null;
    text: string;
} {
    const match = /^(\d+)\.\s*(.*)$/.exec(label);

    return match
        ? { number: match[1], text: match[2] }
        : { number: null, text: label };
}

/**
 * The 17 goals as a grid of equal-size tiles. Each tile is a label around a
 * real checkbox, so Tab, Space and the screen reader's "checked" state come
 * from the browser. A checked tile gets a primary border, a tint and a check
 * mark, so the state never rests on color alone. Columns follow the width of
 * the picker (container queries), not the viewport. Stored values are
 * unchanged: each checked tile submits `name` with the enum value.
 */
export default function SdgGrid({
    label,
    options,
    selected,
    onChange,
    name,
    invalid = false,
    describedBy,
    idPrefix = 'sdg',
}: Props) {
    function toggle(value: string, checked: boolean) {
        onChange(
            checked
                ? [...selected, value]
                : selected.filter((item) => item !== value),
        );
    }

    return (
        <div className="@container">
            <div
                role="group"
                aria-label={label}
                aria-describedby={describedBy}
                className="grid auto-rows-fr grid-cols-1 gap-2 @md:grid-cols-2 @2xl:grid-cols-3 @4xl:grid-cols-4"
            >
                {options.map((option, index) => {
                    const { number, text } = splitSdgLabel(option.label);
                    const checked = selected.includes(option.value);
                    const id = `${idPrefix}-${option.value}`;

                    return (
                        <label
                            key={option.value}
                            htmlFor={id}
                            title={option.label}
                            className={cn(
                                'relative flex h-18 min-w-0 cursor-pointer items-center gap-3 rounded-lg border bg-background px-3 py-2 text-sm transition-colors',
                                'hover:bg-accent has-[:focus-visible]:focus-ring',
                                checked
                                    ? 'border-primary-text bg-primary/10 ring-1 ring-primary-text'
                                    : 'border-input',
                                invalid && !checked && 'border-destructive',
                            )}
                        >
                            <input
                                id={id}
                                type="checkbox"
                                name={name}
                                value={option.value}
                                checked={checked}
                                onChange={(event) =>
                                    toggle(option.value, event.target.checked)
                                }
                                aria-label={
                                    number
                                        ? `SDG ${number}: ${text}`
                                        : option.label
                                }
                                // The first tile carries the invalid flag so a failed
                                // submit can move focus into the group.
                                aria-invalid={
                                    invalid && index === 0 ? true : undefined
                                }
                                className="peer sr-only"
                            />
                            <span
                                aria-hidden
                                className={cn(
                                    'flex size-8 shrink-0 items-center justify-center rounded-md text-sm font-semibold tabular-nums',
                                    checked
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-muted-foreground',
                                )}
                            >
                                {number ?? '•'}
                            </span>
                            <span
                                aria-hidden
                                className="line-clamp-3 min-w-0 flex-1 leading-snug font-medium break-words"
                            >
                                {text}
                            </span>
                            <Check
                                aria-hidden
                                strokeWidth={3}
                                className={cn(
                                    'size-4 shrink-0 text-primary-text',
                                    checked ? 'visible' : 'invisible',
                                )}
                            />
                        </label>
                    );
                })}
            </div>
        </div>
    );
}
