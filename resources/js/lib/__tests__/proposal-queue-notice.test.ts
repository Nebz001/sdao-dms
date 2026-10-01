import { describe, expect, it } from 'vitest';
import { proposalQueueNotice } from '@/lib/proposal-queue-notice';

describe('proposalQueueNotice', () => {
    it('says nothing for an empty list on every tab', () => {
        for (const filter of [null, 'overdue', 'approved', 'returned', 'decided'] as const) {
            expect(proposalQueueNotice(filter, '2026-2027', 0)).toBeNull();
        }
    });

    it('announces the pending count', () => {
        expect(proposalQueueNotice(null, '2026-2027', 1)?.text).toBe('1 proposal awaiting your review.');
        expect(proposalQueueNotice(null, '2026-2027', 3)?.text).toBe('3 proposals awaiting your review.');
    });

    it('warns about overdue proposals', () => {
        expect(proposalQueueNotice('overdue', '2026-2027', 2)).toEqual({
            tone: 'warning',
            text: '2 overdue proposals.',
        });
    });

    it('uses the same number as the list for the history tabs', () => {
        expect(proposalQueueNotice('decided', '2026-2027', 5)?.text).toBe(
            'Showing decisions for 2026-2027 (5).',
        );
        expect(proposalQueueNotice('approved', '2026-2027', 2)?.text).toBe(
            'Showing approved proposals for 2026-2027 (2).',
        );
    });
});
