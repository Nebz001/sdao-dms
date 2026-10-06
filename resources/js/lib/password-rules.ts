/**
 * Turns the backend's password rule string (Laravel's
 * Password::defaults()->toPasswordRulesString(), e.g.
 * "minlength: 8; required: lower; required: upper; required: special;")
 * into a sentence. Reading the same string the server sends means the hint
 * cannot drift from the rule that is actually enforced.
 */
export function describePasswordRules(rules: string): string {
    const entries = rules
        .split(';')
        .map((entry) => entry.trim())
        .filter(Boolean);

    const value = (key: string) =>
        entries
            .find((entry) => entry.startsWith(`${key}:`))
            ?.slice(key.length + 1)
            .trim();
    const required = new Set(
        entries
            .filter((entry) => entry.startsWith('required:'))
            .map((entry) => entry.slice('required:'.length).trim()),
    );

    const needs: string[] = [];

    if (required.has('lower') && required.has('upper')) {
        needs.push('upper and lower case letters');
    } else if (required.has('lower')) {
        needs.push('a lower case letter');
    } else if (required.has('upper')) {
        needs.push('an upper case letter');
    }

    if (required.has('digit')) {
        needs.push('a number');
    }

    if (required.has('special')) {
        needs.push('a symbol');
    }

    const min = value('minlength');
    const length = min ? `At least ${min} characters` : 'Any length';

    if (needs.length === 0) {
        return `${length}.`;
    }

    const list =
        needs.length === 1
            ? needs[0]
            : `${needs.slice(0, -1).join(', ')} and ${needs[needs.length - 1]}`;

    return `${length}, with ${list}.`;
}
