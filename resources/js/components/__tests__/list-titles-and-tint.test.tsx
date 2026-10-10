import { cleanup, render, screen, within } from '@testing-library/react';
import { CalendarDays, Users } from 'lucide-react';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { DateBadge } from '@/components/activity-picker';
import ClientListPage from '@/components/client-list-page';
import type { ListRow } from '@/components/client-list-page';
import IconTile from '@/components/icon-tile';
import IdentityPill from '@/components/identity-pill';
import { BRAND_TINT, BRAND_TINT_OUTLINE } from '@/lib/brand-tint';

vi.mock('@inertiajs/react', () => ({
    Link: ({ children, href, ...rest }: { children: ReactNode; href: unknown; [key: string]: unknown }) => (
        <a href={typeof href === 'string' ? href : (href as { url: string }).url} {...rest}>
            {children}
        </a>
    ),
    usePage: () => ({ props: {}, url: '/renewals' }),
}));

const rows: Array<ListRow> = [
    {
        id: 1,
        title: 'Organization Renewal',
        status: 'in_review',
        created_at: '2026-09-06T00:00:00Z',
        current_approver: 'SDAO',
        period: '2027-2028',
    },
    {
        id: 2,
        title: 'Organization Registration',
        status: 'approved',
        created_at: '2026-09-05T00:00:00Z',
        current_approver: null,
        period: null,
    },
];

describe('list rows use the clean title and the period badge', () => {
    afterEach(cleanup);

    it('shows the form type as the title, the year as a neutral chip, and no organization or dash', () => {
        render(
            <ClientListPage
                kind="renewal"
                icon={CalendarDays}
                title="Renewals"
                subtitle="Keep your organization active"
                searchPlaceholder="Search"
                startLabel="New Renewal"
                startHref="/renewals/create"
                canStart={{ enabled: true, reason: null }}
                rows={rows}
                dateLabel="Submitted"
                empty={{ title: 'None', description: 'None', steps: [] }}
                rowAction={(row) => ({ label: 'View', href: `/renewals/${row.id}` })}
            />,
        );

        const first = screen.getByText('Organization Renewal').closest('li')!;
        expect(within(first).getByText('2027-2028')).toBeInTheDocument();
        expect(first.textContent).not.toContain('—');
        expect(first.textContent).not.toContain('(');

        // A row with no period shows no empty chip.
        const second = screen.getByText('Organization Registration').closest('li')!;
        expect(within(second).queryByText('2027-2028')).not.toBeInTheDocument();
    });
});

describe('the one NU blue tint', () => {
    afterEach(cleanup);

    it('is the single style of every icon tile, whatever the icon', () => {
        const { container } = render(
            <>
                <IconTile icon={CalendarDays} />
                <IconTile icon={Users} size="lg" />
            </>,
        );

        for (const tile of Array.from(container.querySelectorAll('span'))) {
            for (const klass of BRAND_TINT.split(' ')) {
                expect(tile).toHaveClass(klass);
            }

            expect(tile.className).not.toMatch(/(blue|sky|cyan|teal|violet|orange|emerald|amber)-\d/);
        }
    });

    it('colors an active date badge with it, and keeps an inactive one neutral', () => {
        const { container } = render(
            <>
                <DateBadge date="2026-10-15" active />
                <DateBadge date="2026-10-28" />
            </>,
        );
        const [active, inactive] = Array.from(container.querySelectorAll('span[aria-hidden]'));

        expect(active).toHaveClass('bg-brand-soft', 'text-brand-soft-foreground');
        expect(inactive).toHaveClass('bg-muted');
        expect(inactive).not.toHaveClass('bg-brand-soft');
    });

    it('colors the brand role pill with it, divider included', () => {
        const { container } = render(<IdentityPill icon={Users} tone="brand" label="President" value="PICE" />);
        const pill = container.firstElementChild!;

        for (const klass of BRAND_TINT_OUTLINE.split(' ')) {
            expect(pill).toHaveClass(klass);
        }

        expect(pill.className).not.toMatch(/info|sky|cyan/);
        expect(container.querySelector('[role="none"], [data-orientation="vertical"]')).toHaveClass('bg-brand-soft-border');
    });
});
