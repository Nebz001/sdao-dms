export type SplitAccountName = {
    /** The real name, safe to show as the primary line. */
    name: string;
    /** The role text from a trailing "(...)" group, without parentheses; null when there is none. */
    detail: string | null;
};

/**
 * Splits a stored account name for DISPLAY only, e.g.
 * "Maria Dolores C. Evangelista (Associate Dean, Medical Technology)" becomes
 * the name "Maria Dolores C. Evangelista" and the detail
 * "Associate Dean, Medical Technology".
 *
 * Only a single, clean group at the very end of the string counts: the group
 * holds no nested or stray parentheses, is not empty, and leaves a non-empty
 * name before it. Anything else (an unmatched parenthesis, a group in the
 * middle, a name that is only a group) comes back whole, as one plain name,
 * so odd data never breaks a row. The stored value is never changed; search,
 * sorting and matching keep using the full stored name.
 */
export function splitAccountName(stored: string): SplitAccountName {
    const match = /^(.*?\S)\s*\(([^()]*[^()\s][^()]*)\)\s*$/.exec(stored);

    // A name part with an unbalanced parenthesis is odd data, not a clean split.
    const balanced = (text: string) => text.split('(').length === text.split(')').length;

    if (!match || !balanced(match[1])) {
        return { name: stored, detail: null };
    }

    const detail = match[2].replace(/\s+/g, ' ').trim();

    return { name: match[1].trim(), detail: detail === '' ? null : detail };
}
