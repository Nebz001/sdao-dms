<?php

namespace App\Http\Requests\Proposals;

use App\Enums\ProposalCalendarMode;
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
        ];
    }
}
