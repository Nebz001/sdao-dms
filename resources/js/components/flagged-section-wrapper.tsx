import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';

type Props = {
    sectionKey: string;
    flagged: string[];
    children: ReactNode;
};

/**
 * Phase 2 item 9 — wraps one field-group on a resubmit (edit) page, applying
 * a visible highlight plus a small badge when the reviewer flagged this exact
 * section on the return that put the document in its current Returned state.
 * Purely visual: flagging never blocks or alters what the student can submit.
 */
export default function FlaggedSectionWrapper({ sectionKey, flagged, children }: Props) {
    const isFlagged = flagged.includes(sectionKey);

    return (
        <div className={isFlagged ? 'space-y-2 rounded-lg ring-2 ring-destructive/60' : undefined}>
            {isFlagged && (
                <Badge variant="destructive" className="ml-1">
                    Flagged for revision
                </Badge>
            )}
            {children}
        </div>
    );
}
