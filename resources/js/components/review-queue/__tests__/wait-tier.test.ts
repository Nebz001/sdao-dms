import { describe, expect, it } from 'vitest';
import { TIER_ACCENT_TEXT, TIER_CARD_TONE, TIER_TONE } from '../wait-tier';

describe('wait tier styling', () => {
    it('keeps the card surface and the pill on the same band', () => {
        // Card tone and pill tone must move together, or a card can read amber
        // while its pill reads red.
        expect(TIER_CARD_TONE.fresh).toBe('default');
        expect(TIER_TONE.fresh).toBe('neutral');

        expect(TIER_CARD_TONE.aging).toBe('warning');
        expect(TIER_TONE.aging).toBe('warning');

        expect(TIER_CARD_TONE.overdue).toBe('alert');
        expect(TIER_TONE.overdue).toBe('destructive');
    });

    it('gives every band a text accent from the status tokens', () => {
        expect(TIER_ACCENT_TEXT.aging).toBe('text-warning-foreground');
        expect(TIER_ACCENT_TEXT.overdue).toBe('text-destructive-foreground');
        expect(TIER_ACCENT_TEXT.fresh).toBe('text-primary-text');
    });
});
