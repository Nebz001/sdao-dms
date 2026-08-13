import { Form, Head } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { register } from '@/routes';
import { resend, store } from '@/routes/register/verify';

type Props = {
    email: string;
    resendCooldownSeconds: number;
};

export default function VerifyRegistrationCode({
    email,
    resendCooldownSeconds,
}: Props) {
    const [code, setCode] = useState<string>('');
    const [cooldown, setCooldown] = useState<number>(resendCooldownSeconds);

    useEffect(() => {
        if (cooldown <= 0) {
            return;
        }

        const timer = setInterval(() => {
            setCooldown((seconds) => Math.max(0, seconds - 1));
        }, 1000);

        return () => clearInterval(timer);
    }, [cooldown]);

    return (
        <>
            <Head title="Verify your email" />

            <div className="flex flex-col gap-6">
                <p className="text-center text-sm text-muted-foreground">
                    We sent a 6-digit code to{' '}
                    <span className="font-medium text-foreground">
                        {email}
                    </span>
                    . Enter it below to finish creating your account.
                </p>

                <Form
                    {...store.form()}
                    className="flex flex-col gap-4"
                    resetOnError
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="flex flex-col items-center justify-center gap-3 text-center">
                                <div className="flex w-full items-center justify-center">
                                    <InputOTP
                                        name="code"
                                        maxLength={OTP_MAX_LENGTH}
                                        value={code}
                                        onChange={(value) => setCode(value)}
                                        disabled={processing}
                                        pattern={REGEXP_ONLY_DIGITS}
                                        autoFocus
                                    >
                                        <InputOTPGroup>
                                            {Array.from(
                                                { length: OTP_MAX_LENGTH },
                                                (_, index) => (
                                                    <InputOTPSlot
                                                        key={index}
                                                        index={index}
                                                    />
                                                ),
                                            )}
                                        </InputOTPGroup>
                                    </InputOTP>
                                </div>
                                <InputError message={errors.code} />
                            </div>

                            <Button
                                type="submit"
                                variant="brand-fixed"
                                className="w-full"
                                loading={processing}
                                loadingText="Verifying…"
                                disabled={code.length < OTP_MAX_LENGTH}
                                data-test="verify-registration-code-button"
                            >
                                Verify and create account
                            </Button>
                        </>
                    )}
                </Form>

                <Form {...resend.form()} onSuccess={() => setCooldown(resendCooldownSeconds)}>
                    {({ processing }) => (
                        <div className="text-center text-sm text-muted-foreground">
                            Didn&apos;t get a code?{' '}
                            <button
                                type="submit"
                                disabled={processing || cooldown > 0}
                                className="cursor-pointer text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! disabled:cursor-not-allowed disabled:text-muted-foreground disabled:no-underline dark:decoration-neutral-500"
                            >
                                {cooldown > 0
                                    ? `Resend code (${cooldown}s)`
                                    : 'Resend code'}
                            </button>
                        </div>
                    )}
                </Form>

                <div className="text-center text-sm text-muted-foreground">
                    Wrong email?{' '}
                    <TextLink href={register()}>Start over</TextLink>
                </div>
            </div>
        </>
    );
}

VerifyRegistrationCode.layout = {
    title: 'Verify your email',
    description: 'Enter the code we sent to confirm your school email address',
};
