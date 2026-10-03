import ReviewQueuePage from '@/components/review-queue/review-queue-page';
import type { ReviewQueuePageProps } from '@/components/review-queue/review-queue-page';
import type { ReviewQueueConfig } from '@/components/review-queue/types';
import * as reviewRenewals from '@/routes/review/renewals';

const config: ReviewQueueConfig = {
    headTitle: 'Review: Renewals',
    title: 'Renewal Review Queue',
    subtitle: 'Organization renewals waiting for SDAO review',
    noun: 'renewal',
    typeLabel: 'Renewal',
    emptyDescription: 'Submitted renewals will show up here as soon as a student org sends one in.',
    showRoute: (id) => reviewRenewals.show(id).url,
};

export default function ReviewRenewalsIndex(props: ReviewQueuePageProps) {
    return <ReviewQueuePage config={config} {...props} />;
}

ReviewRenewalsIndex.layout = {
    breadcrumbs: [{ title: 'Review' }, { title: 'Renewals' }],
};
