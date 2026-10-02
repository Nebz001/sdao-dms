import PageNotice from '@/components/page-notice';

/**
 * A form's error summary: an urgent destructive notice with the lead sentence
 * and each distinct error as a bullet. Per-field errors stay under their
 * field (InputError); this is for errors that belong to the whole form.
 */
export default function AlertError({
    errors,
    title,
}: {
    errors: string[];
    title?: string;
}) {
    return (
        <PageNotice tone="destructive" urgent title={title || 'Something went wrong.'}>
            <ul className="mt-1 list-inside list-disc">
                {Array.from(new Set(errors)).map((error, index) => (
                    <li key={index}>{error}</li>
                ))}
            </ul>
        </PageNotice>
    );
}
