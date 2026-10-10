import { Form, Head, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import AfterActivityReportController from '@/actions/App/Http/Controllers/AfterActivityReportController';
import { ActivityCard } from '@/components/activity-picker';
import type {
    AttachmentSlotDef,
    ExistingAttachment,
} from '@/components/attachment-slot-field';
import FlaggedSectionWrapper from '@/components/flagged-section-wrapper';
import {
    FocusFirstError,
    FormCard,
    FormFooter,
    FormSection,
    FormShell,
    FormStrip,
} from '@/components/form-shell';
import FormSubmitConfirm from '@/components/form-submit-confirm';
import GeneralRevisionNotice from '@/components/general-revision-notice';
import ReportFormSections from '@/components/report-form-sections';
import type { FlaggedRevisionProps } from '@/types';

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
} & FlaggedRevisionProps;

export default function EditReport({
    document,
    detail,
    attachmentSlots,
    attachments,
    flaggedSections,
    flaggedComment,
    flaggedSectionComments,
}: Props) {
    const { auth } = usePage().props;
    const formId = `edit-report-form-${document.id}`;
    const flags = { flaggedSections, flaggedComment, flaggedSectionComments };

    return (
        <>
            <Head title="Edit After-Activity Report" />

            <FormShell
                title="Edit & Resubmit Report"
                subtitle="Update the details below and resubmit for review."
            >
                {flaggedSections.includes('general') && (
                    <GeneralRevisionNotice
                        sectionComment={flaggedSectionComments.general}
                        comment={flaggedComment}
                    />
                )}

                <Form
                    {...AfterActivityReportController.update.form({
                        document: document.id,
                    })}
                    id={formId}
                >
                    {({ processing, errors }) => (
                        <FormCard>
                            <FocusFirstError errors={errors} />
                            <FormStrip
                                left={
                                    auth.organization ? (
                                        <>
                                            Reporting for{' '}
                                            <strong className="font-semibold text-foreground">
                                                {auth.organization.name}
                                            </strong>
                                        </>
                                    ) : (
                                        document.title
                                    )
                                }
                                right={auth.organization?.school?.name}
                            />

                            {detail?.activity && (
                                <FormSection title="Which activity?">
                                    <FlaggedSectionWrapper
                                        sectionKey="event_details"
                                        flagged={flaggedSections}
                                        comment={flaggedComment}
                                        sectionComment={
                                            flaggedSectionComments.event_details
                                        }
                                        className={
                                            flaggedSections.includes(
                                                'event_details',
                                            )
                                                ? 'p-3'
                                                : undefined
                                        }
                                    >
                                        <ActivityCard
                                            showDate
                                            activity={{
                                                id: String(document.id),
                                                title: detail.activity.title,
                                                date: detail.activity.activity_date ?? '',
                                                start_time: detail.activity.start_time,
                                                end_time: detail.activity.end_time,
                                                venue: detail.activity.venue,
                                            }}
                                        />
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            The activity can&apos;t be changed on a returned report.
                                        </p>
                                    </FlaggedSectionWrapper>
                                </FormSection>
                            )}

                            <ReportFormSections
                                errors={errors}
                                defaults={detail ?? undefined}
                                attachmentSlots={attachmentSlots}
                                attachments={attachments}
                                flags={flags}
                            />

                            <FormFooter status="Your adviser or SDAO sees it again as soon as you resubmit.">
                                <FormSubmitConfirm
                                    formId={formId}
                                    processing={processing}
                                    title="Resubmit this report?"
                                    description="It goes back to the approver who returned it."
                                    confirmLabel="Save & Resubmit"
                                >
                                    Save &amp; Resubmit
                                    <ArrowRight aria-hidden />
                                </FormSubmitConfirm>
                            </FormFooter>
                        </FormCard>
                    )}
                </Form>
            </FormShell>
        </>
    );
}

EditReport.layout = {
    breadcrumbs: [{ title: 'Reports' }, { title: 'Edit' }],
    columnWidth: '3xl',
};
