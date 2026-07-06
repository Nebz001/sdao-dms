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
import * as registrations from '@/routes/registrations';

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

type Props = {
    membership: Membership | null;
    organizationTypes: OrganizationTypeOption[];
};

export default function CreateRegistration({ membership, organizationTypes }: Props) {
    if (!membership) {
        return (
            <>
                <Head title="Submit Registration" />
                <div className="mx-auto max-w-2xl p-8">
                    <p className="text-sm text-muted-foreground">
                        You are not bound as an officer of any organization. Contact your
                        adviser to be bound before submitting a registration.
                    </p>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Submit Registration" />

            <div className="mx-auto max-w-2xl space-y-6 p-8">
                <Heading
                    title="Organization Registration"
                    description={`Submitting for ${membership.organization.name} as ${membership.position_label}`}
                />

                <Form {...RegistrationController.store.form()} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            {/* Organization type */}
                            <div className="grid gap-2">
                                <Label htmlFor="organization_type">Organization Type</Label>
                                <Select name="organization_type" required>
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
                                    placeholder="Full name of contact officer"
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
                                    placeholder="e.g. 09171234567"
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
                                    placeholder="organization@email.com"
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
                                    placeholder="Brief description of the organization's purpose and activities…"
                                    rows={4}
                                    required
                                />
                                <InputError message={errors.description} />
                            </div>

                            {/* Roster (read-only org shown separately) */}
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

CreateRegistration.layout = {
    breadcrumbs: [
        { title: 'Registrations', href: registrations.create() },
        { title: 'New Registration' },
    ],
};
