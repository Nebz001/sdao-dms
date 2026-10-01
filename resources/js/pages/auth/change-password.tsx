import { Form, Head, router } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import ChangeTemporaryPasswordController from '@/actions/App/Http/Controllers/Settings/ChangeTemporaryPasswordController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';

type Props = {
    passwordRules: string;
};

export default function ChangePassword({ passwordRules }: Props) {
    return (
        <>
            <Head title="Change your password" />

            <Form
                {...ChangeTemporaryPasswordController.update.form()}
                resetOnSuccess={['current_password', 'password', 'password_confirmation']}
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <Alert>
                            <KeyRound />
                            <AlertDescription>
                                You are using a temporary password. Set a new one to continue. You will stay logged in.
                            </AlertDescription>
                        </Alert>

                        <div className="grid gap-2">
                            <Label htmlFor="current_password">Temporary password</Label>
                            <PasswordInput
                                id="current_password"
                                name="current_password"
                                autoComplete="current-password"
                                autoFocus
                                required
                                placeholder="The password from your email"
                            />
                            <InputError message={errors.current_password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">New password</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                required
                                placeholder="New password"
                                passwordrules={passwordRules}
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">Confirm new password</Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                required
                                placeholder="Confirm new password"
                                passwordrules={passwordRules}
                            />
                            <InputError message={errors.password_confirmation} />
                        </div>

                        <div className="grid gap-2">
                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                                data-test="change-password-button"
                            >
                                {processing && <Spinner />}
                                Change password
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                className="w-full"
                                disabled={processing}
                                onClick={() =>
                                    router.post(
                                        logout.url(),
                                        {},
                                        { onSuccess: () => router.flushAll() },
                                    )
                                }
                            >
                                Log out
                            </Button>
                        </div>
                    </div>
                )}
            </Form>
        </>
    );
}

ChangePassword.layout = {
    title: 'Change your password',
    description: 'Choose a password only you know before you continue',
};
