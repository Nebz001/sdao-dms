<?php

namespace App\Approval;

use App\Enums\FormType;
use App\Models\Document;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * "Save draft" on the Fix and Resend page: the values the officer typed into
 * the flagged items, kept in the cache for 30 days (no table, so nothing to
 * migrate) and shared by the organization's officers, like the document.
 *
 * It never touches the document, so the returned version stays the baseline
 * "changed" is measured against. Only flagged items can be saved: the same
 * lock as the resend, enforced here too. Files are not drafted; they are
 * chosen again when resending.
 *
 * Shape: section key => [field key => value]; an Activity Calendar row is
 * its own section ("activity_2").
 */
final class FixDraft
{
    private const int DAYS = 30;

    private const int MAX_BYTES = 200_000;

    /** @return array<string, array<string, mixed>>|null */
    public static function get(Document $document): ?array
    {
        return Cache::get(self::key($document));
    }

    public static function forget(Document $document): void
    {
        Cache::forget(self::key($document));
    }

    /**
     * @param  array<string, mixed>  $sections
     *
     * @throws ValidationException
     */
    public static function put(Document $document, ReturnedEditLock $lock, array $sections): void
    {
        $allowed = $document->form_type === FormType::ActivityCalendar
            ? collect(SectionFields::calendarFields())->pluck('key')->all()
            : null;
        $map = ReturnedEditLock::fieldMap($document);
        $clean = [];

        foreach ($sections as $section => $values) {
            if (! $lock->isFixable((string) $section) || ! is_array($values)) {
                throw ValidationException::withMessages([
                    'locked' => 'Only the flagged items can be saved.',
                ]);
            }

            $fields = $allowed ?? collect($map[$section] ?? [])->pluck('key')->all();
            $unknown = array_diff(array_keys($values), $fields);

            if ($unknown !== []) {
                throw ValidationException::withMessages([
                    'locked' => 'Only the flagged items can be saved.',
                ]);
            }

            $clean[$section] = $values;
        }

        if (strlen((string) json_encode($clean)) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['draft' => 'That draft is too large to save.']);
        }

        Cache::put(self::key($document), $clean, now()->addDays(self::DAYS));
    }

    private static function key(Document $document): string
    {
        return "fix-resend-draft:{$document->id}";
    }
}
