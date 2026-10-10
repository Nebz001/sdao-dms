import { Form, Head, usePage } from '@inertiajs/react';
import { ArrowRight, CalendarX2 } from 'lucide-react';
import { useState } from 'react';
import AfterActivityReportController from '@/actions/App/Http/Controllers/AfterActivityReportController';
import ActivityPicker from '@/components/activity-picker';
import type { PickerActivity } from '@/components/activity-picker';
import type { AttachmentSlotDef } from '@/components/attachment-slot-field';
import BlockedState from '@/components/blocked-state';
import {
    FocusFirstError,
    FormCard,
    FormField,
    FormFooter,
    FormSection,
    FormShell,
    FormStrip,
} from '@/components/form-shell';
import FormSubmitConfirm from '@/components/form-submit-confirm';
import PageNotice from '@/components/page-notice';
import ReportFormSections from '@/components/report-form-sections';
import { NotAnOfficerBlocked } from '@/components/student-blocked';
import * as activityProposals from '@/routes/activity-proposals';

type Membership = {
    id: number;
    position: string;
    position_label: string;
    organization: { id: number; name: string; school: string | null };
};

type EligibleProposal = {
    activity_proposal_id: number;
    title: string;
    approved_on: string | null;
    activity: {
        name: string;
        venue: string;
        activity_date: string;
    } | null;
};

type ApprovedActivity = {
    activity_proposal_id: number;
    title: string;
    venue: string | null;
    activity_date: string;
    start_time: string | null;
    end_time: string | null;
    term_label: string | null;
    approved_on: string | null;
    report_filed: boolean;
};

type Props = {
    membership: Membership | null;
    eligibleProposals: EligibleProposal[];
    approvedActivities?: ApprovedActivity[];
    attachmentSlots: AttachmentSlotDef[];
};

const FORM_ID = 'after-activity-report-form';

export default function CreateReport({
    membership,
    eligibleProposals,
    approvedActivities = [],
    attachmentSlots,
}: Props) {
    const { auth } = usePage().props;
    const preparedBy = [auth.user.first_name, auth.user.last_name]
        .filter(Boolean)
        .join(' ') || auth.user.name;

    // With a single reportable activity there is nothing to choose, so it
    // starts picked; with several the student picks from the list.
    const [activityId, setActivityId] = useState(
        eligibleProposals.length === 1
            ? String(eligibleProposals[0].activity_proposal_id)
            : '',
    );

    const pickerActivities: PickerActivity[] = approvedActivities.map((a) => ({
        id: String(a.activity_proposal_id),
        title: a.title,
        date: a.activity_date,
        start_time: a.start_time,
        end_time: a.end_time,
        venue: a.venue,
        group: a.term_label,
        disabledReason: a.report_filed ? 'Report filed' : null,
    }));

    if (!membership) {
        return (
            <>
                <Head title="Submit After-Activity Report" />
                <NotAnOfficerBlocked action="file an after-activity report" />
            </>
        );
    }

    if (approvedActivities.length === 0) {
        return (
            <>
                <Head title="Submit After-Activity Report" />
                <BlockedState
                    icon={CalendarX2}
                    title="No approved activities yet"
                    body={
                        <>
                            <strong>{membership.organization.name}</strong> has no approved activities to report on. A
                            report can only be filed against an approved activity proposal.
                        </>
                    }
                    primary={{ label: 'View activity proposals', href: activityProposals.index() }}
                />
            </>
        );
    }

    return (
        <>
            <Head title="Submit After-Activity Report" />

            <FormShell
                title="After-Activity Report"
                subtitle="Tell your adviser and SDAO how your approved activity went."
            >
                <Form
                    {...AfterActivityReportController.store.form()}
                    id={FORM_ID}
                >
                    {({ processing, errors }) => (
                        <FormCard>
                            <FocusFirstError errors={errors} />
                            <FormStrip
                                left={
                                    <>
                                        Reporting for{' '}
                                        <strong className="font-semibold text-foreground">
                                            {membership.organization.name}
                                        </strong>{' '}
                                        as {membership.position_label}
                                    </>
                                }
                                right={membership.organization.school}
                            />

                            <FormSection title="Which activity?">
                                <input
                                    type="hidden"
                                    name="activity_proposal_id"
                                    value={activityId}
                                />
                                <FormField
                                    id="activity_proposal_id"
                                    label="Approved activity"
                                    error={errors.activity_proposal_id}
                                >
                                    {(aria) => (
                                        <ActivityPicker
                                            id="activity_proposal_id"
                                            activities={pickerActivities}
                                            value={activityId}
                                            onChange={setActivityId}
                                            placeholder="Choose an approved activity"
                                            helper="Only approved activities without a report can be picked"
                                            showDate
                                            invalid={Boolean(errors.activity_proposal_id)}
                                            describedBy={aria['aria-describedby']}
                                        />
                                    )}
                                </FormField>
                                {eligibleProposals.length === 0 && (
                                    <PageNotice tone="info">
                                        Every approved activity of{' '}
                                        {membership.organization.name} already
                                        has a report, so there is nothing to
                                        report on right now.
                                    </PageNotice>
                                )}
                            </FormSection>

                            <ReportFormSections
                                errors={errors}
                                preparedByDefault={preparedBy}
                                attachmentSlots={attachmentSlots}
                            />

                            <FormFooter status="Your adviser reviews it first, then SDAO.">
                                <FormSubmitConfirm
                                    formId={FORM_ID}
                                    processing={processing}
                                    title="Submit this report for review?"
                                    description="It goes to your adviser first, then SDAO. You can't edit it unless an approver returns it."
                                    confirmLabel="Submit for Review"
                                >
                                    Submit for Review
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

CreateReport.layout = {
    breadcrumbs: [{ title: 'Reports' }, { title: 'New Report' }],
    columnWidth: '3xl',
};
