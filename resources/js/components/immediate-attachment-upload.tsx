import { useState } from 'react';
import type { AttachmentSlotDef, ExistingAttachment } from '@/components/attachment-slot-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import * as attachments from '@/routes/attachments';

type Props = {
    documentId: number;
    slot: AttachmentSlotDef;
    existing?: ExistingAttachment | null;
};

/**
 * Phase 2 item 8, Mode B — attach-to-existing-document upload, independent of
 * the parent form's own Submit/Update. Uploads immediately on file selection
 * via a plain fetch (the endpoint returns JSON, not an Inertia response), with
 * its own loading state and inline error feedback. Currently only used for
 * Activity Proposal's optional Resume of Resource Person(s) slot.
 */
export default function ImmediateAttachmentUpload({ documentId, slot, existing = null }: Props) {
    const [current, setCurrent] = useState<ExistingAttachment | null>(existing);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    function xsrfToken(): string {
        return decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
    }

    async function handleSelect(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        e.target.value = '';

        if (!file) {
            return;
        }

        setUploading(true);
        setError(null);

        const formData = new FormData();
        formData.append('document_id', String(documentId));
        formData.append('slot_key', slot.key);
        formData.append('file', file);

        try {
            const response = await fetch(attachments.store().url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: formData,
            });

            if (!response.ok) {
                const body = await response.json().catch(() => null);
                setError(body?.errors?.file?.[0] ?? body?.errors?.slot_key?.[0] ?? 'Upload failed. Try a different file.');

                return;
            }

            setCurrent(await response.json());
        } catch {
            setError('Upload failed. Check your connection and try again.');
        } finally {
            setUploading(false);
        }
    }

    async function handleRemove() {
        if (!current) {
            return;
        }

        setUploading(true);
        setError(null);

        try {
            await fetch(attachments.destroy(current.id).url, {
                method: 'DELETE',
                headers: { 'X-XSRF-TOKEN': xsrfToken() },
            });
            setCurrent(null);
        } catch {
            setError('Could not remove the file. Try again.');
        } finally {
            setUploading(false);
        }
    }

    return (
        <div className="grid gap-2">
            <Label htmlFor={slot.key}>
                {slot.label}
                {!slot.required && <span className="ml-1 font-normal text-muted-foreground">(optional)</span>}
            </Label>

            {current && (
                <div className="flex items-center gap-3 text-sm">
                    <a href={current.download_url} className="text-primary underline underline-offset-4">
                        {current.original_filename}
                    </a>
                    <Button type="button" variant="ghost" size="sm" onClick={handleRemove} disabled={uploading}>
                        Remove
                    </Button>
                </div>
            )}

            {!current && (
                <div className="flex items-center gap-3">
                    <Input id={slot.key} type="file" accept={slot.accept} onChange={handleSelect} disabled={uploading} />
                    {uploading && <Spinner />}
                </div>
            )}

            <InputError message={error ?? undefined} />
        </div>
    );
}
