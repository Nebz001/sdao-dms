<?php

namespace App\Http\Requests\Proposals;

use App\Http\Requests\Concerns\NormalizesSpecificObjectives;
use App\Rules\AtLeastOneSpecificObjective;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProposalDraftRequest extends FormRequest
{
    use NormalizesSpecificObjectives;

    protected function prepareForValidation(): void
    {
        $this->normalizeSpecificObjectives();
    }

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
            // Group E backlog — collapsed back into a single field.
            'objectives' => ['nullable', 'string'],
            // Specific Objectives: optional, one per line, plain text.
            'specific_objectives' => AtLeastOneSpecificObjective::rules(),
            // Group E backlog — Activity Description restored.
            'activity_description' => ['nullable', 'string'],
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
            // Group E backlog — typed in directly by the submitting officer.
            'responsible_persons' => ['nullable', 'array'],
            'responsible_persons.*' => ['nullable', 'string', 'max:255'],
            // proposed_budget is no longer part of step-2 autosave (Phase 2
            // item 7 slice 4a) — it's set once at step 1. source_of_funding
            // is gone entirely (Group D item 4).
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'specific_objectives' => 'Specific Objectives',
        ];
    }
}
