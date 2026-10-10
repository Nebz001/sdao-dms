import { Plus, X } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import AttachmentDropZone from '@/components/attachment-drop-zone';
import type {
    AttachmentSlotDef,
    ExistingAttachment,
} from '@/components/attachment-slot-field';
import FlaggedSectionWrapper from '@/components/flagged-section-wrapper';
import { AddonInput, FormField, FormSection } from '@/components/form-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { FlaggedRevisionProps } from '@/types';

export type ReportDefaults = {
    summary?: string;
    event_program?: string | null;
    outcomes?: string | null;
    target_participants_percentage?: number | null;
    participant_count?: number | null;
    activity_chairs?: string[] | null;
    prepared_by?: string | null;
};

type Props = {
    errors: Record<string, string | undefined>;
    defaults?: ReportDefaults;
    preparedByDefault?: string;
    attachmentSlots: AttachmentSlotDef[];
    /** Files already on the document (edit and resubmit). */
    attachments?: Record<string, ExistingAttachment[]>;
    /** Present on edit and resubmit only: highlights what the approver flagged. */
    flags?: FlaggedRevisionProps;
};

/**
 * The report's four data sections (What happened, Participants, People,
 * Attachments), shared by the new-report page and the edit and resubmit page.
 * Every field name, rule and attachment slot is the one the form always had;
 * only the layout is new.
 *
 * The approver flags (SectionFlags) are per group, not per visual section:
 * `summary_program` covers Summary, Program, Activity chairs and Prepared by;
 * `evaluation` covers Outcomes and the two participant figures. A group that
 * spans two sections is highlighted in both, and its comments are printed once,
 * at the first place it appears.
 */
export default function ReportFormSections({
    errors,
    defaults,
    preparedByDefault = '',
    attachmentSlots,
    attachments = {},
    flags,
}: Props) {
    const [chairs, setChairs] = useState<string[]>(
        defaults?.activity_chairs?.length ? defaults.activity_chairs : [''],
    );

    function flagged(
        key: string,
        children: ReactNode,
        { withComments }: { withComments: boolean },
    ) {
        if (!flags) {
            return children;
        }

        const isFlagged = flags.flaggedSections.includes(key);

        return (
            <FlaggedSectionWrapper
                sectionKey={key}
                flagged={flags.flaggedSections}
                comment={withComments ? flags.flaggedComment : null}
                sectionComment={
                    withComments ? flags.flaggedSectionComments[key] : null
                }
                className={isFlagged ? 'p-3' : undefined}
            >
                <div className="space-y-4">{children}</div>
            </FlaggedSectionWrapper>
        );
    }

    return (
        <>
            <FormSection title="What happened">
                {flagged(
                    'summary_program',
                    <>
                        <FormField
                            id="summary"
                            label="Summary"
                            error={errors.summary}
                        >
                            {(aria) => (
                                <Textarea
                                    id="summary"
                                    name="summary"
                                    rows={4}
                                    defaultValue={defaults?.summary}
                                    placeholder="Summarize how the activity was conducted"
                                    {...aria}
                                />
                            )}
                        </FormField>
                        <FormField
                            id="event_program"
                            label="Program"
                            error={errors.event_program}
                        >
                            {(aria) => (
                                <Textarea
                                    id="event_program"
                                    name="event_program"
                                    rows={4}
                                    defaultValue={defaults?.event_program ?? ''}
                                    placeholder="Order of activities, from opening to closing"
                                    {...aria}
                                />
                            )}
                        </FormField>
                    </>,
                    { withComments: true },
                )}
                {flagged(
                    'evaluation',
                    <FormField
                        id="outcomes"
                        label="Outcomes"
                        optional
                        error={errors.outcomes}
                    >
                        {(aria) => (
                            <Textarea
                                id="outcomes"
                                name="outcomes"
                                rows={4}
                                defaultValue={defaults?.outcomes ?? ''}
                                placeholder="What were the results?"
                                {...aria}
                            />
                        )}
                    </FormField>,
                    { withComments: true },
                )}
            </FormSection>

            <FormSection title="Participants">
                {flagged(
                    'evaluation',
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            id="target_participants_percentage"
                            label="Target participants reached"
                            error={errors.target_participants_percentage}
                        >
                            {(aria) => (
                                <AddonInput
                                    id="target_participants_percentage"
                                    name="target_participants_percentage"
                                    type="number"
                                    min={0}
                                    max={100}
                                    inputMode="numeric"
                                    defaultValue={
                                        defaults?.target_participants_percentage ??
                                        undefined
                                    }
                                    placeholder="e.g. 85"
                                    suffix="%"
                                    invalid={aria['aria-invalid']}
                                    aria-describedby={aria['aria-describedby']}
                                />
                            )}
                        </FormField>
                        <FormField
                            id="participant_count"
                            label="Number of participants"
                            optional
                            error={errors.participant_count}
                        >
                            {(aria) => (
                                <AddonInput
                                    id="participant_count"
                                    name="participant_count"
                                    type="number"
                                    min={0}
                                    inputMode="numeric"
                                    defaultValue={
                                        defaults?.participant_count ?? undefined
                                    }
                                    placeholder="e.g. 120"
                                    suffix="people"
                                    invalid={aria['aria-invalid']}
                                    aria-describedby={aria['aria-describedby']}
                                />
                            )}
                        </FormField>
                    </div>,
                    { withComments: false },
                )}
            </FormSection>

            <FormSection title="People">
                {flagged(
                    'summary_program',
                    <>
                        <fieldset className="grid gap-2">
                            <legend className="mb-1.5 text-sm leading-snug font-medium">
                                Activity chairs
                            </legend>
                            {chairs.map((chair, index) => {
                                const chairError =
                                    errors[`activity_chairs.${index}`] ??
                                    (index === 0
                                        ? errors.activity_chairs
                                        : undefined);

                                return (
                                    <div key={index} className="grid gap-1">
                                        <div className="flex items-center gap-2">
                                            <Input
                                                id={`activity_chairs_${index}`}
                                                name={`activity_chairs[${index}]`}
                                                value={chair}
                                                onChange={(event) =>
                                                    setChairs((prev) =>
                                                        prev.map((current, i) =>
                                                            i === index
                                                                ? event.target.value
                                                                : current,
                                                        ),
                                                    )
                                                }
                                                placeholder="Full name"
                                                aria-label={`Activity chair ${index + 1}`}
                                                aria-invalid={
                                                    chairError ? true : undefined
                                                }
                                                aria-describedby={
                                                    chairError
                                                        ? `activity_chairs_${index}_error`
                                                        : undefined
                                                }
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                disabled={chairs.length === 1}
                                                aria-label={`Remove activity chair ${index + 1}`}
                                                onClick={() =>
                                                    setChairs((prev) =>
                                                        prev.filter(
                                                            (_, i) => i !== index,
                                                        ),
                                                    )
                                                }
                                            >
                                                <X aria-hidden />
                                            </Button>
                                        </div>
                                        {chairError && (
                                            <p
                                                id={`activity_chairs_${index}_error`}
                                                className="text-sm text-destructive"
                                            >
                                                {chairError}
                                            </p>
                                        )}
                                    </div>
                                );
                            })}
                            <Button
                                type="button"
                                variant="link"
                                size="sm"
                                className="h-auto w-fit p-0"
                                onClick={() => setChairs((prev) => [...prev, ''])}
                            >
                                <Plus aria-hidden />
                                Add another chair
                            </Button>
                        </fieldset>

                        <FormField
                            id="prepared_by"
                            label="Prepared by"
                            error={errors.prepared_by}
                            helper="Filled in with your name. Change it if someone else prepared the report."
                        >
                            {(aria) => (
                                <Input
                                    id="prepared_by"
                                    name="prepared_by"
                                    defaultValue={
                                        defaults?.prepared_by ?? preparedByDefault
                                    }
                                    placeholder="Full name"
                                    {...aria}
                                />
                            )}
                        </FormField>
                    </>,
                    { withComments: false },
                )}
            </FormSection>

            <FormSection title="Attachments" aside="PDF or image">
                {attachmentSlots.map((slot) => {
                    const zone = (
                        <AttachmentDropZone
                            slot={slot}
                            existing={attachments[slot.key]}
                            error={errors[`attachments.${slot.key}`]}
                        />
                    );

                    return flags ? (
                        <FlaggedSectionWrapper
                            key={slot.key}
                            sectionKey={slot.key}
                            flagged={flags.flaggedSections}
                            comment={flags.flaggedComment}
                            sectionComment={flags.flaggedSectionComments[slot.key]}
                            className={
                                flags.flaggedSections.includes(slot.key)
                                    ? 'p-3'
                                    : undefined
                            }
                        >
                            {zone}
                        </FlaggedSectionWrapper>
                    ) : (
                        <div key={slot.key}>{zone}</div>
                    );
                })}
            </FormSection>
        </>
    );
}
