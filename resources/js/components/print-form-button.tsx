import { PrinterIcon } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { print } from '@/routes/documents';

type Props = {
    documentId: number;
};

/**
 * Downloads and opens a document's printable official form (Phase 2 —
 * printable official forms). A plain navigation, not an Inertia visit: the
 * route returns application/pdf, not an Inertia response — see
 * attachments-card.tsx for the same reasoning. The tab is opened
 * synchronously in the click handler so popup blockers don't kill it once
 * the fetch resolves.
 */
export default function PrintFormButton({ documentId }: Props) {
    const [pending, setPending] = useState(false);

    async function handleClick() {
        const tab = window.open('', '_blank');

        setPending(true);

        try {
            const response = await fetch(print(documentId).url, {
                headers: { Accept: 'application/pdf' },
            });

            if (!response.ok) {
                tab?.close();
                toast.error('Could not generate the printable form. Please try again.');

                return;
            }

            const blob = await response.blob();
            const url = URL.createObjectURL(blob);

            if (tab) {
                tab.location.href = url;
            } else {
                const link = document.createElement('a');
                link.href = url;
                link.download = '';
                link.click();
            }

            setTimeout(() => URL.revokeObjectURL(url), 60_000);
        } catch {
            tab?.close();
            toast.error('Could not generate the printable form. Please try again.');
        } finally {
            setPending(false);
        }
    }

    return (
        <Button variant="outline" size="sm" onClick={handleClick} disabled={pending} aria-busy={pending}>
            {pending ? <Spinner /> : <PrinterIcon />}
            {pending ? 'Preparing…' : 'Print Form'}
        </Button>
    );
}
