<?php

namespace App\Http\Requests\Registrations;

use App\Enums\OrganizationType;
use App\Models\Program;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via policy.
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'adviser_id' => ['required', 'integer', 'exists:users,id'],
            'organization_type' => ['required', 'string', Rule::enum(OrganizationType::class)],
            'purpose_of_organization' => ['required', 'string', 'max:5000'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_no' => ['required', 'string', 'max:50'],
            'email_address' => ['required', 'email', 'max:255'],
            'date_organized' => ['required', 'date'],
            'roster' => ['nullable', 'array'],
            'roster.*' => ['string', 'max:255'],
        ];
    }

    /**
     * Exact field corrections (Phase 2 item 7 slice 2) — auto-generated
     * validation messages read using the client's real-form wording.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'organization_type' => 'Type of Organization',
            'purpose_of_organization' => 'Purpose of Organization',
            'contact_no' => 'Contact No.',
            'email_address' => 'Email Address',
            'school_id' => 'College',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $schoolId = $this->integer('school_id');
            $programId = $this->input('program_id') !== null ? $this->integer('program_id') : null;

            if ($programId !== null) {
                $belongsToSchool = Program::where('id', $programId)->where('school_id', $schoolId)->exists();

                if (! $belongsToSchool) {
                    $validator->errors()->add('program_id', 'The selected program does not belong to the selected school.');
                }
            }
        });
    }
}
