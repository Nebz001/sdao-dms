import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';

const HEAD = 'text-xs font-medium tracking-wide text-muted-foreground uppercase';

export type DataColumn<T> = {
    key: string;
    header: string;
    cell: (row: T) => ReactNode;
    /** Right-aligns the column in the table. */
    align?: 'right';
    /**
     * Where the column goes in the stacked card layout: "title" top left,
     * "badge" top right, "action" at the bottom. Unset columns become
     * label and value rows.
     */
    slot?: 'title' | 'badge' | 'action';
    className?: string;
};

/**
 * One column definition rendered twice: a table from md up, and below that a
 * list of stacked cards, so a narrow screen never scrolls sideways.
 */
export default function DataTable<T>({
    rows,
    columns,
    rowKey,
    rowClassName,
    roomy = false,
}: {
    rows: T[];
    columns: DataColumn<T>[];
    rowKey: (row: T) => string | number;
    rowClassName?: (row: T) => string | undefined;
    /** Taller table rows, for tables with two-line cells. */
    roomy?: boolean;
}) {
    const titleColumn = columns.find((c) => c.slot === 'title');
    const badgeColumn = columns.find((c) => c.slot === 'badge');
    const actionColumn = columns.find((c) => c.slot === 'action');
    const detailColumns = columns.filter((c) => !c.slot);

    return (
        <>
            <div className="hidden md:block">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {columns.map((c) => (
                                <TableHead key={c.key} className={cn(HEAD, c.align === 'right' && 'text-right')}>
                                    {c.slot === 'action' ? <span className="sr-only">{c.header}</span> : c.header}
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody className={cn(roomy && '[&_td]:py-4')}>
                        {rows.map((row) => (
                            <TableRow key={rowKey(row)} className={rowClassName?.(row)}>
                                {columns.map((c) => (
                                    <TableCell
                                        key={c.key}
                                        className={cn('whitespace-normal', c.align === 'right' && 'text-right', c.className)}
                                    >
                                        {c.cell(row)}
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            <ul className="flex flex-col gap-3 md:hidden">
                {rows.map((row) => (
                    <li
                        key={rowKey(row)}
                        className={cn('flex flex-col gap-3 rounded-lg border p-4', rowClassName?.(row))}
                    >
                        {(titleColumn || badgeColumn) && (
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0 font-semibold break-words">{titleColumn?.cell(row)}</div>
                                {badgeColumn && <div className="shrink-0">{badgeColumn.cell(row)}</div>}
                            </div>
                        )}
                        {detailColumns.length > 0 && (
                            <dl className="grid grid-cols-[auto_1fr] items-baseline gap-x-4 gap-y-1.5 text-sm">
                                {detailColumns.map((c) => (
                                    <div key={c.key} className="contents">
                                        <dt className="text-muted-foreground">{c.header}</dt>
                                        <dd className="min-w-0">{c.cell(row)}</dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                        {actionColumn && (
                            <div className="[&_a]:w-full [&_button]:w-full">{actionColumn.cell(row)}</div>
                        )}
                    </li>
                ))}
            </ul>
        </>
    );
}

/** The secondary "View" button used at the end of table rows (same as Recently decided). */
export function RowViewButton({ href, label }: { href: string | null; label: string }) {
    if (href === null) {
        return (
            <Button size="sm" variant="secondary" disabled>
                View<span className="sr-only"> {label}</span>
            </Button>
        );
    }

    return (
        <Button asChild size="sm" variant="secondary">
            <Link href={href}>
                View<span className="sr-only"> {label}</span>
            </Link>
        </Button>
    );
}
