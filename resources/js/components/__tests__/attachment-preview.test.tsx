import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { AttachmentPreview, previewKind } from '@/components/attachment-preview';
import type { ExistingAttachment } from '@/components/attachment-slot-field';

const pdf: ExistingAttachment = {
    id: 1,
    original_filename: 'by_laws.pdf',
    download_url: '/attachments/1',
    preview_url: '/attachments/1/preview',
    size: 1153434,
    mime_type: 'application/pdf',
};

function renderTile(file: ExistingAttachment) {
    return render(
        <AttachmentPreview file={file} label="By-Laws">
            <button type="button">Preview By-Laws</button>
        </AttachmentPreview>,
    );
}

beforeEach(() => {
    URL.createObjectURL = vi.fn(() => 'blob:preview');
    URL.revokeObjectURL = vi.fn();
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('previewKind', () => {
    it('previews PDFs and images, and nothing else', () => {
        expect(previewKind(pdf)).toBe('pdf');
        expect(previewKind({ original_filename: 'a.png', mime_type: 'image/png' })).toBe('image');
        expect(previewKind({ original_filename: 'a.webp' })).toBe('image');
        expect(previewKind({ original_filename: 'a.docx', mime_type: 'application/vnd.ms-word' })).toBeNull();
    });
});

describe('AttachmentPreview', () => {
    it('opens a dialog with the file name, size, open-in-new-tab and an explicit download, without downloading on open', async () => {
        vi.stubGlobal('fetch', vi.fn(() => Promise.resolve(new Response(new Blob(['%PDF']), { status: 200 }))));
        const user = userEvent.setup();
        renderTile(pdf);

        await user.click(screen.getByRole('button', { name: 'Preview By-Laws' }));

        const dialog = await screen.findByRole('dialog', { name: 'By-Laws' });
        expect(dialog).toHaveTextContent('by_laws.pdf · 1.1 MB');
        expect(screen.getByRole('link', { name: /Open in new tab/ })).toHaveAttribute('href', '/attachments/1/preview');
        expect(screen.getByRole('link', { name: /Download/ })).toHaveAttribute('href', '/attachments/1');
        expect(screen.getByRole('link', { name: /Download/ })).toHaveAttribute('download', 'by_laws.pdf');
        expect(fetch).toHaveBeenCalledWith('/attachments/1/preview', expect.anything());

        await waitFor(() => expect(screen.getByTitle(/By-Laws \(by_laws.pdf\)/)).toBeInTheDocument());
    });

    it('shows a loading state until the file arrives', async () => {
        vi.stubGlobal('fetch', vi.fn(() => new Promise(() => {})));
        const user = userEvent.setup();
        renderTile(pdf);

        await user.click(screen.getByRole('button', { name: 'Preview By-Laws' }));

        expect(await screen.findByText(/Loading by_laws.pdf/)).toBeInTheDocument();
    });

    it('shows an error state when the file fails to load, with download still available', async () => {
        vi.stubGlobal('fetch', vi.fn(() => Promise.resolve(new Response('no', { status: 403 }))));
        const user = userEvent.setup();
        renderTile(pdf);

        await user.click(screen.getByRole('button', { name: 'Preview By-Laws' }));

        expect(await screen.findByText('The file could not be loaded')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Download/ })).toBeInTheDocument();
    });

    it('shows a fallback card, and never fetches, for a type that cannot be previewed', async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        const user = userEvent.setup();
        renderTile({ ...pdf, original_filename: 'notes.docx', mime_type: 'application/msword' });

        await user.click(screen.getByRole('button', { name: 'Preview By-Laws' }));

        expect(await screen.findByText('This file cannot be previewed')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Download/ })).toBeInTheDocument();
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('closes with Escape and returns focus to the tile that opened it', async () => {
        vi.stubGlobal('fetch', vi.fn(() => Promise.resolve(new Response(new Blob(['%PDF']), { status: 200 }))));
        const user = userEvent.setup();
        renderTile(pdf);
        const tile = screen.getByRole('button', { name: 'Preview By-Laws' });

        await user.click(tile);
        await screen.findByRole('dialog');
        await user.keyboard('{Escape}');

        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
        expect(tile).toHaveFocus();
    });
});
