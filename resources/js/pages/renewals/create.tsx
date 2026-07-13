import { Form, Head } from '@inertiajs/react';
import RenewalController from '@/actions/App/Http/Controllers/RenewalController';
import AttachmentSlotField from '@/components/attachment-slot-field';
import type {AttachmentSlotDef} from '@/components/attachment-slot-field';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

type Props = {
    membership: Membership | null;
    priorRecord: PriorRecord;
    alreadyRenewed: boolean;
    academicYear: string;
    organizationTypes: OrganizationTypeOption[];
    attachmentSlots: AttachmentSlotDef[];
};

export default function CreateRenewal({
    membership,
    priorRecord,
    alreadyRenewed,
    academicYear,
    organizationTypes,
    attachmentSlots,
}: Props) {
    if (!membership) {
        return (
            <>
                <Head title="Submit Renewal" />
                <div className="mx-auto max-w-2xl p-8">
                    <p className="text-sm text-muted-foreground">
                        You are not bound as an officer of any organization. Contact your
                        adviser to be bound before submitting a renewal.
                    </p>
                </div>
            </>
        );
    }

    if (!priorRecord) {
        return (
            <>
                <Head title="Submit Renewal" />
                <div className="mx-auto max-w-2xl p-8">
                    <p className="text-sm text-muted-foreground">
                        {membership.organization.name} has no prior approved registration to
                        renew. Submit an organization registration first.
                    </p>
                </div>
            </>
        );
    }

    if (alreadyRenewed) {
        return (
            <>
                <Head title="Submit Renewal" />
                <div className="mx-auto max-w-2xl p-8">
                    <p className="text-sm text-muted-foreground">
                        {membership.organization.name} already has a renewal on file for{' '}
                        {academicYear}. Check{' '}
                        <a href={renewals.index().url} className="underline">
                            My Renewals
                        </a>{' '}
                        for its status.
                    </p>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Submit Renewal" />

            <div className="mx-auto max-w-2xl space-y-6 p-8">
                <Heading
                    title="Organization Renewal"
                    description={`Renewing ${membership.organization.name} for ${academicYear}. Details are pre-filled from the most recent approved record — update anything that has changed.`}
                />

                {/* Organization Name / College / Program (Phase 2 item 7 slice 2) —
                    read-only field-presence parity; not editable on renewal. */}
                <div className="grid gap-1 rounded-md border p-4 text-sm">
                    <p>
                        <span className="font-medium">Organization Name:</span> {membership.organization.name}
                    </p>
                    <p>
                        <span className="font-medium">College:</span> {membership.organization.college ?? '—'}
                    </p>
                    {membership.organization.program && (
                        <p>
                            <span className="font-medium">Program:</span> {membership.organization.program}
                        </p>
                    )}
                </div>

                <Form {...RenewalController.store.form()} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            {/* Organization type */}
                            <div className="grid gap-2">
                                <Label htmlFor="organization_type">Type of Organization</Label>
                                <Select
                                    name="organization_type"
                                    defaultValue={priorRecord.organization_type}
                                    required
                                >
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
                                <InputError message={errors.organization_type} />
                            </div>

                            {/* Contact person */}
                            <div className="grid gap-2">
                                <Label htmlFor="contact_person">Contact Person</Label>
                                <Input
                                    id="contact_person"
                                    name="contact_person"
                                    defaultValue={priorRecord.contact_person}
                                    required
                                />
                                <InputError message={errors.contact_person} />
                            </div>

                            {/* Contact no. */}
                            <div className="grid gap-2">
                                <Label htmlFor="contact_no">Contact No.</Label>
                                <Input
                                    id="contact_no"
                                    name="contact_no"
                                    defaultValue={priorRecord.contact_no}
                                    required
                                />
                                <InputError message={errors.contact_no} />
                            </div>

                            {/* Email address */}
                            <div className="grid gap-2">
                                <Label htmlFor="email_address">Email Address</Label>
                                <Input
                                    id="email_address"
                                    type="email"
                                    name="email_address"
                                    defaultValue={priorRecord.email_address}
                                    required
                                />
                                <InputError message={errors.email_address} />
                            </div>

                            {/* Date organized */}
                            <div className="grid gap-2">
                                <Label htmlFor="date_organized">Date Organized</Label>
                                <Input
                                    id="date_organized"
                                    type="date"
                                    name="date_organized"
                                    defaultValue={priorRecord.date_organized}
                                    required
                                />
                                <InputError message={errors.date_organized} />
                            </div>

                            {/* Purpose of organization */}
                            <div className="grid gap-2">
                                <Label htmlFor="purpose_of_organization">Purpose of Organization</Label>
                                <Textarea
                                    id="purpose_of_organization"
                                    name="purpose_of_organization"
                                    defaultValue={priorRecord.purpose_of_organization}
                                    rows={4}
                                    required
                                />
                                <InputError message={errors.purpose_of_organization} />
                            </div>

                            {attachmentSlots.map((slot) => (
                                <AttachmentSlotField
                                    key={slot.key}
                                    slot={slot}
                                    error={errors[`attachments.${slot.key}`]}
                                />
                            ))}

                            <div className="flex items-center gap-4">
                                <Button loading={processing} loadingText="Submitting…">Submit for Review</Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CreateRenewal.layout = {
    breadcrumbs: [
        { title: 'Renewals', href: renewals.index() },
        { title: 'New Renewal' },
    ],
};
