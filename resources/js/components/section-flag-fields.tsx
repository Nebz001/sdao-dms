import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

export type SectionFlagDef = {
    key: string;
    label: string;
};

type Props = {
    sections: SectionFlagDef[];
};

/**
 * Phase 2 item 9 — one checkbox per flaggable section, rendered inside the
 * existing "Return for Revision" <Form> block. Purely informational: no
 * section is required, any combination (including none) is a valid return.
 * Each checkbox submits as `sections[]` — Radix's Checkbox mirrors its
 * checked state onto a real hidden <input name="sections[]" value={key}>,
 * so an unchecked box is simply omitted from the request, matching native
 * checkbox-array semantics that Laravel's `sections` array rule expects.
 */
export default function SectionFlagFields({ sections }: Props) {
    if (sections.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-2">
            <span className="text-sm font-medium">Flag sections needing revision (optional)</span>
            <div className="grid gap-2 sm:grid-cols-2">
                {sections.map((section) => (
                    <div key={section.key} className="flex items-center gap-2">
                        <Checkbox id={`section-${section.key}`} name="sections[]" value={section.key} />
                        <Label htmlFor={`section-${section.key}`} className="font-normal">
                            {section.label}
                        </Label>
                    </div>
                ))}
            </div>
        </div>
    );
}
