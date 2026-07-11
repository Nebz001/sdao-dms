import type { AttachmentSlotDef, ExistingAttachment } from '@/components/attachment-slot-field';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Props = {
    slots: AttachmentSlotDef[];
    files: Record<string, ExistingAttachment[]>;
};

/**
 * Phase 2 item 8 — one "Attachments" card shared by every form's show and
 * review-show page, listing every slot with a download link per uploaded
 * file, or "Not provided" for an empty optional slot.
 */
export default function AttachmentsCard({ slots, files }: Props) {
    if (slots.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Attachments</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-3 text-sm">
                {slots.map((slot) => {
                    const uploaded = files[slot.key] ?? [];

                    return (
                        <div key={slot.key} className="grid grid-cols-3 gap-2">
                            <span className="font-medium text-muted-foreground">{slot.label}</span>
                            <div className="col-span-2">
                                {uploaded.length > 0 ? (
                                    <ul className="space-y-1">
                                        {uploaded.map((file) => (
                                            <li key={file.id}>
                                                <a
                                                    href={file.download_url}
                                                    className="text-primary underline underline-offset-4"
                                                >
                                                    {file.original_filename}
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <span className="text-muted-foreground">Not provided</span>
                                )}
                            </div>
                        </div>
                    );
                })}
            </CardContent>
        </Card>
    );
}
