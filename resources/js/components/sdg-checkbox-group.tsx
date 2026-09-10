import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

export type SdgOption = { value: string; label: string };

type Props = {
    idPrefix: string;
    options: SdgOption[];
    selected: string[];
    onChange: (next: string[]) => void;
};

/**
 * SDG multi-select (Group B item 1) — a bordered, scrollable checkbox list.
 * The 17 real UN goals don't fit a single-value shadcn Select once an
 * activity can target more than one, and this project has no combobox/
 * popover primitive to build a token-style multi-select from (no
 * `command.tsx`/`popover.tsx` under components/ui). A checkbox list is the
 * same technique already established for multi-value selection elsewhere in
 * this app — see SectionFlagFields — just without that component's
 * conditional per-item note field, which SDGs have no equivalent of.
 */
export default function SdgCheckboxGroup({
    idPrefix,
    options,
    selected,
    onChange,
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
                            name="sdg[]"
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
