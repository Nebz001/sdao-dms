import { splitAccountName } from '@/lib/account-name';
import { cn } from '@/lib/utils';

/**
 * An account name for a list row or card: the real name on the first line in
 * the caller's name style, and, when the stored name ends in a role group like
 * "(Associate Dean, Medical Technology)", that role as a small muted second
 * line. A plain name stays one line. The full stored name is the hover title.
 *
 * Both lines wrap (never widen the row), so it is safe in the stacked card
 * layouts below `md`.
 */
export default function AccountName({
    name,
    className,
    nameClassName,
}: {
    /** The stored name, exactly as it comes from the database. */
    name: string;
    className?: string;
    /** Type style of the name line; defaults to the bold row-name style. */
    nameClassName?: string;
}) {
    const parts = splitAccountName(name);

    return (
        <span className={cn('flex min-w-0 flex-col', className)} title={parts.detail ? name : undefined}>
            <span className={cn('font-semibold break-words', nameClassName)}>{parts.name}</span>
            {parts.detail && (
                <span className="text-xs leading-snug font-normal break-words text-muted-foreground">{parts.detail}</span>
            )}
        </span>
    );
}
