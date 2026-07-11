<?php

namespace App\Http\Requests\Reports;

use App\Attachments\AttachmentSlots;
use App\Enums\FormType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReportRequest extends FormRequest
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
            'summary' => ['required', 'string', 'max:10000'],
            'outcomes' => ['nullable', 'string', 'max:10000'],
            'participant_count' => ['nullable', 'integer', 'min:0'],
            'activity_chairs' => ['required', 'array', 'min:1'],
            'activity_chairs.*' => ['required', 'string', 'max:255'],
            'prepared_by' => ['required', 'string', 'max:255'],
            'event_program' => ['required', 'string', 'max:10000'],
            'target_participants_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            // Phase 2 item 8 — nullable at Update; AttachmentStorage::assertRequiredSlotsFilled
            // is the real completeness gate (persisted rows + anything newly attached).
            ...AttachmentSlots::validationRules(FormType::AfterActivityReport, requiredAtWrite: false),
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
            ...AttachmentSlots::validationAttributes(FormType::AfterActivityReport),
        ];
    }
}
