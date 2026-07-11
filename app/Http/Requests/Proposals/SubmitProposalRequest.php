<?php

namespace App\Http\Requests\Proposals;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubmitProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'objectives' => ['required', 'string'],
            'narrative' => ['required', 'string'],
            // Exact field corrections (Phase 2 item 7 slice 4b).
            'criteria_mechanics' => ['required', 'string'],
            'program_flow' => ['required', 'string'],
            'source_of_funding' => ['required', 'string'],
            'expenses' => ['required', 'string'],
            // proposed_budget is no longer collected at step 2 (Phase 2
            // item 7 slice 4a) — it's set once at step 1.
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'criteria_mechanics' => 'Criteria/Mechanics',
            'program_flow' => 'Program Flow',
            'source_of_funding' => 'Source of Funding',
        ];
    }
}
