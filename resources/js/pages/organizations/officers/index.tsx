import { Form, Head, router } from '@inertiajs/react';
import { Users } from 'lucide-react';
import {  useState } from 'react';
import type {FormEvent} from 'react';
import OrganizationOfficerController from '@/actions/App/Http/Controllers/OrganizationOfficerController';
import ConfirmDialog from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';
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
import * as officers from '@/routes/officers';

type Organization = { id: number; name: string };

type MembershipEntry = {
    id: number;
    user: { id: number; name: string; email: string };
    position: string;
    position_label: string;
    academic_year: string;
};

type StudentEntry = { id: number; name: string; email: string };

type PositionOption = { value: string; label: string };

type Props = {
    organization: Organization;
    memberships: MembershipEntry[];
    students: StudentEntry[];
    search: string;
    positions: PositionOption[];
};

export default function OfficersIndex({ organization, memberships, students, search, positions }: Props) {
    const [searchValue, setSearchValue] = useState(search);
    const [deactivateError, setDeactivateError] = useState<string | null>(null);
    // Officer search is an explicit-submit round trip (not a live typeahead
    // like the adviser search), so without this the box appears to do
    // nothing while the request is in flight, and an empty result gives no
    // explanation at all — the exact gap QA reported. Mirrors the
    // searching/done status pattern already established for adviser search
    // (registrations/create.tsx). Starts as 'done' when the page itself
    // loaded with a search term already applied (e.g. a refresh), so the
    // empty-state message is correct immediately, not just after a fresh click.
    const [searchStatus, setSearchStatus] = useState<'idle' | 'searching' | 'done'>(search !== '' ? 'done' : 'idle');

    function deactivate(membershipId: number) {
        setDeactivateError(null);
        router.delete(officers.destroy(organization.id, membershipId), {
            onError: () => setDeactivateError('Could not deactivate this officer. Please try again.'),
        });
    }

    function runSearch(e: FormEvent) {
        e.preventDefault();
        setSearchStatus('searching');
        router.get(
            officers.index({ organization: organization.id }).url,
            { search: searchValue },
            {
                preserveState: true,
                only: ['students', 'search'],
                onFinish: () => setSearchStatus('done'),
            },
        );
    }

    return (
        <>
            <Head title={`Officers — ${organization.name}`} />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-balance">
                        Officers — {organization.name}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Manage active officers for the current academic year.
                    </p>
                </div>

                {/* Current active officers */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Active Officers</CardTitle>
                    </CardHeader>
                    <CardContent className="divide-y">
                        {deactivateError && (
                            <p className="pb-3 text-sm text-destructive">{deactivateError}</p>
                        )}
                        {memberships.length === 0 ? (
                            <Empty>
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <Users />
                                    </EmptyMedia>
                                    <EmptyTitle>No active officers bound yet</EmptyTitle>
                                    <EmptyDescription>
                                        Bind a president or secretary below to get started.
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            memberships.map((m) => (
                                <div
                                    key={m.id}
                                    className="flex items-center justify-between gap-4 py-3"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">{m.user.name}</p>
                                        <p className="truncate text-sm text-muted-foreground">
                                            {m.user.email} · {m.position_label} · {m.academic_year}
                                        </p>
                                    </div>
                                    <ConfirmDialog
                                        trigger={
                                            <Button size="sm" variant="outline">
                                                Deactivate
                                            </Button>
                                        }
                                        title={`Deactivate ${m.user.name}?`}
                                        description="This removes their ability to submit or act on documents for this organization. Their history is retained, and the adviser can bind a replacement afterward."
                                        confirmLabel="Deactivate"
                                        confirmVariant="destructive"
                                        onConfirm={() => deactivate(m.id)}
                                    />
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                {/* Bind new officer */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Bind Officer</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <form onSubmit={runSearch} className="flex items-end gap-2">
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="student-search">Find student by name or email</Label>
                                <Input
                                    id="student-search"
                                    value={searchValue}
                                    onChange={(e) => {
                                        setSearchValue(e.target.value);
                                        setSearchStatus('idle');
                                    }}
                                    placeholder="e.g. juan@student.nu-lipa.edu.ph"
                                />
                            </div>
                            <Button type="submit" variant="outline">
                                Search
                            </Button>
                        </form>

                        {searchStatus === 'searching' && (
                            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Spinner className="size-3.5" /> Searching students…
                            </p>
                        )}
                        {searchStatus === 'done' && searchValue.trim() !== '' && students.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No matching students found. Only Verified accounts can be bound — if this student
                                just registered, ask SDAO to verify them from Pending Accounts first.
                            </p>
                        )}

                        <Form
                            {...OrganizationOfficerController.store.form({
                                organization: organization.id,
                            })}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="user_id">Student</Label>
                                        <Select name="user_id" required>
                                            <SelectTrigger id="user_id" className="w-full">
                                                <SelectValue placeholder="Select student…" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {students.length === 0 ? (
                                                    <div className="px-2 py-1.5 text-sm text-muted-foreground">
                                                        No matching students found.
                                                    </div>
                                                ) : (
                                                    students.map((s) => (
                                                        <SelectItem
                                                            key={s.id}
                                                            value={String(s.id)}
                                                        >
                                                            {s.name} ({s.email})
                                                        </SelectItem>
                                                    ))
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.user_id} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="position">Position</Label>
                                        <Select name="position" required>
                                            <SelectTrigger id="position" className="w-full">
                                                <SelectValue placeholder="Select position…" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {positions.map((p) => (
                                                    <SelectItem key={p.value} value={p.value}>
                                                        {p.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.position} />
                                    </div>

                                    <Button loading={processing} loadingText="Binding…">Bind Officer</Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

OfficersIndex.layout = {
    breadcrumbs: [{ title: 'Organizations' }, { title: 'Officers' }],
};
