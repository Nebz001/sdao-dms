import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import AlertError from '@/components/alert-error';
import PageHeader from '@/components/page-header';
import PageNotice from '@/components/page-notice';

describe('PageNotice', () => {
    it('is a polite status region with the message and an icon', () => {
        const { container } = render(
            <PageNotice tone="info">Submitting for Debate Society.</PageNotice>,
        );

        const notice = screen.getByRole('status');
        expect(notice).toHaveTextContent('Submitting for Debate Society.');
        expect(notice.querySelector('svg')).not.toBeNull();
        expect(container.querySelector('[role="alert"]')).toBeNull();
    });

    it('is an alert only when it is urgent', () => {
        render(
            <PageNotice tone="destructive" urgent title="Cannot approve.">
                The adviser is assigned elsewhere.
            </PageNotice>,
        );

        expect(screen.getByRole('alert')).toHaveTextContent('Cannot approve. The adviser is assigned elsewhere.');
        expect(screen.queryByRole('status')).toBeNull();
    });

    it.each([
        ['destructive', 'border-destructive/40', 'bg-destructive/10'],
        ['warning', 'border-warning/40', 'bg-warning/10'],
        ['info', 'border-info/40', 'bg-info/10'],
        ['success', 'border-success/40', 'bg-success/10'],
        ['neutral', 'border-muted-foreground/40', 'bg-muted-foreground/10'],
    ] as const)('tints the %s tone with a border and a background of the same color', (tone, border, background) => {
        render(<PageNotice tone={tone}>Message</PageNotice>);

        const notice = screen.getByRole('status');
        expect(notice.className).toContain(border);
        expect(notice.className).toContain(background);
    });

    it.each([
        ['destructive', 'text-destructive-foreground'],
        ['warning', 'text-warning-foreground'],
        ['info', 'text-info-foreground'],
        ['success', 'text-success-foreground'],
        ['neutral', 'text-foreground'],
    ] as const)('sets the %s lead sentence in bold, in the tone color', (tone, expected) => {
        render(
            <PageNotice tone={tone} title="Lead sentence.">
                Details.
            </PageNotice>,
        );

        const lead = screen.getByText('Lead sentence.');
        expect(lead.tagName).toBe('STRONG');
        expect(lead.className).toContain('font-semibold');
        expect(lead.className).toContain(expected);
    });

    it('keeps the layout identical for every tone: icon, text, then the action', () => {
        render(
            <PageNotice tone="warning" title="Lead." action={<a href="/x">Open these 3</a>}>
                Details.
            </PageNotice>,
        );

        const notice = screen.getByRole('status');
        expect(notice.querySelector('svg')).not.toBeNull();
        expect(screen.getByRole('link', { name: 'Open these 3' })).toBeInTheDocument();
        // The description row wraps, so on a phone the action drops under the text.
        const row = screen.getByRole('link', { name: 'Open these 3' }).parentElement?.parentElement;
        expect(row?.className).toContain('flex-wrap');
    });

    it('renders an action beside the message', () => {
        render(
            <PageNotice tone="destructive" action={<a href="/x">Open these 3</a>}>
                3 activities are not approved.
            </PageNotice>,
        );

        expect(screen.getByRole('status')).toHaveTextContent('3 activities are not approved.');
        expect(screen.getByRole('link', { name: 'Open these 3' })).toBeInTheDocument();
    });
});

describe('AlertError', () => {
    it('is an urgent notice with the lead and each distinct error once', () => {
        render(<AlertError errors={['Name is required.', 'Name is required.', 'Date is invalid.']} />);

        const alert = screen.getByRole('alert');
        expect(alert).toHaveTextContent('Something went wrong.');
        expect(screen.getAllByRole('listitem')).toHaveLength(2);
        expect(alert.className).toContain('bg-destructive/10');
    });

    it('takes a custom lead', () => {
        render(<AlertError title="Fix 2 things to continue." errors={['One.']} />);

        expect(screen.getByText('Fix 2 things to continue.')).toBeInTheDocument();
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
