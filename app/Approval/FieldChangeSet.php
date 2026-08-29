<?php

namespace App\Approval;

use App\Attachments\AttachmentSlots;
use App\Enums\FormType;
use App\Models\Document;
use Illuminate\Database\Eloquent\Model;

/**
 * Builds the field_changes payload frozen onto a Resubmitted transition:
 * for every flagged section that has tracked fields, the before/after
 * display strings of each of those fields.
 *
 * WHY EXPLICIT SNAPSHOTS, NOT replicate()/getOriginal():
 *
 *   - Three of the five resubmit paths write via a relation query-builder
 *     mass update ($document->registrationDetail()->update([...])), which
 *     hydrates no model, fires no events, and syncs no $original —
 *     getOriginal() has nothing to say there.
 *   - The one that DOES write through a model instance in place
 *     (ResubmitActivityProposal) syncs $original inside save(), so
 *     getOriginal() called after the update returns the NEW values, not the
 *     old ones. Classic trap.
 *   - UpdateActivityCalendar deletes and recreates every row, so there is no
 *     "before instance" to interrogate at all.
 *   - replicate() would work by accident in the single-model cases, but it
 *     returns an unsaved model with the PK stripped and loaded relations
 *     copied, and fires the `replicating` event. It means "prepare a copy
 *     for insertion", not "freeze a read".
 *
 * So: extract cast values into a plain array BEFORE the write, and build the
 * after-array from a FRESHLY RE-READ model. One idiom for all five paths,
 * and — critically — both sides then come through the identical cast
 * pipeline. If the after-side were built from the raw request payload
 * instead, every enum and every date would report as changed on every
 * single resubmission.
 *
 * `changed` is decided on the FORMATTED strings, not the raw values. That
 * sidesteps Carbon/enum identity, decimal:2 string-vs-float, and array key
 * order — and matches what a human actually sees: if both sides render
 * identically, "X → X" is noise.
 */
final class FieldChangeSet
{
    /**
     * Freeze the current cast values of $defs off $model into a plain array.
     * A null $model yields all-null values (e.g. an off-calendar proposal
     * whose linked CalendarActivity has somehow gone missing).
     *
     * @param  array<int, FieldDefinition>  $defs
     * @return array<string, mixed>
     */
    public static function snapshot(?Model $model, array $defs): array
    {
        $values = [];

        foreach ($defs as $def) {
            $values[$def->key] = match (true) {
                $model === null => null,
                $def->using !== null => ($def->using)($model),
                default => $model->getAttribute($def->key),
            };
        }

        return $values;
    }

    /**
     * @param  array<int, FieldDefinition>  $defs
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array<int, array{key: string, label: string, old: string|null, new: string|null, changed: bool}>
     */
    public static function diffFields(array $defs, array $old, array $new): array
    {
        $rows = [];

        foreach ($defs as $def) {
            $before = FieldValueFormatter::format($old[$def->key] ?? null, $def->type);
            $after = FieldValueFormatter::format($new[$def->key] ?? null, $def->type);

            $rows[] = [
                'key' => $def->key,
                'label' => $def->label,
                'old' => $before,
                'new' => $after,
                'changed' => $before !== $after,
            ];
        }

        return $rows;
    }

    /**
     * Every form type except Activity Calendar.
     *
     * Returns null (not an empty array) when nothing is reportable — nothing
     * flagged, or only sections with no tracked fields and no attachment
     * slot (general, resource_person on Proposal, event_details on Report).
     * One null check on the frontend.
     *
     * $hadAttachmentsBefore and $attachmentFiles are optional and only
     * matter for a flagged key that is ALSO an attachment slot key (see
     * AttachmentSlots::for($formType)) — Registration/Renewal/AfterActivity
     * Report flag attachments this way (SectionFlags::attachmentSlotFlags()
     * uses the slot key directly as the section key). A caller with no
     * attachment slots (or that hasn't wired this up) can simply omit them;
     * every such flagged key then falls through to the scalar-field branch
     * below, finds no field definitions, and is skipped exactly as before.
     *
     * @param  array<int, string>  $flaggedKeys
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @param  array<string, bool>  $hadAttachmentsBefore  slot key => had at least one file, snapshotted BEFORE AttachmentStorage::storeMany() ran
     * @param  array<string, mixed>  $attachmentFiles  the same slot_key => file(s) payload passed to storeMany() for this request
     * @return array<string, array{label: string, status: string, fields: array<int, array<string, mixed>>}>|null
     */
    public static function build(
        FormType $formType,
        array $flaggedKeys,
        array $old,
        array $new,
        array $hadAttachmentsBefore = [],
        array $attachmentFiles = [],
    ): ?array {
        $registry = SectionFields::for($formType);
        $labels = SectionFlags::labelsFor($formType);
        $slotsByKey = collect(AttachmentSlots::for($formType))->keyBy('key');
        $sections = [];

        foreach ($flaggedKeys as $key) {
            $slot = $slotsByKey->get($key);

            if ($slot !== null) {
                // No filenames recorded, by design — just enough for an
                // approver to see whether a flagged attachment was actually
                // touched this resubmit, and whether a single-file slot's
                // old file was replaced or a multi-file slot only grew.
                $touched = array_key_exists($key, $attachmentFiles);

                $sections[$key] = [
                    'label' => $labels[$key] ?? $slot->label,
                    'status' => match (true) {
                        ! $touched => 'unchanged',
                        ! ($hadAttachmentsBefore[$key] ?? false) => 'added',
                        $slot->multiple => 'added', // accumulates; the old file(s) are never removed
                        default => 'replaced',
                    },
                    'fields' => [],
                ];

                continue;
            }

            $defs = $registry[$key] ?? [];

            if ($defs === []) {
                continue;
            }

            $sections[$key] = [
                'label' => $labels[$key] ?? $key,
                'status' => 'changed',
                'fields' => self::diffFields($defs, $old, $new),
            ];
        }

        return $sections === [] ? null : $sections;
    }

    /**
     * Snapshots which of the flagged, attachment-slot sections currently
     * have at least one uploaded file — call BEFORE
     * AttachmentStorage::storeMany() runs, exactly like snapshot() above
     * must run before its own model writes, so build()'s
     * $hadAttachmentsBefore reflects the state before this resubmit rather
     * than after.
     *
     * @param  array<int, string>  $flaggedKeys
     * @return array<string, bool>
     */
    public static function snapshotAttachmentPresence(Document $document, array $flaggedKeys): array
    {
        $slotKeys = collect(AttachmentSlots::for($document->form_type))->pluck('key');
        $presence = [];

        foreach ($flaggedKeys as $key) {
            if (! $slotKeys->contains($key)) {
                continue;
            }

            $presence[$key] = $document->attachments()->where('slot_key', $key)->exists();
        }

        return $presence;
    }

    /**
     * Activity Calendar: positional zip of old row i against new row i, for
     * whichever "activity_{i}" keys were flagged.
     *
     * LIMITATION, inherited verbatim from SectionFlags' own design note:
     * "activity_{i}" is a POSITION, not an identity. ActivityCalendar's
     * activities() relation orders by activity_date then start_time, so if
     * the student edits a date the row's index can shift and index i
     * before/after may describe two different activities. This is the same
     * limitation the existing flagging feature already has ("Flagged:
     * Activity 3" means the 3rd row at return time), and fixing it needs
     * stable row ids — a different, larger change. Do NOT build a
     * name-matching heuristic to paper over it: a genuine rename and a row
     * reorder are indistinguishable, so a heuristic would lie confidently.
     *
     * Count mismatch IS handled: a flagged index always existed at return
     * time, but the student can delete rows on resubmit, so index i may be
     * missing from the new set. That yields status 'removed' rather than a
     * crash. 'added' is unreachable in practice (flags only ever reference
     * pre-existing rows) and handled defensively only.
     *
     * Labels are baked in server-side here rather than deferred to the
     * frontend's own calendar label helper, so field_changes stays
     * self-describing and the shared React component needs no per-form-type
     * label plumbing.
     *
     * @param  array<int, string>  $flaggedKeys
     * @param  array<int, array<string, mixed>>  $oldRows  ordered positional snapshots
     * @param  array<int, array<string, mixed>>  $newRows  ordered positional snapshots
     * @return array<string, array{label: string, status: string, fields: array<int, array<string, mixed>>}>|null
     */
    public static function buildForCalendar(array $flaggedKeys, array $oldRows, array $newRows): ?array
    {
        $defs = SectionFields::calendarFields();
        $sections = [];

        foreach ($flaggedKeys as $key) {
            if (! preg_match('/^activity_(\d+)$/', $key, $matches)) {
                continue;
            }

            $index = (int) $matches[1];
            $oldRow = $oldRows[$index] ?? null;
            $newRow = $newRows[$index] ?? null;

            if ($oldRow === null && $newRow === null) {
                continue;
            }

            $sections[$key] = [
                'label' => 'Activity '.($index + 1),
                'status' => match (true) {
                    $newRow === null => 'removed',
                    $oldRow === null => 'added',
                    default => 'changed',
                },
                'fields' => self::diffFields($defs, $oldRow ?? [], $newRow ?? []),
            ];
        }

        return $sections === [] ? null : $sections;
    }
}
