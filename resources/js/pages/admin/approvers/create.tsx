import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import ApproverController from '@/actions/App/Http/Controllers/Admin/ApproverController';
import CenteredContainer from '@/components/centered-container';
import ConfirmDialog from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import PageNotice from '@/components/page-notice';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
type OrganizationOption = {
    id: number;
    name: string;
    /** The organization's current adviser, who a new adviser would replace. */
    adviser: { id: number; name: string } | null;
};
type SdaoMemberOption = { id: number; name: string; email: string };

type Props = {
    roles: RoleOption[];
    schools: SchoolOption[];
    programs: ProgramOption[];
    organizations: OrganizationOption[];
    sdaoMembers: SdaoMemberOption[];
    /** From the "Create adviser account" link on an organization page. */
    preset: { role: string | null; organization_id: number | null };
};

const SDAO_ROLE = 'sdao_member';
const ADVISER_ROLE = 'adviser';
const NO_REPLACEMENT = 'none';

export default function CreateApprover({ roles, schools, programs, organizations, sdaoMembers, preset }: Props) {
    const [confirmOpen, setConfirmOpen] = useState(false);

    const form = useForm({
        first_name: '',
        last_name: '',
        email: '',
        id_number: '',
        role: preset.role ?? '',
        school_id: '',
        program_id: '',
        organization_id:
            preset.role === ADVISER_ROLE && preset.organization_id !== null ? String(preset.organization_id) : '',
        replaces_user_id: NO_REPLACEMENT,
        deactivate_replaced: true,
        deactivate_outgoing_adviser: false,
    });

    const selectedRole = roles.find((r) => r.value === form.data.role);
    const scopeType = selectedRole?.scope_type ?? '';
    const replacedMember =
        form.data.role === SDAO_ROLE
            ? sdaoMembers.find((m) => String(m.id) === form.data.replaces_user_id)
            : undefined;
    // An adviser created for an organization takes over from its current
    // adviser, if it has one.
    const selectedOrganization =
        form.data.role === ADVISER_ROLE
            ? organizations.find((o) => String(o.id) === form.data.organization_id)
            : undefined;
    const outgoingAdviser = selectedOrganization?.adviser ?? null;
    // The swap's own refusal (stale page, a collision) comes back under
    // `adviser`, which is not one of this form's fields.
    const adviserError = (form.errors as Record<string, string | undefined>).adviser;

    const handleRoleChange = (role: string) => {
        form.setData((data) => ({
            ...data,
            role,
            school_id: '',
            program_id: '',
            organization_id: '',
            replaces_user_id: NO_REPLACEMENT,
            deactivate_replaced: true,
            deactivate_outgoing_adviser: false,
        }));
    };

    // The native submit handler only runs once the browser's own required and
    // email checks pass, so the dialog never opens over an incomplete form.
    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setConfirmOpen(true);
    };

    const create = (close: () => void) => {
        form.transform(({ deactivate_outgoing_adviser, ...data }) => ({
            ...data,
            replaces_user_id:
                data.role === SDAO_ROLE && data.replaces_user_id !== NO_REPLACEMENT
                    ? data.replaces_user_id
                    : null,
            deactivate_replaced: data.deactivate_replaced,
            // Only for an adviser created for an organization: what happens to
            // the current adviser, and which adviser this page showed, so the
            // server refuses if it changed in the meantime.
            ...(data.role === ADVISER_ROLE && data.organization_id !== ''
                ? {
                      outgoing_adviser: deactivate_outgoing_adviser ? 'deactivated' : 'returned_to_pool',
                      current_adviser_id: outgoingAdviser?.id ?? null,
                  }
                : {}),
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
                <PageHeader title="Provision Approver" subtitle="Creates the account with a one time password and emails it to the approver. They must change it the first time they log in." />

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid gap-6 sm:grid-cols-2 sm:gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="first_name">First name</Label>
                            <Input
                                id="first_name"
                                name="first_name"
                                required
                                maxLength={100}
                                autoComplete="off"
                                value={form.data.first_name}
                                onChange={(e) => form.setData('first_name', e.target.value)}
                                aria-invalid={form.errors.first_name ? true : undefined}
                            />
                            <InputError message={form.errors.first_name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="last_name">Last name</Label>
                            <Input
                                id="last_name"
                                name="last_name"
                                required
                                maxLength={100}
                                autoComplete="off"
                                value={form.data.last_name}
                                onChange={(e) => form.setData('last_name', e.target.value)}
                                aria-invalid={form.errors.last_name ? true : undefined}
                            />
                            <InputError message={form.errors.last_name} />
                        </div>
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
                            Any address they can receive mail at. Their one time password is emailed here. Student
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
                            <InputError message={adviserError} />

                            {outgoingAdviser !== null && (
                                <div className="mt-2 flex flex-col gap-3">
                                    <PageNotice
                                        tone="warning"
                                        title={`${outgoingAdviser.name} stops being the adviser of ${selectedOrganization?.name}.`}
                                    >
                                        The new adviser takes over their pending documents and join requests. They
                                        are told their role ended, and the organization&apos;s officers are told who
                                        the new adviser is.
                                    </PageNotice>
                                    <div className="flex items-start gap-3">
                                        <Checkbox
                                            id="deactivate_outgoing_adviser"
                                            checked={form.data.deactivate_outgoing_adviser}
                                            onCheckedChange={(checked) =>
                                                form.setData('deactivate_outgoing_adviser', checked === true)
                                            }
                                        />
                                        <div className="grid gap-1">
                                            <Label htmlFor="deactivate_outgoing_adviser">
                                                Also deactivate {outgoingAdviser.name}&apos;s account
                                            </Label>
                                            <p className="text-sm text-muted-foreground">
                                                {form.data.deactivate_outgoing_adviser
                                                    ? 'They are signed out everywhere and can no longer log in. You can reactivate them later.'
                                                    : 'Left unchecked, they return to the pool of unassigned advisers and keep their account.'}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}
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
                                The replaced member loses the SDAO role and pending documents move to the new member.
                                Their account and document history are never deleted.
                            </p>
                            <InputError message={form.errors.replaces_user_id} />

                            {replacedMember !== undefined && (
                                <div className="mt-2 flex items-start gap-3">
                                    <Checkbox
                                        id="deactivate_replaced"
                                        checked={form.data.deactivate_replaced}
                                        onCheckedChange={(checked) => form.setData('deactivate_replaced', checked === true)}
                                    />
                                    <div className="grid gap-1">
                                        <Label htmlFor="deactivate_replaced">Also deactivate this account</Label>
                                        <p className="text-sm text-muted-foreground">
                                            Signs them out everywhere and stops them logging in. You can reactivate them later.
                                        </p>
                                    </div>
                                </div>
                            )}
                            <InputError message={form.errors.deactivate_replaced} />
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
                                {form.data.first_name} {form.data.last_name} will be added as {selectedRole?.label}. Their one time password will
                                be emailed to:
                            </span>
                            <span className="block py-2 font-medium break-all text-foreground">
                                {form.data.email}
                            </span>
                            </>
                    }
                    notice={
                        <div className="flex flex-col gap-2">
                            <PageNotice tone="warning" title="Check the address carefully.">
                                A typo sends the temporary password to the wrong person.
                            </PageNotice>
                            {outgoingAdviser !== null && (
                                <PageNotice
                                    tone="info"
                                    title={`Replace ${outgoingAdviser.name} as adviser of ${selectedOrganization?.name}?`}
                                >
                                    {form.data.deactivate_outgoing_adviser
                                        ? 'They will also be deactivated and can no longer log in. Their history stays and keeps showing their name.'
                                        : 'They go back to the pool of unassigned advisers and keep their account.'}
                                </PageNotice>
                            )}
                            {replacedMember !== undefined && (
                                <PageNotice tone="info" title={`Remove SDAO role from ${replacedMember.name}?`}>
                                    {form.data.deactivate_replaced
                                        ? 'They will also be deactivated and can no longer log in. Their history stays and keeps showing their name.'
                                        : 'They keep their account and history but can no longer review documents.'}
                                </PageNotice>
                            )}
                        </div>
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
