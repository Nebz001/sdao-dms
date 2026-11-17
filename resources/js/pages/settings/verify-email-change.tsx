import { Form, Head } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { OTP_MAX_LENGTH } from '@/lib/otp';
import { edit } from '@/routes/profile';
import * as schoolEmailRoutes from '@/routes/profile/verify-email';
import * as personalEmailRoutes from '@/routes/profile/verify-personal-email';

type Props = {
    /** Which address is being changed: the school email (login) or the personal one (notifications). */
    kind?: 'school' | 'personal';
    email: string;
    resendCooldownSeconds: number;
};

const COPY = {
    school: {
        title: 'Verify your new school email',
        description: 'Confirm the new address before it replaces your current one',
        keeps: 'Your account keeps its current school email until you verify the new one.',
    },
    personal: {
        title: 'Verify your personal email',
        description: 'Confirm the address before we start sending notifications to it',
        keeps: 'Notifications keep going to your current address until you verify this one.',
    },
} as const;

export default function VerifyEmailChange({
    kind = 'school',
    email,
    resendCooldownSeconds,
}: Props) {
    const { store, resend } = kind === 'personal' ? personalEmailRoutes : schoolEmailRoutes;
    const copy = COPY[kind];
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
            <Head title={copy.title} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={copy.title}
                    description={copy.description}
                />

                <p className="text-sm text-muted-foreground">
                    We sent a 6-digit code to{' '}
                    <span className="font-medium text-foreground">
                        {email}
                    </span>
                    . {copy.keeps}
                </p>

                <Form {...store.form()} className="flex flex-col gap-4" resetOnError>
                    {({ errors, processing }) => (
                        <>
                            <div className="flex flex-col items-center justify-center gap-3">
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
                                <InputError message={errors.code} />
                            </div>

                            <Button
                                type="submit"
                                loading={processing}
                                loadingText="Verifying…"
                                disabled={code.length < OTP_MAX_LENGTH}
                                data-test="verify-email-change-button"
                            >
                                Verify email
                            </Button>
                        </>
                    )}
                </Form>

                <Form
                    {...resend.form()}
                    onSuccess={() => setCooldown(resendCooldownSeconds)}
                >
                    {({ processing }) => (
                        <div className="text-sm text-muted-foreground">
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
                            <span className="mt-1 block text-xs">
                                Not in your inbox? Check your spam or junk
                                folder.
                            </span>
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}

VerifyEmailChange.layout = {
    breadcrumbs: [{ title: 'Profile settings', href: edit() }],
};
