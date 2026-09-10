import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

export type SdgOption = { value: string; label: string };

type Props = {
    idPrefix: string;
    options: SdgOption[];
    selected: string[];
    onChange: (next: string[]) => void;
    /**
     * The submitted field name for each checked box, e.g. "sdg[]" (calendar
     * activity rows — inert there, since that page builds its payload from
     * React state via router.post, not native form serialization) or
     * "target_sdg[]" (activity proposals — load-bearing there, since that
     * page submits via Inertia's <Form>, which serializes the real DOM at
     * submit time). Defaults to "sdg[]" for the calendar's existing callers.
     */
    name?: string;
};

/**
 * SDG multi-select (Group B item 1, reused for Group C item 1's Target SDG).
 * A bordered, scrollable checkbox list. The 17 real UN goals don't fit a
 * single-value shadcn Select once a selection can target more than one, and
 * this project has no combobox/popover primitive to build a token-style
 * multi-select from (no `command.tsx`/`popover.tsx` under components/ui). A
 * checkbox list is the same technique already established for multi-value
 * selection elsewhere in this app — see SectionFlagFields — just without
 * that component's conditional per-item note field, which SDGs have no
 * equivalent of.
 */
export default function SdgCheckboxGroup({
    idPrefix,
    options,
    selected,
    onChange,
    name = 'sdg[]',
}: Props) {
    function toggle(value: string, checked: boolean) {
        onChange(
            checked
                ? [...selected, value]
                : selected.filter((v) => v !== value),
        );
    }

    return (
        <div className="grid max-h-56 gap-2 overflow-y-auto rounded-md border p-3 sm:grid-cols-2">
            {options.map((option) => {
                const id = `${idPrefix}-${option.value}`;

                return (
                    <div key={option.value} className="flex items-center gap-2">
                        <Checkbox
                            id={id}
                            name={name}
                            value={option.value}
                            checked={selected.includes(option.value)}
                            onCheckedChange={(value) =>
                                toggle(option.value, value === true)
                            }
                        />
                        <Label htmlFor={id} className="font-normal">
                            {option.label}
                        </Label>
                    </div>
                );
            })}
        </div>
    );
}
