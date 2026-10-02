import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import MeterBar from '@/components/meter-bar';
import ReturnAnalyticsRow from '@/components/return-analytics-row';
import type { ReturnAnalytics } from '@/components/return-analytics-row';

vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children, ...props }: { href: string; children: React.ReactNode }) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
}));

const enough: ReturnAnalytics = {
    reasons: {
        sample: 12,
        minimum: 10,
        enough: true,
        href: '/admin/activity?action=returned',
        rows: [
            { label: 'Objectives', count: 5, percent: 42 },
            { label: 'Attachment: Letter of Intent', count: 2, percent: 17 },
        ],
    },
    rates: {
        minimum: 5,
        rows: [
            { formType: 'after_activity_report', label: 'After-Activity Report', submitted: 10, returned: 5, enough: true, percent: 50, href: '/a' },
            { formType: 'organization_renewal', label: 'Organization Renewal', submitted: 2, returned: 1, enough: false, percent: null, href: '/b' },
        ],
        average: { value: 1.6, sample: 8, minimum: 5, enough: true },
    },
};

describe('MeterBar', () => {
    it('exposes its value to assistive tech and clamps out-of-range values', () => {
        render(<MeterBar value={140} tone="warning" label="Objectives: 42%" />);

        const meter = screen.getByRole('meter', { name: 'Objectives: 42%' });
        expect(meter).toHaveAttribute('aria-valuenow', '100');
    });
});

describe('ReturnAnalyticsRow', () => {
    it('prints every percent as text next to its bar', () => {
        render(<ReturnAnalyticsRow data={enough} />);

        expect(screen.getByText('42%')).toBeInTheDocument();
        expect(screen.getByText('Attachment: Letter of Intent')).toBeInTheDocument();
        expect(screen.getByText('50%')).toBeInTheDocument();
        expect(screen.getByText('1.6')).toBeInTheDocument();
        expect(screen.getByText('average submissions before a document is approved')).toBeInTheDocument();
    });

    it('shows Not enough data yet instead of a percent from a tiny sample', () => {
        render(<ReturnAnalyticsRow data={enough} />);

        expect(screen.getByText('Not enough data yet')).toBeInTheDocument();
        expect(screen.queryByText('100%')).toBeNull();
    });

    it('withholds the reasons list and the average when their samples are too small', () => {
        render(
            <ReturnAnalyticsRow
                data={{
                    reasons: { ...enough.reasons, enough: false, sample: 3, rows: [] },
                    rates: { ...enough.rates, average: { value: null, sample: 2, minimum: 5, enough: false } },
                }}
            />,
        );

        expect(screen.getByText('Needs 10 returned documents with flagged sections. 3 so far this academic year.')).toBeInTheDocument();
        expect(screen.getByText(/Not enough data yet for the average number of submissions/)).toBeInTheDocument();
        expect(screen.queryByText('1.6')).toBeNull();
    });
});
