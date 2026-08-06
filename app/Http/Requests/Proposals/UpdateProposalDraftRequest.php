<?php

namespace App\Http\Requests\Proposals;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProposalDraftRequest extends FormRequest
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
            'objectives' => ['nullable', 'string'],
            'narrative' => ['nullable', 'string'],
            // Exact field corrections (Phase 2 item 7 slice 4b).
            'criteria_mechanics' => ['nullable', 'string'],
            'program_flow' => ['nullable', 'string'],
            'source_of_funding' => ['nullable', 'string'],
            // Autosave must tolerate a half-filled table (a row with a
            // label typed but no amount yet, or vice versa) — validation
            // here stays permissive; SubmitProposalRequest/
            // UpdateActivityProposalRequest enforce the real shape at
            // submit time.
            'expense_items' => ['nullable', 'array'],
            'expense_items.*.label' => ['nullable', 'string', 'max:255'],
            'expense_items.*.amount' => ['nullable', 'string', 'max:32'],
            // proposed_budget is no longer part of step-2 autosave (Phase 2
            // item 7 slice 4a) — it's set once at step 1.
        ];
    }
}
