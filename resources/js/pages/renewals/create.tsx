import { Form, Head } from '@inertiajs/react';
import { ArrowRight, CalendarClock, FileCheck2, FileX2 } from 'lucide-react';
import { useState } from 'react';
import RenewalController from '@/actions/App/Http/Controllers/RenewalController';
import AttachmentRequirements, { requirementsSummary, uploadedCount } from '@/components/attachment-requirements';
import type { AttachmentSlotDef } from '@/components/attachment-slot-field';
import BlockedState from '@/components/blocked-state';
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
import { NotAnOfficerBlocked } from '@/components/student-blocked';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { mine } from '@/routes/organizations';
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
                <RenewalBlocked
                    membership={membership}
                    eligibility={eligibility}
                    coveredYear={coveredYear}
                />
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

/**
 * The renewal form is closed for this officer. Each reason the server reports
 * (RenewalEligibility) gets its own title and next step; none of them is a
 * new rule, only the existing eligibility answer in words.
 */
function RenewalBlocked({
    membership,
    eligibility,
    coveredYear,
}: {
    membership: Membership | null;
    eligibility: Eligibility;
    coveredYear: string;
}) {
    if (!membership) {
        return <NotAnOfficerBlocked action="renew it" />;
    }

    const organization = membership.organization.name;
    const goToOrganization = { label: 'Go to My Organization', href: mine() };

    if (eligibility.status === 'season_closed') {
        return (
            <BlockedState
                icon={CalendarClock}
                title="Renewal not open yet"
                body={
                    <>
                        You can renew <strong>{organization}</strong> during <strong>3rd Term</strong>. SDAO will notify
                        you once renewal opens.
                    </>
                }
                primary={goToOrganization}
            />
        );
    }

    if (eligibility.status === 'already_filed') {
        return (
            <BlockedState
                icon={FileCheck2}
                title="Renewal already filed"
                body={
                    <>
                        <strong>{organization}</strong> already has a renewal for <strong>{coveredYear}</strong>. You can
                        follow it under My Renewals.
                    </>
                }
                secondary={goToOrganization}
                primary={{ label: 'See my renewals', href: renewals.index() }}
            />
        );
    }

    if (eligibility.status === 'not_yet_due') {
        return (
            <BlockedState
                icon={CalendarClock}
                title="Renewal not due yet"
                body={eligibility.message ?? <><strong>{organization}</strong> doesn’t need to renew yet.</>}
                primary={goToOrganization}
            />
        );
    }

    return (
        <BlockedState
            icon={FileX2}
            title="Nothing to renew yet"
            body={
                <>
                    <strong>{organization}</strong> has no approved registration on record, so there is nothing to renew
                    from. Its registration has to be approved first.
                </>
            }
            primary={goToOrganization}
        />
    );
}
