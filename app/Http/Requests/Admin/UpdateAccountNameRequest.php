<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * SDAO correcting a person's first and last name. Same rules and messages as
 * every other place a name is entered.
 */
class UpdateAccountNameRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        return Gate::allows('access-admin');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => $this->personNameRules(),
            'last_name' => $this->personNameRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->personNameMessages();
    }
}
