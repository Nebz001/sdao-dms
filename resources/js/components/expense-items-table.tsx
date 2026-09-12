type ExpenseItem = { material: string; quantity: string; unit_price: string };

type Props = {
    items: ExpenseItem[] | null;
    total: string | null;
    /** Legacy free-text value, shown only when there are no itemized rows. */
    legacyText: string | null;
};

function rowTotal(item: ExpenseItem): number {
    return (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0);
}

function money(amount: number): string {
    return amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/**
 * Read-only itemized Expenses table (client request, post-Part-2) — shared
 * by activity-proposals/show.tsx and review/activity-proposals/show.tsx,
 * which rendered this section identically. Falls back to the pre-existing
 * free-text `expenses` prose for proposals submitted before expense_items
 * existed — see App\Models\ActivityProposal's docblock.
 *
 * Rows are {material, quantity, unit_price} (Group D item 3, was
 * {label, amount}); the row total (quantity × unit_price) is computed here,
 * never stored, same precedent as the grand `total` prop.
 */
export default function ExpenseItemsTable({ items, total, legacyText }: Props) {
    if (items && items.length > 0) {
        return (
            <div>
                <p className="mb-1 font-medium">Expenses</p>
                <div className="overflow-hidden rounded-md border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/20 text-xs text-muted-foreground">
                                <th className="px-3 py-1.5 text-left font-medium">Material</th>
                                <th className="px-3 py-1.5 text-right font-medium">Qty</th>
                                <th className="px-3 py-1.5 text-right font-medium">Unit Price</th>
                                <th className="px-3 py-1.5 text-right font-medium">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((item, i) => (
                                <tr key={i} className="border-b last:border-b-0">
                                    <td className="px-3 py-1.5">{item.material}</td>
                                    <td className="px-3 py-1.5 text-right tabular-nums text-muted-foreground">{item.quantity}</td>
                                    <td className="px-3 py-1.5 text-right tabular-nums text-muted-foreground">₱{item.unit_price}</td>
                                    <td className="px-3 py-1.5 text-right tabular-nums text-muted-foreground">₱{money(rowTotal(item))}</td>
                                </tr>
                            ))}
                            <tr className="bg-muted/40 font-semibold">
                                <td className="px-3 py-1.5" colSpan={3}>
                                    Total
                                </td>
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
