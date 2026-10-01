<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // The school-domain rule only guards an email CHANGE. A save that
        // leaves the email as it is (e.g. an admin-provisioned approver on a
        // personal address updating their name) must not be re-judged.
        return $this->profileRules($this->user()->id, requireSchoolDomain: ! $this->emailIsUnchanged());
    }

    /**
     * Whether the submitted email is the account's current one, compared
     * lowercase and trimmed so a change of letter case alone never counts as
     * a change (login lowercases emails, so the two are the same address).
     */
    public function emailIsUnchanged(): bool
    {
        return Str::lower(trim((string) $this->input('email'))) === Str::lower(trim($this->user()->email));
    }
}
