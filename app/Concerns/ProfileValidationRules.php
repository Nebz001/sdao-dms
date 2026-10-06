<?php

namespace App\Concerns;

use App\Models\User;
use App\Rules\NotStudentEmailDomain;
use App\Rules\SchoolEmailDomain;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    private const int PERSON_NAME_MAX_LENGTH = 100;

    /**
     * Get the validation rules used to validate user profiles.
     *
     * @param  'student'|'staff'|null  $audience  Restrict the school-email domain check to one audience.
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null, ?string $audience = null, bool $requireSchoolDomain = true): array
    {
        return [
            'first_name' => $this->personNameRules(),
            'last_name' => $this->personNameRules(),
            'email' => $this->emailRules($userId, $audience, requireSchoolDomain: $requireSchoolDomain),
        ];
    }

    /**
     * Rules for a first or last name. Input is trimmed by the framework's
     * TrimStrings middleware. Letters (including accented ones such as ñ),
     * spaces, periods, hyphens and apostrophes only, starting with a letter.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function personNameRules(): array
    {
        return ['required', 'string', 'max:'.self::PERSON_NAME_MAX_LENGTH, "regex:/^\\p{L}[\\p{L}\\p{M} .'’-]*$/u"];
    }

    /**
     * Plain-words messages for first_name and last_name.
     *
     * @return array<string, string>
     */
    protected function personNameMessages(): array
    {
        $messages = [];

        foreach (['first_name' => 'First name', 'last_name' => 'Last name'] as $field => $label) {
            $messages["{$field}.required"] = 'Enter a '.strtolower($label).'.';
            $messages["{$field}.max"] = "{$label} must be ".self::PERSON_NAME_MAX_LENGTH.' characters or fewer.';
            $messages["{$field}.regex"] = "{$label} can only have letters, spaces, periods, hyphens, and apostrophes, and must start with a letter.";
            $messages["{$field}.string"] = "{$label} must be text.";
        }

        return $messages;
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * Every account email must be an NU Lipa school address — the domain
     * check runs before the uniqueness lookup so a personal address is
     * rejected without touching the database.
     *
     * Admin provisioning passes `allowPersonal: true` to accept any valid
     * address except one on a student domain (see NotStudentEmailDomain).
     * `requireSchoolDomain: false` skips the domain check entirely, for a
     * profile save that leaves the email unchanged.
     *
     * @param  'student'|'staff'|null  $audience  Restrict to one domain list, or null to accept any configured domain.
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null, ?string $audience = null, bool $allowPersonal = false, bool $requireSchoolDomain = true): array
    {
        $domainRules = match (true) {
            $allowPersonal => [new NotStudentEmailDomain],
            $requireSchoolDomain => [new SchoolEmailDomain($audience)],
            default => [],
        };

        return [
            'required',
            'string',
            'email',
            'max:255',
            ...$domainRules,
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }

    /**
     * Get the validation rules used to validate a student/staff ID number.
     *
     * Required student self-registration IDs follow NU Lipa's confirmed
     * format: a 4-digit year, a dash, then a 6-digit number (e.g.
     * 2023-182854). Optional admin-provisioned staff IDs have no confirmed
     * format yet, so they validate as a reasonable non-empty identifier.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function idNumberRules(bool $required, ?int $userId = null): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            $required ? 'regex:/^\d{4}-\d{6}$/' : 'regex:/^[A-Za-z0-9-]{5,32}$/',
            $userId === null
                ? Rule::unique(User::class, 'id_number')
                : Rule::unique(User::class, 'id_number')->ignore($userId),
        ];
    }
}
