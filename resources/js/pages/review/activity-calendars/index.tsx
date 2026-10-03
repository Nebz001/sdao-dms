import ReviewQueuePage from '@/components/review-queue/review-queue-page';
import type { ReviewQueuePageProps } from '@/components/review-queue/review-queue-page';
import type { ReviewQueueConfig } from '@/components/review-queue/types';
import * as reviewActivityCalendars from '@/routes/review/activity-calendars';

const config: ReviewQueueConfig = {
    headTitle: 'Review: Activity Calendars',
    title: 'Activity Calendar Review Queue',
    subtitle: 'Activity calendars waiting for SDAO review',
    noun: 'calendar',
    typeLabel: 'Activity calendar',
    emptyDescription: 'Submitted activity calendars will show up here as soon as a student org sends one in.',
    showRoute: (id) => reviewActivityCalendars.show(id).url,
};

export default function ReviewActivityCalendarsIndex(props: ReviewQueuePageProps) {
    return <ReviewQueuePage config={config} {...props} />;
}

ReviewActivityCalendarsIndex.layout = {
    breadcrumbs: [{ title: 'Review' }, { title: 'Activity Calendars' }],
};
