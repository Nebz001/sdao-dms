import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import ApproverController from '@/actions/App/Http/Controllers/Admin/ApproverController';
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

type RoleOption = { value: string; label: string; scope_type: string };
type SchoolOption = { id: number; name: string };
type ProgramOption = { id: number; name: string; school_id: number };
type OrganizationOption = { id: number; name: string };

type Props = {
    roles: RoleOption[];
    schools: SchoolOption[];
    programs: ProgramOption[];
    organizations: OrganizationOption[];
};

export default function CreateApprover({ roles, schools, programs, organizations }: Props) {
    const [selectedRole, setSelectedRole] = useState<string>('');

    const scopeType = roles.find((r) => r.value === selectedRole)?.scope_type ?? '';

    return (
        <>
            <Head title="Provision Approver" />

            <div className="max-w-2xl space-y-6">
                <Heading
                    title="Provision Approver"
                    description="Creates the account with a working default password and emails the approver their login details."
                />

                <Form {...ApproverController.store.form()} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input id="name" name="name" required />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">School email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    placeholder="firstname.lastname@nu-lipa.edu.ph"
                                />
                                <p className="text-sm text-muted-foreground">
                                    Must be an NU Lipa staff address — their login details are emailed here.
                                </p>
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="id_number">Staff ID number (optional)</Label>
                                <Input id="id_number" type="text" name="id_number" autoComplete="off" />
                                <InputError message={errors.id_number} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="role">Role</Label>
                                <Select name="role" required onValueChange={setSelectedRole}>
                                    <SelectTrigger id="role" className="w-full">
                                        <SelectValue placeholder="Select role…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {roles.map((r) => (
                                            <SelectItem key={r.value} value={r.value}>
                                                {r.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.role} />
                            </div>

                            {scopeType === 'school' && (
                                <div className="grid gap-2">
                                    <Label htmlFor="school_id">School</Label>
                                    <Select name="school_id" required>
                                        <SelectTrigger id="school_id" className="w-full">
                                            <SelectValue placeholder="Select school…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {schools.map((s) => (
                                                <SelectItem key={s.id} value={String(s.id)}>
                                                    {s.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.school_id} />
                                </div>
                            )}

                            {scopeType === 'program' && (
                                <div className="grid gap-2">
                                    <Label htmlFor="program_id">Program</Label>
                                    <Select name="program_id" required>
                                        <SelectTrigger id="program_id" className="w-full">
                                            <SelectValue placeholder="Select program…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {programs.map((p) => (
                                                <SelectItem key={p.id} value={String(p.id)}>
                                                    {p.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.program_id} />
                                </div>
                            )}

                            {scopeType === 'organization' && (
                                <div className="grid gap-2">
                                    <Label htmlFor="organization_id">Organization (optional)</Label>
                                    <Select name="organization_id">
                                        <SelectTrigger id="organization_id" className="w-full">
                                            <SelectValue placeholder="Select organization…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {organizations.map((o) => (
                                                <SelectItem key={o.id} value={String(o.id)}>
                                                    {o.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-sm text-muted-foreground">
                                        Leave blank to create an available adviser. They&apos;ll be bound to an
                                        organization automatically once a registration naming them is approved.
                                    </p>
                                    <InputError message={errors.organization_id} />
                                </div>
                            )}

                            {scopeType === 'global' && selectedRole !== '' && (
                                <p className="text-sm text-muted-foreground">
                                    This role is global — no school, program, or organization scope needed.
                                </p>
                            )}

                            <div className="flex items-center gap-4">
                                <Button loading={processing} loadingText="Creating…">Create Approver</Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CreateApprover.layout = {
    breadcrumbs: [{ title: 'Admin' }, { title: 'Approvers' }, { title: 'Provision' }],
};
