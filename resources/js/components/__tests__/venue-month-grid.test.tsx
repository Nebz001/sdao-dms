import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import VenueMonthGrid from '@/components/venue-month-grid';
import type { VenueBooking } from '@/types/venue-calendar';

function booking(overrides: Partial<VenueBooking>): VenueBooking {
    return {
        id: 1,
        name: 'Test Event',
        venue: 'Auditorium',
        activity_date: '2026-09-10',
        start_time: '09:00',
        end_time: '11:00',
        status: 'approved',
        organization: 'IT Guild',
        document_id: 1,
        ...overrides,
    };
}

describe('VenueMonthGrid', () => {
    it('renders a solid chip for a confirmed booking', () => {
        const bookingsByDate = {
            '2026-09-10': [
                booking({ id: 1, status: 'approved', venue: 'Auditorium' }),
            ],
        };

        render(
            <VenueMonthGrid
                year={2026}
                month={8}
                bookingsByDate={bookingsByDate}
                selectedDate={null}
                onSelectDay={vi.fn()}
                today="2026-09-10"
            />,
        );

        const chip = screen.getByText('Auditorium');

        expect(chip).toHaveClass('bg-success/15');
    });

    it('renders a dashed outline chip for a tentative booking', () => {
        const bookingsByDate = {
            '2026-09-11': [
                booking({ id: 2, status: 'in_review', venue: 'Gymnasium' }),
            ],
        };

        render(
            <VenueMonthGrid
                year={2026}
                month={8}
                bookingsByDate={bookingsByDate}
                selectedDate={null}
                onSelectDay={vi.fn()}
                today="2026-09-10"
            />,
        );

        const chip = screen.getByText('Gymnasium');

        expect(chip).toHaveClass('border-dashed');
        expect(chip).not.toHaveClass('bg-success/15');
    });

    it('shows both a confirmed and a tentative chip on the same day', () => {
        const bookingsByDate = {
            '2026-09-10': [
                booking({ id: 1, status: 'approved', venue: 'Auditorium' }),
                booking({ id: 2, status: 'in_review', venue: 'Gymnasium' }),
            ],
        };

        render(
            <VenueMonthGrid
                year={2026}
                month={8}
                bookingsByDate={bookingsByDate}
                selectedDate={null}
                onSelectDay={vi.fn()}
                today="2026-09-10"
            />,
        );

        expect(screen.getByText('Auditorium')).toBeInTheDocument();
        expect(screen.getByText('Gymnasium')).toBeInTheDocument();
    });

    it('renders no chips for a day with no bookings', () => {
        render(
            <VenueMonthGrid
                year={2026}
                month={8}
                bookingsByDate={{}}
                selectedDate={null}
                onSelectDay={vi.fn()}
                today="2026-09-10"
            />,
        );

        const day10 = screen.getByRole('gridcell', {
            name: /Sep 10, 2026, no bookings/,
        });

        expect(day10).toBeInTheDocument();
    });

    it('caps visible chips and shows a "+N more" overflow marker', () => {
        const bookingsByDate = {
            '2026-09-10': [
                booking({ id: 1, venue: 'Auditorium' }),
                booking({ id: 2, venue: 'Gymnasium' }),
                booking({ id: 3, venue: 'Conference Room' }),
                booking({ id: 4, venue: 'Field' }),
            ],
        };

        render(
            <VenueMonthGrid
                year={2026}
                month={8}
                bookingsByDate={bookingsByDate}
                selectedDate={null}
                onSelectDay={vi.fn()}
                today="2026-09-10"
            />,
        );

        expect(screen.getByText('+2 more')).toBeInTheDocument();
        // Only the first two chips render by name; the rest are folded into the marker.
        expect(screen.getByText('Auditorium')).toBeInTheDocument();
        expect(screen.getByText('Gymnasium')).toBeInTheDocument();
        expect(screen.queryByText('Conference Room')).not.toBeInTheDocument();
    });

    it("calls onSelectDay with the clicked day's ISO date", async () => {
        const user = userEvent.setup();
        const onSelectDay = vi.fn();

        render(
            <VenueMonthGrid
                year={2026}
                month={8}
                bookingsByDate={{}}
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
            <VenueMonthGrid
                year={2026}
                month={8}
                bookingsByDate={{}}
                selectedDate="2026-09-12"
                onSelectDay={vi.fn()}
                today="2026-09-10"
            />,
        );

        expect(
            screen.getByRole('gridcell', { name: /Sep 12, 2026/ }),
        ).toHaveAttribute('aria-pressed', 'true');
        expect(
            screen.getByRole('gridcell', { name: /Sep 10, 2026/ }),
        ).toHaveAttribute('aria-pressed', 'false');
    });

    it('moves focus to the next day on ArrowRight', async () => {
        const user = userEvent.setup();

        render(
            <VenueMonthGrid
                year={2026}
                month={8}
                bookingsByDate={{}}
                selectedDate={null}
                onSelectDay={vi.fn()}
                today="2026-09-10"
            />,
        );

        const day10 = screen.getByRole('gridcell', { name: /Sep 10, 2026/ });
        day10.focus();
        await user.keyboard('{ArrowRight}');

        expect(
            screen.getByRole('gridcell', { name: /Sep 11, 2026/ }),
        ).toHaveFocus();
    });

    it('moves focus down a week on ArrowDown', async () => {
        const user = userEvent.setup();

        render(
            <VenueMonthGrid
                year={2026}
                month={8}
                bookingsByDate={{}}
                selectedDate={null}
                onSelectDay={vi.fn()}
                today="2026-09-10"
            />,
        );

        const day10 = screen.getByRole('gridcell', { name: /Sep 10, 2026/ });
        day10.focus();
        await user.keyboard('{ArrowDown}');

        expect(
            screen.getByRole('gridcell', { name: /Sep 17, 2026/ }),
        ).toHaveFocus();
    });
});
