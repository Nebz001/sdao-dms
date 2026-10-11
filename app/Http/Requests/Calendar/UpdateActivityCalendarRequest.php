<?php

namespace App\Http\Requests\Calendar;

use App\Approval\FieldDefinition;
use App\Approval\ReturnedEditLock;
use App\Approval\SectionFields;
use App\Enums\Sdg;
use App\Models\CalendarActivity;
use App\Models\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class UpdateActivityCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization handled in controller via Gate
    }

    /**
     * The Fix and Resend lock for this document, when the return flagged anything fixable.
     */
    private function lock(): ?ReturnedEditLock
    {
        $document = $this->route('document');

        return $document instanceof Document ? ReturnedEditLock::for($document) : null;
    }

    /**
     * The Fix and Resend page only posts the activities the approver flagged,
     * keyed by their position. Every other row is filled in from what is
     * stored so the action sees the whole calendar; a row it posted anyway is
     * kept as posted, and the action refuses it if it differs.
     */
    protected function prepareForValidation(): void
    {
        $lock = $this->lock();
        $document = $this->route('document');

        if ($lock === null || ! $document instanceof Document) {
            return;
        }

        $posted = (array) $this->input('activities', []);
        $defs = SectionFields::calendarFields();
        $rows = [];

        foreach ($document->activityCalendar->activities()->get() as $index => $stored) {
            $rows[$index] = $posted[$index] ?? $this->storedRow($stored, $defs);
        }

        // Anything posted beyond the stored rows is an attempt to add one.
        foreach ($posted as $index => $row) {
            if (! array_key_exists($index, $rows)) {
                $rows[$index] = $row;
            }
        }

        $this->merge(['activities' => $rows]);
    }

    /**
     * @param  array<int, FieldDefinition>  $defs
     * @return array<string, mixed>
     */
    private function storedRow(CalendarActivity $stored, array $defs): array
    {
        $row = [];

        foreach ($defs as $def) {
            $value = $stored->getAttribute($def->key);
            $row[$def->key] = match (true) {
                $value instanceof Collection => $value->map(fn ($item) => $item->value)->values()->all(),
                $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
                default => $value,
            };
        }

        return $row;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = $this->baseRules();
        $lock = $this->lock();

        // A row the student cannot edit keeps the date it already had, even if
        // that day has passed since; only the rows being fixed need a future date.
        if ($lock !== null) {
            foreach (array_keys((array) $this->input('activities', [])) as $index) {
                if (! $lock->isFixable("activity_{$index}")) {
                    $rules["activities.{$index}.activity_date"] = ['required', 'date'];
                }
            }
        }

        return $rules;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    private function baseRules(): array
    {
        return [
            // Term is frozen at original submission (Phase 2 item 6) and is
            // never re-derived from user input on resubmit.
            'activities' => ['required', 'array', 'min:1'],
            'activities.*.name' => ['required', 'string', 'max:255'],
            'activities.*.venue' => ['required', 'string', 'max:255'],
            'activities.*.activity_date' => ['required', 'date', 'after_or_equal:today'],
            'activities.*.start_time' => ['required', 'date_format:H:i'],
            'activities.*.end_time' => ['required', 'date_format:H:i', 'after:activities.*.start_time'],
            'activities.*.description' => ['nullable', 'string'],
            // Exact field corrections (Phase 2 item 7 slice 1).
            // SDG multi-select (Group B item 1) — see StoreActivityCalendarRequest.
            'activities.*.sdg' => ['required', 'array', 'min:1'],
            'activities.*.sdg.*' => [Rule::enum(Sdg::class)],
            'activities.*.participant_program_assigned' => ['required', 'string', 'max:255'],
            'activities.*.budget' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Group B item 3 — see StoreActivityCalendarRequest::attributes() for
     * why this wildcard map exists.
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
