/**
 * Display formatting for the shared document page. Every value arrives from the
 * server raw (ISO dates, digit strings, byte counts); formatting happens once,
 * here, so no form type formats its own.
 */

const LONG_DATE = new Intl.DateTimeFormat('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
const SHORT_DATE = new Intl.DateTimeFormat('en-US', { year: 'numeric', month: 'numeric', day: 'numeric' });
const TIME = new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit' });

/** A date-only string ("2024-08-07") must not shift a day when read in a negative-offset timezone. */
function parse(value: string): Date {
    return /^\d{4}-\d{2}-\d{2}$/.test(value) ? new Date(`${value}T00:00:00`) : new Date(value);
}

/** "August 7, 2024", or null when the value is empty or unparseable. */
export function formatLongDate(value: string | null | undefined): string | null {
    if (!value) {
        return null;
    }

    const date = parse(value);

    return Number.isNaN(date.getTime()) ? null : LONG_DATE.format(date);
}

/** "9/12/2026", for compact meta chips. */
export function formatShortDate(value: string | null | undefined): string | null {
    if (!value) {
        return null;
    }

    const date = parse(value);

    return Number.isNaN(date.getTime()) ? null : SHORT_DATE.format(date);
}

/** "September 12, 2026 at 12:02 AM". */
export function formatDateTime(value: string | null | undefined): string | null {
    if (!value) {
        return null;
    }

    const date = parse(value);

    return Number.isNaN(date.getTime()) ? null : `${LONG_DATE.format(date)} at ${TIME.format(date)}`;
}

/** "0917 523 6732" for an 11-digit mobile number; anything else is returned as typed. */
export function formatPhone(value: string | null | undefined): string | null {
    if (!value) {
        return null;
    }

    const digits = value.replace(/[\s-]/g, '');

    // Only a local 11-digit mobile number ("09175236732"); an international
    // number or a landline keeps the spacing it was typed with.
    if (/^0\d{10}$/.test(digits)) {
        return `${digits.slice(0, 4)} ${digits.slice(4, 7)} ${digits.slice(7)}`;
    }

    return value;
}

/** "1.1 MB", "240 KB", or null when the size is unknown. */
export function formatFileSize(bytes: number | null | undefined): string | null {
    if (bytes === null || bytes === undefined) {
        return null;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1).replace(/\.0$/, '')} MB`;
}

/** "1 day", "3 days", "Under a day". */
export function formatDuration(days: number | null | undefined): string | null {
    if (days === null || days === undefined) {
        return null;
    }

    if (days < 1) {
        return 'Under a day';
    }

    return days === 1 ? '1 day' : `${days} days`;
}

/** "₱1,500.00" from a stored amount string. */
export function formatPeso(value: string | number | null | undefined): string | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const amount = Number(String(value).replace(/,/g, ''));

    if (Number.isNaN(amount)) {
        return `₱${value}`;
    }

    return `₱${amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}
