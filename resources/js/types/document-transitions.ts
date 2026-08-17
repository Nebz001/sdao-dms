/**
 * Shared shapes for a document's revision-transition history and the
 * flagged-section state derived from its most recent Return (see
 * App\Approval\SectionFlags on the backend). Centralized here because,
 * unlike SectionFlagDef (colocated with its owning component,
 * @/components/section-flag-fields), these shapes are used across many
 * page files with no single natural owner.
 *
 * Activity Calendar deliberately does NOT use TransitionEntry — its
 * section-comments feature is deferred (its "activity_N" keys aren't stable
 * across a resubmit), so its history entries and edit-page props are a
 * genuinely narrower shape, declared locally on its own pages rather than
 * forced into these. It DOES import FieldChanges below, though — that one is
 * form-type agnostic and carries its own baked-in section labels, so
 * Calendar's no-stable-identity problem doesn't apply to it.
 */

export type TransitionEntry = {
    id: number;
    action: string;
    from_status: string | null;
    to_status: string;
    step_position: number | null;
    comment: string | null;
    flagged_sections: string[] | null;
    section_comments: Record<string, string> | null;
    field_changes: FieldChanges | null;
    actor: { name: string } | null;
    created_at: string;
};

/**
 * Field-level revision diffs, written only onto a `resubmitted` transition
 * (null on every other action — see App\Approval\FieldChangeSet). Values are
 * pre-formatted display strings frozen server-side at the moment of
 * resubmission — never raw data to be re-formatted here. `old`/`new` are
 * null for an empty/absent value.
 *
 * `changed` is decided server-side by comparing those formatted strings.
 * Rows with changed === false are kept on purpose: a flagged section where
 * NOTHING changed is exactly what an approver needs to know.
 */
export type FieldChangeRow = {
    key: string;
    label: string;
    old: string | null;
    new: string | null;
    changed: boolean;
};

/**
 * `status` is 'changed' for every form type except Activity Calendar, whose
 * positional "activity_{i}" sections can also be 'removed' (the student
 * deleted that row on resubmit) or, defensively, 'added'.
 */
export type SectionFieldChanges = {
    label: string;
    status: 'changed' | 'removed' | 'added';
    fields: FieldChangeRow[];
};

/** section key => that section's diff. */
export type FieldChanges = Record<string, SectionFieldChanges>;

/** key => label, for resolving flagged_sections/section_comments keys to display text. */
export type FlaggedSectionLabels = Record<string, string>;

/**
 * Resubmit (edit) page props reflecting the return that put a document in
 * its current Returned state — flaggedComment is the general comment
 * (required on every return), flaggedSectionComments is the optional
 * per-section notes given for a subset of the flagged sections.
 */
export type FlaggedRevisionProps = {
    flaggedSections: string[];
    flaggedComment: string | null;
    flaggedSectionComments: Record<string, string>;
};
