/**
 * Fix and Resend (the page a returned document opens): the data the server
 * sends and the one comparison the page uses to say a flagged item is "fixed".
 * The server runs its own comparison again on resend (ReturnedEditLock); this
 * one only drives the progress the officer sees, so it follows the same rule:
 * trimmed text, numbers by value.
 */

export type FixItemKind = 'section' | 'file' | 'row';

export type FixItem = {
    key: string;
    label: string;
    /** The approver's note for this item (the general note when it has none of its own). */
    comment: string | null;
    kind: FixItemKind;
};

export type FixDraftValues = Record<string, Record<string, unknown>>;

export type FixProps = {
    /** False when the return flagged nothing fixable: the whole form stays editable. */
    active: boolean;
    formLabel: string;
    organization: string | null;
    returnedAt: string | null;
    returnedBy: { name: string | null; role: string } | null;
    /** Who the resend goes back to, as a label: "SDAO", "College Dean". */
    role: string;
    comment: string | null;
    items: FixItem[];
    /** Flagged, but with nothing to edit here (General and the like). */
    notes: { label: string; comment: string | null }[];
    /** "Resend to SDAO": the step the document resumes at. */
    resendLabel: string;
    back: { href: string; title: string };
    draft: FixDraftValues | null;
    draftUrl: string;
};

function normalize(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }

    if (Array.isArray(value)) {
        return JSON.stringify(value.map(normalize));
    }

    if (typeof value === 'object') {
        return JSON.stringify(
            Object.keys(value as Record<string, unknown>)
                .sort()
                .map((key) => [
                    key,
                    normalize((value as Record<string, unknown>)[key]),
                ]),
        );
    }

    const text = String(value).trim();

    return /^-?\d+(\.\d+)?$/.test(text) ? String(Number(text)) : text;
}

/** Two values are the same when they read the same after trimming; "5000" and "5000.00" are the same. */
export function sameValue(a: unknown, b: unknown): boolean {
    return normalize(a) === normalize(b);
}

/** True when any field of `current` differs from the returned version in `baseline`. */
export function hasChanged(
    baseline: Record<string, unknown>,
    current: Record<string, unknown>,
): boolean {
    return Object.keys(baseline).some(
        (key) => !sameValue(baseline[key], current[key]),
    );
}

/** "Fix 2 more to resend." / "Everything is fixed." — and why the button is disabled, for screen readers too. */
export function progressText(fixed: number, total: number): string {
    const left = total - fixed;

    if (left <= 0) {
        return `${fixed} of ${total} fixed. Ready to resend.`;
    }

    return `${fixed} of ${total} fixed. Fix ${left} more to resend.`;
}

/** "3 items need your changes" / "1 item needs your changes". */
export function needText(total: number): string {
    return total === 1
        ? '1 item needs your changes'
        : `${total} items need your changes`;
}
