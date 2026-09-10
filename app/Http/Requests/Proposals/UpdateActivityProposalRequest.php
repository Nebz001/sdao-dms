<?php

namespace App\Http\Requests\Proposals;

use App\Enums\ActivityNature;
use App\Enums\ActivityType;
use App\Enums\Sdg;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActivityProposalRequest extends FormRequest
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
            'proposed_budget' => ['nullable', 'numeric', 'min:0'],
            // Optional off-calendar activity update fields
            'title' => ['nullable', 'string', 'max:255'],
            'venue' => ['nullable', 'string', 'max:255'],
            'activity_date' => ['nullable', 'date', 'after_or_equal:today'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            // Term is NOT a per-submission field — it's a global,
            // admin-controlled setting read via CurrentPeriod::get(), same as
            // StoreProposalStepOneRequest. No rule here.
            // Optional on-calendar activity re-link
            'calendar_activity_id' => ['nullable', 'integer', Rule::exists('calendar_activities', 'id')],
            // Exact field corrections (Phase 2 item 7 slice 4a) — optional
            // here since resubmission doesn't force re-entry of unchanged
            // step-1 fields (mirrors proposed_budget's own nullable rule
            // above, already established for this resubmit flow).
            'activity_nature' => ['nullable', Rule::enum(ActivityNature::class)],
            // "Others" conditional text field (Group B item 5) — same
            // required-when-others rule as StoreProposalStepOneRequest;
            // nullable/optional wrapping matches this class's own resubmit
            // semantics (activity_nature itself is optional here too).
            'activity_nature_other' => [Rule::requiredIf($this->input('activity_nature') === ActivityNature::Others->value), 'nullable', 'string', 'max:255'],
            'activity_type' => ['nullable', Rule::enum(ActivityType::class)],
            'activity_type_other' => [Rule::requiredIf($this->input('activity_type') === ActivityType::Others->value), 'nullable', 'string', 'max:255'],
            'partner_organizations' => ['nullable', 'array', 'min:1'],
            'partner_organizations.*' => ['required', 'string', 'max:255'],
            'target_sdg' => ['nullable', Rule::enum(Sdg::class)],
            'budget_source' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'Title of Activity',
            'activity_date' => 'Date of Activity',
            'activity_nature' => 'Nature of Activity',
            'activity_nature_other' => 'Nature of Activity — please specify',
            'activity_type' => 'Type of Activity',
            'activity_type_other' => 'Type of Activity — please specify',
            'partner_organizations' => 'Partner Organization(s)/School(s)/RSO',
            'target_sdg' => 'Target SDG',
            'proposed_budget' => 'Proposed Budget',
            'budget_source' => 'Budget Source',
            'criteria_mechanics' => 'Criteria/Mechanics',
            'program_flow' => 'Program Flow',
            'source_of_funding' => 'Source of Funding',
            'expense_items' => 'Expenses',
        ];
    }
}
