import ReviewQueuePage from '@/components/review-queue/review-queue-page';
import type { ReviewQueuePageProps } from '@/components/review-queue/review-queue-page';
import type { ReviewQueueConfig } from '@/components/review-queue/types';
import * as reviewReports from '@/routes/review/reports';

const config: ReviewQueueConfig = {
    headTitle: 'Review: After-Activity Reports',
    title: 'Report Review Queue',
    subtitle: 'After-activity reports waiting for your review',
    noun: 'report',
    typeLabel: 'After-activity report',
    emptyDescription: 'Submitted after-activity reports will show up here as soon as a student org sends one in.',
    showRoute: (id) => reviewReports.show(id).url,
};

export default function ReviewReportsIndex(props: ReviewQueuePageProps) {
    return <ReviewQueuePage config={config} {...props} />;
}

ReviewReportsIndex.layout = {
    breadcrumbs: [{ title: 'Review' }, { title: 'Reports' }],
};
