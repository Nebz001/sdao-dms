<?php

namespace App\Http\Requests\Proposals;

use App\Enums\ActivityNature;
use App\Enums\ActivityType;
use App\Enums\ProposalCalendarMode;
use App\Enums\Sdg;
use App\Enums\Term;
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
            'activity_date' => [Rule::requiredIf($isOffCalendar), 'nullable', 'date'],
            'start_time' => [Rule::requiredIf($isOffCalendar), 'nullable', 'date_format:H:i'],
            'end_time' => [Rule::requiredIf($isOffCalendar), 'nullable', 'date_format:H:i', 'after:start_time'],
            'term' => [Rule::requiredIf($isOffCalendar), 'nullable', Rule::enum(Term::class)],

            // Exact field corrections (Phase 2 item 7 slice 4a) — apply
            // regardless of calendar_mode; proposal-level classification/
            // budget data, not schedule data.
            'activity_nature' => ['required', Rule::enum(ActivityNature::class)],
            'activity_type' => ['required', Rule::enum(ActivityType::class)],
            'partner_organizations' => ['required', 'array', 'min:1'],
            'partner_organizations.*' => ['required', 'string', 'max:255'],
            'target_sdg' => ['required', Rule::enum(Sdg::class)],
            'proposed_budget' => ['required', 'numeric', 'min:0'],
            'budget_source' => ['required', 'string', 'max:255'],
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
            'activity_type' => 'Type of Activity',
            'partner_organizations' => 'Partner Organization(s)/School(s)/RSO',
            'target_sdg' => 'Target SDG',
            'proposed_budget' => 'Proposed Budget',
            'budget_source' => 'Budget Source',
        ];
    }
}
