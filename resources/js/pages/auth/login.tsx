import { Form, Head } from '@inertiajs/react';
import { AlertCircleIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
    /** Present only when this response IS the throttled-login re-render —
     *  see AppServiceProvider::renderThrottledLogin(). */
    retryAfterSeconds?: number;
};

export default function Login({ status, canResetPassword, retryAfterSeconds }: Props) {
    const [secondsRemaining, setSecondsRemaining] = useState(retryAfterSeconds ?? 0);

    // Inertia re-renders this same mounted component on a fresh throttled
    // response rather than remounting it, so a new retryAfterSeconds value
    // (a second over-the-limit submit after the first countdown finished)
    // needs to reset the countdown during render — the pattern React's own
    // docs recommend for "adjust state when a prop changes", not an effect.
    const [prevRetryAfterSeconds, setPrevRetryAfterSeconds] = useState(retryAfterSeconds);

    if (retryAfterSeconds !== prevRetryAfterSeconds) {
        setPrevRetryAfterSeconds(retryAfterSeconds);
        setSecondsRemaining(retryAfterSeconds ?? 0);
    }

    useEffect(() => {
        if (secondsRemaining <= 0) {
            return;
        }

        const timer = setInterval(() => {
            setSecondsRemaining((seconds) => Math.max(0, seconds - 1));
        }, 1000);

        return () => clearInterval(timer);
    }, [secondsRemaining]);

    const throttled = secondsRemaining > 0;

    return (
        <>
            <Head title="Log in" />

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            {throttled && (
                <Alert variant="destructive" className="mb-4">
                    <AlertCircleIcon />
                    <AlertTitle>Too many attempts</AlertTitle>
                    <AlertDescription>
                        Please wait {secondsRemaining}s before trying again.
                    </AlertDescription>
                </Alert>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">Password</Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-sm"
                                            tabIndex={5}
                                        >
                                            Forgot your password?
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">Remember me</Label>
                            </div>

                            <Button
                                type="submit"
                                variant="brand-fixed"
                                className="mt-4 w-full"
                                tabIndex={4}
                                disabled={processing || throttled}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                {throttled ? `Try again in ${secondsRemaining}s` : 'Log in'}
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            Don't have an account?{' '}
                            <TextLink href={register()} tabIndex={5}>
                                Sign up
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Login.layout = {
    title: 'Log in to your account',
    description: 'Enter your email and password below to log in',
};
