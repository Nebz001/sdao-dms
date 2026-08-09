import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import PublicActivitiesSection from '@/components/public-activities-section';
import { formatCalendarDate } from '@/lib/utils';
import type { PublicActivity } from '@/types/public-activity';

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

/**
 * Builds N activities on days 1..N of the REAL current month. PublicMiniCalendar
 * always renders the true current month here (PublicActivitiesSection doesn't
 * expose a `today` override the way the calendar component itself does for its
 * own tests), so activity dates must land in that same month to be clickable —
 * days 1-8 are valid in every month regardless of length.
 */
function activitiesInCurrentMonth(count: number): PublicActivity[] {
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth();

    return Array.from({ length: count }, (_, i) => {
        const day = i + 1;
        const iso = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

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
        render(<PublicActivitiesSection activities={activities} />);

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
        render(<PublicActivitiesSection activities={activities} />);

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
