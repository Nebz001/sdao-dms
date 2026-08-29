import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import AttachmentSlotField from '@/components/attachment-slot-field';
import type { AttachmentSlotDef } from '@/components/attachment-slot-field';

function makeSlot(overrides: Partial<AttachmentSlotDef> = {}): AttachmentSlotDef {
    return {
        key: 'letter_of_intent',
        label: 'Letter of Intent',
        required: true,
        multiple: false,
        accept: '.pdf,.jpg,.jpeg,.png',
        max_kb: 10240,
        ...overrides,
    };
}

function makeFile(name: string, sizeInBytes: number): File {
    const file = new File(['x'], name, { type: 'application/pdf' });
    Object.defineProperty(file, 'size', { value: sizeInBytes });

    return file;
}

/*
 * A file over a slot's max_kb used to only be caught server-side, after a
 * full upload round trip — and past PHP's own post_max_size ceiling, only
 * after a repeat submit click (see UploadLimits). This is the client-side
 * guard added instead: reject at selection time, before any upload starts.
 */
describe('AttachmentSlotField', () => {
    it('rejects a file larger than the slot max_kb, without calling onFilesChange', async () => {
        const onFilesChange = vi.fn();
        const slot = makeSlot({ max_kb: 10240 }); // 10 MB
        const user = userEvent.setup();
        render(<AttachmentSlotField slot={slot} onFilesChange={onFilesChange} />);

        const oversizedFile = makeFile('huge.pdf', 11 * 1024 * 1024); // 11 MB
        await user.upload(screen.getByLabelText('Letter of Intent'), oversizedFile);

        expect(screen.getByText(/is too large/)).toBeInTheDocument();
        expect(screen.getByText(/up to 10 MB/)).toBeInTheDocument();
        expect(onFilesChange).not.toHaveBeenCalledWith(expect.anything());
    });

    it('clears a previously rejected selection so the oversized file cannot be submitted', async () => {
        const slot = makeSlot();
        const user = userEvent.setup();
        render(<AttachmentSlotField slot={slot} />);

        const input = screen.getByLabelText('Letter of Intent') as HTMLInputElement;
        await user.upload(input, makeFile('huge.pdf', 11 * 1024 * 1024));

        expect(input.files).toHaveLength(0);
    });

    it('accepts a file within the size limit and clears any prior size error', async () => {
        const onFilesChange = vi.fn();
        const slot = makeSlot();
        const user = userEvent.setup();
        render(<AttachmentSlotField slot={slot} onFilesChange={onFilesChange} />);

        const input = screen.getByLabelText('Letter of Intent');
        await user.upload(input, makeFile('huge.pdf', 11 * 1024 * 1024));
        expect(screen.getByText(/is too large/)).toBeInTheDocument();

        await user.upload(input, makeFile('fine.pdf', 1024 * 1024));

        expect(screen.queryByText(/is too large/)).not.toBeInTheDocument();
        expect(onFilesChange).toHaveBeenLastCalledWith(expect.objectContaining({ length: 1 }));
    });

    it('shows the slot’s max size as a hint even before any selection', () => {
        const slot = makeSlot({ max_kb: 5120 });
        render(<AttachmentSlotField slot={slot} />);

        expect(screen.getByText(/Max 5 MB per file/)).toBeInTheDocument();
    });
});
