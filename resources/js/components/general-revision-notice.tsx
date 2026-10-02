import PageNotice from '@/components/page-notice';

type GeneralRevisionNoticeProps = {
    /** The section's own comment, written for the "General" flag. */
    sectionComment?: string | null;
    /** The approver's overall comment on the return. */
    comment?: string | null;
};

/**
 * The notice a student sees at the top of an edit page when an approver flagged
 * the "General" section while returning the document: something to fix that
 * does not belong to one field. The per-field flags are shown by
 * FlaggedSectionWrapper; this one is for the whole document. It uses the
 * warning tone because a returned document is a warning everywhere else in the
 * app (the Returned badge).
 */
export default function GeneralRevisionNotice({
    sectionComment,
    comment,
}: GeneralRevisionNoticeProps) {
    return (
        <PageNotice tone="warning" title="General revisions requested.">
            {sectionComment && <p>{sectionComment}</p>}
            {comment && <p className={sectionComment ? 'mt-1' : undefined}>{comment}</p>}
        </PageNotice>
    );
}
