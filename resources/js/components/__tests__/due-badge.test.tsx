import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import DueBadge from '@/components/due-badge';
import type { StepDeadline } from '@/components/due-badge';

const base: StepDeadline = {
    state: 'on_track',
    label: 'Due in 2 days',
    leadTimeDays: 3,
    leadTimeLabel: '3 days',
    dueAt: '2026-09-19T12:00:00+00:00',
    dueDate: '9/19/2026 8:00 PM',
    arrivedAt: '2026-09-16T12:00:00+00:00',
    stepPosition: 1,
    daysOverdue: 0,
    daysRemaining: 2,
};

describe('DueBadge', () => {
    it('prints the reading the server computed', () => {
        render(<DueBadge deadline={base} />);

        expect(screen.getByText('Due in 2 days')).toBeInTheDocument();
    });

    it('says how late an overdue step is, in words', () => {
        render(<DueBadge deadline={{ ...base, state: 'overdue', label: 'Overdue by 1 day', daysOverdue: 1, daysRemaining: 0 }} />);

        expect(screen.getByText('Overdue by 1 day')).toBeInTheDocument();
    });

    it('renders nothing when no step clock is running', () => {
        const { container } = render(<DueBadge deadline={null} />);

        expect(container).toBeEmptyDOMElement();
    });
});
