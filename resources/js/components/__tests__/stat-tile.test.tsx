import { render, screen } from '@testing-library/react';
import { Inbox } from 'lucide-react';
import { describe, expect, it } from 'vitest';
import StatTile from '@/components/stat-tile';
import { TooltipProvider } from '@/components/ui/tooltip';

/**
 * StatTile is the admin dashboard's KPI tile: a trend indicator (up/down/
 * flat) and, for the one tile marked `urgent` (Unassigned Advisers), an
 * alert badge. Tone is carried by icon and value color, never a card edge. This pins both
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

        expect(screen.getByText('+3 vs. last week')).toBeInTheDocument();
        const trend = screen.getByRole('img', { name: '+3 submitted versus last week' });
        expect(trend.className).toContain('text-success');
        expect(
            document.querySelector('.lucide-trending-up'),
        ).toBeInTheDocument();
    });

    it('shows a downward trend icon and destructive color for a negative delta', () => {
        renderTile({
            weekly: { thisWeek: 1, lastWeek: 4, delta: -3, noun: 'submitted' },
        });

        expect(screen.getByText('−3 vs. last week')).toBeInTheDocument();
        const trend = screen.getByRole('img', { name: '−3 submitted versus last week' });
        expect(trend.className).toContain('text-destructive');
        expect(
            document.querySelector('.lucide-trending-down'),
        ).toBeInTheDocument();
    });

    it('shows a flat/minus icon and muted color for a zero delta, never colored', () => {
        renderTile({
            weekly: { thisWeek: 2, lastWeek: 2, delta: 0, noun: 'submitted' },
        });

        expect(screen.getByText('No change')).toBeInTheDocument();
        const trend = screen.getByRole('img', { name: 'No change in submitted versus last week' });
        expect(trend.className).toContain('text-muted-foreground');
        expect(document.querySelector('.lucide-minus')).toBeInTheDocument();
    });

    it('keeps the chip on one line and the full sentence in the label', () => {
        renderTile({
            weekly: { thisWeek: 5, lastWeek: 2, delta: 3, noun: 'registered' },
        });

        const trend = screen.getByRole('img', { name: '+3 registered versus last week' });
        expect(trend.className).toContain('whitespace-nowrap');
        expect(trend).toHaveTextContent('+3 vs. last week');
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

    it('never draws a side border accent on a tile, urgent or not', () => {
        const urgent = renderTile({ count: 1, urgent: true });
        expect(
            urgent.container.querySelector('[data-slot="card"]')?.className,
        ).not.toContain('border-l');
        urgent.unmount();

        const plain = renderTile({ count: 4, urgent: false });
        expect(
            plain.container.querySelector('[data-slot="card"]')?.className,
        ).not.toContain('border-l');
    });

    it('carries the tone through the value text color on a nonzero tile', () => {
        renderTile({ count: 4, urgent: false });

        expect(screen.getByText('4').className).toContain('text-primary-text');
    });
});

describe('StatTile: admin dashboard tiles', () => {
    it('renders without an icon and shows a hint', () => {
        const { container } = render(
            <TooltipProvider>
                <StatTile
                    label="Stuck with approvers"
                    count={24}
                    href="/admin/stuck-documents"
                    hint="Oldest idle 11 days"
                    hintTone="destructive"
                />
            </TooltipProvider>,
        );

        expect(container.querySelector('svg')).toBeNull();
        expect(screen.getByText('Oldest idle 11 days').className).toContain('text-destructive-foreground');
    });

    it.each([
        ['warning', 'text-warning-foreground'],
        ['muted', 'text-muted-foreground'],
    ] as const)('colors a %s hint with its token', (hintTone, expected) => {
        renderTile({ hint: 'A hint', hintTone });

        expect(screen.getByText('A hint').className).toContain(expected);
    });
});
