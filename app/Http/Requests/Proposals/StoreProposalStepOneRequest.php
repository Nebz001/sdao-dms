<?php

namespace App\Http\Requests\Proposals;

use App\Attachments\AttachmentSlots;
use App\Enums\ActivityNature;
use App\Enums\ActivityType;
use App\Enums\BudgetSource;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Enums\Sdg;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProposalStepOneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization handled in controller via Gate / membership check
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isOnCalendar = $this->input('calendar_mode') === ProposalCalendarMode::OnCalendar->value;
        $isOffCalendar = $this->input('calendar_mode') === ProposalCalendarMode::OffCalendar->value;

        return [
            'calendar_mode' => ['required', Rule::enum(ProposalCalendarMode::class)],

            // On-calendar fields
            'calendar_activity_id' => [
                Rule::requiredIf($isOnCalendar),
                'nullable',
                'integer',
                Rule::exists('calendar_activities', 'id'),
            ],

            // Off-calendar fields
            'title' => [Rule::requiredIf($isOffCalendar), 'nullable', 'string', 'max:255'],
            'venue' => [Rule::requiredIf($isOffCalendar), 'nullable', 'string', 'max:255'],
            'activity_date' => [Rule::requiredIf($isOffCalendar), 'nullable', 'date', 'after_or_equal:today'],
            'start_time' => [Rule::requiredIf($isOffCalendar), 'nullable', 'date_format:H:i'],
            'end_time' => [Rule::requiredIf($isOffCalendar), 'nullable', 'date_format:H:i', 'after:start_time'],
            // Term is NOT a per-submission field — it's a global,
            // admin-controlled setting read via CurrentPeriod::get() in
            // StartProposalDraft, same as Activity Calendar. No rule here.

            // Exact field corrections (Phase 2 item 7 slice 4a) — apply
            // regardless of calendar_mode; proposal-level classification/
            // budget data, not schedule data.
            'activity_nature' => ['required', Rule::enum(ActivityNature::class)],
            // "Others" conditional text field (Group B item 5) — required
            // only when the matching select is actually "others"; the enum's
            // raw value ('others'), not its label, is what the select posts.
            'activity_nature_other' => [Rule::requiredIf($this->input('activity_nature') === ActivityNature::Others->value), 'nullable', 'string', 'max:255'],
            'activity_type' => ['required', Rule::enum(ActivityType::class)],
            'activity_type_other' => [Rule::requiredIf($this->input('activity_type') === ActivityType::Others->value), 'nullable', 'string', 'max:255'],
            'partner_organizations' => ['required', 'array', 'min:1'],
            'partner_organizations.*' => ['required', 'string', 'max:255'],
            // Multi-select (Group C item 1) — at least one goal, each a real
            // Sdg case; lives on activity_proposals directly (one row per
            // document), so no array-index wildcards are needed the way
            // calendar_activities.sdg needed activities.*.sdg.*.
            'target_sdg' => ['required', 'array', 'min:1'],
            'target_sdg.*' => [Rule::enum(Sdg::class)],
            'proposed_budget' => ['required', 'numeric', 'min:0'],
            // Closed dropdown (Group C item 2) — was free text.
            'budget_source' => ['required', Rule::enum(BudgetSource::class)],
            // Step-1 required attachments (Group C item 3): Request Letter,
            // Resume of Resource Person(s) (optional), Sample Post-Survey
            // Form — same Mode A bundled pattern as
            // StoreRegistrationRequest's required attachments.
            ...AttachmentSlots::validationRules(FormType::ActivityProposal, requiredAtWrite: true, step: 1),
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
            ...AttachmentSlots::validationAttributes(FormType::ActivityProposal, step: 1),
        ];
    }
}
