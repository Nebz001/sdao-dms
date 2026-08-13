<?php

namespace App\Identity\EmailVerification;

use App\Mail\EmailVerificationCodeMail;
use App\Models\EmailVerificationCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Issues and checks the 6-digit codes that prove ownership of an NU Lipa
 * school email address, for both self-registration and profile email
 * changes. A `purpose` string (e.g. "registration", "profile-email-change")
 * scopes codes so unrelated flows for the same address never collide.
 */
class EmailVerificationCodeService
{
    /**
     * @param  array<string, mixed>|null  $payload  The pending write this code gates, applied only once verified.
     */
    public function issue(string $email, string $purpose, ?array $payload = null, ?int $userId = null): EmailVerificationCode
    {
        // A freshly requested code replaces any still-open one for the same
        // address/purpose — only the most recently sent code is ever usable.
        EmailVerificationCode::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->delete();

        $code = (string) random_int(100000, 999999);

        $record = EmailVerificationCode::create([
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'payload' => $payload,
            'user_id' => $userId,
            'expires_at' => now()->addMinutes((int) config('school.verification_code.ttl_minutes')),
        ]);

        try {
            Mail::to($email)->send(new EmailVerificationCodeMail($code, $record->expires_at));
        } catch (Throwable $exception) {
            Log::error('Failed to send email verification code.', [
                'email' => $email,
                'purpose' => $purpose,
                'exception' => $exception,
            ]);

            throw ValidationException::withMessages([
                'email' => "We couldn't send a verification code to that address. Please try again in a moment.",
            ]);
        }

        return $record;
    }

    /**
     * @throws ValidationException if no open code exists, it's locked/expired, or the code doesn't match.
     */
    public function verify(string $email, string $purpose, string $code): EmailVerificationCode
    {
        $record = EmailVerificationCode::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($record === null) {
            throw ValidationException::withMessages([
                'code' => 'That code is no longer valid. Request a new one.',
            ]);
        }

        if ($record->isLocked()) {
            throw ValidationException::withMessages([
                'code' => "Too many incorrect attempts. Try again {$record->locked_until->diffForHumans()}.",
            ]);
        }

        if ($record->isExpired()) {
            throw ValidationException::withMessages([
                'code' => 'That code has expired. Request a new one.',
            ]);
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            $maxAttempts = (int) config('school.verification_code.max_attempts');

            if ($record->attempts >= $maxAttempts) {
                $record->update([
                    'locked_until' => now()->addMinutes((int) config('school.verification_code.lockout_minutes')),
                ]);

                throw ValidationException::withMessages([
                    'code' => 'Too many incorrect attempts. Request a new code in '.config('school.verification_code.lockout_minutes').' minutes.',
                ]);
            }

            $remaining = $maxAttempts - $record->attempts;

            throw ValidationException::withMessages([
                'code' => "That code doesn't match. {$remaining} attempt(s) left.",
            ]);
        }

        $record->update(['consumed_at' => now()]);

        return $record;
    }
}
