import type { ReactNode } from 'react';
import PageNotice from '@/components/page-notice';
import { toneFor } from '@/lib/status-tones';

/**
 * Tells an approver why a document they opened has no review actions: it was
 * approved, rejected, or returned for revision. The tone follows the document
 * status from lib/status-tones.ts (approved green, returned amber, rejected
 * red), so the note reads like the status badge above it. It is information,
 * not an error, so it is a polite status message.
 */
export default function ReviewStatusNotice({
    status,
    children,
}: {
    status: string;
    children: ReactNode;
}) {
    return <PageNotice tone={toneFor('document', status)}>{children}</PageNotice>;
}
