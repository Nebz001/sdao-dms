import { Check } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

export type SectionFlagDef = {
    key: string;
    label: string;
};

/**
 * A selectable chip backed by a real checkbox, so it submits as `sections[]`
 * through native form serialization, is reachable and toggled from the
 * keyboard, and announces its checked state. The checkbox is visually hidden;
 * the chip is its label.
 */
export function FlagChip({
    id,
    value,
    checked,
    onCheckedChange,
    children,
}: {
    id: string;
    value: string;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    children: ReactNode;
}) {
    return (
        <label
            htmlFor={id}
            className={cn(
                'inline-flex min-h-8 cursor-pointer items-center gap-1.5 rounded-full border px-3 py-1 text-sm font-medium transition-colors',
                'has-focus-visible:focus-ring-edge',
                checked
                    ? 'border-warning/40 bg-warning/10 text-warning-foreground'
                    : 'border-input bg-background text-foreground hover:bg-accent',
            )}
        >
            <input
                id={id}
                type="checkbox"
                name="sections[]"
                value={value}
                checked={checked}
                onChange={(e) => onCheckedChange(e.target.checked)}
                className="sr-only"
            />
            {checked && <Check className="size-3.5" aria-hidden />}
            {children}
        </label>
    );
}

type Props = {
    sections: SectionFlagDef[];
};

/**
 * One selectable chip per flaggable section of this form type, rendered
 * inside the "Return for revision" <Form>. Selecting a chip reveals an
 * optional note specific to that section; the shared message field still
 * covers the general case, so neither a chip nor a note is ever required.
 * Each chip submits as `sections[]`, a note as `section_comments[key]`;
 * deselecting a chip un-renders its note, dropping it from the next
 * submission.
 */
export default function SectionFlagFields({ sections }: Props) {
    const [checked, setChecked] = useState<Record<string, boolean>>({});

    if (sections.length === 0) {
        return null;
    }

    const selected = sections.filter((section) => checked[section.key]);

    return (
        <fieldset className="flex flex-col gap-3">
            <legend className="mb-2 text-sm font-medium">Which parts need fixing</legend>
            <div className="flex flex-wrap gap-2">
                {sections.map((section) => (
                    <FlagChip
                        key={section.key}
                        id={`section-${section.key}`}
                        value={section.key}
                        checked={checked[section.key] ?? false}
                        onCheckedChange={(value) => setChecked((prev) => ({ ...prev, [section.key]: value }))}
                    >
                        {section.label}
                    </FlagChip>
                ))}
            </div>
            {selected.map((section) => (
                <Textarea
                    key={section.key}
                    name={`section_comments[${section.key}]`}
                    placeholder={`Note specific to ${section.label} (optional)…`}
                    aria-label={`Note for ${section.label}`}
                    rows={2}
                />
            ))}
        </fieldset>
    );
}
