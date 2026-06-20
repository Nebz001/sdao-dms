import { Head, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

type RoleEntry = {
    role: string;
    label: string;
    scope: string;
};

type UserEntry = {
    id: number;
    name: string;
    email: string;
    roles: RoleEntry[];
};

type Props = {
    users: UserEntry[];
};

export default function DevLogin({ users }: Props) {
    function loginAs(userId: number) {
        router.post('/dev/login', { user_id: userId });
    }

    // Group users by their first role label for display.
    const groups = users.reduce<Record<string, UserEntry[]>>((acc, user) => {
        const label = user.roles[0]?.label ?? 'No role';

        if (!acc[label]) {
            acc[label] = [];
        }

        acc[label].push(user);

        return acc;
    }, {});

    return (
        <>
            <Head title="Dev Login" />

            <div className="mx-auto max-w-2xl p-8">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold">Dev Login</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Log in as any seeded user. This page is only available outside
                        production.
                    </p>
                </div>

                <div className="flex flex-col gap-6">
                    {Object.entries(groups).map(([roleLabel, groupUsers]) => (
                        <div key={roleLabel}>
                            <h2 className="mb-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                                {roleLabel}
                            </h2>
                            <div className="flex flex-col gap-2">
                                {groupUsers.map((user) => (
                                    <div
                                        key={user.id}
                                        className="flex items-center justify-between rounded-lg border border-border px-4 py-3"
                                    >
                                        <div>
                                            <p className="font-medium">{user.name}</p>
                                            <p className="text-sm text-muted-foreground">
                                                {user.email}
                                                {user.roles[0]?.scope &&
                                                    user.roles[0].scope !== 'Global' && (
                                                        <span className="ml-2">
                                                            — {user.roles[0].scope}
                                                        </span>
                                                    )}
                                            </p>
                                        </div>
                                        <Button
                                            size="sm"
                                            onClick={() => loginAs(user.id)}
                                        >
                                            Log in as
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}

DevLogin.layout = {
    title: 'Dev Login',
    description: 'Act as any seeded user (dev only)',
};
