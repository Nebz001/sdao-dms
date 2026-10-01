import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import PageHeader from '@/components/page-header';
import PageNotice from '@/components/page-notice';

describe('PageNotice', () => {
    it('is a polite status region with the message and an icon', () => {
        const { container } = render(
            <PageNotice tone="down">4 submissions this week, down 2 from last week.</PageNotice>,
        );

        const notice = screen.getByRole('status');
        expect(notice).toHaveTextContent('4 submissions this week, down 2 from last week.');
        expect(notice.querySelector('svg')).not.toBeNull();
        expect(container.querySelector('[role="alert"]')).toBeNull();
    });

    it.each([
        ['up', 'border-success/40'],
        ['down', 'text-destructive-foreground'],
        ['info', 'border-info/40'],
        ['warning', 'border-warning/40'],
    ] as const)('colors the %s tone with its status token', (tone, expected) => {
        render(<PageNotice tone={tone}>Message</PageNotice>);

        expect(screen.getByRole('status').className).toContain(expected);
    });
});

describe('PageHeader', () => {
    it('renders the title, the static subtitle, the badge and the actions', () => {
        render(
            <PageHeader
                title="Admin Dashboard"
                subtitle="Everything happening across SDAO this academic year"
                badge={<span>Active</span>}
                actions={<button type="button">Print</button>}
            />,
        );

        expect(screen.getByRole('heading', { level: 1, name: 'Admin Dashboard' })).toBeInTheDocument();
        expect(screen.getByText('Everything happening across SDAO this academic year')).toBeInTheDocument();
        expect(screen.getByText('Active')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Print' })).toBeInTheDocument();
    });
});
