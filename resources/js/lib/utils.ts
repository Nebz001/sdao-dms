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
 * Formats an ISO timestamp as a short relative time for a live activity
 * feed, e.g. "5m ago", "3h ago", "2d ago" — falling back to an absolute date
 * past 30 days, where "relative" stops being useful at a glance.
 */
export function formatRelativeTime(dateString: string): string {
    const date = new Date(dateString);
    const diffMinutes = Math.round((Date.now() - date.getTime()) / 60000);

    if (diffMinutes < 1) {
        return 'just now';
    }

    if (diffMinutes < 60) {
        return `${diffMinutes}m ago`;
    }

    const diffHours = Math.round(diffMinutes / 60);

    if (diffHours < 24) {
        return `${diffHours}h ago`;
    }

    const diffDays = Math.round(diffHours / 24);

    if (diffDays < 30) {
        return `${diffDays}d ago`;
    }

    return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
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
