<?php

namespace App\Identity\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The one place an account is retired, shared by the admin Deactivate action
 * and the SDAO replacement flow (ProvisionApprover) so the two can never
 * drift apart. Nothing is deleted from the audit trail: the user row, its
 * document_transitions, step approvals and notifications all stay.
 *
 * Everything runs in one transaction, so a caller that is already inside one
 * (the replacement flow) rolls this back together with its own steps.
 */
class AccountDeactivator
{
    public function deactivate(User $account, ?User $by = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($account, $by, $reason) {
            $account->forceFill([
                'deactivated_at' => now(),
                'deactivated_reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
                'deactivated_by' => $by?->id,
                // Invalidates any "remember me" cookie.
                'remember_token' => Str::random(60),
            ])->save();

            // Ends every web session, including a current one if this runs
            // for the acting user's own request (guarded against upstream).
            $account->endOtherSessions();
            $account->revokeAllApiTokens();
            $account->pushTokens()->delete();

            DB::table('password_reset_tokens')->where('email', $account->email)->delete();
        });
    }

    public function reactivate(User $account): void
    {
        $account->forceFill([
            'deactivated_at' => null,
            'deactivated_reason' => null,
            'deactivated_by' => null,
        ])->save();
    }
}
