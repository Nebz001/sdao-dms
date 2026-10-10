import { Check } from 'lucide-react';
import { useRef, useState } from 'react';
import type { ChangeEvent } from 'react';
import { AttachmentPreviewLink } from '@/components/attachment-preview';
import type { AttachmentSlotDef, ExistingAttachment } from '@/components/attachment-slot-field';
import { formatFileSize } from '@/components/document-view/format';
import FlaggedSectionWrapper from '@/components/flagged-section-wrapper';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { FlaggedRevisionProps } from '@/types';

function maxSizeLabel(kb: number): string {
    const mb = kb / 1024;

    return `${Number.isInteger(mb) ? mb : mb.toFixed(1)} MB`;
}

/** ".pdf,.jpg,.png" -> ["pdf", "jpg", "png"] */
function extensionsOf(accept: string): string[] {
    return accept
        .split(',')
        .map((part) => part.trim().replace(/^\./, '').toLowerCase())
        .filter(Boolean);
}

/** How many requirements have a file, either just chosen or already on the document. */
export function uploadedCount(
    slots: AttachmentSlotDef[],
    files: Record<string, File | null>,
    existing: Record<string, ExistingAttachment[]> = {},
): number {
    return slots.filter((slot) => files[slot.key] || (existing[slot.key]?.length ?? 0) > 0).length;
}

/** "2 of 6 uploaded · PDF up to 10 MB each", from the real slots and their real limits. */
export function requirementsSummary(
    slots: AttachmentSlotDef[],
    files: Record<string, File | null>,
    existing: Record<string, ExistingAttachment[]> = {},
): string {
    const extensions = [...new Set(slots.flatMap((slot) => extensionsOf(slot.accept)))];
    const imageTypes = ['jpg', 'jpeg', 'png'];
    const onlyPdfAndImages = extensions.every((e) => e === 'pdf' || imageTypes.includes(e));
    const kind =
        extensions.length === 1 && extensions[0] === 'pdf'
            ? 'PDF'
            : onlyPdfAndImages
              ? 'PDF or image'
              : extensions.map((e) => e.toUpperCase()).join(', ');
    const limit = maxSizeLabel(Math.min(...slots.map((slot) => slot.max_kb)));

    return `${uploadedCount(slots, files, existing)} of ${slots.length} uploaded · ${kind} up to ${limit} each`;
}

type Props = {
    slots: AttachmentSlotDef[];
    /** The files chosen in this session, by slot key. */
    files: Record<string, File | null>;
    onFileChange: (key: string, file: File | null) => void;
    /** Files already on the document (edit and resubmit). */
    existing?: Record<string, ExistingAttachment[]>;
    /** Server errors, keyed `attachments.<slot key>`. */
    errors?: Record<string, string | undefined>;
    /** A short line under a requirement's name, by slot key. */
    helpers?: Record<string, string>;
    /** Present on edit and resubmit: highlights the rows the approver flagged. */
    flags?: FlaggedRevisionProps;
    /**
     * Give the file inputs their form field names, for a page that submits
     * through a native <Form>. Without it the chosen files travel in page
     * state instead (registration create, which posts a plain payload).
     */
    native?: boolean;
};

/**
 * The requirements of a registration or renewal as one numbered list: a
 * number circle, the requirement's name, an optional helper line and an
 * Upload button. A row with a file turns green (check circle, file name,
 * "Replace"). The slots and their rules come from the AttachmentSlots
 * registry; this only draws them. A file over the slot's limit, or of a type
 * the slot does not accept, is refused on selection with a message on its row.
 */
export default function AttachmentRequirements({
    slots,
    files,
    onFileChange,
    existing = {},
    errors = {},
    helpers = {},
    flags,
    native = false,
}: Props) {
    return (
        <ul className="divide-y overflow-hidden rounded-lg border">
            {slots.map((slot, index) => (
                <RequirementRow
                    key={slot.key}
                    slot={slot}
                    number={index + 1}
                    file={files[slot.key] ?? null}
                    existing={existing[slot.key] ?? []}
                    error={errors[`attachments.${slot.key}`]}
                    helper={helpers[slot.key]}
                    flags={flags}
                    native={native}
                    onFileChange={(file) => onFileChange(slot.key, file)}
                />
            ))}
        </ul>
    );
}

function RequirementRow({
    slot,
    number,
    file,
    existing,
    error,
    helper,
    flags,
    native,
    onFileChange,
}: {
    slot: AttachmentSlotDef;
    number: number;
    file: File | null;
    existing: ExistingAttachment[];
    error?: string;
    helper?: string;
    flags?: FlaggedRevisionProps;
    native: boolean;
    onFileChange: (file: File | null) => void;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [localError, setLocalError] = useState<string | null>(null);
    const done = file !== null || existing.length > 0;
    const shownError = localError ?? error;
    const describedBy = [helper ? `${slot.key}-helper` : null, shownError ? `${slot.key}-error` : null]
        .filter(Boolean)
        .join(' ');

    function handleChange(event: ChangeEvent<HTMLInputElement>) {
        const picked = event.target.files?.[0];

        if (!picked) {
            return;
        }

        const extensions = extensionsOf(slot.accept);
        const extension = picked.name.split('.').pop()?.toLowerCase() ?? '';
        let problem: string | null = null;

        if (picked.size > slot.max_kb * 1024) {
            problem = `"${picked.name}" is too large. This requirement accepts files up to ${maxSizeLabel(slot.max_kb)}.`;
        } else if (extensions.length > 0 && !extensions.includes(extension)) {
            problem = `"${picked.name}" is not an accepted file type. Use ${extensions.join(', ').toUpperCase()}.`;
        }

        if (problem) {
            setLocalError(problem);
            // Put the input back to what was accepted before, so the refused file cannot be submitted.
            event.target.value = '';
            onFileChange(null);

            return;
        }

        setLocalError(null);
        onFileChange(picked);
    }

    const row = (
        <div className={cn('flex flex-wrap items-center gap-3 px-3.5 py-3', done && 'bg-success/10')}>
            <span
                aria-hidden
                className={cn(
                    'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                    done ? 'bg-success text-white' : 'bg-muted text-muted-foreground',
                )}
            >
                {done ? <Check className="size-4" strokeWidth={3} /> : number}
            </span>

            <div className="min-w-0 flex-1 basis-48 space-y-0.5">
                <p className="text-sm leading-snug font-semibold">
                    {slot.label}
                    {!slot.required && (
                        <span className="ml-1.5 text-xs font-normal text-muted-foreground">optional</span>
                    )}
                    {done && <span className="sr-only"> (uploaded)</span>}
                </p>
                {file ? (
                    <p className="truncate text-xs font-medium text-success-foreground">
                        {file.name}
                        <span className="font-normal text-muted-foreground"> · {formatFileSize(file.size)}</span>
                    </p>
                ) : existing.length > 0 ? (
                    <p className="truncate text-xs font-medium text-success-foreground">
                        <AttachmentPreviewLink file={existing[0]} label={slot.label} />
                    </p>
                ) : null}
                {helper && (
                    <p id={`${slot.key}-helper`} className="text-xs text-muted-foreground">
                        {helper}
                    </p>
                )}
            </div>

            <input
                ref={inputRef}
                id={`attachment-${slot.key}`}
                type="file"
                name={native ? `attachments[${slot.key}]` : undefined}
                accept={slot.accept}
                onChange={handleChange}
                className="sr-only"
                tabIndex={-1}
                aria-hidden
            />
            <Button
                type="button"
                variant={done ? 'ghost' : 'outline'}
                size="sm"
                onClick={() => inputRef.current?.click()}
                aria-label={`${done ? 'Replace' : 'Upload'} ${slot.label}`}
                aria-describedby={describedBy || undefined}
                aria-invalid={shownError ? true : undefined}
            >
                {done ? 'Replace' : 'Upload'}
            </Button>

            {shownError && (
                <p id={`${slot.key}-error`} className="basis-full text-sm text-destructive">
                    {shownError}
                </p>
            )}
        </div>
    );

    return (
        <li>
            {flags ? (
                <FlaggedSectionWrapper
                    sectionKey={slot.key}
                    flagged={flags.flaggedSections}
                    comment={flags.flaggedComment}
                    sectionComment={flags.flaggedSectionComments[slot.key]}
                    className={flags.flaggedSections.includes(slot.key) ? 'm-1.5 p-1.5' : undefined}
                >
                    {row}
                </FlaggedSectionWrapper>
            ) : (
                row
            )}
        </li>
    );
}
