import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import ApproverController from '@/actions/App/Http/Controllers/Admin/ApproverController';
import CenteredContainer from '@/components/centered-container';
import ConfirmDialog from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type RoleOption = { value: string; label: string; scope_type: string };
type SchoolOption = { id: number; name: string };
type ProgramOption = { id: number; name: string; school_id: number };
type OrganizationOption = { id: number; name: string };
type SdaoMemberOption = { id: number; name: string; email: string };

type Props = {
    roles: RoleOption[];
    schools: SchoolOption[];
    programs: ProgramOption[];
    organizations: OrganizationOption[];
    sdaoMembers: SdaoMemberOption[];
};

const SDAO_ROLE = 'sdao_member';
const NO_REPLACEMENT = 'none';

export default function CreateApprover({ roles, schools, programs, organizations, sdaoMembers }: Props) {
    const [confirmOpen, setConfirmOpen] = useState(false);

    const form = useForm({
        name: '',
        email: '',
        id_number: '',
        role: '',
        school_id: '',
        program_id: '',
        organization_id: '',
        replaces_user_id: NO_REPLACEMENT,
    });

    const selectedRole = roles.find((r) => r.value === form.data.role);
    const scopeType = selectedRole?.scope_type ?? '';
    const replacedMember =
        form.data.role === SDAO_ROLE
            ? sdaoMembers.find((m) => String(m.id) === form.data.replaces_user_id)
            : undefined;

    const handleRoleChange = (role: string) => {
        form.setData((data) => ({
            ...data,
            role,
            school_id: '',
            program_id: '',
            organization_id: '',
            replaces_user_id: NO_REPLACEMENT,
        }));
    };

    // The native submit handler only runs once the browser's own required and
    // email checks pass, so the dialog never opens over an incomplete form.
    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setConfirmOpen(true);
    };

    const create = (close: () => void) => {
        form.transform((data) => ({
            ...data,
            replaces_user_id:
                data.role === SDAO_ROLE && data.replaces_user_id !== NO_REPLACEMENT
                    ? data.replaces_user_id
                    : null,
        }));

        form.post(ApproverController.store.url(), {
            preserveScroll: true,
            // Success redirects away; on a validation error the dialog closes so the
            // inline errors on the form are visible.
            onFinish: close,
        });
    };

    return (
        <>
            <Head title="Provision Approver" />

            <CenteredContainer maxWidth="2xl" className="space-y-6">
                <Heading
                    title="Provision Approver"
                    description="Creates the account with a working default password and emails the approver their login details."
                />

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            required
                            autoComplete="off"
                            placeholder="name@example.com"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                        />
                        <p className="text-sm text-muted-foreground">
                            Any address they can receive mail at. Their login details are emailed here. Student
                            addresses are not allowed.
                        </p>
                        <InputError message={form.errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="id_number">Staff ID number (optional)</Label>
                        <Input
                            id="id_number"
                            type="text"
                            name="id_number"
                            autoComplete="off"
                            value={form.data.id_number}
                            onChange={(e) => form.setData('id_number', e.target.value)}
                        />
                        <InputError message={form.errors.id_number} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="role">Role</Label>
                        <Select name="role" required value={form.data.role} onValueChange={handleRoleChange}>
                            <SelectTrigger id="role" className="w-full">
                                <SelectValue placeholder="Select role…" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    {roles.map((r) => (
                                        <SelectItem key={r.value} value={r.value}>
                                            {r.label}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.role} />
                    </div>

                    {scopeType === 'school' && (
                        <div className="grid gap-2">
                            <Label htmlFor="school_id">School</Label>
                            <Select
                                name="school_id"
                                required
                                value={form.data.school_id}
                                onValueChange={(value) => form.setData('school_id', value)}
                            >
                                <SelectTrigger id="school_id" className="w-full">
                                    <SelectValue placeholder="Select school…" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {schools.map((s) => (
                                            <SelectItem key={s.id} value={String(s.id)}>
                                                {s.name}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.school_id} />
                        </div>
                    )}

                    {scopeType === 'program' && (
                        <div className="grid gap-2">
                            <Label htmlFor="program_id">Program</Label>
                            <Select
                                name="program_id"
                                required
                                value={form.data.program_id}
                                onValueChange={(value) => form.setData('program_id', value)}
                            >
                                <SelectTrigger id="program_id" className="w-full">
                                    <SelectValue placeholder="Select program…" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {programs.map((p) => (
                                            <SelectItem key={p.id} value={String(p.id)}>
                                                {p.name}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.program_id} />
                        </div>
                    )}

                    {scopeType === 'organization' && (
                        <div className="grid gap-2">
                            <Label htmlFor="organization_id">Organization (optional)</Label>
                            <Select
                                name="organization_id"
                                value={form.data.organization_id}
                                onValueChange={(value) => form.setData('organization_id', value)}
                            >
                                <SelectTrigger id="organization_id" className="w-full">
                                    <SelectValue placeholder="Select organization…" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {organizations.map((o) => (
                                            <SelectItem key={o.id} value={String(o.id)}>
                                                {o.name}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <p className="text-sm text-muted-foreground">
                                Leave blank to create an available adviser. They&apos;ll be bound to an
                                organization automatically once a registration naming them is approved.
                            </p>
                            <InputError message={form.errors.organization_id} />
                        </div>
                    )}

                    {scopeType === 'global' && form.data.role !== '' && (
                        <p className="text-sm text-muted-foreground">
                            This role is global. No school, program, or organization scope is needed.
                        </p>
                    )}

                    {form.data.role === SDAO_ROLE && (
                        <div className="grid gap-2">
                            <Label htmlFor="replaces_user_id">Replaces current SDAO member (optional)</Label>
                            <Select
                                name="replaces_user_id"
                                value={form.data.replaces_user_id}
                                onValueChange={(value) => form.setData('replaces_user_id', value)}
                            >
                                <SelectTrigger id="replaces_user_id" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value={NO_REPLACEMENT}>No one, add as an extra member</SelectItem>
                                        {sdaoMembers.map((m) => (
                                            <SelectItem key={m.id} value={String(m.id)}>
                                                {m.name} ({m.email})
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <p className="text-sm text-muted-foreground">
                                The replaced member keeps their account and document history. They only lose the
                                SDAO role, and pending documents move to the new member.
                            </p>
                            <InputError message={form.errors.replaces_user_id} />
                        </div>
                    )}

                    <div className="flex items-center gap-4">
                        <Button type="submit" loading={form.processing} loadingText="Creating…">
                            Create approver
                        </Button>
                    </div>
                </form>

                <ConfirmDialog
                    open={confirmOpen}
                    onOpenChange={setConfirmOpen}
                    title="Create this approver account?"
                    description={
                        <>
                            <span className="block">
                                {form.data.name} will be added as {selectedRole?.label}. Their login details will be
                                emailed to:
                            </span>
                            <span className="block py-2 font-medium break-all text-foreground">
                                {form.data.email}
                            </span>
                            <span className="block">
                                Check the address carefully. A typo sends the temporary password to the wrong person.
                            </span>
                            {replacedMember !== undefined && (
                                <span className="mt-3 block">
                                    <span className="block font-medium text-foreground">
                                        Remove SDAO role from {replacedMember.name}?
                                    </span>
                                    <span className="block">
                                        They keep their account and history but can no longer review documents.
                                    </span>
                                </span>
                            )}
                        </>
                    }
                    confirmLabel="Create approver"
                    onConfirm={({ close }) => create(close)}
                />
            </CenteredContainer>
        </>
    );
}

CreateApprover.layout = {
    breadcrumbs: [{ title: 'Admin' }, { title: 'Approvers' }, { title: 'Provision' }],
};
