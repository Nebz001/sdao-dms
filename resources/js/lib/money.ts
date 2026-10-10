/** 5000 -> "₱5,000.00". Display only; the server stays the source of truth for every amount. */
export function formatPeso(amount: number): string {
    return `₱${amount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

/** A decimal string from the form or the server ("5000", "12.5", "") as a number; blank or junk is 0. */
export function parseAmount(value: string | number | null | undefined): number {
    const parsed = typeof value === 'number' ? value : parseFloat(value ?? '');

    return Number.isFinite(parsed) ? parsed : 0;
}
