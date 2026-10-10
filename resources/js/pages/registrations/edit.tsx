import { Form, Head } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';
import RegistrationController from '@/actions/App/Http/Controllers/RegistrationController';
import AdviserPicker from '@/components/adviser-picker';
import type { AdviserResult } from '@/components/adviser-picker';
import AttachmentRequirements, { requirementsSummary } from '@/components/attachment-requirements';
import type { AttachmentSlotDef, ExistingAttachment } from '@/components/attachment-slot-field';
import FlaggedSectionWrapper from '@/components/flagged-section-wrapper';
import {
    FocusFirstError,
    FormCard,
    FormField,
    FormFooter,
    FormSection,
    FormShell,
    FormStrip,
    LockedValue,
} from '@/components/form-shell';
import FormSubmitConfirm from '@/components/form-submit-confirm';
import GeneralRevisionNotice from '@/components/general-revision-notice';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import * as registrations from '@/routes/registrations';
import type { FlaggedRevisionProps } from '@/types';

type DocumentData = {
    id: number;
    title: string;
    organization: { name: string; college: string | null; program: string | null };
};

type DetailData = {
    organization_type_label: string;
    purpose_of_organization: string;
    contact_person: string;
    contact_no: string;
    email_address: string;
    date_organized: string;
    adviser: { id: number; name: string } | null;
} | null;

type Props = {
    document: DocumentData;
    detail: DetailData;
    attachmentSlots: AttachmentSlotDef[];
    attachments: Record<string, ExistingAttachment[]>;
} & FlaggedRevisionProps;

export default function EditRegistration({
    document,
    detail,
    attachmentSlots,
    attachments,
    flaggedSections,
    flaggedComment,
    flaggedSectionComments,
}: Props) {
    const formId = `edit-registration-form-${document.id}`;
    const flags = { flaggedSections, flaggedComment, flaggedSectionComments };

    // Return-for-revision preserves the ability to pick a NEW adviser (Phase
    // 2 item 5). Left untouched, the existing adviser is kept: the hidden
    // adviser_id stays empty and the server keeps the one it has.
    const [selectedAdviser, setSelectedAdviser] = useState<AdviserResult | null>(null);
    const [files, setFiles] = useState<Record<string, File | null>>({});

    function flagged(key: string, children: React.ReactNode) {
        return (
            <FlaggedSectionWrapper
                sectionKey={key}
                flagged={flaggedSections}
                comment={flaggedComment}
                sectionComment={flaggedSectionComments[key]}
                className={flaggedSections.includes(key) ? 'p-3' : undefined}
            >
                <div className="space-y-4">{children}</div>
            </FlaggedSectionWrapper>
        );
    }

    return (
        <>
            <Head title="Edit Registration" />

            <FormShell
                title="Edit & Resubmit Registration"
                subtitle="Update the details below and resubmit for SDAO review."
            >
                {flaggedSections.includes('general') && (
                    <GeneralRevisionNotice sectionComment={flaggedSectionComments.general} comment={flaggedComment} />
                )}

                <Form {...RegistrationController.update.form({ document: document.id })} id={formId}>
                    {({ processing, errors }) => (
                        <FormCard>
                            <FocusFirstError errors={errors} />
                            <FormStrip
                                left={
                                    <>
                                        Registration for{' '}
                                        <strong className="font-semibold text-foreground">
                                            {document.organization.name}
                                        </strong>
                                    </>
                                }
                                right={document.organization.college}
                            />

                            <FormSection title="About the organization">
                                {/* Name, type, college and program are fixed at founding:
                                    the type is derived from the college binding, so a
                                    resubmission can no longer change it (structural fix,
                                    2026-09-09 plan). */}
                                <LockedValue
                                    label="Organization name"
                                    value={document.organization.name}
                                    note="Can’t be changed"
                                />
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {detail && (
                                        <LockedValue
                                            label="Type of organization"
                                            value={detail.organization_type_label}
                                            note="Fixed"
                                        />
                                    )}
                                    <LockedValue
                                        label="College"
                                        value={document.organization.college ?? '—'}
                                        note="Fixed"
                                    />
                                    {document.organization.program && (
                                        <LockedValue label="Program" value={document.organization.program} note="Fixed" />
                                    )}
                                </div>
                                {flagged(
                                    'organization_details',
                                    <>
                                        <FormField id="date_organized" label="Date organized" error={errors.date_organized}>
                                            {(aria) => (
                                                <Input
                                                    id="date_organized"
                                                    type="date"
                                                    name="date_organized"
                                                    defaultValue={detail?.date_organized}
                                                    {...aria}
                                                />
                                            )}
                                        </FormField>
                                        <FormField id="purpose_of_organization" label="Purpose" error={errors.purpose_of_organization}>
                                            {(aria) => (
                                                <Textarea
                                                    id="purpose_of_organization"
                                                    name="purpose_of_organization"
                                                    defaultValue={detail?.purpose_of_organization}
                                                    rows={4}
                                                    {...aria}
                                                />
                                            )}
                                        </FormField>
                                    </>,
                                )}
                            </FormSection>

                            <FormSection title="Adviser">
                                {flagged(
                                    'adviser_selection',
                                    <FormField
                                        id="adviser"
                                        label="Faculty adviser"
                                        error={errors.adviser_id}
                                        helper="Leave it as is to keep your current adviser. SDAO will confirm it."
                                    >
                                        {(aria) => (
                                            <>
                                                <AdviserPicker
                                                    id="adviser"
                                                    selected={selectedAdviser}
                                                    onSelect={setSelectedAdviser}
                                                    current={detail?.adviser}
                                                    invalid={Boolean(aria['aria-invalid'])}
                                                    describedBy={aria['aria-describedby']}
                                                />
                                                <input type="hidden" name="adviser_id" value={selectedAdviser?.id ?? ''} />
                                            </>
                                        )}
                                    </FormField>,
                                )}
                            </FormSection>

                            <FormSection title="Contact">
                                {flagged(
                                    'contact_information',
                                    <>
                                        <FormField id="contact_person" label="Contact person" error={errors.contact_person}>
                                            {(aria) => (
                                                <Input
                                                    id="contact_person"
                                                    name="contact_person"
                                                    defaultValue={detail?.contact_person}
                                                    {...aria}
                                                />
                                            )}
                                        </FormField>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <FormField id="contact_no" label="Contact number" error={errors.contact_no}>
                                                {(aria) => (
                                                    <Input
                                                        id="contact_no"
                                                        name="contact_no"
                                                        type="tel"
                                                        defaultValue={detail?.contact_no}
                                                        {...aria}
                                                    />
                                                )}
                                            </FormField>
                                            <FormField id="email_address" label="Organization email" error={errors.email_address}>
                                                {(aria) => (
                                                    <Input
                                                        id="email_address"
                                                        name="email_address"
                                                        type="email"
                                                        defaultValue={detail?.email_address}
                                                        {...aria}
                                                    />
                                                )}
                                            </FormField>
                                        </div>
                                    </>,
                                )}
                            </FormSection>

                            <FormSection title="Requirements" aside={requirementsSummary(attachmentSlots, files, attachments)}>
                                <AttachmentRequirements
                                    native
                                    slots={attachmentSlots}
                                    files={files}
                                    onFileChange={(key, file) => setFiles((prev) => ({ ...prev, [key]: file }))}
                                    existing={attachments}
                                    errors={errors}
                                    flags={flags}
                                />
                            </FormSection>

                            <FormFooter status="SDAO sees it again as soon as you resubmit.">
                                <FormSubmitConfirm
                                    formId={formId}
                                    processing={processing}
                                    title="Resubmit this registration?"
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

EditRegistration.layout = {
    breadcrumbs: [{ title: 'Registrations', href: registrations.index() }, { title: 'Edit' }],
    columnWidth: '3xl',
};
