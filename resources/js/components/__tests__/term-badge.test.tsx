import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import TermBadge from '@/components/term-badge';

describe('TermBadge', () => {
    afterEach(cleanup);

    it('shows the term as a tag with the full label as its title', () => {
        render(<TermBadge label="2nd Term, 2026-2027" />);

        const badge = screen.getByText('2nd Term, 2026-2027');

        expect(badge).toBeInTheDocument();
        expect(badge).toHaveAttribute('title', '2nd Term, 2026-2027');
        expect(badge.querySelector('svg')).toHaveAttribute('aria-hidden', 'true');
    });
});
