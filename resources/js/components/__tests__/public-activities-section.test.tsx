import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import PublicActivitiesSection from '@/components/public-activities-section';
import { formatCalendarDate } from '@/lib/utils';
import type { PublicActivity } from '@/types/public-activity';

/** Fixed "today" used throughout — day 1 so every day-1..8 fixture activity
 * below still counts as upcoming, keeping these tests independent of the
 * real wall-clock date. */
const TODAY = '2026-09-01';

function activity(overrides: Partial<PublicActivity>): PublicActivity {
    return {
        id: 1,
        name: 'Test Event',
        venue: 'Auditorium',
        activity_date: '2026-09-10',
        start_time: '09:00',
        end_time: '11:00',
        organization: 'IT Guild',
        ...overrides,
    };
}

/** Builds N activities on days 1..N of the fixed reference month (Sep 2026). */
function activitiesInCurrentMonth(count: number): PublicActivity[] {
    return Array.from({ length: count }, (_, i) => {
        const day = i + 1;
        const iso = `2026-09-${String(day).padStart(2, '0')}`;

        return activity({
            id: i + 1,
            name: `Event ${i + 1}`,
            activity_date: iso,
        });
    });
}

describe('PublicActivitiesSection — pagination', () => {
    it('shows no pagination controls when activities fit on one page', () => {
        render(
            <PublicActivitiesSection
                activities={activitiesInCurrentMonth(5)}
                today={TODAY}
            />,
        );

        expect(screen.queryByText(/Page \d+ of \d+/)).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Next page' }),
        ).not.toBeInTheDocument();
    });

    it('paginates at 5 per page and shows the indicator once activities exceed that', () => {
        render(
            <PublicActivitiesSection
                activities={activitiesInCurrentMonth(8)}
                today={TODAY}
            />,
        );

        expect(screen.getByText('Page 1 of 2')).toBeInTheDocument();
        expect(screen.getByText('Event 1')).toBeInTheDocument();
        expect(screen.getByText('Event 5')).toBeInTheDocument();
        expect(screen.queryByText('Event 6')).not.toBeInTheDocument();
    });

    it('disables Previous on page 1 and enables Next', () => {
        render(
            <PublicActivitiesSection
                activities={activitiesInCurrentMonth(8)}
                today={TODAY}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Previous page' }),
        ).toBeDisabled();
        expect(
            screen.getByRole('button', { name: 'Next page' }),
        ).toBeEnabled();
    });

    it('advancing to the last page shows the remainder and disables Next', async () => {
        const user = userEvent.setup();
        render(
            <PublicActivitiesSection
                activities={activitiesInCurrentMonth(8)}
                today={TODAY}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Next page' }));

        expect(screen.getByText('Page 2 of 2')).toBeInTheDocument();
        expect(screen.getByText('Event 6')).toBeInTheDocument();
        expect(screen.getByText('Event 8')).toBeInTheDocument();
        expect(screen.queryByText('Event 5')).not.toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Next page' }),
        ).toBeDisabled();
        expect(
            screen.getByRole('button', { name: 'Previous page' }),
        ).toBeEnabled();
    });

    it('selecting a day on the calendar resets pagination to page 1 of the filtered set', async () => {
        const user = userEvent.setup();
        const activities = activitiesInCurrentMonth(8);
        render(
            <PublicActivitiesSection activities={activities} today={TODAY} />,
        );

        await user.click(screen.getByRole('button', { name: 'Next page' }));
        expect(screen.getByText('Page 2 of 2')).toBeInTheDocument();

        // Event 3 is on day 3 — select that day on the mini calendar.
        const day3Label = formatCalendarDate(activities[2].activity_date);

        await user.click(
            screen.getByRole('gridcell', { name: new RegExp(day3Label) }),
        );

        expect(
            screen.getByText(`Activities on ${day3Label}`),
        ).toBeInTheDocument();
        expect(screen.getByText('Event 3')).toBeInTheDocument();
        expect(screen.queryByText(/Page \d+ of \d+/)).not.toBeInTheDocument();
    });

    it('clearing the filter resets pagination to page 1 of the full list', async () => {
        const user = userEvent.setup();
        const activities = activitiesInCurrentMonth(8);
        render(
            <PublicActivitiesSection activities={activities} today={TODAY} />,
        );

        await user.click(screen.getByRole('button', { name: 'Next page' }));

        const day3Label = formatCalendarDate(activities[2].activity_date);

        await user.click(
            screen.getByRole('gridcell', { name: new RegExp(day3Label) }),
        );
        await user.click(
            screen.getByRole('button', { name: 'Clear filter' }),
        );

        expect(screen.getByText('Page 1 of 2')).toBeInTheDocument();
        expect(screen.getByText('Event 1')).toBeInTheDocument();
    });
});

describe('PublicActivitiesSection — month navigation', () => {
    function activitiesAcrossMonths(): PublicActivity[] {
        return [
            activity({
                id: 1,
                name: 'Past Month Event',
                activity_date: '2026-08-20',
            }),
            activity({
                id: 2,
                name: 'Current Month Event',
                activity_date: '2026-09-15',
            }),
            activity({
                id: 3,
                name: 'Future Month Event',
                activity_date: '2026-10-05',
            }),
        ];
    }

    it('opens on the current month with the current month\'s activity visible', () => {
        render(
            <PublicActivitiesSection
                activities={activitiesAcrossMonths()}
                today="2026-09-10"
            />,
        );

        expect(
            screen.getByRole('grid', { name: 'September 2026' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('gridcell', { name: /Sep 15, 2026/ }).tagName,
        ).toBe('BUTTON');
    });

    it('navigating to the next month reveals that month\'s activity', async () => {
        const user = userEvent.setup();
        render(
            <PublicActivitiesSection
                activities={activitiesAcrossMonths()}
                today="2026-09-10"
            />,
        );

        await user.click(
            screen.getByRole('button', { name: 'Next month' }),
        );

        expect(
            screen.getByRole('grid', { name: 'October 2026' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('gridcell', { name: /Oct 5, 2026/ }).tagName,
        ).toBe('BUTTON');
    });

    it('navigating to the previous month reveals a past activity', async () => {
        const user = userEvent.setup();
        render(
            <PublicActivitiesSection
                activities={activitiesAcrossMonths()}
                today="2026-09-10"
            />,
        );

        await user.click(
            screen.getByRole('button', { name: 'Previous month' }),
        );

        expect(
            screen.getByRole('grid', { name: 'August 2026' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('gridcell', { name: /Aug 20, 2026/ }).tagName,
        ).toBe('BUTTON');
    });

    it("the \"Next up\" list never leads with a past activity even though the calendar can navigate to it", () => {
        render(
            <PublicActivitiesSection
                activities={activitiesAcrossMonths()}
                today="2026-09-10"
            />,
        );

        expect(screen.getByText('Next up')).toBeInTheDocument();
        expect(screen.getByText('Current Month Event')).toBeInTheDocument();
        expect(screen.getByText('Future Month Event')).toBeInTheDocument();
        expect(
            screen.queryByText('Past Month Event'),
        ).not.toBeInTheDocument();
    });

    it('selecting a past day still surfaces its activity in the list', async () => {
        const user = userEvent.setup();
        render(
            <PublicActivitiesSection
                activities={activitiesAcrossMonths()}
                today="2026-09-10"
            />,
        );

        await user.click(
            screen.getByRole('button', { name: 'Previous month' }),
        );
        await user.click(
            screen.getByRole('gridcell', { name: /Aug 20, 2026/ }),
        );

        expect(screen.getByText('Past Month Event')).toBeInTheDocument();
    });

    it('navigating to a different month clears an active day filter', async () => {
        const user = userEvent.setup();
        render(
            <PublicActivitiesSection
                activities={activitiesAcrossMonths()}
                today="2026-09-10"
            />,
        );

        await user.click(
            screen.getByRole('gridcell', { name: /Sep 15, 2026/ }),
        );
        expect(screen.getByText('Activities on Sep 15, 2026')).toBeInTheDocument();

        await user.click(
            screen.getByRole('button', { name: 'Next month' }),
        );

        expect(screen.getByText('Next up')).toBeInTheDocument();
    });
});
