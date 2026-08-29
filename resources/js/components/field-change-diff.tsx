import type { FieldChangeRow, FieldChanges, SectionFieldChanges } from '@/types/document-transitions';

/**
 * Renders the field-level before/after diffs frozen onto a `resubmitted`
 * transition (see App\Approval\FieldChangeSet). Form-type agnostic: every
 * label and every value is already a display string from the server, so
 * this component needs no label registry and no per-form-type props.
 *
 * Only rows the server marked changed are listed; a flagged section where
 * nothing changed collapses to one line saying so, which is the whole
 * reason unchanged rows are persisted at all.
 *
 * Deliberately not text-destructive, unlike the "Flagged:"/section-comments
 * block above it (see review/registrations/show.tsx etc.) — those are the
 * approver's complaint, this is the student's answer to it, so it reads in
 * the neutral muted/foreground pair the rest of the timeline already uses.
 */

const EMPTY = '—';

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
        <div className="mt-1 space-y-1.5 text-xs">
            <p className="font-medium text-muted-foreground">Changes made on this revision</p>
            {sections.map(([key, section]) => {
                const changed = section.fields.filter((field) => field.changed);

                return (
                    <div key={key} className="space-y-0.5">
                        <p className="font-medium">{section.label}</p>
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
                                    <ul className="space-y-0.5">
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
    const before = field.old ?? EMPTY;
    const after = field.new ?? EMPTY;

    return (
        <li className="break-words text-muted-foreground">
            <span className="font-medium">{field.label}:</span>{' '}
            <span className="line-through">{before}</span>{' '}
            <span aria-hidden="true">→</span>{' '}
            <span className="text-foreground">{after}</span>
        </li>
    );
}
