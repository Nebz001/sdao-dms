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
            'description' => ['required', 'string', 'max:5000'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:50'],
            'contact_email' => ['required', 'email', 'max:255'],
            'date_organized' => ['required', 'date'],
            'roster' => ['nullable', 'array'],
            'roster.*' => ['string', 'max:255'],
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
