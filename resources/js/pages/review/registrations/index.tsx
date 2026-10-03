import ReviewQueuePage from '@/components/review-queue/review-queue-page';
import type { ReviewQueuePageProps } from '@/components/review-queue/review-queue-page';
import type { ReviewQueueConfig } from '@/components/review-queue/types';
import * as reviewRegistrations from '@/routes/review/registrations';

const config: ReviewQueueConfig = {
    headTitle: 'Review: Registrations',
    title: 'Registration Review Queue',
    subtitle: 'Organization registrations waiting for SDAO review',
    noun: 'registration',
    typeLabel: 'Registration',
    emptyDescription: 'Submitted registrations will show up here as soon as a student org sends one in.',
    showRoute: (id) => reviewRegistrations.show(id).url,
};

export default function ReviewRegistrationsIndex(props: ReviewQueuePageProps) {
    return <ReviewQueuePage config={config} {...props} />;
}

ReviewRegistrationsIndex.layout = {
    breadcrumbs: [{ title: 'Review' }, { title: 'Registrations' }],
};
