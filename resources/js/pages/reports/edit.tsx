import { Form, Head } from '@inertiajs/react';
import AfterActivityReportController from '@/actions/App/Http/Controllers/AfterActivityReportController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type DocumentData = { id: number; title: string };

type DetailData = {
    narrative: string;
    outcomes: string | null;
    participant_count: number | null;
    activity: { title: string } | null;
} | null;

type Props = {
    document: DocumentData;
    detail: DetailData;
};

export default function EditReport({ document, detail }: Props) {
    return (
        <>
            <Head title="Edit After-Activity Report" />

            <div className="mx-auto max-w-2xl space-y-6 p-8">
                <Heading
                    title="Edit & Resubmit Report"
                    description="Update the details below and resubmit for SDAO review."
                />

                {detail?.activity && (
                    <p className="text-sm text-muted-foreground">
                        Activity: <strong>{detail.activity.title}</strong> (cannot be
                        changed)
                    </p>
                )}

                <Form
                    {...AfterActivityReportController.update.form({ document: document.id })}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            {/* Narrative */}
                            <div className="grid gap-2">
                                <Label htmlFor="narrative">Narrative</Label>
                                <Textarea
                                    id="narrative"
                                    name="narrative"
                                    defaultValue={detail?.narrative}
                                    rows={5}
                                    required
                                />
                                <InputError message={errors.narrative} />
                            </div>

                            {/* Outcomes */}
                            <div className="grid gap-2">
                                <Label htmlFor="outcomes">Outcomes</Label>
                                <Textarea
                                    id="outcomes"
                                    name="outcomes"
                                    defaultValue={detail?.outcomes ?? ''}
                                    rows={4}
                                />
                                <InputError message={errors.outcomes} />
                            </div>

                            {/* Participant count */}
                            <div className="grid gap-2">
                                <Label htmlFor="participant_count">Participant Count</Label>
                                <Input
                                    id="participant_count"
                                    type="number"
                                    name="participant_count"
                                    min={0}
                                    defaultValue={detail?.participant_count ?? undefined}
                                />
                                <InputError message={errors.participant_count} />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button disabled={processing}>Save &amp; Resubmit</Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

EditReport.layout = {
    breadcrumbs: [{ title: 'Reports' }, { title: 'Edit' }],
};
