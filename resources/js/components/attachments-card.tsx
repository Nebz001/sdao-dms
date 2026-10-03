import { Eye, File, FileText, ImageIcon } from 'lucide-react';
import { AttachmentPreview } from '@/components/attachment-preview';
import type { AttachmentSlotDef, ExistingAttachment } from '@/components/attachment-slot-field';
import { CardHeading } from '@/components/document-view/details';
import { formatFileSize } from '@/components/document-view/format';
import { Card, CardContent } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';

type Props = {
    slots: AttachmentSlotDef[];
    files: Record<string, ExistingAttachment[]>;
};

function FileIcon({ mime }: { mime?: string }) {
    if (mime?.startsWith('image/')) {
        return <ImageIcon aria-hidden />;
    }

    return mime === 'application/pdf' || mime === undefined ? <FileText aria-hidden /> : <File aria-hidden />;
}

/**
 * One "Attachments" card shared by every form's show and review page: a tile
 * per uploaded file (type icon, slot name, filename and size, open-in-new-tab
 * button), and a dashed tile for each slot with nothing uploaded. The
 * download link is a plain navigation, not an Inertia visit: it returns the
 * file, not an Inertia response.
 */
export default function AttachmentsCard({ slots, files }: Props) {
    if (slots.length === 0) {
        return null;
    }

    const total = slots.reduce((sum, slot) => sum + (files[slot.key]?.length ?? 0), 0);

    return (
        <Card>
            <CardHeading title="Attachments" aside={`${total} ${total === 1 ? 'file' : 'files'}`} />
            <CardContent>
                {total === 0 ? (
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <FileText />
                            </EmptyMedia>
                            <EmptyTitle>No files attached</EmptyTitle>
                            <EmptyDescription>Nothing was uploaded with this document.</EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <ul className="grid grid-cols-1 gap-3 md:grid-cols-2">
                        {slots.flatMap((slot) => {
                            const uploaded = files[slot.key] ?? [];

                            if (uploaded.length === 0) {
                                return (
                                    <li
                                        key={slot.key}
                                        className="flex items-center gap-3 rounded-lg border border-dashed p-3 text-sm"
                                    >
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                            <FileText className="size-5" aria-hidden />
                                        </span>
                                        <span className="min-w-0">
                                            <span className="block font-medium text-muted-foreground">{slot.label}</span>
                                            <span className="block text-xs text-muted-foreground">Not provided</span>
                                        </span>
                                    </li>
                                );
                            }

                            return uploaded.map((file, index) => {
                                const size = formatFileSize(file.size);
                                const name = uploaded.length > 1 ? `${slot.label} ${index + 1}` : slot.label;

                                return (
                                    <li key={file.id}>
                                        <AttachmentPreview file={file} label={name}>
                                            <button
                                                type="button"
                                                aria-label={`Preview ${name} (${file.original_filename})`}
                                                className="flex w-full items-center gap-3 rounded-lg border bg-card p-3 text-left transition-colors hover:bg-accent focus-visible:focus-ring-edge"
                                            >
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-destructive/10 text-destructive-foreground [&_svg]:size-5">
                                            <FileIcon mime={file.mime_type} />
                                        </span>
                                        <span className="min-w-0 flex-1 text-sm">
                                            <span className="block font-medium break-words">{name}</span>
                                            <span className="block text-xs break-all text-muted-foreground">
                                                {file.original_filename}
                                                {size && (
                                                    <>
                                                        {' · '}
                                                        <span className="whitespace-nowrap">{size}</span>
                                                    </>
                                                )}
                                            </span>
                                        </span>
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-md border text-muted-foreground">
                                            <Eye className="size-4" aria-hidden />
                                        </span>
                                            </button>
                                        </AttachmentPreview>
                                    </li>
                                );
                            });
                        })}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
