import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import FormTypeBadge from '@/components/form-type-badge';
import TagBadge from '@/components/tag-badge';
import WaitBadge from '@/components/wait-badge';

describe('TagBadge', () => {
    it('is a soft gray fill in normal case, rounded, with no strong border', () => {
        render(<TagBadge>School of Accountancy, Business, and Management</TagBadge>);

        const tag = screen.getByText('School of Accountancy, Business, and Management');
        expect(tag.className).toContain('bg-secondary');
        expect(tag.className).toContain('rounded-md');
        expect(tag.className).toContain('border-transparent');
        expect(tag.className).not.toContain('uppercase');
        expect(tag.className).not.toContain('font-semibold');
    });

    it('lets a long name wrap instead of truncating it', () => {
        render(<TagBadge>A very long organization name that would otherwise be cut off</TagBadge>);

        const tag = screen.getByText(/A very long organization name/);
        expect(tag.className).toContain('whitespace-normal');
        expect(tag.className).toContain('break-words');
        expect(tag.className).not.toContain('truncate');
    });

    it('is not a tone: it carries no status color', () => {
        render(<TagBadge>JPIA</TagBadge>);

        expect(screen.getByText('JPIA').className).not.toMatch(/(^|\s)(bg|text|border)-(success|info|warning|destructive)/);
    });
});

describe('FormTypeBadge', () => {
    it('is the same neutral tag', () => {
        render(<FormTypeBadge label="Activity Proposal" />);

        const tag = screen.getByText('Activity Proposal');
        expect(tag.className).toContain('bg-secondary');
        expect(tag.className).not.toContain('uppercase');
    });
});

describe('WaitBadge', () => {
    it.each([
        ['normal', 'bg-muted-foreground/10', 'On track'],
        ['warning', 'bg-warning/10', 'Getting close'],
        ['overdue', 'bg-destructive/10', 'Overdue'],
    ] as const)('tones the %s tier with %s and names it for assistive tech', (tier, expected, name) => {
        render(<WaitBadge days={3} tier={tier} />);

        const badge = screen.getByText('3d').closest('[data-slot=badge]');
        expect(badge?.className).toContain(expected);
        expect(screen.getByText(`Waiting 3 days, ${name.toLowerCase()}`)).toHaveClass('sr-only');
    });

    it('keeps the number in normal case and uses the singular for one day', () => {
        render(<WaitBadge days={1} tier="normal" />);

        expect(screen.getByText('1d').closest('[data-slot=badge]')?.className).toContain('normal-case');
        expect(screen.getByText('Waiting 1 day, on track')).toBeInTheDocument();
    });
});
