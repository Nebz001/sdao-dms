import { Form, Head, router } from '@inertiajs/react';
import OrganizationOfficerController from '@/actions/App/Http/Controllers/OrganizationOfficerController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
    positions: PositionOption[];
};

export default function OfficersIndex({ organization, memberships, students, positions }: Props) {
    function deactivate(membershipId: number) {
        router.delete(officers.destroy(organization.id, membershipId));
    }

    return (
        <>
            <Head title={`Officers — ${organization.name}`} />

            <div className="mx-auto max-w-2xl space-y-6 p-8">
                <div>
                    <h1 className="text-xl font-semibold">Officers — {organization.name}</h1>
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
                        {memberships.length === 0 ? (
                            <p className="py-2 text-sm text-muted-foreground">
                                No active officers bound yet.
                            </p>
                        ) : (
                            memberships.map((m) => (
                                <div
                                    key={m.id}
                                    className="flex items-center justify-between py-3"
                                >
                                    <div>
                                        <p className="font-medium">{m.user.name}</p>
                                        <p className="text-sm text-muted-foreground">
                                            {m.user.email} · {m.position_label} · {m.academic_year}
                                        </p>
                                    </div>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => deactivate(m.id)}
                                    >
                                        Deactivate
                                    </Button>
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
                    <CardContent>
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
                                                {students.map((s) => (
                                                    <SelectItem
                                                        key={s.id}
                                                        value={String(s.id)}
                                                    >
                                                        {s.name} ({s.email})
                                                    </SelectItem>
                                                ))}
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

                                    <Button disabled={processing}>Bind Officer</Button>
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
