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
            // Group D item 1 — split out of the single `objectives` field.
            'overall_goal' => ['nullable', 'string'],
            'specific_objectives' => ['nullable', 'string'],
            // Exact field corrections (Phase 2 item 7 slice 4b).
            'criteria_mechanics' => ['nullable', 'string'],
            'program_flow' => ['nullable', 'string'],
            // Autosave must tolerate a half-filled table (a row with a
            // material typed but no quantity/unit_price yet, or vice versa)
            // — validation here stays permissive; SubmitProposalRequest/
            // UpdateActivityProposalRequest enforce the real shape at
            // submit time. Group D item 3 — {material, quantity, unit_price}.
            'expense_items' => ['nullable', 'array'],
            'expense_items.*.material' => ['nullable', 'string', 'max:255'],
            'expense_items.*.quantity' => ['nullable', 'string', 'max:32'],
            'expense_items.*.unit_price' => ['nullable', 'string', 'max:32'],
            // proposed_budget is no longer part of step-2 autosave (Phase 2
            // item 7 slice 4a) — it's set once at step 1. source_of_funding
            // is gone entirely (Group D item 4).
        ];
    }
}
