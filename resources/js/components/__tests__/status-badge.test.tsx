import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import {
    AccountStatusBadge,
    ActionBadge,
    FlagBadge,
    OrganizationStatusBadge,
    RenewalBadge,
    RequestStatusBadge,
    RequirementBadge,
    StatusBadge,
    ToneBadge,
    VenueStatusBadge,
} from '@/components/status-badge';
import type { Tone } from '@/lib/status-tones';

/**
 * Every tone is the same recipe with a different color token: a thin border and
 * a very light tint of the tone, text in the tone's "-foreground" shade, never a
 * solid fill. This pins the recipe so a badge cannot drift back to solid.
 */
const TONE_CLASSES: Record<Tone, [string, string, string]> = {
    success: ['border-success/40', 'bg-success/10', 'text-success-foreground'],
    info: ['border-info/40', 'bg-info/10', 'text-info-foreground'],
    warning: ['border-warning/40', 'bg-warning/10', 'text-warning-foreground'],
    destructive: ['border-destructive/40', 'bg-destructive/10', 'text-destructive-foreground'],
    neutral: ['border-muted-foreground/40', 'bg-muted-foreground/10', 'text-foreground/75'],
};

describe('ToneBadge', () => {
    it.each(Object.keys(TONE_CLASSES) as Tone[])('renders the %s tone as a tinted outline', (tone) => {
        render(<ToneBadge tone={tone}>Label</ToneBadge>);

        const badge = screen.getByText('Label');

        for (const cls of TONE_CLASSES[tone]) {
            expect(badge.className).toContain(cls);
        }

        expect(badge.className).toContain('uppercase');
        expect(badge.className).toContain('font-semibold');
    });

    it('is never a solid fill', () => {
        render(<ToneBadge tone="success">Label</ToneBadge>);

        const badge = screen.getByText('Label');
        expect(badge.className).not.toMatch(/(^|\s)bg-(success|info|warning|destructive)(\s|$)/);
        expect(badge.className).not.toContain('text-background');
        expect(badge.className).not.toContain('text-white');
    });
});

describe('StatusBadge', () => {
    it.each([
        ['draft', 'neutral', 'Draft'],
        ['in_review', 'info', 'In review'],
        ['returned', 'warning', 'Returned'],
        ['approved', 'success', 'Approved'],
        ['rejected', 'destructive', 'Rejected'],
    ] as const)('shows %s as %s', (status, tone, label) => {
        render(<StatusBadge status={status} />);

        expect(screen.getByText(label).className).toContain(TONE_CLASSES[tone][1]);
    });

    it('falls back to a neutral badge with its own words for an unknown status', () => {
        render(<StatusBadge status="some_future_status" />);

        const badge = screen.getByText('Some Future Status');
        expect(badge.className).toContain('bg-muted-foreground/10');
    });
});

describe('ActionBadge', () => {
    it.each([
        ['submitted', 'info'],
        ['resubmitted', 'info'],
        ['approved', 'success'],
        ['advanced', 'success'],
        ['completed', 'success'],
        ['returned', 'warning'],
        ['rejected', 'destructive'],
        ['withdrawn', 'neutral'],
    ] as const)('shows "%s" as %s', (action, tone) => {
        render(<ActionBadge action={action} />);

        expect(screen.getByText(new RegExp(`^${action}$`, 'i')).className).toContain(TONE_CLASSES[tone][1]);
    });

    it('never shares a tone between Returned and In review, since both can sit on one dashboard', () => {
        render(
            <>
                <ActionBadge action="returned" />
                <StatusBadge status="in_review" />
            </>,
        );

        expect(screen.getByText('Returned').className).toContain('bg-warning/10');
        expect(screen.getByText('In review').className).toContain('bg-info/10');
    });
});

describe('the other status domains', () => {
    it.each([
        ['organization', <OrganizationStatusBadge key="a" status="active" />, 'Active', 'success'],
        ['organization', <OrganizationStatusBadge key="b" status="pending_review" />, 'Pending review', 'info'],
        ['organization', <OrganizationStatusBadge key="c" status="needs_renewal" />, 'Needs renewal', 'warning'],
        ['organization', <OrganizationStatusBadge key="d" status="inactive" />, 'Inactive', 'neutral'],
        ['account', <AccountStatusBadge key="e" status="unverified" />, 'Pending verification', 'warning'],
        ['account', <AccountStatusBadge key="f" status="rejected" />, 'Not approved', 'destructive'],
        ['request', <RequestStatusBadge key="g" status="pending" />, 'Pending', 'info'],
        ['request', <RequestStatusBadge key="h" status="declined" />, 'Declined', 'destructive'],
        ['renewal', <RenewalBadge key="i" status="due" />, 'Renewal due', 'warning'],
        ['renewal', <RenewalBadge key="j" status="not_yet_due" />, 'Not yet due', 'neutral'],
        ['requirement', <RequirementBadge key="k" status="action_needed" />, 'Missing', 'warning'],
        ['venue', <VenueStatusBadge key="l" status="confirmed" />, 'Confirmed', 'success'],
        ['venue', <VenueStatusBadge key="m" status="tentative" />, 'Tentative', 'warning'],
        ['flag', <FlagBadge key="n" flag="urgent" />, 'Urgent', 'destructive'],
        ['flag', <FlagBadge key="o" flag="flagged" />, 'Flagged for revision', 'warning'],
    ] as const)('%s: "%s" is %s', (_domain, element, label, tone) => {
        render(element);

        expect(screen.getByText(label).className).toContain(TONE_CLASSES[tone][1]);
    });
});
