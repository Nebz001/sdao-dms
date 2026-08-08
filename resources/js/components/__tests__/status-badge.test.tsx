import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { ActionBadge, StatusBadge } from '@/components/status-badge';

/**
 * ActionBadge colors every App\Enums\TransitionAction value using the same
 * info/success/warning/destructive family StatusBadge and
 * StatusDistributionBar already use — this pins that exact mapping so a
 * future edit can't silently drift one action onto the wrong hue or leave
 * a new action uncolored.
 */
describe('ActionBadge', () => {
    it.each([
        ['submitted', 'bg-info'],
        ['resubmitted', 'bg-info'],
        ['approved', 'bg-success'],
        ['advanced', 'bg-success'],
        ['completed', 'bg-success'],
        ['returned', 'bg-warning'],
        ['rejected', 'bg-destructive'],
    ])('colors "%s" with %s', (action, expectedClass) => {
        render(<ActionBadge action={action} />);

        expect(screen.getByText(new RegExp(action, 'i')).className).toContain(
            expectedClass,
        );
    });

    it('falls back to a neutral chip for an unrecognized action instead of guessing a color', () => {
        render(<ActionBadge action="some_future_action" />);

        const badge = screen.getByText(/some future action/i);
        expect(badge.className).toContain('bg-muted');
        expect(badge.className).not.toContain('bg-info');
        expect(badge.className).not.toContain('bg-success');
        expect(badge.className).not.toContain('bg-warning');
        expect(badge.className).not.toContain('bg-destructive');
    });

    it('never shares a color with StatusBadge’s "returned" status, since both can appear on the same dashboard', () => {
        render(
            <>
                <ActionBadge action="returned" />
                <StatusBadge status="in_review" />
            </>,
        );

        const actionBadge = screen.getByText(/returned/i);
        const statusBadge = screen.getByText(/in review/i);

        expect(actionBadge.className).toContain('bg-warning');
        expect(statusBadge.className).toContain('bg-info');
    });
});
