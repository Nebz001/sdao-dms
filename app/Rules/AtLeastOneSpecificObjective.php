<?php

namespace App\Rules;

use App\Support\SpecificObjectives;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Specific Objectives value that is present must hold at least one
 * non-empty line. Use after `nullable`, so an absent field is simply skipped.
 */
class AtLeastOneSpecificObjective implements ValidationRule
{
    /** The longest Specific Objectives text, newlines included. */
    public const int MAX_LENGTH = 2000;

    /**
     * Implicit so a whitespace-only string is still checked: Laravel skips
     * ordinary rules for those. An absent or null value is ignored in validate().
     */
    public bool $implicit = true;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && ! SpecificObjectives::hasAny($value)) {
            $fail('Add at least one specific objective, one per line.');
        }
    }

    /**
     * The full rule set for the field, shared by every request that takes it.
     *
     * @return array<int, string|self>
     */
    public static function rules(): array
    {
        return ['nullable', 'string', 'max:'.self::MAX_LENGTH, new self];
    }
}
