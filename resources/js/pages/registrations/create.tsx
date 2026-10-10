import { Head, router, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';
import AdviserPicker from '@/components/adviser-picker';
import type { AdviserResult } from '@/components/adviser-picker';
import AttachmentRequirements, { requirementsSummary, uploadedCount } from '@/components/attachment-requirements';
import type { AttachmentSlotDef } from '@/components/attachment-slot-field';
import {
    FocusFirstError,
    FormCard,
    FormField,
    FormFooter,
    FormSection,
    FormShell,
    FormStrip,
} from '@/components/form-shell';
import PageNotice from '@/components/page-notice';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import * as registrations from '@/routes/registrations';

type OrganizationTypeOption = { value: string; label: string };
type Program = { id: number; name: string };
type SchoolOption = { id: number; name: string; type: string; programs: Program[] };

type Props = {
    canPropose: boolean;
    schools: SchoolOption[];
    organizationTypes: OrganizationTypeOption[];
    attachmentSlots: AttachmentSlotDef[];
};

// Matches App\Enums\OrganizationType::CoCurricular->value. An Extra-Curricular
// org is university-wide and has no college (Phase 2 remediation item 3) —
// College (and, in turn, Program) only applies to a Co-Curricular org.
const CO_CURRICULAR = 'co_curricular';

export default function CreateRegistration({ canPropose, schools, organizationTypes, attachmentSlots }: Props) {
    const { auth, currentPeriod } = usePage().props;

    const [name, setName] = useState('');
    const [schoolId, setSchoolId] = useState('');
    const [programId, setProgramId] = useState('');
    const [organizationType, setOrganizationType] = useState('');
    const [purposeOfOrganization, setPurposeOfOrganization] = useState('');
    const [contactPerson, setContactPerson] = useState('');
    const [contactNo, setContactNo] = useState('');
    const [emailAddress, setEmailAddress] = useState('');
    const [dateOrganized, setDateOrganized] = useState('');
    const [attachmentFiles, setAttachmentFiles] = useState<Record<string, File | null>>({});
    const [selectedAdviser, setSelectedAdviser] = useState<AdviserResult | null>(null);

    const [processing, setProcessing] = useState(false);
    const [uploadProgress, setUploadProgress] = useState<number | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const needsCollege = organizationType === CO_CURRICULAR;
    const selectedSchool = schools.find((s) => String(s.id) === schoolId);
    const needsProgram = needsCollege && selectedSchool?.type === 'regular';

    function submit() {
        setProcessing(true);
        setUploadProgress(0);
        setErrors({});

        router.post(
            registrations.store().url,
            {
                name,
                school_id: needsCollege ? schoolId : '',
                program_id: needsProgram ? programId : '',
                adviser_id: selectedAdviser?.id ?? '',
                organization_type: organizationType,
                purpose_of_organization: purposeOfOrganization,
                contact_person: contactPerson,
                contact_no: contactNo,
                email_address: emailAddress,
                date_organized: dateOrganized,
                attachments: attachmentFiles,
            },
            {
                // A large attachment upload used to look hung — the button
                // just showed a static "Submitting…" for as long as the
                // upload took, with no feedback, which is what actually drove
                // the repeated-click complaint (the "too large" error can
                // only be reported once the request completes). A visible
                // percentage makes clear that progress is happening.
                onProgress: (event) => setUploadProgress(event?.percentage ?? null),
                onError: (errs) => setErrors(errs as Record<string, string>),
                onFinish: () => {
                    setProcessing(false);
                    setUploadProgress(null);
                },
            },
        );
    }

    if (!canPropose) {
        return (
            <>
                <Head title="Submit Registration" />
                <FormShell
                    title="Register a New Organization"
                    subtitle="Start a brand-new student organization. SDAO reviews it and approves your adviser."
                >
                    <PageNotice tone="info">
                        You already have an active organization or an in-progress registration. You cannot propose
                        another organization at this time.
                    </PageNotice>
                </FormShell>
            </>
        );
    }

    // What is still missing before "Review and Submit" opens. The server
    // validates everything again; this only says what is left to do.
    const missing = [
        name.trim() === '' && 'the organization name',
        organizationType === '' && 'the type',
        dateOrganized === '' && 'the date organized',
        needsCollege && schoolId === '' && 'the college',
        needsProgram && programId === '' && 'the program',
        purposeOfOrganization.trim() === '' && 'the purpose',
        selectedAdviser === null && 'an adviser',
        contactPerson.trim() === '' && 'the contact person',
        contactNo.trim() === '' && 'the contact number',
        emailAddress.trim() === '' && 'the organization email',
        attachmentSlots.some((slot) => slot.required && !attachmentFiles[slot.key]) && 'every requirement',
    ].filter(Boolean) as string[];
    const formValid = missing.length === 0;
    const uploaded = uploadedCount(attachmentSlots, attachmentFiles);

    return (
        <>
            <Head title="Submit Registration" />

            <FormShell
                title="Register a New Organization"
                subtitle="Start a brand-new student organization. SDAO reviews it and approves your adviser."
            >
                <FormCard>
                    <FocusFirstError errors={errors} />
                    <FormStrip
                        left={
                            <>
                                New organization for{' '}
                                <strong className="font-semibold text-foreground">{currentPeriod.academic_year}</strong>
                            </>
                        }
                        right={`Filed by ${auth.user.first_name ?? auth.user.name}`}
                    />

                    <FormSection title="About the organization">
                        <FormField id="name" label="Organization name" error={errors.name}>
                            {(aria) => (
                                <Input
                                    id="name"
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                    placeholder="e.g. Computer Science Society"
                                    {...aria}
                                />
                            )}
                        </FormField>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField id="organization_type" label="Type of organization" error={errors.organization_type}>
                                {(aria) => (
                                    <Select
                                        value={organizationType}
                                        onValueChange={(value) => {
                                            setOrganizationType(value);

                                            // Extra-Curricular orgs are university-wide —
                                            // clear a previously-picked college/program
                                            // rather than silently submitting it.
                                            if (value !== CO_CURRICULAR) {
                                                setSchoolId('');
                                                setProgramId('');
                                            }
                                        }}
                                    >
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
                                        value={dateOrganized}
                                        onChange={(e) => setDateOrganized(e.target.value)}
                                        {...aria}
                                    />
                                )}
                            </FormField>

                            {needsCollege && (
                                <FormField id="college" label="College" error={errors.school_id}>
                                    {(aria) => (
                                        <Select
                                            value={schoolId}
                                            onValueChange={(value) => {
                                                setSchoolId(value);
                                                setProgramId('');
                                            }}
                                        >
                                            <SelectTrigger id="college" className="w-full" {...aria}>
                                                <SelectValue placeholder="Select college" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {schools.map((s) => (
                                                    <SelectItem key={s.id} value={String(s.id)}>
                                                        {s.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    )}
                                </FormField>
                            )}

                            {needsProgram && (
                                <FormField id="program" label="Program" error={errors.program_id}>
                                    {(aria) => (
                                        <Select value={programId} onValueChange={setProgramId}>
                                            <SelectTrigger id="program" className="w-full" {...aria}>
                                                <SelectValue placeholder="Select program" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {selectedSchool?.programs.map((p) => (
                                                    <SelectItem key={p.id} value={String(p.id)}>
                                                        {p.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    )}
                                </FormField>
                            )}
                        </div>

                        <FormField id="purpose_of_organization" label="Purpose" error={errors.purpose_of_organization}>
                            {(aria) => (
                                <Textarea
                                    id="purpose_of_organization"
                                    value={purposeOfOrganization}
                                    onChange={(e) => setPurposeOfOrganization(e.target.value)}
                                    placeholder="What is the organization for, and what activities will it hold?"
                                    rows={4}
                                    {...aria}
                                />
                            )}
                        </FormField>
                    </FormSection>

                    <FormSection title="Adviser">
                        <FormField
                            id="adviser"
                            label="Faculty adviser"
                            error={errors.adviser_id}
                            helper="SDAO will confirm your adviser"
                        >
                            {(aria) => (
                                <AdviserPicker
                                    id="adviser"
                                    selected={selectedAdviser}
                                    onSelect={setSelectedAdviser}
                                    invalid={Boolean(aria['aria-invalid'])}
                                    describedBy={aria['aria-describedby']}
                                />
                            )}
                        </FormField>
                    </FormSection>

                    <FormSection title="Contact">
                        <FormField id="contact_person" label="Contact person" error={errors.contact_person}>
                            {(aria) => (
                                <Input
                                    id="contact_person"
                                    value={contactPerson}
                                    onChange={(e) => setContactPerson(e.target.value)}
                                    placeholder="Full name of the officer SDAO can reach"
                                    {...aria}
                                />
                            )}
                        </FormField>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField id="contact_no" label="Contact number" error={errors.contact_no}>
                                {(aria) => (
                                    <Input
                                        id="contact_no"
                                        type="tel"
                                        value={contactNo}
                                        onChange={(e) => setContactNo(e.target.value)}
                                        placeholder="e.g. 09171234567"
                                        {...aria}
                                    />
                                )}
                            </FormField>
                            <FormField id="email_address" label="Organization email" error={errors.email_address}>
                                {(aria) => (
                                    <Input
                                        id="email_address"
                                        type="email"
                                        value={emailAddress}
                                        onChange={(e) => setEmailAddress(e.target.value)}
                                        placeholder="organization@email.com"
                                        {...aria}
                                    />
                                )}
                            </FormField>
                        </div>
                    </FormSection>

                    <FormSection title="Requirements" aside={requirementsSummary(attachmentSlots, attachmentFiles)}>
                        <AttachmentRequirements
                            slots={attachmentSlots}
                            files={attachmentFiles}
                            onFileChange={(key, file) => setAttachmentFiles((prev) => ({ ...prev, [key]: file }))}
                            errors={errors}
                        />
                    </FormSection>

                    <FormFooter
                        status={
                            formValid
                                ? 'You can review everything before it’s sent'
                                : `Still needed before you can review: ${missing.join(', ')}.`
                        }
                    >
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button type="button" disabled={!formValid || processing}>
                                    Review and Submit
                                    <ArrowRight aria-hidden />
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>Submit this organization proposal?</DialogTitle>
                                <DialogDescription>
                                    SDAO will review it. The organization and your adviser choice stay pending until SDAO
                                    approves; the adviser is only bound to your organization at that point, not before.
                                </DialogDescription>
                                <dl className="grid gap-2 rounded-lg border bg-muted/30 p-3 text-sm sm:grid-cols-[auto_1fr] sm:gap-x-4">
                                    <dt className="text-muted-foreground">Organization</dt>
                                    <dd className="font-medium">{name}</dd>
                                    <dt className="text-muted-foreground">Adviser</dt>
                                    <dd className="font-medium">{selectedAdviser?.name}</dd>
                                    <dt className="text-muted-foreground">Requirements</dt>
                                    <dd className="font-medium">
                                        {uploaded} of {attachmentSlots.length} uploaded
                                    </dd>
                                </dl>
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button type="button" variant="secondary">
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button type="button" onClick={submit} disabled={processing}>
                                        {processing ? (
                                            <>
                                                <Spinner />
                                                {uploadProgress !== null ? `Uploading… ${uploadProgress}%` : 'Submitting…'}
                                            </>
                                        ) : (
                                            'Confirm Submission'
                                        )}
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </FormFooter>
                </FormCard>
            </FormShell>
        </>
    );
}

CreateRegistration.layout = {
    breadcrumbs: [
        { title: 'Registrations', href: registrations.create() },
        { title: 'New Registration' },
    ],
    columnWidth: '3xl',
};
