import { Form, Head } from '@inertiajs/react';
import { FolderPlus, Users } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    passwordRules: string;
};

type IntendedPath = 'register_new' | 'join_existing';

/**
 * NU Lipa student IDs are a fixed shape: 4-digit year, dash, 6-digit number
 * (e.g. 2023-182854). Strips everything but digits, caps at 10 digits, and
 * inserts the dash after the 4th — so the field behaves like a formatted
 * code entry rather than free text.
 */
function formatIdNumber(raw: string): string {
    const digits = raw.replace(/\D/g, '').slice(0, 10);

    return digits.length <= 4 ? digits : `${digits.slice(0, 4)}-${digits.slice(4)}`;
}

export default function Register({ passwordRules }: Props) {
    const [idNumber, setIdNumber] = useState('');
    // Rides along as one more key in EmailVerificationCode's encrypted
    // payload (see App\Http\Controllers\Auth\RegistrationController::store()),
    // exactly like name/password/id_number already do — no account exists
    // yet to persist it on. The only thing it ever changes is verifyStore()'s
    // post-verification redirect: "register_new" (or missing) lands on the
    // dashboard exactly as before; "join_existing" lands directly on the
    // organization search page instead.
    const [intendedPath, setIntendedPath] =
        useState<IntendedPath>('register_new');

    return (
        <>
            <Head title="Register" />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label>How do you want to get started?</Label>
                                <ToggleGroup
                                    type="single"
                                    variant="outline"
                                    value={intendedPath}
                                    onValueChange={(value) => {
                                        if (value) {
                                            setIntendedPath(
                                                value as IntendedPath,
                                            );
                                        }
                                    }}
                                    className="grid w-full grid-cols-1 gap-3 sm:grid-cols-2"
                                >
                                    <ToggleGroupItem
                                        value="register_new"
                                        aria-label="Register a new organization"
                                        className="h-auto flex-col items-start gap-1.5 rounded-md! border! p-4 text-left whitespace-normal data-[state=on]:border-primary data-[state=on]:ring-1 data-[state=on]:ring-primary"
                                    >
                                        <FolderPlus className="size-5 text-muted-foreground" />
                                        <span className="text-sm font-semibold">
                                            Register a new organization
                                        </span>
                                        <span className="text-xs font-normal text-muted-foreground">
                                            Found a brand-new org for SDAO to
                                            review.
                                        </span>
                                    </ToggleGroupItem>
                                    <ToggleGroupItem
                                        value="join_existing"
                                        aria-label="Join an existing organization"
                                        className="h-auto flex-col items-start gap-1.5 rounded-md! border! p-4 text-left whitespace-normal data-[state=on]:border-primary data-[state=on]:ring-1 data-[state=on]:ring-primary"
                                    >
                                        <Users className="size-5 text-muted-foreground" />
                                        <span className="text-sm font-semibold">
                                            Join an existing organization
                                        </span>
                                        <span className="text-xs font-normal text-muted-foreground">
                                            Search for your org and request to
                                            join as an officer.
                                        </span>
                                    </ToggleGroupItem>
                                </ToggleGroup>
                                <input
                                    type="hidden"
                                    name="intended_path"
                                    value={intendedPath}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={2}
                                    autoComplete="name"
                                    name="name"
                                    placeholder="Full name"
                                />
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">School email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    tabIndex={3}
                                    autoComplete="email"
                                    name="email"
                                    placeholder="juan.delacruz@students.nu-lipa.edu.ph"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Use your NU Lipa student email — we&apos;ll
                                    send a verification code to it.
                                </p>
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="id_number">
                                    Student ID number
                                </Label>
                                <Input
                                    id="id_number"
                                    type="text"
                                    inputMode="numeric"
                                    required
                                    tabIndex={4}
                                    autoComplete="off"
                                    name="id_number"
                                    placeholder="2023-182854"
                                    value={idNumber}
                                    onChange={(e) =>
                                        setIdNumber(
                                            formatIdNumber(e.target.value),
                                        )
                                    }
                                    maxLength={11}
                                />
                                <InputError message={errors.id_number} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    tabIndex={5}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="Password"
                                    passwordrules={passwordRules}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm password
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    tabIndex={6}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Confirm password"
                                    passwordrules={passwordRules}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                type="submit"
                                variant="brand-fixed"
                                className="mt-2 w-full"
                                tabIndex={7}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                Create account
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            Already have an account?{' '}
                            <TextLink href={login()} tabIndex={7}>
                                Log in
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: 'Create an account',
    description: 'Enter your details below to create your account',
};
