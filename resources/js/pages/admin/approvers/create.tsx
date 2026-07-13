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

            <div className="mx-auto max-w-2xl space-y-6 p-8">
                <Heading
                    title="Provision Approver"
                    description="Creates the account and sends a password-reset link so the approver sets their own password."
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
                                <Label htmlFor="email">Email</Label>
                                <Input id="email" type="email" name="email" required />
                                <InputError message={errors.email} />
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
                                    <Label htmlFor="organization_id">Organization</Label>
                                    <Select name="organization_id" required>
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
