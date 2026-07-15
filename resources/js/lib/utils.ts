import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

/**
 * Formats a snake_case document status for display, e.g. "in_review" -> "In Review".
 */
export function statusLabel(status: string): string {
    return status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

/**
 * Formats a "YYYY-MM-DD" date string for display, e.g. "Jul 13, 2026".
 * Parses by splitting rather than `new Date(date)` to avoid the UTC-midnight
 * timezone shift that can roll the date back a day in negative-UTC-offset zones.
 */
export function formatCalendarDate(date: string): string {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(year, month - 1, day).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

/**
 * Formats a "HH:MM[:SS]"-"HH:MM[:SS]" time pair for display,
 * e.g. "2:00 PM – 3:00 PM".
 */
export function formatTimeRange(start: string, end: string): string {
    const formatTime = (time: string) => {
        const [hours, minutes] = time.split(':').map(Number);

        return new Date(1970, 0, 1, hours, minutes).toLocaleTimeString(undefined, {
            hour: 'numeric',
            minute: '2-digit',
        });
    };

    return `${formatTime(start)} – ${formatTime(end)}`;
}
