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
            // proposed_budget is no longer part of step-2 autosave (Phase 2
            // item 7 slice 4a) — it's set once at step 1.
        ];
    }
}
