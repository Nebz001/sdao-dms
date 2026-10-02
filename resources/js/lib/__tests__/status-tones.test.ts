import { describe, expect, it } from 'vitest';
import { labelFor, toneFor, valuesFor } from '@/lib/status-tones';
import type { StatusDomain, Tone } from '@/lib/status-tones';

describe('status-tones', () => {
    const tones: Tone[] = ['success', 'info', 'warning', 'destructive', 'neutral'];
    const domains: StatusDomain[] = [
        'document',
        'action',
        'organization',
        'account',
        'request',
        'renewal',
        'requirement',
        'wait',
        'idle',
        'venue',
        'flag',
    ];

    it('gives every known value one of the five tones and a label with no em dash', () => {
        for (const domain of domains) {
            for (const value of valuesFor(domain)) {
                expect(tones).toContain(toneFor(domain, value));
                expect(labelFor(domain, value).length).toBeGreaterThan(0);
                expect(labelFor(domain, value)).not.toContain(String.fromCharCode(0x2014));
            }
        }
    });

    it('keeps the three colors that mean the same thing everywhere', () => {
        // Approved is green, returned is amber, rejected is red, in review is blue,
        // in every domain that has the word.
        expect(toneFor('document', 'approved')).toBe('success');
        expect(toneFor('action', 'approved')).toBe('success');
        expect(toneFor('request', 'approved')).toBe('success');
        expect(toneFor('document', 'returned')).toBe('warning');
        expect(toneFor('action', 'returned')).toBe('warning');
        expect(toneFor('document', 'rejected')).toBe('destructive');
        expect(toneFor('action', 'rejected')).toBe('destructive');
        expect(toneFor('account', 'rejected')).toBe('destructive');
        expect(toneFor('document', 'in_review')).toBe('info');
        expect(toneFor('organization', 'pending_review')).toBe('info');
    });

    it('treats nothing-is-happening states as neutral', () => {
        expect(toneFor('document', 'draft')).toBe('neutral');
        expect(toneFor('organization', 'inactive')).toBe('neutral');
        expect(toneFor('action', 'withdrawn')).toBe('neutral');
        expect(toneFor('renewal', 'season_closed')).toBe('neutral');
    });

    it('reads an unknown value as neutral with its own words', () => {
        expect(toneFor('document', 'brand_new')).toBe('neutral');
        expect(labelFor('document', 'brand_new')).toBe('Brand New');
    });
});
