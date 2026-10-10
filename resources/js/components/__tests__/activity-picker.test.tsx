import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { afterEach, describe, expect, it } from 'vitest';
import ActivityPicker from '@/components/activity-picker';
import type { PickerActivity } from '@/components/activity-picker';
import { dateBadgeParts, formatClock, formatClockRange } from '@/lib/activity-time';

const activities: PickerActivity[] = [
    { id: '1', title: 'Bridge Design Challenge', date: '2026-10-15', start_time: '08:00', end_time: '17:00', venue: 'Function Hall', group: '1st Term, 2026-2027' },
    { id: '2', title: 'Leadership Summit', date: '2026-10-28', start_time: '13:00', end_time: '16:00', venue: 'NU Lipa Gymnasium', group: '1st Term, 2026-2027' },
    { id: '3', title: 'hhhg', date: '2026-09-20', start_time: '09:00', end_time: '12:00', venue: 'Room 402', group: '1st Term, 2026-2027', disabledReason: 'Already proposed' },
    { id: '4', title: 'Founders Day', date: '2027-01-12', start_time: '10:00', end_time: '11:30', venue: 'Auditorium', group: '2nd Term, 2026-2027' },
];

function Harness({ list = activities, initial = '' }: { list?: PickerActivity[]; initial?: string }) {
    const [value, setValue] = useState(initial);

    return (
        <form>
            <ActivityPicker
                id="picker"
                name="calendar_activity_id"
                activities={list}
                value={value}
                onChange={setValue}
                placeholder="Choose from your activity calendar"
                helper="The date, time, and venue come from your approved activity calendar"
            />
            <output data-testid="value">{value}</output>
        </form>
    );
}

describe('ActivityPicker', () => {
    afterEach(cleanup);

    it('shows a select-style trigger with the placeholder until something is picked', () => {
        render(<Harness />);

        expect(screen.getByRole('combobox')).toHaveTextContent('Choose from your activity calendar');
        expect(screen.queryByText('Change')).not.toBeInTheDocument();
    });

    it('opens a searchable list grouped by term, with time range and venue', async () => {
        const user = userEvent.setup();
        render(<Harness />);

        await user.click(screen.getByRole('combobox'));

        expect(screen.getByPlaceholderText('Search activities')).toBeInTheDocument();
        expect(screen.getByText('1st Term, 2026-2027')).toBeInTheDocument();
        expect(screen.getByText('2nd Term, 2026-2027')).toBeInTheDocument();
        expect(screen.getByText('8:00 AM to 5:00 PM · Function Hall')).toBeInTheDocument();
    });

    it('filters by title and by venue, and says so when nothing matches', async () => {
        const user = userEvent.setup();
        render(<Harness />);
        await user.click(screen.getByRole('combobox'));

        const search = screen.getByPlaceholderText('Search activities');

        await user.type(search, 'summit');
        expect(screen.getByText('Leadership Summit')).toBeInTheDocument();
        expect(screen.queryByText('Bridge Design Challenge')).not.toBeInTheDocument();

        await user.clear(search);
        await user.type(search, 'auditorium');
        expect(screen.getByText('Founders Day')).toBeInTheDocument();
        expect(screen.queryByText('Leadership Summit')).not.toBeInTheDocument();

        await user.clear(search);
        await user.type(search, 'zzz');
        expect(screen.getByText('No activities match')).toBeInTheDocument();
    });

    it('keeps a disabled row visible with its reason, and picking it does nothing', async () => {
        const user = userEvent.setup();
        render(<Harness />);
        await user.click(screen.getByRole('combobox'));

        const row = screen.getByText('hhhg').closest('[cmdk-item]') as HTMLElement;
        expect(row).toHaveAttribute('aria-disabled', 'true');
        expect(within(row).getByText('Already proposed')).toBeInTheDocument();
        // The reason is what a screen reader hears with the row.
        expect(row).toHaveAccessibleDescription('Already proposed');

        await user.click(row);

        expect(screen.getByTestId('value')).toHaveTextContent('');
        expect(screen.getByPlaceholderText('Search activities')).toBeInTheDocument();
    });

    it('picks with the mouse, fills the submitted field, and shows the selected card', async () => {
        const user = userEvent.setup();
        const { container } = render(<Harness />);
        await user.click(screen.getByRole('combobox'));
        await user.click(screen.getByText('Bridge Design Challenge'));

        expect(screen.getByTestId('value')).toHaveTextContent('1');
        expect(container.querySelector<HTMLInputElement>('input[name="calendar_activity_id"]')).toHaveValue('1');
        expect(screen.queryByPlaceholderText('Search activities')).not.toBeInTheDocument();
        expect(screen.getByText('Bridge Design Challenge')).toBeInTheDocument();
        expect(screen.getByText('8:00 AM to 5:00 PM')).toBeInTheDocument();
        expect(screen.getByText('Function Hall')).toBeInTheDocument();
        expect(screen.getByText('The date, time, and venue come from your approved activity calendar')).toBeInTheDocument();
        expect(screen.queryByRole('combobox')).not.toBeInTheDocument();
    });

    it('picks with the keyboard: arrows move, Enter picks, Escape closes', async () => {
        const user = userEvent.setup();
        render(<Harness />);

        screen.getByRole('combobox').focus();
        await user.keyboard('{ArrowDown}');
        expect(screen.getByPlaceholderText('Search activities')).toBeInTheDocument();

        await user.keyboard('{Escape}');
        expect(screen.queryByPlaceholderText('Search activities')).not.toBeInTheDocument();

        await user.keyboard('{ArrowDown}');
        await user.keyboard('{ArrowDown}');
        await user.keyboard('{Enter}');
        expect(screen.getByTestId('value')).toHaveTextContent('2');
    });

    it('reopens the list from Change, and a new pick replaces the old one', async () => {
        const user = userEvent.setup();
        render(<Harness initial="1" />);

        await user.click(screen.getByRole('button', { name: /Change activity/ }));
        expect(screen.getByPlaceholderText('Search activities')).toBeInTheDocument();

        await user.click(screen.getByText('Founders Day'));
        expect(screen.getByTestId('value')).toHaveTextContent('4');
        expect(screen.getByRole('button', { name: /currently Founders Day/ })).toBeInTheDocument();
    });

    it('opens already showing the activity as the selected card when one is pre-selected', () => {
        render(<Harness initial="2" />);

        expect(screen.getByText('Leadership Summit')).toBeInTheDocument();
        expect(screen.getByText('1:00 PM to 4:00 PM')).toBeInTheDocument();
        expect(screen.queryByRole('combobox')).not.toBeInTheDocument();
    });

    it('shows no group headings when every activity is in one term', async () => {
        const user = userEvent.setup();
        render(<Harness list={activities.slice(0, 2)} />);
        await user.click(screen.getByRole('combobox'));

        expect(screen.queryByText('1st Term, 2026-2027')).not.toBeInTheDocument();
    });
});

describe('activity time formatting', () => {
    it('writes 12 hour AM/PM wall-clock times, with no zone shift', () => {
        expect(formatClock('08:00')).toBe('8:00 AM');
        expect(formatClock('13:05:00')).toBe('1:05 PM');
        expect(formatClock('00:30')).toBe('12:30 AM');
        expect(formatClock('12:00')).toBe('12:00 PM');
        expect(formatClockRange('08:00', '17:00')).toBe('8:00 AM to 5:00 PM');
        expect(formatClockRange(null, null)).toBeNull();
    });

    it('splits a date into month and day from its text, not through a Date', () => {
        expect(dateBadgeParts('2026-10-15')).toEqual({ month: 'OCT', day: '15' });
        expect(dateBadgeParts('2027-01-01')).toEqual({ month: 'JAN', day: '1' });
    });
});
