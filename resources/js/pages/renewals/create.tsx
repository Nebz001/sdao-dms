import { Form, Head } from '@inertiajs/react';
import RenewalController from '@/actions/App/Http/Controllers/RenewalController';
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
    organization: { id: number; name: string };
};

type PriorRecord = {
    organization_type: string;
    description: string;
    contact_person: string;
    contact_number: string;
    contact_email: string;
    date_organized: string;
    roster: string[] | null;
} | null;

type Props = {
    membership: Membership | null;
    priorRecord: PriorRecord;
    alreadyRenewed: boolean;
    academicYear: string;
    organizationTypes: OrganizationTypeOption[];
};

export default function CreateRenewal({
    membership,
    priorRecord,
    alreadyRenewed,
    academicYear,
    organizationTypes,
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

                <Form {...RenewalController.store.form()} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            {/* Organization type */}
                            <div className="grid gap-2">
                                <Label htmlFor="organization_type">Organization Type</Label>
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

                            {/* Contact number */}
                            <div className="grid gap-2">
                                <Label htmlFor="contact_number">Contact Number</Label>
                                <Input
                                    id="contact_number"
                                    name="contact_number"
                                    defaultValue={priorRecord.contact_number}
                                    required
                                />
                                <InputError message={errors.contact_number} />
                            </div>

                            {/* Contact email */}
                            <div className="grid gap-2">
                                <Label htmlFor="contact_email">Contact Email</Label>
                                <Input
                                    id="contact_email"
                                    type="email"
                                    name="contact_email"
                                    defaultValue={priorRecord.contact_email}
                                    required
                                />
                                <InputError message={errors.contact_email} />
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

                            {/* Description */}
                            <div className="grid gap-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    name="description"
                                    defaultValue={priorRecord.description}
                                    rows={4}
                                    required
                                />
                                <InputError message={errors.description} />
                            </div>

                            <p className="text-sm text-muted-foreground">
                                Organization: <strong>{membership.organization.name}</strong>{' '}
                                (pre-filled)
                            </p>

                            <div className="flex items-center gap-4">
                                <Button disabled={processing}>Submit for Review</Button>
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
