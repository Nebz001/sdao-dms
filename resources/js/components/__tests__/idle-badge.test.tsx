import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import IdleBadge from '@/components/idle-badge';

describe('IdleBadge', () => {
    it.each([
        ['fresh', 'text-success-foreground'],
        ['aging', 'text-warning-foreground'],
        ['stale', 'text-destructive-foreground'],
    ] as const)('colors the %s tier with its token', (tier, expected) => {
        render(<IdleBadge days={4} tier={tier} />);

        expect(screen.getByText('4 d').className).toContain(expected);
    });

    it('always prints the number and names the tier for assistive tech', () => {
        render(<IdleBadge days={11} tier="stale" label="idle" />);

        const badge = screen.getByText('11 d idle');
        expect(badge).toBeInTheDocument();
        expect(badge).toHaveAttribute('aria-label', 'Idle for 11 days (overdue)');
    });

    it('uses the singular for one day', () => {
        render(<IdleBadge days={1} tier="fresh" />);

        expect(screen.getByLabelText('Idle for 1 day (recent)')).toBeInTheDocument();
    });
});
