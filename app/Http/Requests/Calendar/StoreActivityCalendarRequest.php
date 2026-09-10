<?php

namespace App\Http\Requests\Calendar;

use App\Enums\Sdg;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActivityCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization handled in controller via Gate
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Term is a global, admin-controlled setting (Phase 2 item 6),
            // not user input — no validation rule for it here.
            'activities' => ['required', 'array', 'min:1'],
            'activities.*.name' => ['required', 'string', 'max:255'],
            'activities.*.venue' => ['required', 'string', 'max:255'],
            'activities.*.activity_date' => ['required', 'date', 'after_or_equal:today'],
            'activities.*.start_time' => ['required', 'date_format:H:i'],
            'activities.*.end_time' => ['required', 'date_format:H:i', 'after:activities.*.start_time'],
            'activities.*.description' => ['nullable', 'string'],
            // Exact field corrections (Phase 2 item 7 slice 1). Required for
            // real student submissions even though the DB columns are
            // nullable (see CalendarActivity migration comment).
            // SDG multi-select (Group B item 1) — at least one goal, each a
            // real Sdg case; mirrors partner_organizations' array+min:1 shape.
            'activities.*.sdg' => ['required', 'array', 'min:1'],
            'activities.*.sdg.*' => [Rule::enum(Sdg::class)],
            'activities.*.participant_program_assigned' => ['required', 'string', 'max:255'],
            'activities.*.budget' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Group B item 3 — without this, Laravel's wildcard resolution turns
     * "activities.*.start_time" into the literal indexed path
     * ("activities.0.start_time") before building the message, and with no
     * attribute registered for that path, getDisplayableAttribute() falls
     * back to snake-casing the raw dotted key — leaking
     * "activities.0.end time" into the student-facing error instead of
     * "End Time". The "activities.*.field" wildcard form here matches every
     * row's indexed path automatically (Validator::getAttribute() searches
     * customAttributes for a Str::is() wildcard match), so this one map
     * covers every row without per-index entries.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'activities.*.name' => 'Activity Name',
            'activities.*.venue' => 'Venue',
            'activities.*.activity_date' => 'Date',
            'activities.*.start_time' => 'Start Time',
            'activities.*.end_time' => 'End Time',
            'activities.*.description' => 'Description',
            'activities.*.sdg' => 'SDG',
            'activities.*.participant_program_assigned' => 'Participant/Program Assigned',
            'activities.*.budget' => 'Budget',
        ];
    }
}
