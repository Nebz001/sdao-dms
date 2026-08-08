import { render, screen } from '@testing-library/react';
import { Inbox } from 'lucide-react';
import { describe, expect, it } from 'vitest';
import StatTile from '@/components/stat-tile';
import { TooltipProvider } from '@/components/ui/tooltip';

/**
 * StatTile is the admin dashboard's KPI tile: a trend indicator (up/down/
 * flat) and, for the one tile marked `urgent` (Unassigned Advisers), an
 * alert badge replacing the ordinary nonzero left-border. This pins both
 * behaviors so a future edit can't silently swap the wrong icon/color or
 * leave the urgent badge rendering (or not rendering) for the wrong count.
 *
 * Wrapped in TooltipProvider here because production only gets one from
 * app.tsx's withApp() wrapper, which isn't present in a component-only render.
 */
function renderTile(
    props: Partial<React.ComponentProps<typeof StatTile>> = {},
) {
    return render(
        <TooltipProvider>
            <StatTile
                label="Awaiting Your Review"
                count={0}
                href="/dashboard"
                icon={Inbox}
                {...props}
            />
        </TooltipProvider>,
    );
}

describe('StatTile — trend indicator', () => {
    it('shows an upward trend icon and success color for a positive delta', () => {
        renderTile({
            weekly: { thisWeek: 5, lastWeek: 2, delta: 3, noun: 'submitted' },
        });

        const trend = screen.getByText('+3 submitted vs. last week');
        expect(trend.parentElement?.className).toContain('text-success');
        expect(
            document.querySelector('.lucide-trending-up'),
        ).toBeInTheDocument();
    });

    it('shows a downward trend icon and destructive color for a negative delta', () => {
        renderTile({
            weekly: { thisWeek: 1, lastWeek: 4, delta: -3, noun: 'submitted' },
        });

        const trend = screen.getByText('−3 submitted vs. last week');
        expect(trend.parentElement?.className).toContain('text-destructive');
        expect(
            document.querySelector('.lucide-trending-down'),
        ).toBeInTheDocument();
    });

    it('shows a flat/minus icon and muted color for a zero delta, never colored', () => {
        renderTile({
            weekly: { thisWeek: 2, lastWeek: 2, delta: 0, noun: 'submitted' },
        });

        const trend = screen.getByText('No change vs. last week');
        expect(trend.parentElement?.className).toContain(
            'text-muted-foreground',
        );
        expect(document.querySelector('.lucide-minus')).toBeInTheDocument();
    });

    it('renders no trend row at all when weekly is omitted', () => {
        renderTile();

        expect(screen.queryByText(/vs\. last week/)).not.toBeInTheDocument();
    });
});

describe('StatTile — urgent badge', () => {
    it('renders the alert badge with an accessible name when urgent and nonzero', () => {
        renderTile({ count: 1, urgent: true });

        expect(
            screen.getByRole('button', {
                name: '1 adviser needs to be assigned',
            }),
        ).toBeInTheDocument();
    });

    it('pluralizes the accessible name for a count greater than one', () => {
        renderTile({ count: 3, urgent: true });

        expect(
            screen.getByRole('button', {
                name: '3 advisers need to be assigned',
            }),
        ).toBeInTheDocument();
    });

    it('renders no alert badge when not urgent, even with a nonzero count', () => {
        renderTile({ count: 5, urgent: false });

        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });

    it('drops the nonzero left-border accent on the urgent tile, so there is exactly one urgent signal', () => {
        const { container } = renderTile({ count: 1, urgent: true });

        expect(
            container.querySelector('[data-slot="card"]')?.className,
        ).not.toContain('border-l-primary');
    });

    it('keeps the ordinary nonzero left-border accent on a non-urgent, nonzero tile', () => {
        const { container } = renderTile({ count: 4, urgent: false });

        expect(
            container.querySelector('[data-slot="card"]')?.className,
        ).toContain('border-l-primary');
    });
});
