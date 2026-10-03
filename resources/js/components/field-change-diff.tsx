import { MoveRight } from 'lucide-react';
import type { FieldChangeRow, FieldChanges, SectionFieldChanges } from '@/types/document-transitions';

/**
 * Renders the field-level before/after diffs frozen onto a `resubmitted`
 * transition (see App\Approval\FieldChangeSet). Form-type agnostic: every
 * label and every value is already a display string from the server, so
 * this component needs no label registry and no per-form-type props.
 *
 * Each changed field reads old value struck through in red, an arrow, then
 * the new value in green; a field with no prior value shows "Not set". Only
 * rows the server marked changed are listed; a flagged section where nothing
 * changed collapses to one line saying so, which is the whole reason
 * unchanged rows are persisted at all.
 */

const NOT_SET = 'Not set';

/** An attachment-slot marker (see SectionFieldChanges' docblock) never has field rows; a calendar-row status always does. */
function isAttachmentStatus(section: SectionFieldChanges): boolean {
    return section.fields.length === 0 && (section.status === 'added' || section.status === 'replaced' || section.status === 'unchanged');
}

function attachmentStatusMessage(status: SectionFieldChanges['status']): string {
    switch (status) {
        case 'replaced':
            return 'The uploaded file was replaced on this revision.';
        case 'added':
            return 'A file was uploaded on this revision.';
        default:
            return 'No file was uploaded for this on resubmission.';
    }
}

export function FieldChangeDiff({ changes }: { changes: FieldChanges | null }) {
    if (!changes) {
        return null;
    }

    const sections = Object.entries(changes);

    if (sections.length === 0) {
        return null;
    }

    return (
        <div className="mt-3 flex flex-col gap-3 rounded-lg border bg-muted/30 p-4 text-sm">
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                Changes made on this revision
            </p>
            {sections.map(([key, section]) => {
                const changed = section.fields.filter((field) => field.changed);
                const showHeading = sections.length > 1 || changed.length === 0;

                return (
                    <div key={key} className="flex flex-col gap-1.5">
                        {showHeading && <p className="font-medium">{section.label}</p>}
                        {isAttachmentStatus(section) ? (
                            <p className="text-muted-foreground">{attachmentStatusMessage(section.status)}</p>
                        ) : (
                            <>
                                {section.status === 'removed' && (
                                    <p className="text-muted-foreground">This activity was removed on resubmission.</p>
                                )}
                                {section.status === 'added' && (
                                    <p className="text-muted-foreground">This activity was added on resubmission.</p>
                                )}
                                {section.status === 'changed' && changed.length === 0 && (
                                    <p className="text-muted-foreground">No changes were made to this section.</p>
                                )}
                                {changed.length > 0 && (
                                    <ul className="flex flex-col gap-1.5">
                                        {changed.map((field) => (
                                            <FieldChangeLine key={field.key} field={field} />
                                        ))}
                                    </ul>
                                )}
                            </>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

function FieldChangeLine({ field }: { field: FieldChangeRow }) {
    return (
        <li className="grid grid-cols-1 gap-x-4 gap-y-0.5 break-words sm:grid-cols-[minmax(8rem,12rem)_1fr]">
            <span className="text-muted-foreground">{field.label}</span>
            <span className="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                <span className="text-destructive-foreground line-through">
                    <span className="sr-only">Before: </span>
                    {field.old ?? NOT_SET}
                </span>
                <MoveRight className="size-3.5 text-muted-foreground" aria-hidden />
                <span className="font-medium text-success-foreground">
                    <span className="sr-only">After: </span>
                    {field.new ?? '—'}
                </span>
            </span>
        </li>
    );
}
