import { describe, expect, it } from 'vitest';
import {
    collapseBlankLines,
    initialsOf,
    parseCriteriaMechanics,
    parseObjectives,
    parseResponsiblePerson,
    parseResponsiblePersons,
    toParagraphs,
} from '@/lib/proposal-text';

describe('collapseBlankLines', () => {
    it('collapses runs of blank lines to one and trims the ends', () => {
        expect(collapseBlankLines('\n\nA\n\n\n\nB  \n\n')).toBe('A\n\nB');
    });

    it('treats whitespace-only lines as blank and normalises CRLF', () => {
        expect(collapseBlankLines('A\r\n   \r\n\t\r\nB')).toBe('A\n\nB');
    });
});

describe('parseObjectives', () => {
    const main = 'To strengthen the preparedness of accountancy students.';

    it('turns lines after a "Specific Objectives:" line into a list', () => {
        expect(parseObjectives(`${main}\n\nSpecific Objectives:\n\nProvide a realistic exam.\n\nIdentify strengths.\n\n\n\nEncourage discipline.`)).toEqual({
            paragraphs: [main],
            specific: ['Provide a realistic exam.', 'Identify strengths.', 'Encourage discipline.'],
        });
    });

    it.each(['specific objectives', 'SPECIFIC OBJECTIVES:', '  Specific Objective  ', 'Specific objectives :'])(
        'recognises the label as %j (any case, with or without the colon)',
        (label) => {
            expect(parseObjectives(`${main}\n${label}\nOne\nTwo`).specific).toEqual(['One', 'Two']);
        },
    );

    it('strips a leading list marker from an item so numbers are not doubled', () => {
        expect(parseObjectives('Main\nSpecific Objectives:\n1. First\n2) Second\n- Third').specific).toEqual(['First', 'Second', 'Third']);
    });

    it('shows paragraphs with blank lines collapsed when there is no Specific Objectives line', () => {
        expect(parseObjectives(`${main}\n\n\n\nA second paragraph.`)).toEqual({ paragraphs: [main, 'A second paragraph.'], specific: null });
    });

    it('works with no main objective before the label', () => {
        expect(parseObjectives('Specific Objectives\nOne')).toEqual({ paragraphs: [], specific: ['One'] });
    });

    it('falls back to plain paragraphs, losing no word, when the label has nothing under it', () => {
        expect(parseObjectives('Main text\n\nSpecific Objectives:')).toEqual({
            paragraphs: ['Main text', 'Specific Objectives:'],
            specific: null,
        });
    });

    it('does not treat a sentence that merely contains the words as the label', () => {
        const text = 'Our specific objectives: are many.\nSecond line';

        expect(parseObjectives(text)).toEqual({ paragraphs: [text], specific: null });
    });

    it('only the first label line starts the list; a later one stays an item, verbatim', () => {
        expect(parseObjectives('Main\nSpecific Objectives\nOne\nSpecific Objectives\nTwo').specific).toEqual([
            'One',
            'Specific Objectives',
            'Two',
        ]);
    });

    it('returns nothing for empty text', () => {
        expect(parseObjectives('  \n \n')).toEqual({ paragraphs: [], specific: null });
    });
});

describe('parseCriteriaMechanics', () => {
    it('reads "Label: value" lines as pairs, exactly as written', () => {
        expect(parseCriteriaMechanics('Participants: 4th-year Accountancy students.\nExam Coverage: Auditing, Taxation.\nDuration: 8 hours (with breaks).')).toEqual([
            { kind: 'pair', label: 'Participants', value: '4th-year Accountancy students.' },
            { kind: 'pair', label: 'Exam Coverage', value: 'Auditing, Taxation.' },
            { kind: 'pair', label: 'Duration', value: '8 hours (with breaks).' },
        ]);
    });

    it('splits only at the first colon so a value may contain colons and times', () => {
        expect(parseCriteriaMechanics('Schedule: 9:00 to 17:00')).toEqual([{ kind: 'pair', label: 'Schedule', value: '9:00 to 17:00' }]);
    });

    it('keeps a line with no "Label:" as plain text, in order, next to pairs', () => {
        expect(parseCriteriaMechanics('Participants: Everyone\nBring a valid ID.\nDuration: 2 hours')).toEqual([
            { kind: 'pair', label: 'Participants', value: 'Everyone' },
            { kind: 'text', text: 'Bring a valid ID.' },
            { kind: 'pair', label: 'Duration', value: '2 hours' },
        ]);
    });

    it('does not read a time, a bare label or a long sentence as a pair', () => {
        expect(parseCriteriaMechanics('9:00 Opening remarks\nParticipants:\nThis is a very long sentence with many words before: a colon').map((r) => r.kind)).toEqual([
            'text',
            'text',
            'text',
        ]);
    });

    it('ignores extra blank lines', () => {
        expect(parseCriteriaMechanics('A: one\n\n\n\nB: two')).toHaveLength(2);
    });

    it('is all text for a free-form paragraph', () => {
        expect(parseCriteriaMechanics('Judged on creativity and teamwork.')).toEqual([{ kind: 'text', text: 'Judged on creativity and teamwork.' }]);
    });
});

describe('responsible persons', () => {
    it.each([
        ['Juan Dela Cruz — JPIA President', 'Juan Dela Cruz', 'JPIA President'],
        ['Maria Santos – Activity Chairperson', 'Maria Santos', 'Activity Chairperson'],
        ['Maria Santos—Activity Chairperson', 'Maria Santos', 'Activity Chairperson'],
        ['Prof. Reyes - Faculty Adviser', 'Prof. Reyes', 'Faculty Adviser'],
        ['Juan Dela Cruz, JPIA President', 'Juan Dela Cruz', 'JPIA President'],
        ['Ana Lim — Treasurer, CS Dept', 'Ana Lim', 'Treasurer, CS Dept'],
    ])('splits %j into a name and a role', (entry, name, role) => {
        expect(parseResponsiblePerson(entry)).toEqual({ name, role });
    });

    it('shows only the name when there is no role', () => {
        expect(parseResponsiblePerson('Juan Dela Cruz')).toEqual({ name: 'Juan Dela Cruz', role: null });
    });

    it('does not treat a hyphen inside a name as a separator', () => {
        expect(parseResponsiblePerson('Jay-Ar Dimaculangan')).toEqual({ name: 'Jay-Ar Dimaculangan', role: null });
    });

    it('keeps an entry whole when one side of the separator is empty', () => {
        expect(parseResponsiblePerson('Juan —')).toEqual({ name: 'Juan —', role: null });
        expect(parseResponsiblePerson(', Treasurer')).toEqual({ name: ', Treasurer', role: null });
    });

    it('keeps one row per stored entry and skips blank entries', () => {
        expect(parseResponsiblePersons(['A — x', '  ', 'B'])).toEqual([
            { name: 'A', role: 'x' },
            { name: 'B', role: null },
        ]);
        expect(parseResponsiblePersons(null)).toEqual([]);
        // A stored list can hold a null or a number; it must not crash the page.
        expect(parseResponsiblePersons(['A', null, 5] as unknown as string[])).toEqual([{ name: 'A', role: null }]);
    });
});

describe('initialsOf', () => {
    it.each([
        ['Juan Dela Cruz', 'JC'],
        ['Maria Santos', 'MS'],
        ['Prof. Reyes', 'R'],
        ['Dr. Alice Lacorte', 'AL'],
        ['Engr. Michael Roxas', 'MR'],
        ['Atty. Jose P. Rizal', 'JR'],
        ['Dr. Engr. Alice Lacorte', 'AL'],
        ['dr alice lacorte', 'AL'],
        ['Íñigo Muñoz', 'ÍM'],
        ['Madonna', 'M'],
        ['Dr.', 'D'],
        ['', '?'],
    ])('%j -> %s', (name, initials) => {
        expect(initialsOf(name)).toBe(initials);
    });
});

describe('toParagraphs', () => {
    it('keeps a single line break inside a paragraph', () => {
        expect(toParagraphs('Line one\nLine two\n\n\n\nNext')).toEqual(['Line one\nLine two', 'Next']);
    });
});
