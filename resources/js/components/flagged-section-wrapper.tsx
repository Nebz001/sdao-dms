import type { ReactNode } from 'react';
import { FlagBadge } from '@/components/status-badge';
import { cn } from '@/lib/utils';

type Props = {
    /** Extra classes for the outer block, e.g. padding so the highlight ring clears the fields. */
    className?: string;
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
export default function FlaggedSectionWrapper({ className, sectionKey, flagged, comment, sectionComment, children }: Props) {
    const isFlagged = flagged.includes(sectionKey);

    return (
        <div className={cn(isFlagged && 'space-y-2 rounded-lg ring-2 ring-warning/60', className)}>
            {isFlagged && <FlagBadge flag="flagged" className="ml-1" />}
            {isFlagged && sectionComment && (
                <p className="ml-1 text-sm font-medium text-warning-foreground">{sectionComment}</p>
            )}
            {isFlagged && comment && (
                <p className="ml-1 text-sm text-muted-foreground">{comment}</p>
            )}
            {children}
        </div>
    );
}
