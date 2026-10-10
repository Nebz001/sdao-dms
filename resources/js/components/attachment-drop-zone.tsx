import { FileCheck2, ImageIcon, Upload, X } from 'lucide-react';
import { useRef, useState } from 'react';
import type { ChangeEvent, DragEvent } from 'react';
import { AttachmentPreviewLink } from '@/components/attachment-preview';
import type {
    AttachmentSlotDef,
    ExistingAttachment,
} from '@/components/attachment-slot-field';
import { formatFileSize } from '@/components/document-view/format';
import { FormField } from '@/components/form-shell';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Props = {
    slot: AttachmentSlotDef;
    /** Overrides slot.label, e.g. to drop the instruction the registry label carries. */
    label?: string;
    /** Short guidance under the label. */
    helper?: string;
    existing?: ExistingAttachment[];
    error?: string;
};

function formatMaxSize(maxKb: number): string {
    const mb = maxKb / 1024;

    return `${Number.isInteger(mb) ? mb : mb.toFixed(1)} MB`;
}

/** ".pdf,.jpg" -> ["pdf", "jpg"] */
function acceptedExtensions(accept: string): string[] {
    return accept
        .split(',')
        .map((part) => part.trim().replace(/^\./, '').toLowerCase())
        .filter(Boolean);
}

/** Writes `files` back into the real <input>, so the surrounding <Form> submits them. */
function syncInput(input: HTMLInputElement | null, files: File[]): void {
    if (!input || typeof DataTransfer === 'undefined') {
        return;
    }

    const transfer = new DataTransfer();
    files.forEach((file) => transfer.items.add(file));
    input.files = transfer.files;
}

/**
 * One attachment slot as a drop zone: icon, "Drop a file here", the size
 * limit and a "Choose file" button. A chosen file turns the zone green with
 * its name, size and a Remove button. The real <input type="file"> stays in
 * the form (visually hidden), so the file still submits natively with the
 * rest of the fields; the button is the keyboard path for what drag and drop
 * does with a mouse. A file that is too large, or of a type the slot does not
 * accept, is refused on selection, before any upload starts.
 */
export default function AttachmentDropZone({
    slot,
    label,
    helper,
    existing = [],
    error,
}: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [files, setFiles] = useState<File[]>([]);
    const [dragging, setDragging] = useState(false);
    const [localError, setLocalError] = useState<string | null>(null);

    const fieldName = slot.multiple
        ? `attachments[${slot.key}][]`
        : `attachments[${slot.key}]`;
    const isPhotos = slot.multiple;
    const Icon = isPhotos ? ImageIcon : Upload;
    const extensions = acceptedExtensions(slot.accept);
    const shownError = localError ?? error;
    const noun = slot.multiple ? 'files' : 'a file';

    function take(picked: File[]) {
        const maxBytes = slot.max_kb * 1024;
        const tooLarge = picked.find((file) => file.size > maxBytes);

        if (tooLarge) {
            setLocalError(
                `"${tooLarge.name}" is too large. This field accepts files up to ${formatMaxSize(slot.max_kb)}.`,
            );

            return false;
        }

        const wrongType = picked.find(
            (file) =>
                extensions.length > 0 &&
                !extensions.includes(
                    file.name.split('.').pop()?.toLowerCase() ?? '',
                ),
        );

        if (wrongType) {
            setLocalError(
                `"${wrongType.name}" is not an accepted file type. Use ${extensions.join(', ').toUpperCase()}.`,
            );

            return false;
        }

        const next = slot.multiple ? [...files, ...picked] : picked.slice(0, 1);

        setLocalError(null);
        setFiles(next);
        syncInput(inputRef.current, next);

        return true;
    }

    function handleChange(event: ChangeEvent<HTMLInputElement>) {
        const picked = Array.from(event.target.files ?? []);

        if (picked.length === 0) {
            return;
        }

        if (!take(picked) && inputRef.current) {
            // Refused: put the input back to what was already accepted.
            syncInput(inputRef.current, files);

            if (files.length === 0) {
                inputRef.current.value = '';
            }
        }
    }

    function handleDrop(event: DragEvent<HTMLDivElement>) {
        event.preventDefault();
        setDragging(false);
        take(Array.from(event.dataTransfer.files));
    }

    function remove(index: number) {
        const next = files.filter((_, i) => i !== index);

        setFiles(next);
        setLocalError(null);
        syncInput(inputRef.current, next);

        if (next.length === 0 && inputRef.current) {
            inputRef.current.value = '';
        }
    }

    const hasFile = files.length > 0 || existing.length > 0;
    const showZone = slot.multiple || files.length === 0;

    return (
        <FormField
            id={slot.key}
            label={label ?? slot.label}
            optional={!slot.required}
            helper={helper}
            helperAbove
            error={shownError}
        >
            {(aria) => (
                <div className="grid gap-2">
                    {existing.map((file) => (
                        <div
                            key={file.id}
                            className="flex items-center gap-3 rounded-lg border border-success/50 bg-success/10 px-3.5 py-3"
                        >
                            <FileCheck2
                                aria-hidden
                                className="size-5 shrink-0 text-success-foreground"
                            />
                            <div className="min-w-0 flex-1 text-sm">
                                <AttachmentPreviewLink file={file} />
                                <p className="text-xs text-muted-foreground">
                                    {formatFileSize(file.size ?? null)}
                                    {file.size ? ' · ' : ''}Already uploaded
                                </p>
                            </div>
                        </div>
                    ))}

                    {files.map((file, index) => (
                        <div
                            key={`${file.name}-${index}`}
                            className="flex items-center gap-3 rounded-lg border border-success/50 bg-success/10 px-3.5 py-3"
                        >
                            <span
                                aria-hidden
                                className="flex size-9 shrink-0 items-center justify-center rounded-md bg-success/15 text-success-foreground"
                            >
                                <FileCheck2 className="size-4" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium">
                                    {file.name}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {formatFileSize(file.size)} · Ready to upload
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => remove(index)}
                                aria-label={`Remove ${file.name}`}
                            >
                                <X aria-hidden />
                                Remove
                            </Button>
                        </div>
                    ))}

                    {showZone && (
                        <div
                            onDragOver={(event) => {
                                event.preventDefault();
                                setDragging(true);
                            }}
                            onDragLeave={() => setDragging(false)}
                            onDrop={handleDrop}
                            className={cn(
                                'flex flex-wrap items-center gap-3 rounded-lg border border-dashed px-3.5 py-3 transition-colors',
                                dragging
                                    ? 'border-primary-text bg-primary/10'
                                    : 'border-input bg-muted/30',
                                shownError && 'border-destructive',
                            )}
                        >
                            <span
                                aria-hidden
                                className="flex size-9 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground"
                            >
                                <Icon className="size-4" />
                            </span>
                            <div className="min-w-0 flex-1 basis-40">
                                <p className="text-sm font-medium">
                                    {slot.multiple
                                        ? hasFile
                                            ? 'Drop more files here'
                                            : 'Drop files here'
                                        : existing.length > 0
                                          ? 'Drop a file here to replace it'
                                          : 'Drop a file here'}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    or choose {noun} from your computer. Up to{' '}
                                    {formatMaxSize(slot.max_kb)}
                                    {slot.multiple ? ' each' : ''}.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => inputRef.current?.click()}
                                aria-describedby={aria['aria-describedby']}
                                aria-invalid={aria['aria-invalid']}
                            >
                                {slot.multiple ? 'Choose files' : 'Choose file'}
                            </Button>
                        </div>
                    )}

                    <input
                        ref={inputRef}
                        id={slot.key}
                        type="file"
                        name={fieldName}
                        accept={slot.accept}
                        multiple={slot.multiple}
                        onChange={handleChange}
                        className="sr-only"
                        tabIndex={-1}
                    />
                </div>
            )}
        </FormField>
    );
}
