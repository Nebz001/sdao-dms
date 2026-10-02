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

        expect(screen.getByText('4 d').closest('[data-slot=badge]')?.className).toContain(expected);
    });

    it('always prints the number and names the tier as text for assistive tech', () => {
        render(<IdleBadge days={11} tier="stale" label="idle" />);

        expect(screen.getByText('11 d idle')).toBeInTheDocument();
        expect(screen.getByText('Idle for 11 days, overdue')).toHaveClass('sr-only');
    });

    it('uses the singular for one day', () => {
        render(<IdleBadge days={1} tier="fresh" />);

        expect(screen.getByText('Idle for 1 day, recent')).toBeInTheDocument();
    });
});
