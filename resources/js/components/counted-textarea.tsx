import { useId, useState } from 'react';
import type { ChangeEvent, ComponentProps } from 'react';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

/** Share of the cap at which the count turns into a warning. */
const WARN_AT = 0.9;

type Tone = 'ok' | 'near' | 'full';

function toneFor(length: number, max: number): Tone {
    if (length >= max) {
        return 'full';
    }

    return length >= max * WARN_AT ? 'near' : 'ok';
}

const STATUS_TEXT: Record<Tone, string> = {
    ok: '',
    near: 'Approaching the limit.',
    full: 'Limit reached. Nothing more can be added.',
};

type CountedTextareaProps = Omit<ComponentProps<typeof Textarea>, 'maxLength'> & {
    /** The server's cap. Input stops here, and the count shows against it. */
    maxLength: number;
};

/**
 * A Textarea that shows a live "n / max" count, warns as the cap approaches
 * and stops input at it, so a value can never silently outgrow what the server
 * accepts. Works controlled (`value`) or uncontrolled (`defaultValue`, with a
 * `ref`). The browser's own maxlength is set too; the change handler trims as
 * well, because a paste or an autofill can still slip past it.
 *
 * The count is not a live region (it would announce on every keystroke); a
 * separate status is, and it only changes when the tone does.
 */
export function CountedTextarea({
    maxLength,
    value,
    defaultValue,
    onChange,
    className,
    id,
    'aria-describedby': describedBy,
    ...props
}: CountedTextareaProps) {
    const fallbackId = useId();
    const countId = `${id ?? fallbackId}-count`;
    const [typed, setTyped] = useState(String(defaultValue ?? '').length);
    const length = value !== undefined ? String(value).length : typed;
    const tone = toneFor(length, maxLength);

    function handleChange(event: ChangeEvent<HTMLTextAreaElement>) {
        if (event.target.value.length > maxLength) {
            event.target.value = event.target.value.slice(0, maxLength);
        }

        setTyped(event.target.value.length);
        onChange?.(event);
    }

    return (
        <div className="flex flex-col gap-1.5">
            <Textarea
                id={id}
                value={value}
                defaultValue={defaultValue}
                maxLength={maxLength}
                onChange={handleChange}
                aria-describedby={[describedBy, countId].filter(Boolean).join(' ')}
                className={className}
                {...props}
            />
            <p
                id={countId}
                data-tone={tone}
                className={cn(
                    'self-end text-xs tabular-nums',
                    tone === 'ok' && 'text-muted-foreground',
                    tone === 'near' && 'text-warning-foreground',
                    tone === 'full' && 'font-medium text-destructive',
                )}
            >
                {length.toLocaleString('en-US')} / {maxLength.toLocaleString('en-US')}
                {tone !== 'ok' && ` · ${(maxLength - length).toLocaleString('en-US')} left`}
            </p>
            <span role="status" className="sr-only">
                {STATUS_TEXT[tone]}
            </span>
        </div>
    );
}
