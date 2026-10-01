<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Admin provisioning may use any valid address (an approver can be reached on
 * a personal mailbox), except one on a student domain: an approver account
 * must never be mistaken for, or collide with, a student identity. The
 * counterpart of SchoolEmailDomain, which is the allow-list used everywhere
 * else.
 */
class NotStudentEmailDomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = Str::lower(Str::afterLast((string) $value, '@'));

        if (in_array($domain, config('school.email_domains.student', []), true)) {
            $fail('The :attribute cannot be a student email address. Use a staff or personal address.');
        }
    }
}
