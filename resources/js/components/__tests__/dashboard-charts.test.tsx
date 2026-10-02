import { render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import ProposalFunnelCard from '@/components/proposal-funnel-chart';
import StatusDistributionCard from '@/components/status-distribution-card';

vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children, ...props }: { href: string; children: React.ReactNode }) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
    router: { visit: vi.fn() },
}));

describe('StatusDistributionCard', () => {
    const data = [
        { status: 'draft', count: 3, href: null },
        { status: 'in_review', count: 5, href: '/admin/stuck-documents?waiting_on=approver' },
        { status: 'returned', count: 3, href: '/admin/stuck-documents?waiting_on=org' },
        { status: 'approved', count: 20, href: '/admin/archive?status=approved&academic_year=current' },
        { status: 'rejected', count: 4, href: '/admin/archive?status=rejected&academic_year=current' },
    ];

    it('shows the total and the word documents in the center', () => {
        render(<StatusDistributionCard data={data} />);

        expect(screen.getByText('35')).toBeInTheDocument();
        expect(screen.getByText('documents')).toBeInTheDocument();
        expect(screen.getByText('All five form types, this academic year')).toBeInTheDocument();
    });

    it('lists every status in sentence case with its count, Approved first and Draft last', () => {
        render(<StatusDistributionCard data={data} />);

        const rows = screen.getAllByRole('listitem');
        expect(rows.map((row) => row.textContent)).toEqual([
            'Approved20',
            'In review5',
            'Returned3',
            'Rejected4',
            'Draft3',
        ]);
    });

    it('links each status that has a list, and leaves drafts as plain text', () => {
        render(<StatusDistributionCard data={data} />);

        expect(within(screen.getByText('In review').closest('li')!).getByRole('link')).toHaveAttribute(
            'href',
            '/admin/stuck-documents?waiting_on=approver',
        );
        expect(within(screen.getByText('Draft').closest('li')!).queryByRole('link')).toBeNull();
    });

    it('says so instead of drawing an empty donut', () => {
        render(<StatusDistributionCard data={data.map((d) => ({ ...d, count: 0 }))} />);

        expect(screen.getByText('No documents in this academic year yet.')).toBeInTheDocument();
        expect(screen.queryByText('documents')).toBeNull();
    });
});

describe('ProposalFunnelCard', () => {
    const funnels = [
        {
            variant: 'regular_on_calendar',
            label: 'Regular, On-Calendar',
            submitted: 48,
            steps: [{ label: 'Adviser', count: 44 }],
            approved: 22,
        },
        {
            variant: 'shs_off_calendar',
            label: 'Senior High School, Off-Calendar',
            submitted: 10,
            steps: [{ label: 'Principal', count: 8 }],
            approved: 5,
        },
    ];

    it('names the selected chain in the subtitle and builds the toggle from the real variants', () => {
        render(<ProposalFunnelCard funnels={funnels} />);

        expect(
            screen.getByText('Regular, On-Calendar chain. Step position is not comparable across variants.'),
        ).toBeInTheDocument();
        expect(screen.getByRole('radio', { name: 'Regular, On-Calendar' })).toBeInTheDocument();
        expect(screen.getByRole('radio', { name: 'Senior High School, Off-Calendar' })).toBeInTheDocument();
    });

    it('has no toggle with a single variant, and an empty message with none', () => {
        const { rerender } = render(<ProposalFunnelCard funnels={[funnels[0]]} />);
        expect(screen.queryByRole('radio')).toBeNull();

        rerender(<ProposalFunnelCard funnels={[]} />);
        expect(screen.getByText('No activity proposals were submitted this academic year.')).toBeInTheDocument();
    });
});

describe('ProposalFunnelCard with many variants', () => {
    it('switches from a toggle to a menu when there are more than three variants', () => {
        const many = ['a', 'b', 'c', 'd'].map((key, index) => ({
            variant: `variant_${key}`,
            label: `Variant ${key}`,
            submitted: 10 - index,
            steps: [{ label: 'Adviser', count: 1 }],
            approved: 1,
        }));

        render(<ProposalFunnelCard funnels={many} />);

        expect(screen.queryByRole('radio')).toBeNull();
        expect(screen.getByRole('combobox', { name: 'Chain variant' })).toBeInTheDocument();
    });
});
