import { Form, Head } from '@inertiajs/react';
import { FolderPlus, Users } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import PasswordRuleHint from '@/components/password-rule-hint';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupCard } from '@/components/ui/radio-group';
import { Spinner } from '@/components/ui/spinner';
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
    // exactly like first_name/last_name/password/id_number already do — no account exists
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
                                <Label id="intended-path-label">
                                    What do you want to do?
                                </Label>
                                <RadioGroup aria-labelledby="intended-path-label">
                                    <RadioGroupCard
                                        name="intended_path"
                                        value="register_new"
                                        checked={intendedPath === 'register_new'}
                                        onChange={() => setIntendedPath('register_new')}
                                        icon={<FolderPlus />}
                                        title="Register a new organization"
                                        description="Start one that does not exist yet"
                                    />
                                    <RadioGroupCard
                                        name="intended_path"
                                        value="join_existing"
                                        checked={intendedPath === 'join_existing'}
                                        onChange={() => setIntendedPath('join_existing')}
                                        icon={<Users />}
                                        title="Join an organization"
                                        description="Ask to be added as an officer"
                                    />
                                </RadioGroup>
                            </div>

                            <div className="grid gap-6 sm:grid-cols-2 sm:gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="first_name">First name</Label>
                                    <Input
                                        id="first_name"
                                        type="text"
                                        required
                                        autoFocus
                                        autoComplete="given-name"
                                        name="first_name"
                                        placeholder="Juan"
                                        maxLength={100}
                                        aria-invalid={errors.first_name ? true : undefined}
                                    />
                                    <InputError message={errors.first_name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="last_name">Last name</Label>
                                    <Input
                                        id="last_name"
                                        type="text"
                                        required
                                        autoComplete="family-name"
                                        name="last_name"
                                        placeholder="Dela Cruz"
                                        maxLength={100}
                                        aria-invalid={errors.last_name ? true : undefined}
                                    />
                                    <InputError message={errors.last_name} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">School email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    autoComplete="email"
                                    name="email"
                                    placeholder="juan.delacruz@students.nu-lipa.edu.ph"
                                />
                                <p className="text-xs text-muted-foreground">
                                    We send a verification code to this address.
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
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="At least 8 characters"
                                    aria-describedby="password-rules"
                                    passwordrules={passwordRules}
                                />
                                <PasswordRuleHint
                                    id="password-rules"
                                    rules={passwordRules}
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
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Type it again"
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
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                Create account
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            Already have an account?{' '}
                            <TextLink href={login()}>
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
    description: 'Enter your details below to get started',
};
