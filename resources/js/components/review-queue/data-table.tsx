import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';

const HEAD = 'text-xs font-medium tracking-wide text-muted-foreground uppercase';

/** Every table's button column reads "Action", whatever the row buttons are. */
const ACTION_HEADER = 'Action';

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
    rowId,
    roomy = false,
}: {
    rows: T[];
    columns: DataColumn<T>[];
    rowKey: (row: T) => string | number;
    rowClassName?: (row: T) => string | undefined;
    /** Marks each row (data-row-id) so a link elsewhere on the page can scroll to and focus it. */
    rowId?: (row: T) => string | number;
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
                                <TableHead
                                    key={c.key}
                                    className={cn(HEAD, (c.align === 'right' || c.slot === 'action') && 'text-right')}
                                >
                                    {c.slot === 'action' ? ACTION_HEADER : c.header}
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody className={cn(roomy && '[&_td]:py-4')}>
                        {rows.map((row) => (
                            <TableRow
                                key={rowKey(row)}
                                data-row-id={rowId?.(row)}
                                tabIndex={rowId ? -1 : undefined}
                                className={cn(rowClassName?.(row), rowId && 'outline-none')}
                            >
                                {columns.map((c) => (
                                    <TableCell
                                        key={c.key}
                                        className={cn(
                                            'whitespace-normal',
                                            (c.align === 'right' || c.slot === 'action') && 'text-right',
                                            c.className,
                                        )}
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
                        data-row-id={rowId?.(row)}
                        tabIndex={rowId ? -1 : undefined}
                        className={cn('flex flex-col gap-3 rounded-lg border p-4', rowId && 'outline-none', rowClassName?.(row))}
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
