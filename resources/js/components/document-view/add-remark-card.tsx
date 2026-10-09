import { router, usePage } from '@inertiajs/react';
import { MessageSquarePlus } from 'lucide-react';
import { useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import type { ConfirmActions } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { CardHeading } from './details';
import type { DocumentViewData } from './types';

/**
 * The "Add remark" box for an approver who has already acted on this
 * document (and for SDAO at any time). A remark is a note: it does not change
 * the document's status, and it cannot be edited or deleted once added, so
 * adding one goes through a confirmation. The page only renders this when the
 * server's `remark` ability allows it; the POST checks the same ability.
 */
export default function AddRemarkCard({ remark }: { remark: DocumentViewData['remark'] }) {
    const [body, setBody] = useState('');
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const trimmed = body.trim();

    function handleConfirm({ close, stopProcessing }: ConfirmActions) {
        router.post(
            remark.url,
            { body: trimmed },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setBody('');
                    close();
                },
                onFinish: stopProcessing,
            },
        );
    }

    return (
        <Card>
            <CardHeading title="Add a remark" />
            <CardContent className="flex flex-col gap-4">
                <CardDescription>
                    Leave a note on this document. The organization&apos;s officers are told, and its status does not
                    change.
                </CardDescription>
                <div className="flex flex-col gap-2">
                    <div className="flex items-baseline justify-between gap-3">
                        <Label htmlFor="document-remark">Remark</Label>
                        <span className="text-xs text-muted-foreground tabular-nums" aria-hidden>
                            {body.length}/{remark.maxLength}
                        </span>
                    </div>
                    <Textarea
                        id="document-remark"
                        rows={3}
                        value={body}
                        maxLength={remark.maxLength}
                        placeholder="Write your remark…"
                        aria-invalid={errors.body ? true : undefined}
                        onChange={(event) => setBody(event.target.value)}
                    />
                    <InputError message={errors.body} />
                </div>
                <ConfirmDialog
                    trigger={
                        <Button type="button" className="self-start" disabled={trimmed === ''}>
                            <MessageSquarePlus data-icon="inline-start" />
                            Add remark
                        </Button>
                    }
                    title="Add this remark?"
                    description="It is added to the history and the officers are notified. A remark cannot be edited or deleted afterwards."
                    confirmLabel="Add remark"
                    onConfirm={handleConfirm}
                />
            </CardContent>
        </Card>
    );
}
