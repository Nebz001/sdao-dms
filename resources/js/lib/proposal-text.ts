/**
 * Display-only parsing for the free-text sections of an activity proposal.
 * Nothing here is stored or sent back to the server, and every parser falls
 * back to plain paragraphs when the text does not fit its pattern. No words
 * are ever dropped, reworded or reordered: the only things removed are blank
 * lines beyond one and a leading list marker ("1.", "-") on a numbered item.
 */

/** Trims trailing spaces per line and collapses runs of blank lines into one. */
export function collapseBlankLines(text: string): string {
    const lines = text.replace(/\r\n?/g, '\n').split('\n').map((line) => line.replace(/\s+$/, ''));
    const kept: string[] = [];

    for (const line of lines) {
        if (line === '' && (kept.length === 0 || kept[kept.length - 1] === '')) {
            continue;
        }

        kept.push(line);
    }

    while (kept.length > 0 && kept[kept.length - 1] === '') {
        kept.pop();
    }

    return kept.join('\n');
}

/** Blank-line separated paragraphs. A single line break inside one is kept. */
export function toParagraphs(text: string): string[] {
    const collapsed = collapseBlankLines(text);

    return collapsed === '' ? [] : collapsed.split('\n\n');
}

export type ParsedObjectives = {
    /** The main objective, as paragraphs. */
    paragraphs: string[];
    /** The numbered items after a "Specific Objectives" line, or null when there is no such line. */
    specific: string[] | null;
};

const SPECIFIC_OBJECTIVES_LINE = /^\s*specific\s+objectives?\s*:?\s*$/i;
const LIST_MARKER = /^\s*(?:\d+\s*[.)]|[-•*])\s+/;

export function parseObjectives(text: string): ParsedObjectives {
    const collapsed = collapseBlankLines(text);
    const lines = collapsed === '' ? [] : collapsed.split('\n');
    const at = lines.findIndex((line) => SPECIFIC_OBJECTIVES_LINE.test(line));

    if (at === -1) {
        return { paragraphs: toParagraphs(text), specific: null };
    }

    const items = lines
        .slice(at + 1)
        .filter((line) => line.trim() !== '')
        .map((line) => line.replace(LIST_MARKER, '').trim());

    // A label with nothing under it is not a list: show every word as written.
    if (items.length === 0 || items.some((item) => item === '')) {
        return { paragraphs: toParagraphs(text), specific: null };
    }

    return { paragraphs: toParagraphs(lines.slice(0, at).join('\n')), specific: items };
}

export type CriteriaRow = { kind: 'pair'; label: string; value: string } | { kind: 'text'; text: string };

/** "Label: value", the label starting with a letter and at most six words. */
const LABEL_VALUE_LINE = /^(\p{L}[^:\n]*?)\s*:\s+(\S.*)$/u;

export function parseCriteriaMechanics(text: string): CriteriaRow[] {
    return collapseBlankLines(text)
        .split('\n')
        .filter((line) => line.trim() !== '')
        .map((line): CriteriaRow => {
            const match = LABEL_VALUE_LINE.exec(line.trim());

            if (match && match[1].trim().split(/\s+/).length <= 6) {
                return { kind: 'pair', label: match[1].trim(), value: match[2].trim() };
            }

            return { kind: 'text', text: line.trim() };
        });
}

export type ResponsiblePerson = { name: string; role: string | null };

/** A spaced hyphen, or an en/em dash with or without spaces. A bare hyphen ("Jay-Ar") is never a separator. */
const DASH_SEPARATOR = /\s+-\s+|\s*[–—]\s*/;

export function parseResponsiblePerson(entry: string): ResponsiblePerson {
    const whole = entry.trim();
    const dash = DASH_SEPARATOR.exec(whole);
    const comma = whole.indexOf(',');

    let name = whole;
    let role = '';

    if (dash) {
        name = whole.slice(0, dash.index).trim();
        role = whole.slice(dash.index + dash[0].length).trim();
    } else if (comma > 0) {
        name = whole.slice(0, comma).trim();
        role = whole.slice(comma + 1).trim();
    }

    // Either half missing means this was not a name-and-role entry: keep it whole.
    if (name === '' || role === '') {
        return { name: whole, role: null };
    }

    return { name, role };
}

/** Entries as stored, one person each; blank entries are skipped. */
export function parseResponsiblePersons(entries: string[] | null | undefined): ResponsiblePerson[] {
    return (entries ?? [])
        .filter((entry): entry is string => typeof entry === 'string' && entry.trim() !== '')
        .map(parseResponsiblePerson);
}

const TITLES = new Set(['mr', 'ms', 'mrs', 'miss', 'dr', 'engr', 'atty', 'prof', 'fr', 'sr', 'ar', 'sir']);

/** Up to two initials (first and last word), skipping leading titles such as Prof., Dr., Engr. */
export function initialsOf(name: string): string {
    const words = name.trim().split(/\s+/).filter(Boolean);

    while (words.length > 1 && TITLES.has(words[0].replace(/\.$/, '').toLowerCase())) {
        words.shift();
    }

    const letters = words
        .map((word) => /\p{L}/u.exec(word)?.[0] ?? '')
        .filter(Boolean)
        .map((letter) => letter.toUpperCase());

    if (letters.length === 0) {
        return '?';
    }

    return letters.length === 1 ? letters[0] : letters[0] + letters[letters.length - 1];
}
