import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import RenewalController from '@/actions/App/Http/Controllers/RenewalController';
import AttachmentRequirements, {
    requirementsSummary,
} from '@/components/attachment-requirements';
import type {
    AttachmentSlotDef,
    ExistingAttachment,
} from '@/components/attachment-slot-field';
import { formatLongDate } from '@/components/document-view/format';
import {
    FixFooter,
    FixItemBox,
    FixProvider,
    FixShell,
    FixSection,
    FixStrip,
    FixSummary,
    FlaggedFile,
    LockedField,
    LockedGrid,
    LockedRequirements,
    fileBadges,
    lockedSummary,
    useFix,
} from '@/components/fix-resend';
import {
    FocusFirstError,
    FormCard,
    FormField,
    FormFooter,
    FormSection,
} from '@/components/form-shell';
import FormSubmitConfirm from '@/components/form-submit-confirm';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { hasChanged } from '@/lib/fix-resend';
import type { FixProps } from '@/lib/fix-resend';

type DocumentData = {
    id: number;
    title: string;
    organization: {
        name: string;
        college: string | null;
        program: string | null;
    };
};

type DetailData = {
    organization_type_label: string;
    purpose_of_organization: string;
    contact_person: string;
    contact_no: string;
    email_address: string;
    date_organized: string;
} | null;

type Props = {
    document: DocumentData;
    detail: DetailData;
    attachmentSlots: AttachmentSlotDef[];
    attachments: Record<string, ExistingAttachment[]>;
    fix: FixProps;
};

export default function EditRenewal({ fix, ...props }: Props) {
    useEffect(() => {
        setLayoutProps({
            breadcrumbs: [{ title: 'Renewals' }, { title: 'Fix and Resend' }],
            trailParent: { title: fix.back.title, href: fix.back.href },
        });
    }, [fix.back.title, fix.back.href]);

    return (
        <FixProvider fix={fix}>
            <Head title="Fix and Resend" />
            <RenewalFix {...props} />
        </FixProvider>
    );
}

function RenewalFix({
    document,
    detail,
    attachmentSlots,
    attachments,
}: Omit<Props, 'fix'>) {
    const { fix, isFlagged } = useFix();
    const formId = `edit-renewal-form-${document.id}`;
    const draft = fix.draft ?? {};
    const [files, setFiles] = useState<Record<string, File | null>>({});

    const baseDetails = {
        date_organized: detail?.date_organized ?? '',
        purpose_of_organization: detail?.purpose_of_organization ?? '',
    };
    const baseContact = {
        contact_person: detail?.contact_person ?? '',
        contact_no: detail?.contact_no ?? '',
        email_address: detail?.email_address ?? '',
    };
    const [details, setDetails] = useState({
        ...baseDetails,
        ...(draft.organization_details as Partial<typeof baseDetails>),
    });
    const [contact, setContact] = useState({
        ...baseContact,
        ...(draft.contact_information as Partial<typeof baseContact>),
    });

    const flaggedKeys = fix.items.map((item) => item.key);
    const flaggedSlots = attachmentSlots.filter((slot) =>
        flaggedKeys.includes(slot.key),
    );
    const lockedSlots = attachmentSlots.filter(
        (slot) => !flaggedKeys.includes(slot.key),
    );

    const flags = {
        flaggedSections: fix.items.map((item) => item.key),
        flaggedComment: fix.comment,
        flaggedSectionComments: Object.fromEntries(
            fix.items.map((item) => [item.key, item.comment ?? '']),
        ),
    };

    return (
        <FixShell fix={fix}>
            <Form
                {...RenewalController.update.form({ document: document.id })}
                id={formId}
            >
                {({ processing, errors }) => (
                    <FormCard>
                        <FocusFirstError errors={errors} />
                        <FixStrip organization={document.organization.name} />
                        <FixSummary />
                        {(errors.locked || errors.flagged) && (
                            <p
                                role="alert"
                                className="border-b bg-destructive/10 px-5 py-3 text-sm text-destructive-foreground sm:px-6"
                            >
                                {errors.locked} {errors.flagged}
                            </p>
                        )}

                        <FixSection
                            title="About the organization"
                            itemKeys={['organization_details']}
                        >
                            <LockedGrid>
                                <LockedField
                                    label="Organization name"
                                    value={document.organization.name}
                                />
                                {detail && (
                                    <LockedField
                                        label="Type"
                                        value={detail.organization_type_label}
                                    />
                                )}
                                <LockedField
                                    label="College"
                                    value={document.organization.college}
                                />
                                {document.organization.program && (
                                    <LockedField
                                        label="Program"
                                        value={document.organization.program}
                                    />
                                )}
                            </LockedGrid>
                            <FixItemBox
                                sectionKey="organization_details"
                                changed={hasChanged(baseDetails, details)}
                                locked={
                                    <LockedGrid>
                                        <LockedField
                                            label="Date organized"
                                            value={formatLongDate(
                                                baseDetails.date_organized,
                                            )}
                                        />
                                        <LockedField
                                            label="Purpose"
                                            value={
                                                baseDetails.purpose_of_organization
                                            }
                                        />
                                    </LockedGrid>
                                }
                            >
                                <FormField
                                    id="date_organized"
                                    label="Date organized"
                                    error={errors.date_organized}
                                >
                                    {(aria) => (
                                        <Input
                                            id="date_organized"
                                            type="date"
                                            name="date_organized"
                                            value={details.date_organized}
                                            onChange={(event) =>
                                                setDetails((prev) => ({
                                                    ...prev,
                                                    date_organized:
                                                        event.target.value,
                                                }))
                                            }
                                            {...aria}
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    id="purpose_of_organization"
                                    label="Purpose"
                                    error={errors.purpose_of_organization}
                                >
                                    {(aria) => (
                                        <Textarea
                                            id="purpose_of_organization"
                                            name="purpose_of_organization"
                                            value={
                                                details.purpose_of_organization
                                            }
                                            onChange={(event) =>
                                                setDetails((prev) => ({
                                                    ...prev,
                                                    purpose_of_organization:
                                                        event.target.value,
                                                }))
                                            }
                                            rows={4}
                                            {...aria}
                                        />
                                    )}
                                </FormField>
                            </FixItemBox>
                        </FixSection>

                        <FixSection
                            title="Contact"
                            itemKeys={['contact_information']}
                        >
                            <FixItemBox
                                sectionKey="contact_information"
                                changed={hasChanged(baseContact, contact)}
                                locked={
                                    <LockedGrid>
                                        <LockedField
                                            label="Contact person"
                                            value={baseContact.contact_person}
                                        />
                                        <LockedField
                                            label="Contact number"
                                            value={baseContact.contact_no}
                                        />
                                        <LockedField
                                            label="Organization email"
                                            value={baseContact.email_address}
                                        />
                                    </LockedGrid>
                                }
                            >
                                <FormField
                                    id="contact_person"
                                    label="Contact person"
                                    error={errors.contact_person}
                                >
                                    {(aria) => (
                                        <Input
                                            id="contact_person"
                                            name="contact_person"
                                            value={contact.contact_person}
                                            onChange={(event) =>
                                                setContact((prev) => ({
                                                    ...prev,
                                                    contact_person:
                                                        event.target.value,
                                                }))
                                            }
                                            {...aria}
                                        />
                                    )}
                                </FormField>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        id="contact_no"
                                        label="Contact number"
                                        error={errors.contact_no}
                                    >
                                        {(aria) => (
                                            <Input
                                                id="contact_no"
                                                name="contact_no"
                                                type="tel"
                                                value={contact.contact_no}
                                                onChange={(event) =>
                                                    setContact((prev) => ({
                                                        ...prev,
                                                        contact_no:
                                                            event.target.value,
                                                    }))
                                                }
                                                {...aria}
                                            />
                                        )}
                                    </FormField>
                                    <FormField
                                        id="email_address"
                                        label="Organization email"
                                        error={errors.email_address}
                                    >
                                        {(aria) => (
                                            <Input
                                                id="email_address"
                                                name="email_address"
                                                type="email"
                                                value={contact.email_address}
                                                onChange={(event) =>
                                                    setContact((prev) => ({
                                                        ...prev,
                                                        email_address:
                                                            event.target.value,
                                                    }))
                                                }
                                                {...aria}
                                            />
                                        )}
                                    </FormField>
                                </div>
                            </FixItemBox>
                        </FixSection>

                        {fix.active ? (
                            <FixSection
                                title="Requirements"
                                lockedLabel={lockedSummary(
                                    attachmentSlots,
                                    flaggedKeys,
                                )}
                                itemKeys={flaggedSlots.map((slot) => slot.key)}
                            >
                                {flaggedSlots.map((slot) => (
                                    <FixItemBox
                                        key={slot.key}
                                        sectionKey={slot.key}
                                        changed={files[slot.key] != null}
                                        badges={fileBadges(
                                            files[slot.key] != null,
                                        )}
                                        locked={null}
                                    >
                                        <FlaggedFile
                                            slot={slot}
                                            existing={
                                                attachments[slot.key] ?? []
                                            }
                                            files={
                                                files[slot.key]
                                                    ? [files[slot.key] as File]
                                                    : []
                                            }
                                            onFilesChange={(list) =>
                                                setFiles((prev) => ({
                                                    ...prev,
                                                    [slot.key]: list[0] ?? null,
                                                }))
                                            }
                                            error={
                                                errors[
                                                    `attachments.${slot.key}`
                                                ]
                                            }
                                        />
                                    </FixItemBox>
                                ))}
                                <LockedRequirements
                                    slots={lockedSlots}
                                    existing={attachments}
                                />
                            </FixSection>
                        ) : (
                            <FormSection
                                title="Requirements"
                                aside={requirementsSummary(
                                    attachmentSlots,
                                    files,
                                    attachments,
                                )}
                            >
                                <AttachmentRequirements
                                    native
                                    slots={attachmentSlots}
                                    files={files}
                                    onFileChange={(key, file) =>
                                        setFiles((prev) => ({
                                            ...prev,
                                            [key]: file,
                                        }))
                                    }
                                    existing={attachments}
                                    errors={errors}
                                    flags={flags}
                                />
                            </FormSection>
                        )}

                        {fix.active ? (
                            <FixFooter
                                formId={formId}
                                processing={processing}
                                collect={() => ({
                                    ...(isFlagged('organization_details')
                                        ? { organization_details: details }
                                        : {}),
                                    ...(isFlagged('contact_information')
                                        ? { contact_information: contact }
                                        : {}),
                                })}
                                title="Resend this renewal?"
                                description={`It goes back to ${fix.role}, who asked for these changes.`}
                            />
                        ) : (
                            <FormFooter
                                status={`${fix.role} sees it again as soon as you resend.`}
                            >
                                <FormSubmitConfirm
                                    formId={formId}
                                    processing={processing}
                                    title="Resend this renewal?"
                                    description={`It goes back to ${fix.role}, who returned it.`}
                                    confirmLabel={fix.resendLabel}
                                >
                                    {fix.resendLabel}
                                </FormSubmitConfirm>
                            </FormFooter>
                        )}
                    </FormCard>
                )}
            </Form>
        </FixShell>
    );
}

EditRenewal.layout = {
    breadcrumbs: [{ title: 'Renewals' }, { title: 'Fix and Resend' }],
    columnWidth: '3xl',
};
