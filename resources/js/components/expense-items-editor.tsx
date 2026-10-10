import { CircleAlert, CircleCheck, Plus, X } from 'lucide-react';
import { AddonInput } from '@/components/form-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatPeso, parseAmount } from '@/lib/money';
import { cn } from '@/lib/utils';

export type ExpenseItem = { material: string; quantity: string; unit_price: string };

export const EMPTY_EXPENSE_ITEM: ExpenseItem = { material: '', quantity: '', unit_price: '' };

export function expenseRowTotal(item: ExpenseItem): number {
    return parseAmount(item.quantity) * parseAmount(item.unit_price);
}

export function expenseTotal(items: ExpenseItem[]): number {
    return items.reduce((sum, item) => sum + expenseRowTotal(item), 0);
}

type Props = {
    items: ExpenseItem[];
    onChange: (items: ExpenseItem[]) => void;
    errors: Record<string, string | undefined>;
    /** The proposed budget from step 1; null when there is none to compare with. */
    budget: number | null;
};

/**
 * Itemized expenses: Item, Qty, Unit price and a live row total, with a
 * running Total and a display-only budget line. Nothing here blocks a
 * submit; the server validates the rows and stays the source of truth for
 * every amount. Below the sm breakpoint each row stacks into a small card.
 * Field names are the ones the form always used:
 * expense_items[i][material|quantity|unit_price].
 */
export default function ExpenseItemsEditor({ items, onChange, errors, budget }: Props) {
    const total = expenseTotal(items);
    const columns = 'sm:grid-cols-[minmax(0,1fr)_5rem_8rem_6.5rem_2rem]';

    function update(index: number, patch: Partial<ExpenseItem>) {
        onChange(items.map((item, i) => (i === index ? { ...item, ...patch } : item)));
    }

    return (
        <div className="grid gap-3">
            <div className="overflow-hidden rounded-lg border">
                <div
                    aria-hidden
                    className={cn(
                        'hidden items-center gap-2 border-b bg-muted/40 px-3 py-2 text-xs font-medium tracking-wide text-muted-foreground uppercase sm:grid',
                        columns,
                    )}
                >
                    <span>Item</span>
                    <span>Qty</span>
                    <span>Unit price</span>
                    <span className="text-right">Total</span>
                    <span />
                </div>

                <ul className="divide-y">
                    {items.map((item, i) => {
                        const rowError =
                            errors[`expense_items.${i}.material`] ??
                            errors[`expense_items.${i}.quantity`] ??
                            errors[`expense_items.${i}.unit_price`];

                        return (
                            <li key={i} className="px-3 py-2.5">
                                <div className={cn('grid grid-cols-2 items-center gap-2', columns)}>
                                    <Input
                                        name={`expense_items[${i}][material]`}
                                        value={item.material}
                                        onChange={(e) => update(i, { material: e.target.value })}
                                        placeholder="e.g. Certificates"
                                        aria-label={`Item ${i + 1}`}
                                        aria-invalid={rowError ? true : undefined}
                                        className="col-span-2 sm:col-span-1"
                                    />
                                    <Input
                                        name={`expense_items[${i}][quantity]`}
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        inputMode="decimal"
                                        value={item.quantity}
                                        onChange={(e) => update(i, { quantity: e.target.value })}
                                        placeholder="0"
                                        aria-label={`Quantity for item ${i + 1}`}
                                        aria-invalid={rowError ? true : undefined}
                                    />
                                    <AddonInput
                                        name={`expense_items[${i}][unit_price]`}
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        inputMode="decimal"
                                        prefix="₱"
                                        value={item.unit_price}
                                        onChange={(e) => update(i, { unit_price: e.target.value })}
                                        placeholder="0.00"
                                        aria-label={`Unit price for item ${i + 1}`}
                                        invalid={Boolean(rowError)}
                                    />
                                    <p className="col-span-2 flex items-center justify-between text-sm tabular-nums sm:col-span-1 sm:block sm:text-right">
                                        <span className="text-xs text-muted-foreground sm:hidden">Line total</span>
                                        <span
                                            className={cn(
                                                'font-semibold',
                                                expenseRowTotal(item) === 0 && 'font-normal text-muted-foreground',
                                            )}
                                        >
                                            {formatPeso(expenseRowTotal(item))}
                                        </span>
                                    </p>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="col-span-2 size-8 justify-self-end sm:col-span-1"
                                        disabled={items.length === 1}
                                        aria-label={`Remove item ${i + 1}`}
                                        onClick={() => onChange(items.filter((_, idx) => idx !== i))}
                                    >
                                        <X aria-hidden />
                                    </Button>
                                </div>
                                {rowError && <p className="mt-1.5 text-sm text-destructive">{rowError}</p>}
                            </li>
                        );
                    })}
                </ul>

                <div className="flex items-center justify-between gap-3 border-t px-3 py-3">
                    <Button
                        type="button"
                        variant="link"
                        size="sm"
                        className="h-auto p-0"
                        onClick={() => onChange([...items, { ...EMPTY_EXPENSE_ITEM }])}
                    >
                        <Plus aria-hidden />
                        Add item
                    </Button>
                    <p className="flex items-baseline gap-2" aria-live="polite">
                        <span className="text-sm text-muted-foreground">Total</span>
                        <span className="text-xl font-bold tabular-nums">{formatPeso(total)}</span>
                    </p>
                </div>
            </div>

            {errors.expense_items && <p className="text-sm text-destructive">{errors.expense_items}</p>}

            {budget !== null && (
                <p
                    className={cn(
                        'flex items-start gap-2 text-sm',
                        total > budget ? 'text-warning-foreground' : 'text-muted-foreground',
                    )}
                    aria-live="polite"
                >
                    {total > budget ? (
                        <>
                            <CircleAlert aria-hidden className="mt-0.5 size-4 shrink-0 text-warning-foreground" />
                            <span>
                                <strong className="font-semibold">Over budget by {formatPeso(total - budget)}.</strong>{' '}
                                Your proposed budget is {formatPeso(budget)}.
                            </span>
                        </>
                    ) : (
                        <>
                            <CircleCheck aria-hidden className="mt-0.5 size-4 shrink-0 text-success-foreground" />
                            <span>
                                <strong className="font-semibold text-success-foreground">Within budget.</strong>{' '}
                                Your proposed budget is {formatPeso(budget)}.
                            </span>
                        </>
                    )}
                </p>
            )}
        </div>
    );
}
