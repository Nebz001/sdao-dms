import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import AfterActivityReportController from '@/actions/App/Http/Controllers/AfterActivityReportController';
import AttachmentSlotField from '@/components/attachment-slot-field';
import type {AttachmentSlotDef, ExistingAttachment} from '@/components/attachment-slot-field';
import FlaggedSectionWrapper from '@/components/flagged-section-wrapper';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type DocumentData = { id: number; title: string };

type DetailData = {
    summary: string;
    outcomes: string | null;
    participant_count: number | null;
    activity_chairs: string[] | null;
    prepared_by: string | null;
    event_program: string | null;
    target_participants_percentage: number | null;
    activity: {
        title: string;
        venue: string | null;
        activity_date: string | null;
        start_time: string | null;
        end_time: string | null;
    } | null;
} | null;

type Props = {
    document: DocumentData;
    detail: DetailData;
    attachmentSlots: AttachmentSlotDef[];
    attachments: Record<string, ExistingAttachment[]>;
    flaggedSections: string[];
};

export default function EditReport({ document, detail, attachmentSlots, attachments, flaggedSections }: Props) {
    const [chairs, setChairs] = useState<string[]>(detail?.activity_chairs?.length ? detail.activity_chairs : ['']);

    return (
        <>
            <Head title="Edit After-Activity Report" />

            <div className="max-w-2xl space-y-6">
                <Heading
                    title="Edit & Resubmit Report"
                    description="Update the details below and resubmit for SDAO review."
                />

                {flaggedSections.includes('general') && (
                    <div className="rounded-md border border-destructive/60 bg-destructive/10 p-3 text-sm text-destructive">
                        General revisions requested — see the reviewer's comment in Revision History below.
                    </div>
                )}

                {detail?.activity && (
                    <FlaggedSectionWrapper sectionKey="event_details" flagged={flaggedSections}>
                    <div className="rounded-md border p-4 text-sm text-muted-foreground">
                        <p>
                            <span className="font-medium text-foreground">Name of Event:</span>{' '}
                            {detail.activity.title} (cannot be changed)
                        </p>
                        {detail.activity.venue && detail.activity.activity_date && (
                            <p>
                                <span className="font-medium text-foreground">Date and Time of Event:</span>{' '}
                                {detail.activity.activity_date} · {detail.activity.start_time}–{detail.activity.end_time} ·{' '}
                                {detail.activity.venue}
                            </p>
                        )}
                    </div>
                    </FlaggedSectionWrapper>
                )}

                <Form
                    {...AfterActivityReportController.update.form({ document: document.id })}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <FlaggedSectionWrapper sectionKey="summary_program" flagged={flaggedSections}>
                            <div className="space-y-6">
                            {/* Summary */}
                            <div className="grid gap-2">
                                <Label htmlFor="summary">Summary</Label>
                                <Textarea
                                    id="summary"
                                    name="summary"
                                    defaultValue={detail?.summary}
                                    rows={5}
                                    required
                                />
                                <InputError message={errors.summary} />
                            </div>

                            {/* Activity Chair/s */}
                            <div className="grid gap-2">
                                <div className="flex items-center justify-between">
                                    <Label>Activity Chair/s</Label>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setChairs((prev) => [...prev, ''])}
                                    >
                                        + Add Chair
                                    </Button>
                                </div>
                                {chairs.map((chair, i) => (
                                    <div key={i} className="space-y-1">
                                        <div className="flex items-center gap-2">
                                            <Input
                                                name={`activity_chairs[${i}]`}
                                                value={chair}
                                                onChange={(e) =>
                                                    setChairs((prev) => {
                                                        const next = [...prev];
                                                        next[i] = e.target.value;

                                                        return next;
                                                    })
                                                }
                                                placeholder="Full name"
                                                required
                                            />
                                            {chairs.length > 1 && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => setChairs((prev) => prev.filter((_, idx) => idx !== i))}
                                                >
                                                    Remove
                                                </Button>
                                            )}
                                        </div>
                                        <InputError message={errors[`activity_chairs.${i}`]} />
                                    </div>
                                ))}
                                <InputError message={errors.activity_chairs} />
                            </div>

                            {/* Prepared By */}
                            <div className="grid gap-2">
                                <Label htmlFor="prepared_by">Prepared By</Label>
                                <Input
                                    id="prepared_by"
                                    name="prepared_by"
                                    defaultValue={detail?.prepared_by ?? ''}
                                    placeholder="Full name"
                                    required
                                />
                                <InputError message={errors.prepared_by} />
                            </div>

                            {/* Program */}
                            <div className="grid gap-2">
                                <Label htmlFor="event_program">Program</Label>
                                <Textarea
                                    id="event_program"
                                    name="event_program"
                                    defaultValue={detail?.event_program ?? ''}
                                    placeholder="Order of activities / program flow for the event…"
                                    rows={4}
                                    required
                                />
                                <InputError message={errors.event_program} />
                            </div>
                            </div>
                            </FlaggedSectionWrapper>

                            <FlaggedSectionWrapper sectionKey="evaluation" flagged={flaggedSections}>
                            <div className="space-y-6">
                            {/* % Target Participants */}
                            <div className="grid gap-2">
                                <Label htmlFor="target_participants_percentage">
                                    Activity Evaluation Report — % Target Participants
                                </Label>
                                <Input
                                    id="target_participants_percentage"
                                    type="number"
                                    name="target_participants_percentage"
                                    min={0}
                                    max={100}
                                    defaultValue={detail?.target_participants_percentage ?? undefined}
                                    required
                                />
                                <InputError message={errors.target_participants_percentage} />
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
                            </div>
                            </FlaggedSectionWrapper>

                            <FlaggedSectionWrapper sectionKey="attachments" flagged={flaggedSections}>
                            <div className="space-y-6">
                            {attachmentSlots.map((slot) => (
                                <AttachmentSlotField
                                    key={slot.key}
                                    slot={slot}
                                    existing={attachments[slot.key]}
                                    error={errors[`attachments.${slot.key}`]}
                                />
                            ))}
                            </div>
                            </FlaggedSectionWrapper>

                            <div className="flex items-center gap-4">
                                <Button loading={processing} loadingText="Resubmitting…">Save & Resubmit</Button>
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
