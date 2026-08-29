import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { FieldChangeDiff } from '@/components/field-change-diff';
import type { FieldChanges } from '@/types/document-transitions';

/*
 * Attachment slots (Registration/Renewal/AfterActivityReport) previously had
 * no history entry at all when flagged — SectionFields deliberately excludes
 * them from field diffing. FieldChangeSet::build() now emits a
 * replaced/added/unchanged status marker with an empty `fields` array for
 * these; this pins that FieldChangeDiff renders the right message for each,
 * and — critically — doesn't confuse an attachment's 'added' with a
 * calendar row's 'added' (same status string, different fields shape,
 * different wording).
 */
describe('FieldChangeDiff — attachment slot markers', () => {
    it('renders "replaced" for an attachment slot whose file was swapped', () => {
        const changes: FieldChanges = {
            by_laws: { label: 'By-Laws', status: 'replaced', fields: [] },
        };

        render(<FieldChangeDiff changes={changes} />);

        expect(screen.getByText('By-Laws')).toBeInTheDocument();
        expect(screen.getByText('The uploaded file was replaced on this revision.')).toBeInTheDocument();
    });

    it('renders "added" for an attachment slot that had no prior file', () => {
        const changes: FieldChanges = {
            by_laws: { label: 'By-Laws', status: 'added', fields: [] },
        };

        render(<FieldChangeDiff changes={changes} />);

        expect(screen.getByText('A file was uploaded on this revision.')).toBeInTheDocument();
    });

    it('renders "unchanged" for a flagged attachment slot the student never touched', () => {
        const changes: FieldChanges = {
            by_laws: { label: 'By-Laws', status: 'unchanged', fields: [] },
        };

        render(<FieldChangeDiff changes={changes} />);

        expect(screen.getByText('No file was uploaded for this on resubmission.')).toBeInTheDocument();
    });

    it('does not confuse a calendar row\'s "added" status (which carries field rows) with an attachment marker', () => {
        const changes: FieldChanges = {
            activity_0: {
                label: 'Activity 1',
                status: 'added',
                fields: [{ key: 'name', label: 'Activity Name', old: null, new: 'New Activity', changed: true }],
            },
        };

        render(<FieldChangeDiff changes={changes} />);

        expect(screen.getByText('This activity was added on resubmission.')).toBeInTheDocument();
        expect(screen.queryByText('A file was uploaded on this revision.')).not.toBeInTheDocument();
    });
});
