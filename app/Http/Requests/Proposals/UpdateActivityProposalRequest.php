<?php

namespace App\Http\Requests\Proposals;

use App\Enums\ActivityNature;
use App\Enums\ActivityType;
use App\Enums\Sdg;
use App\Enums\Term;
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
            'proposed_budget' => ['nullable', 'numeric', 'min:0'],
            // Optional off-calendar activity update fields
            'title' => ['nullable', 'string', 'max:255'],
            'venue' => ['nullable', 'string', 'max:255'],
            'activity_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'term' => ['nullable', Rule::enum(Term::class)],
            // Optional on-calendar activity re-link
            'calendar_activity_id' => ['nullable', 'integer', Rule::exists('calendar_activities', 'id')],
            // Exact field corrections (Phase 2 item 7 slice 4a) — optional
            // here since resubmission doesn't force re-entry of unchanged
            // step-1 fields (mirrors proposed_budget's own nullable rule
            // above, already established for this resubmit flow).
            'activity_nature' => ['nullable', Rule::enum(ActivityNature::class)],
            'activity_type' => ['nullable', Rule::enum(ActivityType::class)],
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
            'activity_type' => 'Type of Activity',
            'partner_organizations' => 'Partner Organization(s)/School(s)/RSO',
            'target_sdg' => 'Target SDG',
            'proposed_budget' => 'Proposed Budget',
            'budget_source' => 'Budget Source',
        ];
    }
}
