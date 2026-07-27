import { Form, router } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import ConfirmDialog from '@/components/confirm-dialog';
import type { ConfirmActions } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { Button } from '@/components/ui/button';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import { disable, enable } from '@/routes/two-factor';

export type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
    /**
     * False once the server has cleaned up an in-progress setup left
     * unconfirmed past the grace period (see TwoFactorAuthenticationRequest
     * ::CONFIRMATION_GRACE_SECONDS). Undefined-safe: treated as "still
     * pending" until the server says otherwise, since a page that has never
     * fetched setup data has nothing to expire.
     */
    twoFactorPendingConfirmation?: boolean;
};

export default function ManageTwoFactor(props: Props) {
    const requiresConfirmation = props.requiresConfirmation ?? false;
    const twoFactorEnabled = props.twoFactorEnabled ?? false;

    const {
        qrCodeSvg,
        hasSetupData,
        manualSetupKey,
        clearSetupData,
        clearTwoFactorAuthData,
        fetchSetupData,
        recoveryCodesList,
        fetchRecoveryCodes,
        errors,
    } = useTwoFactorAuth();
    const [showSetupModal, setShowSetupModal] = useState<boolean>(false);
    const prevTwoFactorEnabled = useRef(twoFactorEnabled);
    const twoFactorPendingConfirmation =
        props.twoFactorPendingConfirmation ?? true;

    useEffect(() => {
        if (prevTwoFactorEnabled.current && !twoFactorEnabled) {
            clearTwoFactorAuthData();
        }

        prevTwoFactorEnabled.current = twoFactorEnabled;
    }, [twoFactorEnabled, clearTwoFactorAuthData]);

    // The server cleans up an in-progress setup once it's sat unconfirmed
    // past the grace period. If this client already fetched QR/setup data
    // (hasSetupData) but the server now reports nothing pending and 2FA
    // still isn't enabled, that cleanup already ran and the QR on screen is
    // stale — no authenticator-app confirmation can ever succeed against it.
    const isSetupStale =
        hasSetupData && !twoFactorEnabled && !twoFactorPendingConfirmation;

    useEffect(() => {
        if (isSetupStale) {
            clearTwoFactorAuthData();
            toast.error(
                'Your two-factor setup expired. Please start again.',
            );
        }
    }, [isSetupStale, clearTwoFactorAuthData]);

    if (!(props.canManageTwoFactor ?? false)) {
        return null;
    }

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Two-factor authentication"
                description="Manage your two-factor authentication settings"
            />
            {twoFactorEnabled ? (
                <div className="flex flex-col items-start justify-start space-y-4">
                    <p className="text-sm text-muted-foreground">
                        You will be prompted for a secure, random pin during
                        login, which you can retrieve from the TOTP-supported
                        application on your phone.
                    </p>

                    <div className="relative inline">
                        <ConfirmDialog
                            trigger={
                                <Button variant="destructive" type="button">
                                    Disable 2FA
                                </Button>
                            }
                            title="Disable two-factor authentication?"
                            description="Your account will no longer require a second factor to sign in — anyone with just your password will be able to access it. If you change your mind, you'll need to go through setup again, including scanning a new QR code and confirming a new pin."
                            confirmLabel="Disable 2FA"
                            confirmVariant="destructive"
                            onConfirm={({ close, stopProcessing }: ConfirmActions) =>
                                router.delete(disable.url(), {
                                    preserveScroll: true,
                                    onSuccess: close,
                                    onFinish: stopProcessing,
                                })
                            }
                        />
                    </div>

                    <TwoFactorRecoveryCodes
                        recoveryCodesList={recoveryCodesList}
                        fetchRecoveryCodes={fetchRecoveryCodes}
                        errors={errors}
                    />
                </div>
            ) : (
                <div className="flex flex-col items-start justify-start space-y-4">
                    <p className="text-sm text-muted-foreground">
                        When you enable two-factor authentication, you will be
                        prompted for a secure pin during login. This pin can be
                        retrieved from a TOTP-supported application on your
                        phone.
                    </p>

                    <div>
                        {hasSetupData ? (
                            <Button onClick={() => setShowSetupModal(true)}>
                                <ShieldCheck />
                                Continue setup
                            </Button>
                        ) : (
                            <Form
                                {...enable.form()}
                                onSuccess={() => setShowSetupModal(true)}
                            >
                                {({ processing }) => (
                                    <Button type="submit" disabled={processing}>
                                        Enable 2FA
                                    </Button>
                                )}
                            </Form>
                        )}
                    </div>
                </div>
            )}

            <TwoFactorSetupModal
                isOpen={showSetupModal && !isSetupStale}
                onClose={() => setShowSetupModal(false)}
                requiresConfirmation={requiresConfirmation}
                twoFactorEnabled={twoFactorEnabled}
                qrCodeSvg={qrCodeSvg}
                manualSetupKey={manualSetupKey}
                clearSetupData={clearSetupData}
                fetchSetupData={fetchSetupData}
                errors={errors}
            />
        </div>
    );
}
