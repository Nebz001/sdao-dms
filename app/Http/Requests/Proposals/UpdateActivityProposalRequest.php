<?php

namespace App\Http\Requests\Proposals;

use App\Attachments\AttachmentSlots;
use App\Enums\ActivityNature;
use App\Enums\ActivityType;
use App\Enums\BudgetSource;
use App\Enums\FormType;
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
            // Group E backlog — collapsed back into a single field.
            'objectives' => ['required', 'string'],
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
            // Group E backlog — typed in directly by the submitting officer,
            // not a picker (see ActivityProposal's responsible_persons docblock).
            'responsible_persons' => ['required', 'array', 'min:1'],
            'responsible_persons.*' => ['required', 'string', 'max:255'],
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
            // Multi-select (Group C item 1) — see StoreProposalStepOneRequest.
            'target_sdg' => ['nullable', 'array', 'min:1'],
            'target_sdg.*' => [Rule::enum(Sdg::class)],
            // Closed dropdown (Group C item 2) — was free text.
            'budget_source' => ['nullable', Rule::enum(BudgetSource::class)],
            // Step-1 attachments (Group C item 3) — not required here:
            // untouched slots from the original submission are preserved,
            // same as every other resubmit form's attachment rules.
            ...AttachmentSlots::validationRules(FormType::ActivityProposal, requiredAtWrite: false, step: 1),
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
            'objectives' => 'Objectives',
            'criteria_mechanics' => 'Criteria/Mechanics',
            'program_flow' => 'Program Flow',
            'expense_items' => 'Expenses',
            'responsible_persons' => 'Responsible Person(s)',
            ...AttachmentSlots::validationAttributes(FormType::ActivityProposal, step: 1),
        ];
    }
}
