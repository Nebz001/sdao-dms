import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import FormTypeBadge from '@/components/form-type-badge';
import TagBadge from '@/components/tag-badge';

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
