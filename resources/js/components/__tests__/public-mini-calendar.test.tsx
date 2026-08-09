import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import PublicMiniCalendar from '@/components/public-mini-calendar';
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

describe('PublicMiniCalendar', () => {
    it("highlights today's cell with the brand fill", () => {
        render(
            <PublicMiniCalendar
                activities={[]}
                selectedDate={null}
                onSelectDay={vi.fn()}
                today="2026-09-10"
            />,
        );

        const todayCell = screen.getByRole('gridcell', {
            name: 'Sep 10, 2026',
        });
        const todayNumber = todayCell.querySelector('span');

        expect(todayNumber).toHaveClass('bg-brand');
        expect(todayNumber).toHaveClass('text-brand-foreground');
    });

    it('renders a has-activity day as a clickable button with a dot', () => {
        render(
            <PublicMiniCalendar
                activities={[activity({ activity_date: '2026-09-15' })]}
                selectedDate={null}
                onSelectDay={vi.fn()}
                today="2026-09-10"
            />,
        );

        const day15 = screen.getByRole('gridcell', { name: /Sep 15, 2026/ });

        expect(day15.tagName).toBe('BUTTON');
        expect(day15.querySelector('.bg-brand')).toBeInTheDocument();
    });

    it('renders a day with no activities as a non-interactive cell, no dot', () => {
        render(
            <PublicMiniCalendar
                activities={[]}
                selectedDate={null}
                onSelectDay={vi.fn()}
                today="2026-09-10"
            />,
        );

        const day12 = screen.getByRole('gridcell', { name: 'Sep 12, 2026' });

        expect(day12.tagName).toBe('DIV');
        expect(day12.querySelector('.bg-brand')).not.toBeInTheDocument();
    });

    it('pluralizes the activity count in the accessible label', () => {
        render(
            <PublicMiniCalendar
                activities={[
                    activity({ id: 1, activity_date: '2026-09-15' }),
                    activity({ id: 2, activity_date: '2026-09-15' }),
                ]}
                selectedDate={null}
                onSelectDay={vi.fn()}
                today="2026-09-10"
            />,
        );

        expect(
            screen.getByRole('gridcell', {
                name: 'Sep 15, 2026, 2 activities',
            }),
        ).toBeInTheDocument();
    });

    it("calls onSelectDay with the clicked day's ISO date", async () => {
        const user = userEvent.setup();
        const onSelectDay = vi.fn();

        render(
            <PublicMiniCalendar
                activities={[activity({ activity_date: '2026-09-15' })]}
                selectedDate={null}
                onSelectDay={onSelectDay}
                today="2026-09-10"
            />,
        );

        await user.click(
            screen.getByRole('gridcell', { name: /Sep 15, 2026/ }),
        );

        expect(onSelectDay).toHaveBeenCalledWith('2026-09-15');
    });

    it('marks the selected day as pressed', () => {
        render(
            <PublicMiniCalendar
                activities={[activity({ activity_date: '2026-09-15' })]}
                selectedDate="2026-09-15"
                onSelectDay={vi.fn()}
                today="2026-09-10"
            />,
        );

        expect(
            screen.getByRole('gridcell', { name: /Sep 15, 2026/ }),
        ).toHaveAttribute('aria-pressed', 'true');
    });
});
