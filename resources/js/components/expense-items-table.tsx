type ExpenseItem = { label: string; amount: string };

type Props = {
    items: ExpenseItem[] | null;
    total: string | null;
    /** Legacy free-text value, shown only when there are no itemized rows. */
    legacyText: string | null;
};

/**
 * Read-only itemized Expenses table (client request, post-Part-2) — shared
 * by activity-proposals/show.tsx and review/activity-proposals/show.tsx,
 * which rendered this section identically. Falls back to the pre-existing
 * free-text `expenses` prose for proposals submitted before expense_items
 * existed — see App\Models\ActivityProposal's docblock.
 */
export default function ExpenseItemsTable({ items, total, legacyText }: Props) {
    if (items && items.length > 0) {
        return (
            <div>
                <p className="mb-1 font-medium">Expenses</p>
                <div className="overflow-hidden rounded-md border">
                    <table className="w-full text-sm">
                        <tbody>
                            {items.map((item, i) => (
                                <tr key={i} className="border-b last:border-b-0">
                                    <td className="px-3 py-1.5">{item.label}</td>
                                    <td className="px-3 py-1.5 text-right tabular-nums text-muted-foreground">{item.amount}</td>
                                </tr>
                            ))}
                            <tr className="bg-muted/40 font-semibold">
                                <td className="px-3 py-1.5">Total</td>
                                <td className="px-3 py-1.5 text-right tabular-nums">₱{total}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        );
    }

    if (legacyText) {
        return (
            <div>
                <p className="mb-1 font-medium">Expenses</p>
                <p className="whitespace-pre-wrap text-muted-foreground">{legacyText}</p>
            </div>
        );
    }

    return null;
}
