<?php

namespace App\Approval;

use App\Attachments\AttachmentSlots;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Models\CalendarActivity;
use App\Models\Document;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The Fix and Resend rule, enforced on the server: while a document is
 * Returned, only what the approver flagged may change, and every flagged item
 * must actually change before it can be resent.
 *
 * It reads the flags that already exist (SectionFlags::currentlyFlagged) and
 * the field registry that already exists (SectionFields); it invents no flag
 * system. The unit of editing is the flagged SECTION (every field in it), or
 * one attachment slot, or, for an Activity Calendar, one activity row.
 *
 * `for()` returns null when the return carried nothing fixable: no flags at
 * all, only "General", or only sections with nothing to edit (a renewal's
 * Adviser Selection, a report's Event Details, an on-calendar proposal's
 * Schedule & Venue). That is the "keep the whole form editable" case, and
 * every caller then behaves exactly as it did before this class existed.
 *
 * The comparison is on formatted, trimmed strings (FieldValueFormatter), the
 * same way the revision history decides what "changed" means.
 */
final class ReturnedEditLock
{
    /** @param  list<string>  $fixable  flagged keys that have something to edit */
    private function __construct(
        private readonly Document $document,
        public readonly array $fixable,
    ) {}

    public static function for(Document $document): ?self
    {
        if ($document->status !== DocumentStatus::Returned) {
            return null;
        }

        $fixable = self::fixableKeys($document);

        return $fixable === [] ? null : new self($document, $fixable);
    }

    /**
     * Flagged keys with something the student can actually edit.
     *
     * @return list<string>
     */
    public static function fixableKeys(Document $document): array
    {
        $flagged = SectionFlags::currentlyFlagged($document);

        if ($document->form_type === FormType::ActivityCalendar) {
            $valid = SectionFlags::calendarKeysFor($document);

            return array_values(array_filter($flagged, fn (string $key) => in_array($key, $valid, true)));
        }

        $sections = array_keys(self::fieldMap($document));
        $slots = collect(AttachmentSlots::for($document->form_type))->pluck('key')->all();

        return array_values(array_filter(
            $flagged,
            fn (string $key) => in_array($key, $sections, true) || in_array($key, $slots, true),
        ));
    }

    /**
     * Section key => the fields that section covers, for this document. Adds
     * the "Others, please specify" companions the revision registry leaves
     * out, and leaves Schedule & Venue out of an on-calendar proposal (those
     * values come from the approved calendar and are never editable).
     *
     * @return array<string, array<int, FieldDefinition>>
     */
    public static function fieldMap(Document $document): array
    {
        $map = array_filter(SectionFields::for($document->form_type), fn (array $defs) => $defs !== []);

        if ($document->form_type === FormType::ActivityProposal) {
            $map['activity_details'] = [
                ...$map['activity_details'],
                new FieldDefinition('activity_nature_other', 'Nature of Activity (specify)'),
                new FieldDefinition('activity_type_other', 'Type of Activity (specify)'),
            ];

            $document->loadMissing('activityProposal');

            if ($document->activityProposal?->calendar_mode !== ProposalCalendarMode::OffCalendar) {
                unset($map['schedule_venue']);
            }
        }

        return $map;
    }

    /**
     * Every tracked field of the form, flat and de-duplicated by key.
     *
     * @return array<int, FieldDefinition>
     */
    public function allDefs(): array
    {
        $defs = [];

        foreach (self::fieldMap($this->document) as $sectionDefs) {
            foreach ($sectionDefs as $def) {
                $defs[$def->key] = $def;
            }
        }

        return array_values($defs);
    }

    public function isFixable(string $key): bool
    {
        return in_array($key, $this->fixable, true);
    }

    /**
     * An upload to a slot the approver did not flag is refused (Mode A and the
     * standalone Mode B endpoint both call this).
     *
     * @throws ValidationException
     */
    public function assertSlotEditable(string $slotKey): void
    {
        if (! $this->isFixable($slotKey)) {
            throw ValidationException::withMessages([
                'locked' => 'This attachment was not flagged, so it can’t be changed: '.self::slotLabel($this->document, $slotKey).'.',
            ]);
        }
    }

    /**
     * Refuses files for slots that were not flagged. Call before anything is stored.
     *
     * @param  array<string, mixed>  $attachmentFiles
     *
     * @throws ValidationException
     */
    public function assertUploadsAllowed(array $attachmentFiles): void
    {
        $blocked = collect(array_keys($attachmentFiles))
            ->reject(fn (string $key) => $this->isFixable($key))
            ->map(fn (string $key) => self::slotLabel($this->document, $key));

        if ($blocked->isNotEmpty()) {
            throw ValidationException::withMessages([
                'locked' => 'These attachments were not flagged, so they can’t be changed: '.$blocked->implode(', ').'.',
            ]);
        }
    }

    /**
     * The after-write check: nothing locked changed, and every flagged item did.
     * Runs inside the resubmit transaction, so a refusal rolls the write back.
     *
     * @param  array<string, mixed>  $old  allDefs() snapshot taken before the write
     * @param  array<string, mixed>  $new  the same snapshot, re-read after it
     * @param  array<string, mixed>  $attachmentFiles
     *
     * @throws ValidationException
     */
    public function assertChanges(array $old, array $new, array $attachmentFiles): void
    {
        $locked = [];
        $unfixed = [];
        $labels = SectionFlags::labelsFor($this->document->form_type);

        foreach (self::fieldMap($this->document) as $section => $defs) {
            $changed = collect($defs)
                ->filter(fn (FieldDefinition $def) => self::differs($def, $old, $new))
                ->map(fn (FieldDefinition $def) => $def->label);

            if ($this->isFixable($section)) {
                if ($changed->isEmpty()) {
                    $unfixed[] = $labels[$section] ?? $section;
                }
            } elseif ($changed->isNotEmpty()) {
                $locked = [...$locked, ...$changed->all()];
            }
        }

        foreach ($this->fixable as $key) {
            if (self::isSlot($this->document, $key) && ! self::hasNewFile($attachmentFiles[$key] ?? null)) {
                $unfixed[] = self::slotLabel($this->document, $key).' (needs a new file)';
            }
        }

        $this->throwIfAny($locked, $unfixed);
    }

    /**
     * Activity Calendar: the posted rows, positionally, against the stored ones.
     * Flagged rows must differ; every other row must be identical and none may
     * be added or removed.
     *
     * @param  array<int, array<string, mixed>>  $oldRows  FieldChangeSet::snapshot() of each stored row, in order
     * @param  array<int, array<string, mixed>>  $newRows  the same snapshot of each posted row, in order
     *
     * @throws ValidationException
     */
    public function assertCalendarRows(array $oldRows, array $newRows): void
    {
        $locked = [];
        $unfixed = [];
        $defs = SectionFields::calendarFields();

        if (count($newRows) !== count($oldRows)) {
            throw ValidationException::withMessages([
                'locked' => 'Activities that were not flagged can’t be added or removed.',
            ]);
        }

        foreach ($oldRows as $index => $oldRow) {
            $changed = collect($defs)
                ->filter(fn (FieldDefinition $def) => self::differs($def, $oldRow, $newRows[$index] ?? []))
                ->map(fn (FieldDefinition $def) => $def->label);
            $name = 'Activity '.($index + 1);

            if ($this->isFixable("activity_{$index}")) {
                if ($changed->isEmpty()) {
                    $unfixed[] = $name;
                }
            } elseif ($changed->isNotEmpty()) {
                $locked[] = "{$name} ({$changed->implode(', ')})";
            }
        }

        $this->throwIfAny($locked, $unfixed);
    }

    /**
     * Request values for every locked field the request left out, taken from
     * what is stored, so a form that only posts the flagged fields still
     * passes the same validation rules. A value the request DID send for a
     * locked field is left alone: it is compared, and refused, after the write.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function lockedInputDefaults(array $input): array
    {
        $document = $this->document;
        $models = match ($document->form_type) {
            FormType::OrganizationRegistration, FormType::OrganizationRenewal => [$document->registrationDetail],
            FormType::AfterActivityReport => [$document->afterActivityReport],
            FormType::ActivityProposal => [$document->activityProposal, $document->activityProposal?->calendarActivity],
            FormType::ActivityCalendar => [],
        };
        $defaults = [];

        foreach (self::fieldMap($document) as $section => $defs) {
            if ($this->isFixable($section)) {
                continue;
            }

            foreach ($defs as $def) {
                // An unposted adviser is simply kept by the registration update.
                if ($def->key === 'adviser_id' || array_key_exists($def->key, $input)) {
                    continue;
                }

                foreach (array_filter($models) as $model) {
                    if ($this->modelHas($model, $def->key)) {
                        $defaults[$def->key] = self::asInput($model->getAttribute($def->key));

                        break;
                    }
                }
            }
        }

        // A proposal's other fields are optional on resubmit and keep their stored
        // value when absent, so only the required narrative fields need filling.
        if ($document->form_type === FormType::ActivityProposal) {
            $defaults = array_intersect_key($defaults, array_flip([
                'objectives', 'activity_description', 'criteria_mechanics', 'program_flow', 'expense_items', 'responsible_persons',
            ]));
        }

        return $defaults;
    }

    /**
     * Whether a flat value is a real, non-empty new upload.
     */
    private static function hasNewFile(mixed $files): bool
    {
        return collect(is_array($files) ? $files : [$files])->filter(fn ($file) => $file !== null)->isNotEmpty();
    }

    private static function isSlot(Document $document, string $key): bool
    {
        return collect(AttachmentSlots::for($document->form_type))->contains(fn ($slot) => $slot->key === $key);
    }

    private static function slotLabel(Document $document, string $key): string
    {
        return collect(AttachmentSlots::for($document->form_type))->first(fn ($slot) => $slot->key === $key)?->label ?? $key;
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private static function differs(FieldDefinition $def, array $old, array $new): bool
    {
        return trim((string) FieldValueFormatter::format($old[$def->key] ?? null, $def->type))
            !== trim((string) FieldValueFormatter::format($new[$def->key] ?? null, $def->type));
    }

    private function modelHas(Model $model, string $key): bool
    {
        return array_key_exists($key, $model->getAttributes()) || $model->hasCast($key) || $model->hasGetMutator($key);
    }

    /** A stored value back into what a request would carry for it. */
    private static function asInput(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            $value instanceof Collection => $value->map(fn ($item) => self::asInput($item))->values()->all(),
            default => $value,
        };
    }

    /**
     * @param  list<string>  $locked
     * @param  list<string>  $unfixed
     *
     * @throws ValidationException
     */
    private function throwIfAny(array $locked, array $unfixed): void
    {
        $errors = [];

        if ($locked !== []) {
            $errors['locked'] = 'These were not flagged, so they can’t be changed: '.implode(', ', array_unique($locked)).'.';
        }

        if ($unfixed !== []) {
            $errors['flagged'] = 'Still needs your changes: '.implode(', ', array_unique($unfixed)).'.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Snapshots of every posted calendar row as the model would store it, so
     * dates, times and goal lists compare on the same footing as stored rows.
     *
     * @param  array<int, array<string, mixed>>  $activities
     * @return array<int, array<string, mixed>>
     */
    public static function snapshotPostedRows(array $activities): array
    {
        $defs = SectionFields::calendarFields();

        return collect($activities)
            ->values()
            ->map(fn (array $row) => FieldChangeSet::snapshot((new CalendarActivity)->forceFill($row), $defs))
            ->all();
    }
}
