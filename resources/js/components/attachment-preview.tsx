import { Download, ExternalLink, FileX, ImageOff, Minus, Plus, RotateCcw } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import type { ExistingAttachment } from '@/components/attachment-slot-field';
import { formatFileSize } from '@/components/document-view/format';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

type Kind = 'pdf' | 'image' | null;

const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

/** What the browser can show inline. Anything else gets the "cannot be previewed" card. */
export function previewKind(file: Pick<ExistingAttachment, 'mime_type' | 'original_filename'>): Kind {
    const extension = file.original_filename.split('.').pop()?.toLowerCase() ?? '';

    if (file.mime_type === 'application/pdf' || (!file.mime_type && extension === 'pdf')) {
        return 'pdf';
    }

    if ((file.mime_type && IMAGE_TYPES.includes(file.mime_type)) || (!file.mime_type && IMAGE_EXTENSIONS.includes(extension))) {
        return 'image';
    }

    return null;
}

type LoadState = { status: 'loading' } | { status: 'error' } | { status: 'ready'; url: string };

/**
 * Fetches the file with the user's session (the preview route is behind the
 * same access check as downloads) so the dialog can show a real loading and
 * error state, which an <iframe> or <img> alone cannot report for an HTTP
 * failure. The blob URL is revoked when the preview closes.
 */
function useFileBlob(previewUrl: string, mime: string): LoadState {
    const [state, setState] = useState<LoadState>({ status: 'loading' });

    useEffect(() => {
        const controller = new AbortController();
        let objectUrl: string | null = null;

        fetch(previewUrl, { signal: controller.signal, credentials: 'same-origin' })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                const blob = await response.blob();
                objectUrl = URL.createObjectURL(new Blob([blob], { type: mime }));
                setState({ status: 'ready', url: objectUrl });
            })
            .catch((error: unknown) => {
                if (!(error instanceof DOMException && error.name === 'AbortError')) {
                    setState({ status: 'error' });
                }
            });

        return () => {
            controller.abort();

            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
            }
        };
    }, [previewUrl, mime]);

    return state;
}

const ZOOM_STEPS = [0.5, 0.75, 1, 1.5, 2, 3];

function ImageViewer({ url, name }: { url: string; name: string }) {
    const [zoom, setZoom] = useState(2);
    const scale = ZOOM_STEPS[zoom];

    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-center justify-end gap-1" role="group" aria-label="Zoom">
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label="Zoom out"
                    disabled={zoom === 0}
                    onClick={() => setZoom((z) => z - 1)}
                >
                    <Minus aria-hidden />
                </Button>
                <span className="w-12 text-center text-sm tabular-nums" aria-live="polite">
                    {Math.round(scale * 100)}%
                </span>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label="Zoom in"
                    disabled={zoom === ZOOM_STEPS.length - 1}
                    onClick={() => setZoom((z) => z + 1)}
                >
                    <Plus aria-hidden />
                </Button>
                <Button type="button" variant="outline" size="icon" aria-label="Reset zoom" onClick={() => setZoom(2)}>
                    <RotateCcw aria-hidden />
                </Button>
            </div>
            <div className="h-[60dvh] overflow-auto rounded-md border bg-muted/30" tabIndex={0} aria-label={`${name} image`}>
                <img
                    src={url}
                    alt={name}
                    className="mx-auto block h-auto max-w-none"
                    style={{ width: `${scale * 100}%`, minWidth: '4rem' }}
                />
            </div>
        </div>
    );
}

function Message({ icon: Icon, title, children }: { icon: typeof FileX; title: string; children: ReactNode }) {
    return (
        <div
            role="status"
            className="flex h-[60dvh] flex-col items-center justify-center gap-2 rounded-md border border-dashed p-6 text-center"
        >
            <span className="flex size-10 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                <Icon className="size-5" aria-hidden />
            </span>
            <p className="font-semibold">{title}</p>
            <p className="max-w-sm text-sm text-muted-foreground">{children}</p>
        </div>
    );
}

function PreviewBody({ file, label, kind }: { file: ExistingAttachment; label: string; kind: Exclude<Kind, null> }) {
    const mime = kind === 'pdf' ? 'application/pdf' : (file.mime_type ?? 'image/png');
    const state = useFileBlob(file.preview_url, mime);

    if (state.status === 'loading') {
        return (
            <div
                role="status"
                aria-busy="true"
                className="flex h-[60dvh] flex-col items-center justify-center gap-3 rounded-md border text-sm text-muted-foreground"
            >
                <Spinner />
                Loading {file.original_filename}…
            </div>
        );
    }

    if (state.status === 'error') {
        return (
            <Message icon={ImageOff} title="The file could not be loaded">
                Check your connection and close this preview to try again. You can still open it in a new tab or download
                it.
            </Message>
        );
    }

    if (kind === 'image') {
        return <ImageViewer url={state.url} name={label} />;
    }

    // The browser's own PDF viewer supplies the page and zoom controls.
    return (
        <iframe
            title={`${label} (${file.original_filename})`}
            src={`${state.url}#toolbar=1&navpanes=0&view=FitH`}
            className="h-[70dvh] w-full rounded-md border bg-muted/30"
        />
    );
}

/** The preview dialog's content: details, open-in-new-tab, download, and the file itself. */
function PreviewContent({ file, label }: { file: ExistingAttachment; label: string }) {
    const kind = previewKind(file);
    const size = formatFileSize(file.size);

    return (
        <DialogContent className="sm:max-w-5xl">
            <div className="flex flex-col gap-1 pr-8">
                <DialogTitle className="break-words">{label}</DialogTitle>
                <DialogDescription className="break-all">
                    {file.original_filename}
                    {size && ` · ${size}`}
                </DialogDescription>
            </div>

            <div className="flex flex-wrap gap-2">
                <Button asChild variant="outline" size="sm">
                    <a href={file.preview_url} target="_blank" rel="noopener noreferrer">
                        <ExternalLink aria-hidden />
                        Open in new tab
                    </a>
                </Button>
                <Button asChild size="sm">
                    <a href={file.download_url} download={file.original_filename}>
                        <Download aria-hidden />
                        Download
                    </a>
                </Button>
            </div>

            {kind ? (
                <PreviewBody file={file} label={label} kind={kind} />
            ) : (
                <Message icon={FileX} title="This file cannot be previewed">
                    Your browser cannot show this type of file. Download it to open it on your device.
                </Message>
            )}
        </DialogContent>
    );
}

/**
 * Wraps any element as the trigger of an attachment preview. The dialog
 * traps focus, closes with Escape or the close button, and returns focus to
 * this trigger. Downloading only happens from the preview's Download button.
 */
export function AttachmentPreview({
    file,
    label,
    children,
}: {
    file: ExistingAttachment;
    label: string;
    children: ReactNode;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <PreviewContent file={file} label={label} />
        </Dialog>
    );
}

/** A text link that opens the preview, for the file lists on the edit forms. */
export function AttachmentPreviewLink({ file, label }: { file: ExistingAttachment; label?: string }) {
    return (
        <AttachmentPreview file={file} label={label ?? file.original_filename}>
            <button
                type="button"
                className="text-left text-primary-text underline underline-offset-4 focus-visible:focus-ring-edge"
            >
                {file.original_filename}
                <span className="sr-only"> (preview)</span>
            </button>
        </AttachmentPreview>
    );
}
