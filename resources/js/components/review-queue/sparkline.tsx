import { cn } from '@/lib/utils';

/**
 * A tiny bar sparkline. The last bar is the current period and gets the
 * full-strength color. `label` is the text alternative for the whole chart.
 */
export default function Sparkline({ values, label }: { values: number[]; label: string }) {
    const max = Math.max(1, ...values);

    return (
        <div role="img" aria-label={label} className="flex h-10 items-end gap-1">
            {values.map((value, index) => (
                <div
                    key={index}
                    className={cn(
                        'min-h-0.5 flex-1 rounded-[2px]',
                        index === values.length - 1 ? 'bg-primary-text' : 'bg-primary-text/35',
                    )}
                    style={{ height: `${Math.max(6, (value / max) * 100)}%` }}
                />
            ))}
        </div>
    );
}
