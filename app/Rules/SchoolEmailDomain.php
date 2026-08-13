<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class SchoolEmailDomain implements ValidationRule
{
    /**
     * @param  'student'|'staff'|null  $audience  Restrict to one domain list, or null to accept any configured domain.
     */
    public function __construct(private readonly ?string $audience = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domains = $this->allowedDomains();

        $submittedDomain = str_contains((string) $value, '@')
            ? Str::lower(Str::afterLast((string) $value, '@'))
            : null;

        if ($submittedDomain === null || ! in_array($submittedDomain, $domains, true)) {
            $fail('The :attribute must be a valid NU Lipa school email address ('.implode(', ', $domains).').');
        }
    }

    /**
     * @return array<int, string>
     */
    private function allowedDomains(): array
    {
        $domains = config('school.email_domains');

        if ($this->audience !== null) {
            return $domains[$this->audience] ?? [];
        }

        return array_values(array_unique(array_merge($domains['student'] ?? [], $domains['staff'] ?? [])));
    }
}
