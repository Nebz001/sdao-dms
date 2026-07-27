/**
 * Shared shapes for a document's revision-transition history and the
 * flagged-section state derived from its most recent Return (see
 * App\Approval\SectionFlags on the backend). Centralized here because,
 * unlike SectionFlagDef (colocated with its owning component,
 * @/components/section-flag-fields), these shapes are used across many
 * page files with no single natural owner.
 *
 * Activity Calendar deliberately does NOT use these — its section-comments
 * feature is deferred (its "activity_N" keys aren't stable across a
 * resubmit), so its history entries and edit-page props are a genuinely
 * narrower shape, declared locally on its own pages rather than forced into
 * these.
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
    actor: { name: string } | null;
    created_at: string;
};

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
