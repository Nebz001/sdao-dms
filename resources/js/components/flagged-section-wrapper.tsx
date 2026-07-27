import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';

type Props = {
    sectionKey: string;
    flagged: string[];
    /** The general comment covering the whole return — shown whenever this
     *  section is flagged, since a comment is required on every return. */
    comment?: string | null;
    /** An optional note specific to this section, on top of the general
     *  comment above. Not every flagged section has one. */
    sectionComment?: string | null;
    children: ReactNode;
};

/**
 * Phase 2 item 9, extended by the section-comments redesign — wraps one
 * field-group on a resubmit (edit) page, applying a visible highlight plus
 * a small badge when the reviewer flagged this exact section on the return
 * that put the document in its current Returned state. Now also surfaces
 * the reviewer's comment(s) in context, right where the student is editing,
 * instead of requiring a trip to the separate Revision History card.
 * Purely informational: flagging never blocks or alters what the student
 * can submit.
 */
export default function FlaggedSectionWrapper({ sectionKey, flagged, comment, sectionComment, children }: Props) {
    const isFlagged = flagged.includes(sectionKey);

    return (
        <div className={isFlagged ? 'space-y-2 rounded-lg ring-2 ring-destructive/60' : undefined}>
            {isFlagged && (
                <Badge variant="destructive" className="ml-1">
                    Flagged for revision
                </Badge>
            )}
            {isFlagged && sectionComment && (
                <p className="ml-1 text-sm font-medium text-destructive">{sectionComment}</p>
            )}
            {isFlagged && comment && (
                <p className="ml-1 text-sm text-muted-foreground">{comment}</p>
            )}
            {children}
        </div>
    );
}
