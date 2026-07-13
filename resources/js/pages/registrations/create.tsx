import { Head, router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import AttachmentSlotField from '@/components/attachment-slot-field';
import type {AttachmentSlotDef} from '@/components/attachment-slot-field';
import Heading from '@/components/heading';
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
import { Label } from '@/components/ui/label';
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
type AdviserResult = { id: number; name: string; email: string; is_available: boolean };

type Props = {
    canPropose: boolean;
    schools: SchoolOption[];
    organizationTypes: OrganizationTypeOption[];
    attachmentSlots: AttachmentSlotDef[];
};

export default function CreateRegistration({ canPropose, schools, organizationTypes, attachmentSlots }: Props) {
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

    const [adviserQuery, setAdviserQuery] = useState('');
    const [adviserResults, setAdviserResults] = useState<AdviserResult[]>([]);
    const [selectedAdviser, setSelectedAdviser] = useState<AdviserResult | null>(null);
    const debounceTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const selectedSchool = schools.find((s) => String(s.id) === schoolId);
    const needsProgram = selectedSchool?.type === 'regular';

    const searchAdvisers = useCallback((query: string) => {
        if (query.trim() === '') {
            setAdviserResults([]);

            return;
        }

        fetch(registrations.adviserSearch.url({ query: { q: query } }), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((res) => res.json())
            .then((data) => setAdviserResults(data.advisers ?? []))
            .catch(() => {});
    }, []);

    useEffect(() => {
        if (debounceTimer.current) {
            clearTimeout(debounceTimer.current);
        }

        debounceTimer.current = setTimeout(() => searchAdvisers(adviserQuery), 600);

        return () => {
            if (debounceTimer.current) {
                clearTimeout(debounceTimer.current);
            }
        };
    }, [adviserQuery, searchAdvisers]);

    function selectAdviser(adviser: AdviserResult) {
        setSelectedAdviser(adviser);
        setAdviserResults([]);
        setAdviserQuery(adviser.name);
    }

    function submit() {
        setProcessing(true);
        setErrors({});

        router.post(
            registrations.store().url,
            {
                name,
                school_id: schoolId,
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
                onError: (errs) => setErrors(errs as Record<string, string>),
                onFinish: () => setProcessing(false),
            },
        );
    }

    if (!canPropose) {
        return (
            <>
                <Head title="Submit Registration" />
                <div className="mx-auto max-w-2xl p-8">
                    <p className="text-sm text-muted-foreground">
                        You already have an active organization or an in-progress registration.
                        You cannot propose another organization at this time.
                    </p>
                </div>
            </>
        );
    }

    const formValid =
        name.trim() !== '' &&
        schoolId !== '' &&
        (!needsProgram || programId !== '') &&
        selectedAdviser !== null &&
        organizationType !== '' &&
        purposeOfOrganization.trim() !== '' &&
        contactPerson.trim() !== '' &&
        contactNo.trim() !== '' &&
        emailAddress.trim() !== '' &&
        dateOrganized !== '' &&
        attachmentSlots.every((slot) => !slot.required || attachmentFiles[slot.key]);

    return (
        <>
            <Head title="Submit Registration" />

            <div className="mx-auto max-w-2xl space-y-6 p-8">
                <Heading
                    title="Propose a New Organization"
                    description="Found a brand-new student organization. SDAO reviews and approves your choice of adviser."
                />

                <div className="space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Organization Name</Label>
                        <Input id="name" value={name} onChange={(e) => setName(e.target.value)} required />
                        {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="college">College</Label>
                        <Select
                            value={schoolId}
                            onValueChange={(value) => {
                                setSchoolId(value);
                                setProgramId('');
                            }}
                        >
                            <SelectTrigger id="college" className="w-full">
                                <SelectValue placeholder="Select college…" />
                            </SelectTrigger>
                            <SelectContent>
                                {schools.map((s) => (
                                    <SelectItem key={s.id} value={String(s.id)}>
                                        {s.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.school_id && <p className="text-sm text-destructive">{errors.school_id}</p>}
                    </div>

                    {needsProgram && (
                        <div className="grid gap-2">
                            <Label htmlFor="program">Program</Label>
                            <Select value={programId} onValueChange={setProgramId}>
                                <SelectTrigger id="program" className="w-full">
                                    <SelectValue placeholder="Select program…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {selectedSchool?.programs.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {p.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.program_id && <p className="text-sm text-destructive">{errors.program_id}</p>}
                        </div>
                    )}

                    {/* Adviser typeahead (Phase 2 item 5) */}
                    <div className="grid gap-2">
                        <Label htmlFor="adviser">Adviser</Label>
                        <Input
                            id="adviser"
                            placeholder="Search adviser by name or email…"
                            value={adviserQuery}
                            onChange={(e) => {
                                setAdviserQuery(e.target.value);
                                setSelectedAdviser(null);
                            }}
                            autoComplete="off"
                        />
                        {adviserResults.length > 0 && (
                            <div className="rounded-md border divide-y">
                                {adviserResults.map((a) => (
                                    <button
                                        key={a.id}
                                        type="button"
                                        onClick={() => selectAdviser(a)}
                                        className="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-accent"
                                    >
                                        <span>
                                            {a.name} <span className="text-muted-foreground">({a.email})</span>
                                        </span>
                                        {!a.is_available && (
                                            <span className="text-xs text-yellow-700 dark:text-yellow-400">Assigned elsewhere</span>
                                        )}
                                    </button>
                                ))}
                            </div>
                        )}
                        {selectedAdviser && !selectedAdviser.is_available && (
                            <div className="rounded-md bg-yellow-50 p-3 text-sm text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400">
                                ⚠ This adviser is already assigned to another organization — you may still
                                submit, but SDAO will need a different adviser to approve this.
                            </div>
                        )}
                        {errors.adviser_id && <p className="text-sm text-destructive">{errors.adviser_id}</p>}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="organization_type">Type of Organization</Label>
                        <Select value={organizationType} onValueChange={setOrganizationType}>
                            <SelectTrigger id="organization_type" className="w-full">
                                <SelectValue placeholder="Select type…" />
                            </SelectTrigger>
                            <SelectContent>
                                {organizationTypes.map((t) => (
                                    <SelectItem key={t.value} value={t.value}>
                                        {t.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.organization_type && <p className="text-sm text-destructive">{errors.organization_type}</p>}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="contact_person">Contact Person</Label>
                        <Input
                            id="contact_person"
                            value={contactPerson}
                            onChange={(e) => setContactPerson(e.target.value)}
                            placeholder="Full name of contact officer"
                            required
                        />
                        {errors.contact_person && <p className="text-sm text-destructive">{errors.contact_person}</p>}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="contact_no">Contact No.</Label>
                        <Input
                            id="contact_no"
                            value={contactNo}
                            onChange={(e) => setContactNo(e.target.value)}
                            placeholder="e.g. 09171234567"
                            required
                        />
                        {errors.contact_no && <p className="text-sm text-destructive">{errors.contact_no}</p>}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email_address">Email Address</Label>
                        <Input
                            id="email_address"
                            type="email"
                            value={emailAddress}
                            onChange={(e) => setEmailAddress(e.target.value)}
                            placeholder="organization@email.com"
                            required
                        />
                        {errors.email_address && <p className="text-sm text-destructive">{errors.email_address}</p>}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="date_organized">Date Organized</Label>
                        <Input
                            id="date_organized"
                            type="date"
                            value={dateOrganized}
                            onChange={(e) => setDateOrganized(e.target.value)}
                            required
                        />
                        {errors.date_organized && <p className="text-sm text-destructive">{errors.date_organized}</p>}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="purpose_of_organization">Purpose of Organization</Label>
                        <Textarea
                            id="purpose_of_organization"
                            value={purposeOfOrganization}
                            onChange={(e) => setPurposeOfOrganization(e.target.value)}
                            placeholder="Brief description of the organization's purpose and activities…"
                            rows={4}
                            required
                        />
                        {errors.purpose_of_organization && (
                            <p className="text-sm text-destructive">{errors.purpose_of_organization}</p>
                        )}
                    </div>

                    {attachmentSlots.map((slot) => (
                        <AttachmentSlotField
                            key={slot.key}
                            slot={slot}
                            error={errors[`attachments.${slot.key}`]}
                            onFilesChange={(files) =>
                                setAttachmentFiles((prev) => ({ ...prev, [slot.key]: files?.[0] ?? null }))
                            }
                        />
                    ))}

                    <Dialog>
                        <DialogTrigger asChild>
                            <Button type="button" disabled={!formValid || processing}>
                                Review & Submit
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>Submit this organization proposal?</DialogTitle>
                            <DialogDescription>
                                SDAO will review <strong>{name}</strong> and your chosen adviser,{' '}
                                <strong>{selectedAdviser?.name}</strong>. Once submitted, the organization and
                                adviser choice are pending until SDAO approves — the adviser is only actually
                                bound to your organization at that point, not before.
                            </DialogDescription>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="button" onClick={submit} disabled={processing}>
                                    {processing ? (
                                        <>
                                            <Spinner /> Submitting…
                                        </>
                                    ) : (
                                        'Confirm Submission'
                                    )}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </>
    );
}

CreateRegistration.layout = {
    breadcrumbs: [
        { title: 'Registrations', href: registrations.create() },
        { title: 'New Registration' },
    ],
};
