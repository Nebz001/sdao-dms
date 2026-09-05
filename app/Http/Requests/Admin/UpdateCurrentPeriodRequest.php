<?php

namespace App\Http\Requests\Admin;

use App\Enums\Term;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCurrentPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by the `access-admin` route middleware.
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'term' => ['required', 'string', Rule::enum(Term::class)],
            'academic_year' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $academicYear = $this->string('academic_year')->toString();

            if (! preg_match('/^(\d{4})-(\d{4})$/', $academicYear, $matches)) {
                return;
            }

            [, $startYear, $endYear] = $matches;

            if ((int) $endYear !== (int) $startYear + 1) {
                $validator->errors()->add('academic_year', 'The academic year must be two consecutive years, e.g. "2026-2027".');

                return;
            }

            if (abs((int) $startYear - (int) now()->year) > 10) {
                $validator->errors()->add('academic_year', 'That academic year looks like a typo — check it before saving.');
            }
        });
    }
}
