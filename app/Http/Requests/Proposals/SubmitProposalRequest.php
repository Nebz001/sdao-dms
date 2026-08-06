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
            // Itemized expenses (client request, post-Part-2) replace the
            // old free-text `expenses` field going forward — see
            // App\Models\ActivityProposal's expense_items docblock.
            'expense_items' => ['required', 'array', 'min:1'],
            'expense_items.*.label' => ['required', 'string', 'max:255'],
            'expense_items.*.amount' => ['required', 'numeric', 'min:0'],
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
            'expense_items' => 'Expenses',
        ];
    }
}
