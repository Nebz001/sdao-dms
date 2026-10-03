import { describe, expect, it } from 'vitest';
import { splitAccountName } from '@/lib/account-name';

describe('splitAccountName', () => {
    it('leaves a plain name as one line', () => {
        expect(splitAccountName('Zaira Joy Enayo')).toEqual({ name: 'Zaira Joy Enayo', detail: null });
        expect(splitAccountName('Dr. Alice Lacorte')).toEqual({ name: 'Dr. Alice Lacorte', detail: null });
    });

    it('splits a trailing role group into name and detail', () => {
        expect(splitAccountName('Maria Dolores C. Evangelista (Associate Dean, Medical Technology)')).toEqual({
            name: 'Maria Dolores C. Evangelista',
            detail: 'Associate Dean, Medical Technology',
        });
    });

    it('tolerates spacing around the group', () => {
        expect(splitAccountName('Ana Cruz   (  Program Chair  )  ')).toEqual({ name: 'Ana Cruz', detail: 'Program Chair' });
        expect(splitAccountName('Ana Cruz(Dean)')).toEqual({ name: 'Ana Cruz', detail: 'Dean' });
    });

    it('only splits the trailing group, keeping earlier parentheses in the name', () => {
        expect(splitAccountName('John (Jack) Smith (Adviser)')).toEqual({ name: 'John (Jack) Smith', detail: 'Adviser' });
    });

    it('keeps odd data whole as one plain name', () => {
        const odd = [
            'Maria Dean)', // stray closing parenthesis
            'Maria (Dean', // unmatched opening parenthesis
            'Maria ((Dean)', // stray extra opening parenthesis
            'Maria (Dean (acting))', // nested group
            'Maria (Dean) Evangelista', // group in the middle, not trailing
            '(Associate Dean)', // nothing before the group
            'Maria ()', // empty group
            'Maria (   )', // blank group
            '',
        ];

        for (const stored of odd) {
            expect(splitAccountName(stored)).toEqual({ name: stored, detail: null });
        }
    });
});
