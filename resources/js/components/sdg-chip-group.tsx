import type { SdgOption } from '@/components/sdg-checkbox-group';
import { Toggle } from '@/components/ui/toggle';
import { cn } from '@/lib/utils';

type Props = {
    /** Accessible name of the group. */
    label: string;
    options: SdgOption[];
    selected: string[];
    onChange: (next: string[]) => void;
    /** Submitted field name for each pressed chip. */
    name?: string;
    invalid?: boolean;
    describedBy?: string;
};

/** "4. Quality Education" -> { number: "4", text: "Quality Education" } */
function splitLabel(label: string): { number: string | null; text: string } {
    const match = /^(\d+)\.\s+(.*)$/.exec(label);

    return match
        ? { number: match[1], text: match[2] }
        : { number: null, text: label };
}

/**
 * The 17 goals as toggle chips. Each chip is a button with aria-pressed (the
 * shadcn Toggle), and every pressed chip adds a hidden input, so the choice
 * submits exactly as the checkbox list did: target_sdg[] with the enum value.
 */
export default function SdgChipGroup({
    label,
    options,
    selected,
    onChange,
    name = 'target_sdg[]',
    invalid = false,
    describedBy,
}: Props) {
    function toggle(value: string, pressed: boolean) {
        onChange(
            pressed
                ? [...selected, value]
                : selected.filter((item) => item !== value),
        );
    }

    return (
        <div
            role="group"
            aria-label={label}
            aria-describedby={describedBy}
            className="flex flex-wrap gap-2"
        >
            {options.map((option, index) => {
                const { number, text } = splitLabel(option.label);
                const pressed = selected.includes(option.value);

                return (
                    <Toggle
                        key={option.value}
                        variant="outline"
                        size="sm"
                        pressed={pressed}
                        onPressedChange={(next) => toggle(option.value, next)}
                        // The first chip carries the invalid flag so a failed
                        // submit can move focus into the group.
                        aria-invalid={invalid && index === 0 ? true : undefined}
                        className={cn(
                            'h-8 rounded-full px-3 text-xs font-medium',
                            'data-[state=on]:border-primary-text data-[state=on]:bg-primary/15 data-[state=on]:text-primary-text',
                        )}
                    >
                        {number && (
                            <span
                                aria-hidden
                                className="font-normal text-muted-foreground tabular-nums"
                            >
                                {number}
                            </span>
                        )}
                        <span>
                            {number && <span className="sr-only">{number}. </span>}
                            {text}
                        </span>
                    </Toggle>
                );
            })}
            {selected.map((value) => (
                <input key={value} type="hidden" name={name} value={value} />
            ))}
        </div>
    );
}
