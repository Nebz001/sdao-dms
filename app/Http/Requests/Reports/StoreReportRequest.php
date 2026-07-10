<?php

namespace App\Http\Requests\Reports;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via policy.
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'activity_proposal_id' => ['required', 'integer', 'exists:activity_proposals,id'],
            'summary' => ['required', 'string', 'max:10000'],
            'outcomes' => ['nullable', 'string', 'max:10000'],
            'participant_count' => ['nullable', 'integer', 'min:0'],
            // Exact field corrections (Phase 2 item 7 slice 3). Required for
            // real student submissions even though the DB columns are
            // nullable (see AfterActivityReport migration comment).
            'activity_chairs' => ['required', 'array', 'min:1'],
            'activity_chairs.*' => ['required', 'string', 'max:255'],
            'prepared_by' => ['required', 'string', 'max:255'],
            'event_program' => ['required', 'string', 'max:10000'],
            'target_participants_percentage' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'summary' => 'Summary',
            'activity_chairs' => 'Activity Chair/s',
            'prepared_by' => 'Prepared By',
            'event_program' => 'Program',
            'target_participants_percentage' => '% Target Participants',
        ];
    }
}
