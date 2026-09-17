import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

const MAX_WIDTHS = {
    xl: 'max-w-xl',
    '2xl': 'max-w-2xl',
    '3xl': 'max-w-3xl',
} as const;

/**
 * Centers narrower page content (forms, document views) inside the app
 * layout's already-centered max-w-7xl column. Without this, a block child
 * with only a max-w-* class left-aligns inside that column instead of
 * centering, which reads as hugging the sidebar on wide screens.
 */
export default function CenteredContainer({
    maxWidth = '2xl',
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement> & { maxWidth?: keyof typeof MAX_WIDTHS }) {
    return (
        <div
            {...props}
            className={cn('mx-auto w-full', MAX_WIDTHS[maxWidth], className)}
        />
    );
}
