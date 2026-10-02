import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import ReviewStatusNotice from '@/components/review-status-notice';

describe('ReviewStatusNotice', () => {
    it.each([
        ['approved', 'bg-success/10'],
        ['returned', 'bg-warning/10'],
        ['rejected', 'bg-destructive/10'],
        ['draft', 'bg-muted-foreground/10'],
    ])('tones a %s document with %s, matching its status badge', (status, expected) => {
        render(<ReviewStatusNotice status={status}>No further action is available.</ReviewStatusNotice>);

        const notice = screen.getByRole('status');
        expect(notice).toHaveTextContent('No further action is available.');
        expect(notice.className).toContain(expected);
    });
});
