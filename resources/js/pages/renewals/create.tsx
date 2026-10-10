import { Form, Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';
import RenewalController from '@/actions/App/Http/Controllers/RenewalController';
import AttachmentRequirements, { requirementsSummary, uploadedCount } from '@/components/attachment-requirements';
import type { AttachmentSlotDef } from '@/components/attachment-slot-field';
import {
    FocusFirstError,
    FormCard,
    FormField,
    FormFooter,
    FormInfoStrip,
    FormSection,
    FormShell,
    FormStrip,
    LockedValue,
} from '@/components/form-shell';
import FormSubmitConfirm from '@/components/form-submit-confirm';
import PageNotice from '@/components/page-notice';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import * as renewals from '@/routes/renewals';

type OrganizationTypeOption = {
    value: string;
    label: string;
};

type Membership = {
    id: number;
    position: string;
    position_label: string;
    organization: { id: number; name: string; college: string | null; program: string | null };
};

type PriorRecord = {
    organization_type: string;
    purpose_of_organization: string;
    contact_person: string;
    contact_no: string;
    email_address: string;
    date_organized: string;
} | null;

type Eligibility = {
    status: 'eligible' | 'no_prior_record' | 'season_closed' | 'not_yet_due' | 'already_filed' | null;
    message: string | null;
};

type CurrentPeriod = {
    academic_year: string;
    term: string;
    label: string;
};

type Props = {
    membership: Membership | null;
    priorRecord: PriorRecord;
    eligibility: Eligibility;
    currentPeriod: CurrentPeriod;
    organizationTypes: OrganizationTypeOption[];
    attachmentSlots: AttachmentSlotDef[];
};

const FORM_ID = 'renewal-form';

export default function CreateRenewal({
    membership,
    priorRecord,
    eligibility,
    currentPeriod,
    organizationTypes,
    attachmentSlots,
}: Props) {
    const [files, setFiles] = useState<Record<string, File | null>>({});

    // Renewal is only ever eligible during 3rd-term season, and always
    // covers the year that follows the current one — mirrors
    // AcademicPeriod::nextAcademicYear() on the server.
    const coveredYear = (() => {
        const startYear = parseInt(currentPeriod.academic_year.split('-')[0], 10);

        return `${startYear + 1}-${startYear + 2}`;
    })();

    if (!membership || eligibility.status !== 'eligible' || !priorRecord) {
        return (
            <>
                <Head title="Submit Renewal" />
                <FormShell title="Renew Your Organization" subtitle="Keep your organization active for next school year. SDAO reviews it the same way as a registration.">
                    <PageNotice
                        tone="info"
                        title="Renewal not available yet."
                        action={
                            membership && eligibility.status === 'already_filed' ? (
                                <Button asChild variant="outline" size="sm">
                                    <Link href={renewals.index().url}>My Renewals</Link>
                                </Button>
                            ) : undefined
                        }
                    >
                        {!membership
                            ? 'You are not bound as an officer of any organization. Contact your adviser to be bound before submitting a renewal.'
                            : eligibility.message}
                    </PageNotice>
                </FormShell>
            </>
        );
    }

    const organization = membership.organization;
    const uploaded = uploadedCount(attachmentSlots, files);

    return (
        <>
            <Head title="Submit Renewal" />

            <FormShell title="Renew Your Organization" subtitle="Keep your organization active for next school year. SDAO reviews it the same way as a registration.">
                <Form {...RenewalController.store.form()} id={FORM_ID}>
                    {({ processing, errors }) => (
                        <FormCard>
                            <FocusFirstError errors={errors} />
                            <FormStrip
                                left={
                                    <>
                                        Renewing{' '}
                                        <strong className="font-semibold text-foreground">{organization.name}</strong> for{' '}
                                        <strong className="font-semibold text-foreground">{coveredYear}</strong>
                                    </>
                                }
                                right={`Active for ${currentPeriod.academic_year}`}
                            />
                            <FormInfoStrip>
                                We filled in last year’s details. Check them and update anything that changed.
                            </FormInfoStrip>

                            <FormSection title="About the organization">
                                {/* Name, College and Program are fixed on a renewal
                                    (Phase 2 item 7 slice 2): shown, never submitted. */}
                                <LockedValue
                                    label="Organization name"
                                    value={organization.name}
                                    note="Can’t be changed in a renewal"
                                />
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <LockedValue label="College" value={organization.college ?? '—'} note="Fixed" />
                                    {organization.program && (
                                        <LockedValue label="Program" value={organization.program} note="Fixed" />
                                    )}
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormField id="organization_type" label="Type of organization" error={errors.organization_type}>
                                        {(aria) => (
                                            <Select name="organization_type" defaultValue={priorRecord.organization_type}>
                                                <SelectTrigger id="organization_type" className="w-full" {...aria}>
                                                    <SelectValue placeholder="Select type" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {organizationTypes.map((t) => (
                                                        <SelectItem key={t.value} value={t.value}>
                                                            {t.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        )}
                                    </FormField>
                                    <FormField id="date_organized" label="Date organized" error={errors.date_organized}>
                                        {(aria) => (
                                            <Input
                                                id="date_organized"
                                                type="date"
                                                name="date_organized"
                                                defaultValue={priorRecord.date_organized}
                                                {...aria}
                                            />
                                        )}
                                    </FormField>
                                </div>

                                <FormField id="purpose_of_organization" label="Purpose" error={errors.purpose_of_organization}>
                                    {(aria) => (
                                        <Textarea
                                            id="purpose_of_organization"
                                            name="purpose_of_organization"
                                            defaultValue={priorRecord.purpose_of_organization}
                                            rows={4}
                                            {...aria}
                                        />
                                    )}
                                </FormField>
                            </FormSection>

                            {/* No adviser section: a renewal keeps the adviser the
                                organization already has; the form never carried an
                                adviser field and the server takes none. */}

                            <FormSection title="Contact">
                                <FormField id="contact_person" label="Contact person" error={errors.contact_person}>
                                    {(aria) => (
                                        <Input
                                            id="contact_person"
                                            name="contact_person"
                                            defaultValue={priorRecord.contact_person}
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
                                                defaultValue={priorRecord.contact_no}
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
                                                defaultValue={priorRecord.email_address}
                                                {...aria}
                                            />
                                        )}
                                    </FormField>
                                </div>
                            </FormSection>

                            <FormSection title="Requirements" aside={requirementsSummary(attachmentSlots, files)}>
                                <AttachmentRequirements
                                    native
                                    slots={attachmentSlots}
                                    files={files}
                                    onFileChange={(key, file) => setFiles((prev) => ({ ...prev, [key]: file }))}
                                    errors={errors}
                                />
                            </FormSection>

                            <FormFooter status="You can review everything before it’s sent">
                                <FormSubmitConfirm
                                    formId={FORM_ID}
                                    processing={processing}
                                    title="Submit this renewal?"
                                    description={
                                        <>
                                            SDAO reviews it the same way as a registration.
                                            <span className="mt-3 grid gap-1 rounded-lg border bg-muted/30 p-3 text-sm text-foreground sm:grid-cols-[auto_1fr] sm:gap-x-4">
                                                <span className="text-muted-foreground">Organization</span>
                                                <span className="font-medium">{organization.name}</span>
                                                <span className="text-muted-foreground">Covers</span>
                                                <span className="font-medium">{coveredYear}</span>
                                                <span className="text-muted-foreground">Requirements</span>
                                                <span className="font-medium">
                                                    {uploaded} of {attachmentSlots.length} uploaded
                                                </span>
                                            </span>
                                        </>
                                    }
                                    confirmLabel="Submit Renewal"
                                >
                                    Review and Submit
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

CreateRenewal.layout = {
    breadcrumbs: [
        { title: 'Renewals', href: renewals.index() },
        { title: 'New Renewal' },
    ],
    columnWidth: '3xl',
};
