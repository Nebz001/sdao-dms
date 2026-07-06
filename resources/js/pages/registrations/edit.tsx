import { Form, Head } from '@inertiajs/react';
import RegistrationController from '@/actions/App/Http/Controllers/RegistrationController';
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

type OrganizationTypeOption = {
    value: string;
    label: string;
};

type DocumentData = { id: number; title: string };

type DetailData = {
    organization_type: string;
    description: string;
    contact_person: string;
    contact_number: string;
    contact_email: string;
    date_organized: string;
    roster: string[] | null;
} | null;

type Props = {
    document: DocumentData;
    detail: DetailData;
    organizationTypes: OrganizationTypeOption[];
};

export default function EditRegistration({ document, detail, organizationTypes }: Props) {
    return (
        <>
            <Head title="Edit Registration" />

            <div className="mx-auto max-w-2xl space-y-6 p-8">
                <Heading
                    title="Edit & Resubmit Registration"
                    description="Update the details below and resubmit for SDAO review."
                />

                <Form
                    {...RegistrationController.update.form({ document: document.id })}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            {/* Organization type */}
                            <div className="grid gap-2">
                                <Label htmlFor="organization_type">Organization Type</Label>
                                <Select
                                    name="organization_type"
                                    defaultValue={detail?.organization_type}
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
                                    defaultValue={detail?.contact_person}
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
                                    defaultValue={detail?.contact_number}
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
                                    defaultValue={detail?.contact_email}
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
                                    defaultValue={detail?.date_organized}
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
                                    defaultValue={detail?.description}
                                    rows={4}
                                    required
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button disabled={processing}>Save &amp; Resubmit</Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

EditRegistration.layout = {
    breadcrumbs: [{ title: 'Registrations' }, { title: 'Edit' }],
};
