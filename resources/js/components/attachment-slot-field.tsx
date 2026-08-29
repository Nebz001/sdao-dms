import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type AttachmentSlotDef = {
    key: string;
    label: string;
    required: boolean;
    multiple: boolean;
    accept: string;
    max_kb: number;
};

export type ExistingAttachment = {
    id: number;
    original_filename: string;
    download_url: string;
};

type Props = {
    slot: AttachmentSlotDef;
    existing?: ExistingAttachment[];
    error?: string;
    /**
     * Only needed on the one page that doesn't submit via <Form> (registration
     * create, which builds a plain JS payload for router.post instead of
     * relying on native form-field serialization). When provided, the input
     * is driven by this callback instead of its `name` attribute.
     */
    onFilesChange?: (files: FileList | null) => void;
};

/** "10240 KB" -> "10 MB" — slot.max_kb is always a whole number of MB in practice. */
function formatMaxSize(maxKb: number): string {
    const mb = maxKb / 1024;

    return `${Number.isInteger(mb) ? mb : mb.toFixed(1)} MB`;
}

/**
 * Phase 2 item 8, Mode A — one file-input field for a bundled attachment
 * slot, rendered inside the form's existing <Form>/native form so the file
 * submits together with the rest of the fields. A slot with existing files
 * (edit/resubmit) isn't forced to be re-selected; a fresh selection replaces
 * a single-file slot, or adds to a multi-file one.
 */
export default function AttachmentSlotField({ slot, existing = [], error, onFilesChange }: Props) {
    const fieldName = slot.multiple ? `attachments[${slot.key}][]` : `attachments[${slot.key}]`;
    const isNativeRequired = slot.required && existing.length === 0;
    const inputRef = useRef<HTMLInputElement>(null);
    const [sizeError, setSizeError] = useState<string | null>(null);

    /**
     * Rejects an oversized file at selection time, before any upload starts.
     * Previously the only size check was server-side, so a large file had to
     * fully upload (and, past PHP's post_max_size ceiling, needed a repeat
     * submit click before the failure was even reported — see UploadLimits)
     * before the user learned anything was wrong.
     */
    function handleChange(event: React.ChangeEvent<HTMLInputElement>) {
        const files = event.target.files;
        const maxBytes = slot.max_kb * 1024;
        const oversized = files ? Array.from(files).find((file) => file.size > maxBytes) : undefined;

        if (oversized) {
            setSizeError(`"${oversized.name}" is too large — this field accepts files up to ${formatMaxSize(slot.max_kb)}.`);

            if (inputRef.current) {
                inputRef.current.value = '';
            }

            onFilesChange?.(null);

            return;
        }

        setSizeError(null);
        onFilesChange?.(files);
    }

    return (
        <div className="grid gap-2">
            <Label htmlFor={slot.key}>
                {slot.label}
                {!slot.required && <span className="ml-1 font-normal text-muted-foreground">(optional)</span>}
            </Label>

            {existing.length > 0 && (
                <ul className="space-y-1">
                    {existing.map((file) => (
                        <li key={file.id} className="text-sm">
                            <a
                                href={file.download_url}
                                className="text-primary underline underline-offset-4"
                            >
                                {file.original_filename}
                            </a>
                        </li>
                    ))}
                </ul>
            )}

            <Input
                ref={inputRef}
                id={slot.key}
                type="file"
                name={onFilesChange ? undefined : fieldName}
                accept={slot.accept}
                multiple={slot.multiple}
                required={isNativeRequired}
                onChange={handleChange}
            />
            <p className="text-xs text-muted-foreground">
                {existing.length > 0 && (slot.multiple ? 'Selecting files adds to the ones above. ' : 'Selecting a file replaces the one above. ')}
                Max {formatMaxSize(slot.max_kb)} per file.
            </p>
            <InputError message={sizeError ?? error} />
        </div>
    );
}
