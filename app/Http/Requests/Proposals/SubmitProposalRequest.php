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
            // Group D item 1 — split out of the single `objectives` field.
            'overall_goal' => ['required', 'string'],
            'specific_objectives' => ['required', 'string'],
            // Exact field corrections (Phase 2 item 7 slice 4b).
            'criteria_mechanics' => ['required', 'string'],
            'program_flow' => ['required', 'string'],
            // Itemized expenses (client request, post-Part-2) replace the
            // old free-text `expenses` field going forward — see
            // App\Models\ActivityProposal's expense_items docblock. Group D
            // item 3 — {material, quantity, unit_price}, was {label, amount}.
            'expense_items' => ['required', 'array', 'min:1'],
            'expense_items.*.material' => ['required', 'string', 'max:255'],
            'expense_items.*.quantity' => ['required', 'numeric', 'min:0'],
            'expense_items.*.unit_price' => ['required', 'numeric', 'min:0'],
            // proposed_budget is no longer collected at step 2 (Phase 2
            // item 7 slice 4a) — it's set once at step 1. source_of_funding
            // is gone entirely (Group D item 4) — step 2 echoes step 1's
            // budget_source_label read-only instead of asking again.
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'overall_goal' => 'Overall Goal',
            'specific_objectives' => 'Specific Objectives',
            'criteria_mechanics' => 'Criteria/Mechanics',
            'program_flow' => 'Program Flow',
            'expense_items' => 'Expenses',
        ];
    }
}
