import { useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export type SectionFlagDef = {
    key: string;
    label: string;
};

type Props = {
    sections: SectionFlagDef[];
};

/**
 * Phase 2 item 9, extended by the section-comments redesign — one checkbox
 * per flaggable section, rendered inside the existing "Return for Revision"
 * <Form> block. Checking a box reveals an optional note specific to that
 * section; the shared comment field elsewhere in the form still covers the
 * general case, so neither a checkbox nor a note is ever required — any
 * combination, including none, is a valid return.
 *
 * Each checkbox still submits as `sections[]` — Radix's Checkbox mirrors its
 * checked state onto a real hidden <input name="sections[]" value={key}>
 * even in controlled mode (needed here to know which notes to render), so an
 * unchecked box is simply omitted from the request, matching native
 * checkbox-array semantics. A section's note submits as
 * `section_comments[key]`, which PHP parses into an associative array
 * server-side; unchecking a box un-renders its note, dropping it from the
 * next submission with no separate clear-state step needed.
 */
export default function SectionFlagFields({ sections }: Props) {
    const [checked, setChecked] = useState<Record<string, boolean>>({});

    if (sections.length === 0) {
        return null;
    }

    return (
        <div className="space-y-2">
            <span className="text-sm font-medium">
                Flag sections needing revision (optional)
            </span>
            <div className="space-y-2">
                {sections.map((section) => (
                    <div key={section.key} className="rounded-md border p-3">
                        <div className="flex gap-2">
                            <Checkbox
                                id={`section-${section.key}`}
                                name="sections[]"
                                value={section.key}
                                checked={checked[section.key] ?? false}
                                onCheckedChange={(value) =>
                                    setChecked((prev) => ({
                                        ...prev,
                                        [section.key]: value === true,
                                    }))
                                }
                                className="mt-0.5"
                            />
                            {/* This column, not a margin on the textarea, is what keeps
                                the note inside the row: flex-1 gives it exactly the
                                remaining width after the checkbox, so the Textarea's
                                own `w-full` resolves against that width. `ml-*` on a
                                `w-full` textarea would instead push it past the row's
                                right padding — width:100% doesn't shrink to make room
                                for a sibling's margin. */}
                            <div className="flex-1 space-y-2">
                                <Label
                                    htmlFor={`section-${section.key}`}
                                    className="font-normal"
                                >
                                    {section.label}
                                </Label>
                                {checked[section.key] && (
                                    <Textarea
                                        name={`section_comments[${section.key}]`}
                                        placeholder={`Note specific to ${section.label} (optional)…`}
                                        aria-label={`Note for ${section.label}`}
                                        rows={2}
                                    />
                                )}
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
