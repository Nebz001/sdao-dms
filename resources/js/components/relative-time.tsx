import { useRelativeTime } from '@/hooks/use-relative-time';

type Props = { dateString: string };

/**
 * Renders a live-ticking "5m ago" / "3h ago" string for `dateString` (see
 * useRelativeTime). Exists as its own component, rather than calling the
 * hook inline, because several call sites render this from inside a
 * `.map()` over a list — calling a hook there directly would violate the
 * rules of hooks the moment the list's length changes between renders.
 */
export function RelativeTime({ dateString }: Props) {
    return useRelativeTime(dateString);
}
